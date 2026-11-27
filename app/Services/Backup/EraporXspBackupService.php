<?php

namespace App\Services\Backup;

use App\Models\Aktivitas;
use App\Models\Catatan;
use App\Models\Ekstrakurikuler;
use App\Models\JadwalPelajaran;
use App\Models\KegiatanKokurikuler;
use App\Models\KelompokKokurikuler;
use App\Models\MataPelajaran;
use App\Models\NilaiErapot;
use App\Models\Ptk;
use App\Models\Rombel;
use App\Models\SekolahSettings;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TanggalRapor;
use App\Models\TemaKokurikuler;
use App\Models\TranskripNilai;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use App\Services\Navigation\SchoolDataAvailabilityService;
use App\Services\NilaiErapotAuditService;
use App\Support\InitialPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Dekripsi, Restore, dan Backup arsip `.xsp` (format resmi e-Rapor SD Kemendikdasmen).
 *
 * Struktur `.xsp`:
 * - ZIP lapis 1 berisi `file_hasilbackuprsp.xsp` (atau langsung `bcke_*.enc`).
 * - ZIP lapis 2 berisi `bcke_1.enc` .. `bcke_67.enc`.
 * - Setiap `.enc` adalah Base64 dari `[16-byte IV][AES-256-CTR ciphertext]`.
 * - Plaintext setiap entri adalah JSON array 1 elemen:
 *   `[{"id_sekolah": "...", "nm_tabel": "...", "semester": "...", "tahun": "...", "tabel": "public.xxx", "jumlah": N, "data": [...]}]`
 */
class EraporXspBackupService
{
    /** Kunci AES-256-CTR standar aplikasi e-Rapor SD 2025 (32 byte, hex). */
    public const DEFAULT_KEY_HEX = '7f96ecb632c1003f594c0a86219bf6b72dea7dad862b1de63775cbc08d1dc63f';

    public function keyBinary(?string $customKeyHex = null): string
    {
        $hex = trim((string) ($customKeyHex ?: config('simantap.erapor_xsp_key_hex', self::DEFAULT_KEY_HEX)));
        $bin = @hex2bin($hex);
        if ($bin === false || strlen($bin) !== 32) {
            throw new RuntimeException('Kunci enkripsi e-Rapor (.xsp) tidak valid (harus 32 byte / 64 hex karakter).');
        }

        return $bin;
    }

    /**
     * Dekripsi satu konten Base64 `bcke_*.enc` menjadi string JSON plaintext.
     */
    public function decryptEntry(string $base64Content, ?string $keyBin = null): string
    {
        $key = $keyBin ?? $this->keyBinary();
        $raw = base64_decode(trim($base64Content), true);
        if ($raw === false || strlen($raw) <= 16) {
            throw new RuntimeException('Isi entri .enc bukan Base64 yang valid atau terlalu pendek.');
        }

        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $plain = openssl_decrypt($cipher, 'AES-256-CTR', $key, OPENSSL_RAW_DATA, $iv);
        if ($plain === false) {
            throw new RuntimeException('Gagal mendekripsi entri .enc dengan AES-256-CTR.');
        }

        return $plain;
    }

    /**
     * Enkripsi string JSON plaintext menjadi Base64 `bcke_*.enc`.
     */
    public function encryptEntry(string $plainJson, ?string $keyBin = null): string
    {
        $key = $keyBin ?? $this->keyBinary();
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plainJson, 'AES-256-CTR', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            throw new RuntimeException('Gagal mengenkripsi data ke format .enc.');
        }

        return base64_encode($iv.$cipher);
    }

    /**
     * Ekstrak dan dekripsi seluruh entri `bcke_*.enc` dari file `.xsp`.
     *
     * @return array<string, array{meta: array<string, mixed>, rows: array<int, array<string, mixed>>}>
     *                                                                                                  Key berupa nama tabel e-Rapor (mis. `public.tabel_siswa`, `public.tabel_nilaiakhir`, dst.)
     */
    public function extractTablesFromXsp(string $xspFilePath, ?string $keyBin = null): array
    {
        if (! is_file($xspFilePath)) {
            throw new RuntimeException('File .xsp tidak ditemukan.');
        }

        $key = $keyBin ?? $this->keyBinary();
        $zipOuter = new ZipArchive;
        if ($zipOuter->open($xspFilePath) !== true) {
            throw new RuntimeException('File .xsp bukan arsip ZIP yang valid.');
        }

        $tempInnerFile = null;
        $activeZip = $zipOuter;

        try {
            // Cek apakah ZIP bersarang (berisi 1 file .xsp / .zip di dalamnya)
            if ($zipOuter->numFiles === 1) {
                $firstName = (string) $zipOuter->getNameIndex(0);
                if (str_ends_with(strtolower($firstName), '.xsp') || str_ends_with(strtolower($firstName), '.zip')) {
                    $innerBytes = $zipOuter->getFromIndex(0);
                    if ($innerBytes === false) {
                        throw new RuntimeException('Gagal membaca arsip lapis dalam pada file .xsp.');
                    }
                    $tempInnerFile = tempnam(sys_get_temp_dir(), 'xsp_inner_');
                    if ($tempInnerFile === false) {
                        throw new RuntimeException('Gagal menyiapkan file sementara untuk ekstraksi .xsp.');
                    }
                    file_put_contents($tempInnerFile, $innerBytes);
                    $zipInner = new ZipArchive;
                    if ($zipInner->open($tempInnerFile) !== true) {
                        throw new RuntimeException('Arsip lapis dalam .xsp rusak atau bukan ZIP valid.');
                    }
                    $activeZip = $zipInner;
                }
            }

            $tables = [];
            for ($i = 0; $i < $activeZip->numFiles; $i++) {
                $entryName = (string) $activeZip->getNameIndex($i);
                if (str_ends_with($entryName, '/') || ! str_ends_with(strtolower($entryName), '.enc')) {
                    continue;
                }

                $encContent = $activeZip->getFromIndex($i);
                if ($encContent === false || trim($encContent) === '') {
                    continue;
                }

                $plain = $this->decryptEntry($encContent, $key);
                $decoded = json_decode($plain, true);
                if (! is_array($decoded) || empty($decoded[0]) || ! is_array($decoded[0])) {
                    continue;
                }

                $envelope = $decoded[0];
                $tableName = strtolower(trim((string) ($envelope['tabel'] ?? $entryName)));
                $rows = is_array($envelope['data'] ?? null) ? $envelope['data'] : [];
                unset($envelope['data']);

                $tables[$tableName] = [
                    'entry' => $entryName,
                    'meta' => $envelope,
                    'rows' => $rows,
                ];
            }

            if ($tables === []) {
                throw new RuntimeException('Tidak ada entri bcke_*.enc yang dapat didekripsi dari file .xsp.');
            }

            return $tables;
        } finally {
            if ($activeZip !== $zipOuter) {
                $activeZip->close();
            }
            $zipOuter->close();
            if ($tempInnerFile !== null && is_file($tempInnerFile)) {
                @unlink($tempInnerFile);
            }
        }
    }

    /**
     * Ubah nilai identitas kosong dari e-Rapor ('', '-', '--') menjadi null.
     * Nilai seperti '-' tidak boleh dipakai sebagai kunci updateOrCreate karena
     * ratusan siswa/PTK akan saling menimpa.
     */
    private function bersihkanKode(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || preg_match('/^[-.\/\s]+$/', $value) === 1) {
            return null;
        }

        return $value;
    }

    /**
     * Pulihkan data dari arsip `.xsp` e-Rapor ke database SIMANTAP untuk sekolah (tenant) aktif.
     *
     * @return array<string, int|string> Ringkasan jumlah baris per entitas yang dipulihkan.
     */
    public function restoreFromXsp(string $xspFilePath, ?int $tenantId = null): array
    {
        $tables = $this->extractTablesFromXsp($xspFilePath);

        $tenantId = $tenantId ?? auth()->user()?->tenant_id ?? TenantContext::id();
        $sekolahRows = $tables['public.tabel_sekolah']['rows'] ?? [];
        $sekolahRow = $sekolahRows[0] ?? [];
        $npsn = trim((string) ($sekolahRow['npsn'] ?? ''));

        if ($tenantId === null && Schema::hasTable('tenants')) {
            if ($npsn !== '') {
                $tenant = Tenant::where('npsn', $npsn)->first();
                if (! $tenant) {
                    $tenant = Tenant::first();
                }
                if ($tenant) {
                    $tenantId = (int) $tenant->id;
                }
            } else {
                $tenant = Tenant::first();
                if ($tenant) {
                    $tenantId = (int) $tenant->id;
                }
            }
        }

        if ($tenantId !== null) {
            TenantContext::set($tenantId);
        }

        $stats = [
            'npsn' => $npsn ?: '-',
            'nama_sekolah' => trim((string) ($sekolahRow['nama'] ?? '-')),
            'semesters' => 0,
            'ptk' => 0,
            'rombel' => 0,
            'mata_pelajaran' => 0,
            'siswa' => 0,
            'jadwal_pelajaran' => 0,
            'capaian_pembelajaran' => 0,
            'nilai_erapor' => 0,
            'transkrip_nilai' => 0,
            'ekstrakurikuler' => 0,
            'siswa_ekstrakurikuler' => 0,
            'kenaikan_kelas' => 0,
            'catatan' => 0,
            'tanggal_rapor' => 0,
            'tema_kokurikuler' => 0,
            'kegiatan_kokurikuler' => 0,
            'kelompok_kokurikuler' => 0,
            'p5_projek' => 0,
            'p5_penilaian' => 0,
        ];

        $defaultPasswordHash = Hash::make(InitialPassword::generate());

        NilaiErapotAuditService::tanpaAudit(function () use ($tables, $sekolahRow, $tenantId, $defaultPasswordHash, &$stats): void {
            DB::transaction(function () use ($tables, $sekolahRow, $tenantId, $defaultPasswordHash, &$stats): void {
                // 1. Semester (`public.semester`)
                $semesterCodeToModel = [];
                $semesterMetaMap = [];
                $activeSemesterCode = null;

                foreach ($tables['public.semester']['rows'] ?? [] as $row) {
                    $code = trim((string) ($row['semester_id'] ?? ''));
                    if ($code === '') {
                        continue;
                    }
                    $thAjar = trim((string) ($row['tahun_ajaran_id'] ?? substr($code, 0, 4)));
                    $tahunAjaran = strlen($thAjar) === 4 ? ($thAjar.'/'.((int) $thAjar + 1)) : $thAjar;
                    $smtNum = trim((string) ($row['semester'] ?? substr($code, -1)));
                    $namaSmt = ($smtNum === '2' || stripos((string) ($row['nama_semester'] ?? ''), 'genap') !== false) ? 'Genap' : 'Ganjil';

                    $sem = Semester::withoutGlobalScopes()->updateOrCreate(
                        ['tenant_id' => $tenantId, 'semester_id' => $code],
                        ['tahun_ajaran' => $tahunAjaran, 'nama_semester' => $namaSmt]
                    );

                    $semesterCodeToModel[$code] = $sem;
                    $semesterMetaMap[$code] = [
                        'id' => $sem->id,
                        'tahun_ajaran' => $tahunAjaran,
                        'nama_semester' => $namaSmt,
                    ];
                    if ((string) ($row['periode_aktif'] ?? '') === '1' || $activeSemesterCode === null || $code > $activeSemesterCode) {
                        $activeSemesterCode = $code;
                    }
                    $stats['semesters']++;
                }

                $resolveSemesterMeta = function (string $code) use (&$semesterMetaMap, &$semesterCodeToModel, $tenantId): array {
                    $code = trim($code);
                    if ($code !== '' && isset($semesterMetaMap[$code])) {
                        return $semesterMetaMap[$code];
                    }
                    $yr = (int) substr($code ?: date('Y'), 0, 4);
                    if ($yr < 2000) {
                        $yr = (int) date('Y');
                    }
                    $ta = $yr.'/'.($yr + 1);
                    $nm = substr($code, -1) === '2' ? 'Genap' : 'Ganjil';
                    $effCode = $code !== '' ? $code : ($yr.'1');

                    $sem = Semester::withoutGlobalScopes()->firstOrCreate(
                        ['tenant_id' => $tenantId, 'semester_id' => $effCode],
                        ['tahun_ajaran' => $ta, 'nama_semester' => $nm]
                    );
                    $semesterCodeToModel[$effCode] = $sem;
                    $semesterMetaMap[$effCode] = [
                        'id' => $sem->id,
                        'tahun_ajaran' => $ta,
                        'nama_semester' => $nm,
                    ];

                    return $semesterMetaMap[$effCode];
                };

                $activeSemMeta = $resolveSemesterMeta((string) ($activeSemesterCode ?? '20252'));

                // 2. Sekolah (`public.tabel_sekolah`)
                if (! empty($sekolahRow['nama'])) {
                    $existingSekolah = SekolahSettings::withoutGlobalScopes()
                        ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                        ->first();

                    $pengaturan = is_array($existingSekolah?->pengaturan) ? $existingSekolah->pengaturan : [];
                    if (! empty($sekolahRow['nip_kepsek'])) {
                        $pengaturan['nip_kepala_sekolah'] = trim((string) $sekolahRow['nip_kepsek']);
                    }

                    $sekolahData = [
                        'tenant_id' => $tenantId,
                        'nama_sekolah' => trim((string) $sekolahRow['nama']),
                        'npsn' => trim((string) ($sekolahRow['npsn'] ?? $existingSekolah?->npsn ?? '')),
                        'alamat' => trim((string) ($sekolahRow['alamat'] ?? $existingSekolah?->alamat ?? '')),
                        'kode_pos' => trim((string) ($sekolahRow['kd_pos'] ?? $existingSekolah?->kode_pos ?? '')),
                        'telepon' => trim((string) ($sekolahRow['telepon'] ?? $existingSekolah?->telepon ?? '')),
                        'kecamatan' => trim((string) ($sekolahRow['kecamatan'] ?? $existingSekolah?->kecamatan ?? '')),
                        'kota' => trim((string) ($sekolahRow['kab_kota'] ?? $existingSekolah?->kota ?? '')),
                        'provinsi' => trim((string) ($sekolahRow['propinsi'] ?? $existingSekolah?->provinsi ?? '')),
                        'website' => trim((string) ($sekolahRow['website'] ?? $existingSekolah?->website ?? '')),
                        'email' => trim((string) ($sekolahRow['email'] ?? $existingSekolah?->email ?? '')),
                        'kepala_sekolah' => trim((string) ($sekolahRow['nm_kepsek'] ?? $existingSekolah?->kepala_sekolah ?? '')),
                        'jenjang' => strtoupper(trim((string) ($sekolahRow['jenjang'] ?? $existingSekolah?->jenjang ?? 'SD'))),
                        'status' => 'aktif',
                        'pengaturan' => $pengaturan,
                    ];

                    if ($existingSekolah) {
                        $existingSekolah->update($sekolahData);
                    } else {
                        SekolahSettings::withoutGlobalScopes()->create($sekolahData);
                    }
                }

                // 3. PTK (`public.tabel_ptk`, `public.tabel_ptk_pelengkap`, `public.tabel_ptk_terdaftar`)
                $ptkPelengkapById = [];
                foreach ($tables['public.tabel_ptk_pelengkap']['rows'] ?? [] as $row) {
                    $pid = trim((string) ($row['ptk_id'] ?? ''));
                    if ($pid !== '') {
                        $ptkPelengkapById[$pid] = $row;
                    }
                }

                $ptkUuidToUserId = [];
                foreach ($tables['public.tabel_ptk']['rows'] ?? [] as $row) {
                    $ptkUuid = trim((string) ($row['ptk_id'] ?? ''));
                    $nama = trim((string) ($row['nama'] ?? ''));
                    if ($ptkUuid === '' || $nama === '') {
                        continue;
                    }

                    $pel = $ptkPelengkapById[$ptkUuid] ?? [];
                    // e-Rapor memakai '-' untuk data kosong; jangan dipakai sebagai kunci pencocokan
                    // karena banyak PTK akan saling menimpa.
                    $nip = $this->bersihkanKode(trim((string) ($row['nip'] ?? $pel['nip_niy'] ?? '')));
                    $nuptk = $this->bersihkanKode(trim((string) ($row['nuptk'] ?? '')));
                    $gelarDepan = trim((string) ($pel['gelar_depan'] ?? '')) ?: null;
                    $gelarBelakang = trim((string) ($pel['gelar_belakang'] ?? '')) ?: null;

                    Ptk::withoutGlobalScopes()->updateOrCreate(
                        ['dapodik_id' => $ptkUuid, 'semester_id' => $activeSemMeta['id']],
                        [
                            'tenant_id' => $tenantId,
                            'tenant_owned' => $tenantId !== null,
                            'nama' => $nama,
                            'nip' => $nip,
                            'nuptk' => $nuptk,
                            'jk' => trim((string) ($row['jenis_kelamin'] ?? '')) ?: null,
                            'gelar_depan' => $gelarDepan,
                            'gelar_belakang' => $gelarBelakang,
                            'jenis_ptk' => 'Guru',
                        ]
                    );

                    $user = User::withTrashed()
                        ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                        ->where(function ($q) use ($ptkUuid, $nip, $nuptk) {
                            $q->where('dapodik_id', $ptkUuid);
                            if ($nip) {
                                $q->orWhere('nip', $nip)->orWhere('nama_pengguna', $nip);
                            }
                            if ($nuptk) {
                                $q->orWhere('nama_pengguna', $nuptk);
                            }
                        })
                        ->first();

                    $namaLengkap = trim(($gelarDepan ? $gelarDepan.' ' : '').$nama.($gelarBelakang ? ', '.$gelarBelakang : ''));
                    if (! $user) {
                        $baseUsername = $nip ?: ($nuptk ?: 'gtk_'.substr(md5($ptkUuid), 0, 8));
                        $username = $baseUsername;
                        $c = 1;
                        while (User::withTrashed()->where('nama_pengguna', $username)->exists()) {
                            $username = $baseUsername.'_'.$c++;
                        }

                        $user = User::create([
                            'uuid' => (string) Str::uuid(),
                            'tenant_id' => $tenantId,
                            'dapodik_id' => $ptkUuid,
                            'nama_lengkap' => $namaLengkap,
                            'nama_pengguna' => $username,
                            'nip' => $nip,
                            'kata_sandi' => $defaultPasswordHash,
                            'peran' => 'guru',
                            'aktif' => true,
                            'force_password_change' => true,
                        ]);
                    } else {
                        if ($user->trashed()) {
                            $user->restore();
                        }
                        $user->forceFill([
                            'dapodik_id' => $user->dapodik_id ?: $ptkUuid,
                            'nip' => $user->nip ?: $nip,
                            'nama_lengkap' => $namaLengkap ?: $user->nama_lengkap,
                        ])->save();
                    }

                    $ptkUuidToUserId[$ptkUuid] = $user->id;
                    $stats['ptk']++;
                }

                $ptkTerdaftarToUserId = [];
                foreach ($tables['public.tabel_ptk_terdaftar']['rows'] ?? [] as $row) {
                    $ptkTerdaftarId = trim((string) ($row['ptk_terdaftar_id'] ?? ''));
                    $ptkUuid = trim((string) ($row['ptk_id'] ?? ''));
                    if ($ptkTerdaftarId !== '' && isset($ptkUuidToUserId[$ptkUuid])) {
                        $ptkTerdaftarToUserId[$ptkTerdaftarId] = $ptkUuidToUserId[$ptkUuid];
                    }
                }

                // 4. Mata Pelajaran (`public.tabel_mapel`)
                $mapelCodeToName = [];
                $mapelCodeToModelId = [];
                foreach ($tables['public.tabel_mapel']['rows'] ?? [] as $row) {
                    $kode = trim((string) ($row['mata_pelajaran_id'] ?? ''));
                    $nama = trim((string) ($row['nm_lokal'] ?? $row['nm_mapel'] ?? ''));
                    if ($nama === '') {
                        continue;
                    }
                    $mapel = MataPelajaran::withoutGlobalScopes()->updateOrCreate(
                        ['tenant_id' => $tenantId, 'nama' => $nama],
                        [
                            'kode' => $kode ?: null,
                            'jenjang' => 'SD',
                            'kelompok' => trim((string) ($row['kelompok'] ?? 'A')) ?: 'A',
                            'urutan' => (int) ($row['urut_rapor'] ?? 0),
                            'aktif' => true,
                        ]
                    );
                    if ($kode !== '') {
                        $mapelCodeToName[$kode] = $nama;
                        $mapelCodeToModelId[$kode] = $mapel->id;
                    }
                    $stats['mata_pelajaran']++;
                }

                // Tambahkan kamus dari ref_mapeldapo bila ada kode mapel di nilai yang tidak ada di tabel_mapel
                foreach ($tables['public.ref_mapeldapo']['rows'] ?? [] as $row) {
                    $kode = trim((string) ($row['mata_pelajaran_id'] ?? ''));
                    $nama = trim((string) ($row['nm_mapel'] ?? ''));
                    if ($kode !== '' && $nama !== '' && ! isset($mapelCodeToName[$kode])) {
                        $mapelCodeToName[$kode] = $nama;
                    }
                }

                // 5. Rombel (`public.tabel_kelas`)
                $rombelUuidToInfo = [];
                foreach ($tables['public.tabel_kelas']['rows'] ?? [] as $row) {
                    $rUuid = trim((string) ($row['rombongan_belajar_id'] ?? ''));
                    $semCode = trim((string) ($row['semester_id'] ?? ''));
                    $nmKelas = trim((string) ($row['nm_kelas'] ?? ''));
                    if ($rUuid === '' || $nmKelas === '') {
                        continue;
                    }
                    $cleanKelas = preg_replace('/^kelas\s+/i', '', $nmKelas);
                    $semMeta = $resolveSemesterMeta($semCode);
                    $walasUserId = $ptkUuidToUserId[trim((string) ($row['ptk_id'] ?? ''))] ?? null;

                    $rombel = Rombel::withoutGlobalScopes()->updateOrCreate(
                        ['tenant_id' => $tenantId, 'dapodik_id' => $rUuid, 'semester_id' => $semMeta['id']],
                        [
                            'nama_rombel' => $cleanKelas,
                            'tingkat' => trim((string) ($row['tingkat_pendidikan_id'] ?? '')),
                            'kurikulum' => trim((string) ($row['kurikulum_id'] ?? 'Kurikulum Merdeka')),
                            'jenis_rombel' => trim((string) ($row['jenis_rombel'] ?? '1')),
                            'guru_id' => $walasUserId,
                        ]
                    );

                    if ($walasUserId) {
                        User::withoutGlobalScopes()->where('id', $walasUserId)->update(['is_wali_kelas' => true]);
                    }

                    $rombelUuidToInfo[$rUuid] = [
                        'id' => $rombel->id,
                        'kelas' => $cleanKelas,
                        'tingkat' => trim((string) ($row['tingkat_pendidikan_id'] ?? '')),
                        'semester_code' => $semCode,
                        'semester_id' => $semMeta['id'],
                        'tahun_ajaran' => $semMeta['tahun_ajaran'],
                        'nama_semester' => $semMeta['nama_semester'],
                        'guru_id' => $walasUserId,
                    ];
                    $stats['rombel']++;
                }

                // 6. Anggota Kelas (`public.tabel_anggotakelas`) -> petakan siswa ke rombel terbaru
                $anggotaUuidToInfo = [];
                $siswaLatestRombel = [];
                foreach ($tables['public.tabel_anggotakelas']['rows'] ?? [] as $row) {
                    $aUuid = trim((string) ($row['anggota_rombel_id'] ?? ''));
                    $pdUuid = trim((string) ($row['peserta_didik_id'] ?? ''));
                    $rUuid = trim((string) ($row['rombongan_belajar_id'] ?? ''));
                    $semCode = trim((string) ($row['semester_id'] ?? ''));
                    if ($aUuid === '' || $pdUuid === '') {
                        continue;
                    }
                    $rInfo = $rombelUuidToInfo[$rUuid] ?? null;
                    $semMeta = $rInfo ?? $resolveSemesterMeta($semCode);
                    $info = [
                        'peserta_didik_id' => $pdUuid,
                        'rombongan_belajar_id' => $rUuid,
                        'rombel_id' => $rInfo['id'] ?? null,
                        'kelas' => $rInfo['kelas'] ?? '1',
                        'tingkat' => $rInfo['tingkat'] ?? '1',
                        'semester_code' => $semCode,
                        'semester_id' => $semMeta['semester_id'] ?? $semMeta['id'],
                        'tahun_ajaran' => $semMeta['tahun_ajaran'],
                        'nama_semester' => $semMeta['nama_semester'],
                        'guru_id' => $rInfo['guru_id'] ?? null,
                    ];
                    $anggotaUuidToInfo[$aUuid] = $info;

                    if (! isset($siswaLatestRombel[$pdUuid]) || $semCode >= $siswaLatestRombel[$pdUuid]['semester_code']) {
                        $siswaLatestRombel[$pdUuid] = $info;
                    }
                }

                // 7. Siswa (`public.tabel_siswa` + `public.tabel_siswa_pelengkap`)
                $pelengkapSiswaByUuid = [];
                foreach ($tables['public.tabel_siswa_pelengkap']['rows'] ?? [] as $row) {
                    $pdUuid = trim((string) ($row['peserta_didik_id'] ?? ''));
                    if ($pdUuid !== '') {
                        $pelengkapSiswaByUuid[$pdUuid] = $row;
                    }
                }

                $siswaUuidToId = [];
                $siswaUuidToName = [];
                $uniqueSiswaIds = [];
                foreach ($tables['public.tabel_siswa']['rows'] ?? [] as $row) {
                    $pdUuid = trim((string) ($row['peserta_didik_id'] ?? ''));
                    $nama = trim((string) ($row['nm_siswa'] ?? ''));
                    if ($pdUuid === '' || $nama === '') {
                        continue;
                    }

                    $nis = $this->bersihkanKode(trim((string) ($row['nis'] ?? '')));
                    $nisn = $this->bersihkanKode(trim((string) ($row['nisn'] ?? '')));
                    $pel = $pelengkapSiswaByUuid[$pdUuid] ?? [];
                    $lat = $siswaLatestRombel[$pdUuid] ?? null;

                    // Cocokkan lewat dapodik_id dulu. NISN/NIS hanya fallback: NISN kembar
                    // antar siswa membuat orWhere menggabungkan dua peserta didik berbeda.
                    $siswaQuery = fn () => Siswa::withTrashed()
                        ->withoutGlobalScopes()
                        ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId));

                    $siswa = $siswaQuery()->where('dapodik_id', $pdUuid)->first();

                    if (! $siswa && ($nisn !== null || $nis !== null)) {
                        $siswa = $siswaQuery()
                            ->where(function ($q) use ($nisn, $nis) {
                                if ($nisn !== null) {
                                    $q->where('nisn', $nisn);
                                }
                                if ($nis !== null) {
                                    $q->orWhere('nis', $nis);
                                }
                            })
                            ->first();
                    }

                    $ortuNama = trim((string) ($row['nm_ayah'] ?? '')) ?: (trim((string) ($row['nm_ibu'] ?? '')) ?: null);
                    $payload = [
                        'tenant_id' => $tenantId,
                        'dapodik_id' => $pdUuid,
                        // NIS '-' (70 siswa di berkas e-Rapor) harus tetap unik agar tidak saling menimpa.
                        'nis' => $nis ?? $nisn ?? substr(md5($pdUuid), 0, 10),
                        'nisn' => $nisn,
                        'nomor_ijazah' => trim((string) ($pel['no_ijasahnas'] ?? '')) ?: null,
                        'nama_peserta_didik' => $nama,
                        'kelas' => $lat['kelas'] ?? ($siswa?->kelas ?? '1'),
                        'rombel_id' => $lat['rombel_id'] ?? $siswa?->rombel_id,
                        'semester_id' => $lat['semester_id'] ?? $activeSemMeta['id'],
                        'guru_id' => $lat['guru_id'] ?? $siswa?->guru_id,
                        'jenis_kelamin' => in_array(strtoupper((string) ($row['jenis_kelamin'] ?? '')), ['L', 'P'], true)
                            ? strtoupper((string) $row['jenis_kelamin'])
                            : 'L',
                        'tempat_lahir' => trim((string) ($row['tempat_lahir'] ?? '')) ?: null,
                        'tanggal_lahir' => ! empty($row['tanggal_lahir']) ? $row['tanggal_lahir'] : null,
                        'agama' => trim((string) ($row['agama'] ?? '')) ?: null,
                        'alamat' => trim((string) ($row['alamat_siswa'] ?? '')) ?: null,
                        'telepon' => trim((string) ($row['telepon_siswa'] ?? $pel['telepon_ortu'] ?? '')) ?: null,
                        'nama_orang_tua' => $ortuNama,
                        'anak_ke' => is_numeric($pel['anak_ke'] ?? null) ? (int) $pel['anak_ke'] : null,
                        'aktif' => true,
                    ];

                    if ($siswa) {
                        if ($siswa->trashed()) {
                            $siswa->restore();
                        }
                        $siswa->update($payload);
                    } else {
                        $siswa = Siswa::withoutGlobalScopes()->create(array_merge($payload, [
                            'uuid' => (string) Str::uuid(),
                        ]));
                    }

                    $siswaUuidToId[$pdUuid] = $siswa->id;
                    $siswaUuidToName[$pdUuid] = $nama;
                    // Satu peserta didik bisa muncul 2x di .xsp (mis. NIS '-' & NISN sama);
                    // statistik dihitung dari baris unik yang benar-benar tersimpan.
                    $uniqueSiswaIds[$siswa->id] = true;
                }

                $stats['siswa'] = count($uniqueSiswaIds);

                // 8. Pembelajaran / Jadwal (`public.tabel_pembelajaran`)
                $jadwalLookup = [];
                foreach ($tables['public.tabel_pembelajaran']['rows'] ?? [] as $row) {
                    $pembUuid = trim((string) ($row['pembelajaran_id'] ?? ''));
                    $rUuid = trim((string) ($row['rombongan_belajar_id'] ?? ''));
                    $kdMapel = trim((string) ($row['mata_pelajaran_id'] ?? ''));
                    $semCode = trim((string) ($row['semester_id'] ?? ''));
                    $ptkTerdaftarId = trim((string) ($row['ptk_terdaftar_id'] ?? ''));

                    $rInfo = $rombelUuidToInfo[$rUuid] ?? null;
                    $semMeta = $rInfo ?? $resolveSemesterMeta($semCode);
                    $mapelNama = $mapelCodeToName[$kdMapel] ?? trim((string) ($row['nama_mata_pelajaran'] ?? ''));
                    if ($mapelNama === '') {
                        continue;
                    }

                    $guruId = $ptkTerdaftarToUserId[$ptkTerdaftarId] ?? ($rInfo['guru_id'] ?? null);
                    $kelasNama = $rInfo['kelas'] ?? '1';
                    $semId = $semMeta['semester_id'] ?? $semMeta['id'];

                    $jadwal = JadwalPelajaran::withoutGlobalScopes()->updateOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'dapodik_id' => $pembUuid ?: null,
                            'semester_id' => $semId,
                            'rombel_id' => $rInfo['id'] ?? null,
                            'mata_pelajaran' => $mapelNama,
                        ],
                        [
                            'guru_id' => $guruId,
                            'kelas' => $kelasNama,
                            // Kolom jadwal wajib NOT NULL; data .xsp e-Rapor tidak menyimpan hari/jam.
                            'hari' => 'Senin',
                            'jam_mulai' => '07:00:00',
                            'jam_selesai' => '07:35:00',
                        ]
                    );

                    $jadwalLookup[$rUuid.'|'.$kdMapel.'|'.$semCode] = [
                        'id' => $jadwal->id,
                        'guru_id' => $guruId,
                        'mata_pelajaran' => $mapelNama,
                        'kelas' => $kelasNama,
                    ];
                    $stats['jadwal_pelajaran']++;
                }

                // 9. Tujuan Pembelajaran / Capaian TP e-Rapor (`public.tabel_capaiantp`)
                if (Schema::hasTable('capaian_pembelajaran')) {
                    foreach ($tables['public.tabel_capaiantp']['rows'] ?? [] as $row) {
                        $kdMapel = trim((string) ($row['mata_pelajaran_id'] ?? ''));
                        $mapelNama = $mapelCodeToName[$kdMapel] ?? $kdMapel;
                        $tingkat = (int) ($row['tingkat_pendidikan_id'] ?? 1);
                        $fase = match (true) {
                            $tingkat <= 2 => 'A',
                            $tingkat <= 4 => 'B',
                            default => 'C',
                        };
                        $kodeCp = trim((string) ($row['kd_kompetensi'] ?? $row['id_kompetensi'] ?? ''));
                        $deskripsi = trim((string) ($row['kompetensi'] ?? $row['ringkasan_kompetensi'] ?? ''));
                        if ($mapelNama === '' || $deskripsi === '') {
                            continue;
                        }

                        $cpData = [
                            'mata_pelajaran' => $mapelNama,
                            'fase' => $fase,
                            'elemen' => trim((string) ($row['ringkasan_kompetensi'] ?? 'Umum')),
                            'deskripsi' => $deskripsi,
                            'kode_cp' => $kodeCp ?: null,
                            'updated_at' => now(),
                        ];
                        if (Schema::hasColumn('capaian_pembelajaran', 'tenant_id')) {
                            $cpData['tenant_id'] = $tenantId;
                        }

                        DB::table('capaian_pembelajaran')->updateOrInsert(
                            array_filter([
                                'tenant_id' => Schema::hasColumn('capaian_pembelajaran', 'tenant_id') ? $tenantId : null,
                                'mata_pelajaran' => $mapelNama,
                                'kode_cp' => $kodeCp ?: substr(md5($deskripsi), 0, 12),
                            ], fn ($v) => $v !== null),
                            array_merge($cpData, ['created_at' => now()])
                        );
                        $stats['capaian_pembelajaran']++;
                    }
                }

                // 10. Nilai Akhir & Deskripsi e-Rapor (`public.tabel_nilaiakhir` + `public.tabel_deskripsi`)
                $deskripsiMap = [];
                foreach ($tables['public.tabel_deskripsi']['rows'] ?? [] as $row) {
                    $pdUuid = trim((string) ($row['peserta_didik_id'] ?? ''));
                    $kdMapel = trim((string) ($row['mata_pelajaran_id'] ?? ''));
                    $semCode = trim((string) ($row['semester_id'] ?? ''));
                    $desk = trim((string) ($row['deskripsi_peng_m'] ?? $row['deskripsi_ket_m'] ?? ''));
                    if ($pdUuid !== '' && $kdMapel !== '' && $semCode !== '' && $desk !== '') {
                        $deskripsiMap[$pdUuid.'|'.$kdMapel.'|'.$semCode] = $desk;
                    }
                }

                $fallbackGuruId = User::withoutGlobalScopes()
                    ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                    ->whereIn('peran', ['guru', 'admin'])
                    ->value('id');

                $nilaiUniqueKeys = [];
                foreach ($tables['public.tabel_nilaiakhir']['rows'] ?? [] as $row) {
                    $pdUuid = trim((string) ($row['peserta_didik_id'] ?? ''));
                    $aUuid = trim((string) ($row['anggota_rombel_id'] ?? ''));
                    $kdMapel = trim((string) ($row['mata_pelajaran_id'] ?? ''));
                    $semCode = trim((string) ($row['semester_id'] ?? ''));

                    $aInfo = $anggotaUuidToInfo[$aUuid] ?? null;
                    if ($pdUuid === '' && $aInfo) {
                        $pdUuid = $aInfo['peserta_didik_id'];
                    }
                    $siswaId = $siswaUuidToId[$pdUuid] ?? null;
                    if (! $siswaId) {
                        continue;
                    }

                    $semMeta = $aInfo ?? $resolveSemesterMeta($semCode);
                    $rUuid = $aInfo['rombongan_belajar_id'] ?? '';
                    $jInfo = $jadwalLookup[$rUuid.'|'.$kdMapel.'|'.$semCode] ?? null;

                    $mapelNama = $jInfo['mata_pelajaran'] ?? ($mapelCodeToName[$kdMapel] ?? $kdMapel);
                    if ($mapelNama === '') {
                        continue;
                    }

                    $nilaiAkhir = (float) ($row['nilai_peng'] ?? $row['nilai_ket'] ?? 0);
                    $predikat = trim((string) ($row['predikat_peng'] ?? '')) ?: match (true) {
                        $nilaiAkhir >= 90 => 'A',
                        $nilaiAkhir >= 80 => 'B',
                        $nilaiAkhir >= 70 => 'C',
                        default => 'D',
                    };

                    $tingkat = (int) ($aInfo['tingkat'] ?? 1);
                    $fase = match (true) {
                        $tingkat <= 2 => 'A',
                        $tingkat <= 4 => 'B',
                        default => 'C',
                    };

                    $deskripsi = $deskripsiMap[$pdUuid.'|'.$kdMapel.'|'.$semCode] ?? null;
                    $guruId = $jInfo['guru_id'] ?? ($aInfo['guru_id'] ?? $fallbackGuruId);

                    NilaiErapot::withoutGlobalScopes()->updateOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'siswa_id' => $siswaId,
                            'mata_pelajaran' => $mapelNama,
                            'semester' => $semMeta['nama_semester'],
                            'tahun_ajaran' => $semMeta['tahun_ajaran'],
                        ],
                        [
                            'guru_id' => $guruId,
                            'jadwal_pelajaran_id' => $jInfo['id'] ?? null,
                            'kelas' => $aInfo['kelas'] ?? '1',
                            'fase' => $fase,
                            'nilai_formatif' => $nilaiAkhir,
                            'nilai_sumatif' => $nilaiAkhir,
                            'nilai_sumatif_akhir' => $nilaiAkhir,
                            'nilai_akhir' => $nilaiAkhir,
                            'predikat' => $predikat,
                            'deskripsi_capaian' => $deskripsi,
                            'terkirim_erapor' => true,
                            'erapor_synced_at' => now(),
                        ]
                    );
                    $nilaiUniqueKeys[$siswaId.'|'.$mapelNama.'|'.$semMeta['nama_semester'].'|'.$semMeta['tahun_ajaran']] = true;
                }
                // Baris ganda pada .xsp akan bertabrakan pada kunci unik nilai_erapor.
                $stats['nilai_erapor'] = count($nilaiUniqueKeys);

                // 11. Nilai SKL / Transkrip Ijazah (`public.tabel_nilaiskl`)
                $transkripUniqueKeys = [];
                foreach ($tables['public.tabel_nilaiskl']['rows'] ?? [] as $row) {
                    $pdUuid = trim((string) ($row['peserta_didik_id'] ?? ''));
                    $kdMapel = trim((string) ($row['mata_pelajaran_id'] ?? ''));
                    $siswaId = $siswaUuidToId[$pdUuid] ?? null;
                    if (! $siswaId) {
                        continue;
                    }
                    $mapelNama = $mapelCodeToName[$kdMapel] ?? $kdMapel;
                    $mapelId = $mapelCodeToModelId[$kdMapel] ?? null;
                    $nilai = (float) ($row['nilai'] ?? 0);

                    TranskripNilai::withTrashed()->withoutGlobalScopes()->updateOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'siswa_id' => $siswaId,
                            'nama_mapel' => $mapelNama,
                        ],
                        [
                            'mata_pelajaran_id' => $mapelId,
                            'nilai_akhir' => $nilai,
                            'predikat' => TranskripNilai::tentukanPredikat($nilai),
                            'deleted_at' => null,
                        ]
                    );
                    // Transkrip disimpan satu baris per siswa+mapel (nilai SKL lintas semester).
                    $transkripUniqueKeys[$siswaId.'|'.$mapelNama] = true;
                }
                $stats['transkrip_nilai'] = count($transkripUniqueKeys);

                // 12. Ekstrakurikuler (`public.tabel_kelas_ekskul` + `public.tabel_nilai_ekstra`)
                $ekskulKeyToModelId = [];
                foreach ($tables['public.tabel_kelas_ekskul']['rows'] ?? [] as $row) {
                    $nmEkskul = trim((string) ($row['nm_ekskul'] ?? ''));
                    if ($nmEkskul === '') {
                        continue;
                    }
                    $eks = Ekstrakurikuler::withoutGlobalScopes()->firstOrCreate(
                        ['tenant_id' => $tenantId, 'nama' => $nmEkskul],
                        ['tenant_owned' => $tenantId !== null, 'aktif' => true, 'deskripsi' => $row['sk_ekskul'] ?? null]
                    );
                    $ekskulKeyToModelId[(string) ($row['id_kelas_ekskul'] ?? '')] = $eks->id;
                    $ekskulKeyToModelId[(string) ($row['id_ekskul'] ?? '')] = $eks->id;
                    $stats['ekstrakurikuler']++;
                }

                if (Schema::hasTable('siswa_ekstrakurikuler')) {
                    $defaultEks = null;
                    foreach ($tables['public.tabel_nilai_ekstra']['rows'] ?? [] as $row) {
                        $pdUuid = trim((string) ($row['peserta_didik_id'] ?? ''));
                        $siswaId = $siswaUuidToId[$pdUuid] ?? null;
                        if (! $siswaId) {
                            continue;
                        }
                        $eksId = $ekskulKeyToModelId[(string) ($row['id_kelas_ekskul'] ?? '')]
                            ?? $ekskulKeyToModelId[(string) ($row['id_ekstra'] ?? '')]
                            ?? $ekskulKeyToModelId[(string) ($row['id_ekskul_baru'] ?? '')]
                            ?? null;

                        if (! $eksId) {
                            $defaultEks ??= Ekstrakurikuler::withoutGlobalScopes()->firstOrCreate(
                                ['tenant_id' => $tenantId, 'nama' => 'Ekstrakurikuler Pilihan'],
                                ['tenant_owned' => $tenantId !== null, 'aktif' => true]
                            );
                            $eksId = $defaultEks->id;
                        }

                        $pred = match ((string) ($row['nilai_ekstra'] ?? '')) {
                            '4', 'A', 'SB' => 'Sangat Baik',
                            '3', 'B' => 'Baik',
                            '2', 'C' => 'Cukup',
                            default => (string) ($row['nilai_ekstra'] ?? 'Baik'),
                        };

                        $eksPayload = [
                            'nilai' => $pred,
                            'keterangan' => trim((string) ($row['deskripsi'] ?? '')) ?: null,
                            'updated_at' => now(),
                        ];
                        if (Schema::hasColumn('siswa_ekstrakurikuler', 'tenant_id')) {
                            $eksPayload['tenant_id'] = $tenantId;
                        }

                        DB::table('siswa_ekstrakurikuler')->updateOrInsert(
                            ['siswa_id' => $siswaId, 'ekstrakurikuler_id' => $eksId],
                            array_merge($eksPayload, ['created_at' => now()])
                        );
                        $stats['siswa_ekstrakurikuler']++;
                    }
                }

                // 13. Kenaikan Kelas (`public.tabel_kenaikan`)
                if (Schema::hasTable('kenaikan_kelas')) {
                    foreach ($tables['public.tabel_kenaikan']['rows'] ?? [] as $row) {
                        $pdUuid = trim((string) ($row['peserta_didik_id'] ?? ''));
                        $siswaId = $siswaUuidToId[$pdUuid] ?? null;
                        if (! $siswaId) {
                            continue;
                        }
                        $semMeta = $resolveSemesterMeta((string) ($row['semester_id'] ?? ''));
                        $naik = in_array((string) ($row['kenaikan'] ?? '1'), ['1', '3', 'true', 'Ya'], true);

                        DB::table('kenaikan_kelas')->updateOrInsert(
                            [
                                'tenant_id' => $tenantId,
                                'siswa_id' => $siswaId,
                                'tahun_ajaran' => $semMeta['tahun_ajaran'],
                                'semester' => $semMeta['nama_semester'],
                            ],
                            [
                                'naik_kelas' => $naik,
                                'catatan' => 'Dipulihkan dari backup e-Rapor (.xsp)',
                                'updated_at' => now(),
                                'created_at' => now(),
                            ]
                        );
                        $stats['kenaikan_kelas']++;
                    }
                }

                // 14. Catatan Wali Kelas (`public.tabel_cat_wali`)
                foreach ($tables['public.tabel_cat_wali']['rows'] ?? [] as $row) {
                    $pdUuid = trim((string) ($row['peserta_didik_id'] ?? ''));
                    $siswaId = $siswaUuidToId[$pdUuid] ?? null;
                    $isi = trim((string) ($row['deskripsi'] ?? ''));
                    if (! $siswaId || $isi === '') {
                        continue;
                    }
                    $rUuid = trim((string) ($row['rombongan_belajar_id'] ?? ''));
                    $rInfo = $rombelUuidToInfo[$rUuid] ?? null;
                    $guruId = $rInfo['guru_id'] ?? $fallbackGuruId;
                    $semCode = trim((string) ($row['semester_id'] ?? '20252'));
                    $yr = (int) substr($semCode, 0, 4);
                    $tanggal = ($yr >= 2000)
                        ? (substr($semCode, -1) === '2' ? "{$yr}-06-20" : "{$yr}-12-20")
                        : now()->toDateString();

                    Catatan::withTrashed()->withoutGlobalScopes()->updateOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'siswa_id' => $siswaId,
                            'tanggal' => $tanggal,
                            'jenis' => 'akademik',
                        ],
                        [
                            'guru_id' => $guruId,
                            'nama_siswa' => $siswaUuidToName[$pdUuid] ?? 'Siswa',
                            'nama_guru' => 'Wali Kelas',
                            'catatan' => $isi,
                            'deleted_at' => null,
                        ]
                    );
                    $stats['catatan']++;
                }

                // 15. Tanggal Rapor (`public.tabel_tanggalrapor`)
                foreach ($tables['public.tabel_tanggalrapor']['rows'] ?? [] as $row) {
                    $semMeta = $resolveSemesterMeta((string) ($row['semester_id'] ?? ''));
                    $tgl = trim((string) ($row['tanggal'] ?? ''));
                    if ($tgl === '') {
                        continue;
                    }
                    TanggalRapor::withoutGlobalScopes()->updateOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'tahun_ajaran' => $semMeta['tahun_ajaran'],
                            'semester' => $semMeta['nama_semester'],
                        ],
                        [
                            'tanggal' => $tgl,
                            'tempat' => trim((string) ($row['tempat_ttd'] ?? 'Makassar')),
                            'keterangan' => trim((string) ($row['status_kepsek'] ?? 'Kepala Sekolah')),
                        ]
                    );
                    $stats['tanggal_rapor']++;
                }

                // 16. Kokurikuler (`projec.tema_kokurikuler`, `projec.kegiatan_kokurikuler`, `projec.tabel_klp_kokurikuler`)
                $temaUuidToId = [];
                foreach ($tables['projec.tema_kokurikuler']['rows'] ?? [] as $row) {
                    $tUuid = trim((string) ($row['id_tema'] ?? ''));
                    $nama = trim((string) ($row['nama_tema'] ?? ''));
                    if ($nama === '') {
                        continue;
                    }
                    $tema = TemaKokurikuler::withoutGlobalScopes()->updateOrCreate(
                        ['tenant_id' => $tenantId, 'nama' => $nama],
                        ['deskripsi' => 'Tema Kokurikuler e-Rapor', 'aktif' => ((string) ($row['status'] ?? '1')) === '1']
                    );
                    if ($tUuid !== '') {
                        $temaUuidToId[$tUuid] = $tema->id;
                    }
                    $stats['tema_kokurikuler']++;
                }

                $firstKegiatanId = null;
                foreach ($tables['projec.kegiatan_kokurikuler']['rows'] ?? [] as $row) {
                    $nama = trim((string) ($row['nama_kegiatan'] ?? ''));
                    if ($nama === '') {
                        continue;
                    }
                    $temaId = $temaUuidToId[trim((string) ($row['id_tema'] ?? ''))] ?? reset($temaUuidToId) ?: null;
                    $keg = KegiatanKokurikuler::withoutGlobalScopes()->updateOrCreate(
                        ['tenant_id' => $tenantId, 'nama' => $nama],
                        [
                            'tema_id' => $temaId,
                            'kelas' => trim((string) ($row['fase'] ?? 'A')),
                            'deskripsi' => trim((string) ($row['tujuan_akhir'] ?? $row['deskripsi_kegiatan'] ?? '')),
                            'aktif' => true,
                        ]
                    );
                    $firstKegiatanId ??= $keg->id;
                    $stats['kegiatan_kokurikuler']++;
                }

                if ($firstKegiatanId) {
                    foreach ($tables['projec.tabel_klp_kokurikuler']['rows'] ?? [] as $row) {
                        $nmKlp = trim((string) ($row['nm_kelompok'] ?? ''));
                        if ($nmKlp === '') {
                            continue;
                        }
                        KelompokKokurikuler::withoutGlobalScopes()->updateOrCreate(
                            ['tenant_id' => $tenantId, 'kegiatan_id' => $firstKegiatanId, 'nama' => $nmKlp],
                            ['keterangan' => 'Fase '.trim((string) ($row['fase'] ?? 'A'))]
                        );
                        $stats['kelompok_kokurikuler']++;
                    }
                }

                // 17. Projek P5 (`projec.profil_project` + `projec.nilai_catatanprojek`)
                $projekUuidToTitle = [];
                if (Schema::hasTable('p5_projek')) {
                    foreach ($tables['projec.profil_project']['rows'] ?? [] as $row) {
                        $pUuid = trim((string) ($row['id_project'] ?? ''));
                        $nama = trim((string) ($row['nama_project'] ?? ''));
                        if ($nama === '') {
                            continue;
                        }
                        $projekUuidToTitle[$pUuid] = $nama;
                        $pData = [
                            'nama' => $nama,
                            'tema' => 'Fase '.trim((string) ($row['fase'] ?? 'A')),
                            'deskripsi' => trim((string) ($row['deskripsi_project'] ?? '')),
                            'kelas' => trim((string) ($row['fase'] ?? 'A')),
                            // Kolom tanggal wajib NOT NULL; .xsp tidak menyimpan rentang tanggal projek.
                            'tanggal_mulai' => now()->startOfYear()->toDateString(),
                            'tanggal_selesai' => now()->endOfYear()->toDateString(),
                            'koordinator_id' => $fallbackGuruId,
                            'updated_at' => now(),
                        ];
                        if (Schema::hasColumn('p5_projek', 'tenant_id')) {
                            $pData['tenant_id'] = $tenantId;
                        }

                        DB::table('p5_projek')->updateOrInsert(
                            array_filter([
                                'tenant_id' => Schema::hasColumn('p5_projek', 'tenant_id') ? $tenantId : null,
                                'nama' => $nama,
                            ], fn ($v) => $v !== null),
                            array_merge($pData, ['created_at' => now()])
                        );
                        $stats['p5_projek']++;
                    }
                }

                if (Schema::hasTable('p5_penilaian')) {
                    foreach ($tables['projec.nilai_catatanprojek']['rows'] ?? [] as $row) {
                        $pdUuid = trim((string) ($row['peserta_didik_id'] ?? ''));
                        $siswaId = $siswaUuidToId[$pdUuid] ?? null;
                        $catatanP5 = trim((string) ($row['deskripsi'] ?? ''));
                        if (! $siswaId || $catatanP5 === '') {
                            continue;
                        }
                        $th = trim((string) ($row['th_ajaran'] ?? '2024'));
                        $ta = strlen($th) === 4 ? ($th.'/'.((int) $th + 1)) : $th;
                        $tema = $projekUuidToTitle[trim((string) ($row['id_project'] ?? ''))] ?? 'Projek P5';

                        DB::table('p5_penilaian')->updateOrInsert(
                            [
                                'tenant_id' => $tenantId,
                                'siswa_id' => $siswaId,
                                'tema' => $tema,
                                'dimensi' => 'Profil Pelajar Pancasila',
                                'semester' => 'Genap',
                                'tahun_ajaran' => $ta,
                            ],
                            [
                                'predikat' => 'BSH',
                                'catatan' => $catatanP5,
                                'updated_at' => now(),
                                'created_at' => now(),
                            ]
                        );
                        $stats['p5_penilaian']++;
                    }
                }
            });
        });

        SchoolDataAvailabilityService::forget($tenantId !== null ? (int) $tenantId : null);

        try {
            $guru = auth()->user();
            Aktivitas::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'guru_id' => $guru?->id,
                'nama_guru' => $guru?->nama_lengkap ?? 'Admin',
                'jenis' => 'pengaturan',
                'judul' => 'Restore Database e-Rapor (.xsp)',
                'deskripsi' => sprintf(
                    'Memulihkan .xsp (%s): %d siswa, %d PTK, %d rombel, %d nilai rapor, %d transkrip.',
                    $stats['nama_sekolah'],
                    $stats['siswa'],
                    $stats['ptk'],
                    $stats['rombel'],
                    $stats['nilai_erapor'],
                    $stats['transkrip_nilai']
                ),
            ]);
        } catch (\Throwable) {
        }

        return $stats;
    }

    /**
     * Ekspor data e-Rapor dari SIMANTAP menjadi arsip `.xsp` terenkripsi (kompatibel untuk di-restore kembali).
     *
     * @return string Path absolut ke file `.xsp` yang dibuat.
     */
    public function exportToXsp(?int $tenantId = null, ?string $outputPath = null): string
    {
        $tenantId = $tenantId ?? auth()->user()?->tenant_id ?? TenantContext::id();
        $sekolah = SekolahSettings::withoutGlobalScopes()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->first();

        $npsn = trim((string) ($sekolah?->npsn ?: '40313912'));
        $idSekolah = (string) Str::uuid();
        $semesters = Semester::withoutGlobalScopes()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('semester_id')
            ->get();

        $latestSemCode = (string) ($semesters->last()?->semester_id ?: (date('Y').'2'));
        $latestTahun = substr($latestSemCode, 0, 4);

        $semRows = $semesters->map(fn (Semester $s) => [
            'semester_id' => $s->semester_id,
            'tahun_ajaran_id' => substr((string) $s->tahun_ajaran, 0, 4),
            'nama_semester' => $s->tahun_ajaran.' '.$s->nama_semester,
            'semester' => strtolower($s->nama_semester) === 'genap' ? '2' : '1',
            'periode_aktif' => $s->semester_id === $latestSemCode ? '1' : '0',
            'tanggal_mulai' => now()->startOfYear()->toDateString(),
            'tanggal_selesai' => now()->endOfYear()->toDateString(),
        ])->values()->all();

        $sekolahRows = [[
            'sekolah_id' => $idSekolah,
            'nama' => $sekolah?->nama_sekolah ?? 'SIMANTAP Sekolah',
            'npsn' => $npsn,
            'alamat' => $sekolah?->alamat ?? '',
            'kd_pos' => $sekolah?->kode_pos ?? '',
            'telepon' => $sekolah?->telepon ?? '',
            'kecamatan' => $sekolah?->kecamatan ?? '',
            'kab_kota' => $sekolah?->kota ?? '',
            'propinsi' => $sekolah?->provinsi ?? '',
            'website' => $sekolah?->website ?? '',
            'email' => $sekolah?->email ?? '',
            'nm_kepsek' => $sekolah?->kepala_sekolah ?? '',
            'jenjang' => $sekolah?->jenjang ?? 'SD',
        ]];

        $ptkRows = Ptk::withoutGlobalScopes()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get()
            ->map(fn (Ptk $p) => [
                'ptk_id' => $p->dapodik_id ?: (string) Str::uuid(),
                'nama' => $p->nama,
                'nip' => $p->nip,
                'nuptk' => $p->nuptk,
                'jenis_kelamin' => $p->jk ?? 'L',
                'jenis_ptk_id' => '92',
            ])->values()->all();

        $siswaList = Siswa::withoutGlobalScopes()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get();

        $siswaIdToUuid = [];
        $siswaRows = [];
        foreach ($siswaList as $s) {
            $pdUuid = $s->dapodik_id ?: (string) $s->uuid;
            $siswaIdToUuid[$s->id] = $pdUuid;
            $siswaRows[] = [
                'peserta_didik_id' => $pdUuid,
                'nis' => $s->nis,
                'nisn' => $s->nisn,
                'nm_siswa' => $s->nama_peserta_didik,
                'tempat_lahir' => $s->tempat_lahir,
                'tanggal_lahir' => $s->tanggal_lahir?->format('Y-m-d'),
                'jenis_kelamin' => $s->jenis_kelamin ?? 'L',
                'agama' => $s->agama ?? 'Islam',
                'alamat_siswa' => $s->alamat,
                'telepon_siswa' => $s->telepon,
                'nm_ayah' => $s->nama_orang_tua,
            ];
        }

        $mapelList = MataPelajaran::withoutGlobalScopes()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get();

        $mapelNameToCode = [];
        $mapelRows = [];
        foreach ($mapelList as $idx => $m) {
            $code = $m->kode ?: (string) (100000000 + $idx + 1);
            $mapelNameToCode[$m->nama] = $code;
            $mapelRows[] = [
                'mata_pelajaran_id' => $code,
                'nm_mapel' => $m->nama,
                'nm_lokal' => $m->nama,
                'kelompok' => $m->kelompok ?: 'A',
                'urut_rapor' => (string) ($m->urutan ?: ($idx + 1)),
            ];
        }

        $nilaiList = NilaiErapot::withoutGlobalScopes()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get();

        $nilaiRows = [];
        $deskripsiRows = [];
        foreach ($nilaiList as $n) {
            $pdUuid = $siswaIdToUuid[$n->siswa_id] ?? null;
            if (! $pdUuid) {
                continue;
            }
            $kdMapel = $mapelNameToCode[$n->mata_pelajaran] ?? $n->mata_pelajaran;
            $yr = substr((string) $n->tahun_ajaran, 0, 4);
            $smtNum = strtolower((string) $n->semester) === 'genap' ? '2' : '1';
            $semCode = $yr.$smtNum;

            $nilaiRows[] = [
                'id_nilai_akhir' => (string) ($n->uuid ?: Str::uuid()),
                'anggota_rombel_id' => (string) Str::uuid(),
                'peserta_didik_id' => $pdUuid,
                'mata_pelajaran_id' => $kdMapel,
                'semester_id' => $semCode,
                'semester' => $smtNum,
                'nilai_peng' => (string) round((float) $n->nilai_akhir),
                'predikat_peng' => $n->predikat,
            ];

            if (! empty($n->deskripsi_capaian)) {
                $deskripsiRows[] = [
                    'deskripsi_id' => (string) Str::uuid(),
                    'peserta_didik_id' => $pdUuid,
                    'mata_pelajaran_id' => $kdMapel,
                    'semester_id' => $semCode,
                    'semester' => $smtNum,
                    'deskripsi_peng_m' => $n->deskripsi_capaian,
                ];
            }
        }

        $entries = [
            1 => ['nm_tabel' => 'semester', 'tabel' => 'public.semester', 'data' => $semRows],
            2 => ['nm_tabel' => 'tabel sekolah', 'tabel' => 'public.tabel_sekolah', 'data' => $sekolahRows],
            3 => ['nm_tabel' => 'tabel ptk', 'tabel' => 'public.tabel_ptk', 'data' => $ptkRows],
            6 => ['nm_tabel' => 'tabel siswa', 'tabel' => 'public.tabel_siswa', 'data' => $siswaRows],
            12 => ['nm_tabel' => 'tabel mapel', 'tabel' => 'public.tabel_mapel', 'data' => $mapelRows],
            29 => ['nm_tabel' => 'tabel nilaiakhir', 'tabel' => 'public.tabel_nilaiakhir', 'data' => $nilaiRows],
            40 => ['nm_tabel' => 'tabel deskripsi', 'tabel' => 'public.tabel_deskripsi', 'data' => $deskripsiRows],
        ];

        $key = $this->keyBinary();
        $innerTemp = tempnam(sys_get_temp_dir(), 'erapor_inner_');
        if ($innerTemp === false) {
            throw new RuntimeException('Gagal membuat file sementara untuk ekspor .xsp.');
        }

        $outerPath = $outputPath ?: (sys_get_temp_dir().DIRECTORY_SEPARATOR."{$npsn}{$latestSemCode}".date('Ymd')."_{$latestSemCode}.xsp");

        try {
            $zipInner = new ZipArchive;
            if ($zipInner->open($innerTemp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Gagal membuat arsip ZIP lapis dalam untuk .xsp.');
            }

            foreach ($entries as $idx => $spec) {
                $envelope = [[
                    'id_sekolah' => $idSekolah,
                    'nm_tabel' => $spec['nm_tabel'],
                    'semester' => $latestSemCode,
                    'tahun' => $latestTahun,
                    'tabel' => $spec['tabel'],
                    'jumlah' => count($spec['data']),
                    'data' => $spec['data'],
                ]];
                $plainJson = json_encode($envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                $enc = $this->encryptEntry((string) $plainJson, $key);
                $zipInner->addFromString("bcke_{$idx}.enc", $enc);
            }
            $zipInner->close();

            $zipOuter = new ZipArchive;
            if ($zipOuter->open($outerPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Gagal membuat file akhir .xsp.');
            }
            $zipOuter->addFile($innerTemp, 'file_hasilbackuprsp.xsp');
            $zipOuter->close();

            return $outerPath;
        } finally {
            if (is_file($innerTemp)) {
                @unlink($innerTemp);
            }
        }
    }
}
