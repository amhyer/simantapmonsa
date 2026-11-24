<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DapodikSyncLog;
use App\Models\JadwalPelajaran;
use App\Models\Kehadiran;
use App\Models\MataPelajaran;
use App\Models\NilaiErapot;
use App\Models\Ptk;
use App\Models\Rombel;
use App\Models\SekolahSettings;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use App\Services\Navigation\SchoolDataAvailabilityService;
use App\Support\InitialPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DapodikSyncController extends Controller
{
    public function store(Request $request, string $modul)
    {
        @set_time_limit(300);

        // Mode multi-sekolah: konteks sekolah WAJIB berasal dari pemilik API Key
        // (middleware `tenant`), bukan dari payload Bridge.
        if ($this->isMultiSchool() && ! TenantContext::check()) {
            return response()->json([
                'success' => false,
                'message' => 'API Key harus terhubung ke akun dan sekolah yang valid.',
            ], 403);
        }

        $request->merge([
            'nama_semester' => strtolower(trim((string) $request->input('nama_semester', ''))),
        ]);

        $request->validate([
            'semester_id' => 'required|string|max:20',
            'tahun_ajaran' => 'required|string|max:20',
            'nama_semester' => 'required|in:ganjil,genap',
            'data' => 'required|array|max:2000',
        ]);

        $semesterId = $request->input('semester_id');
        $namaSemesterNorm = strtolower(trim((string) $request->input('nama_semester')));
        $semester = Semester::firstOrCreate(
            ['semester_id' => $semesterId],
            [
                'tahun_ajaran' => $request->input('tahun_ajaran'),
                'nama_semester' => $namaSemesterNorm,
            ]
        );

        $data = $request->input('data', []);

        $result = match ($modul) {
            'sekolah' => $this->syncSekolah($data),
            'peserta-didik' => $this->syncPesertaDidik($data, $semester),
            'ptk' => $this->syncPtk($data, $semester),
            'rombongan-belajar' => $this->syncRombel($data, $semester),
            'mata-pelajaran' => $this->syncMataPelajaran($data, $semester),
            'jadwal', 'pembelajaran' => $this->syncJadwal($data, $semester),
            default => null,
        };

        if ($result === null) {
            return response()->json(['success' => false, 'message' => 'Modul tidak dikenal: '.$modul], 404);
        }

        $tipe = match ($modul) {
            'sekolah' => 'semua',
            'peserta-didik' => 'siswa',
            'ptk' => 'gtk',
            'rombongan-belajar' => 'rombel',
            'mata-pelajaran' => 'mapel',
            'jadwal', 'pembelajaran' => 'jadwal',
            default => $modul,
        };

        DapodikSyncLog::create([
            'uuid' => Str::uuid(),
            'user_id' => $request->user()?->id,
            'sekolah_npsn' => null,
            'nama_sekolah' => 'Dapodik Sync',
            'tipe' => $tipe,
            'tahun_ajaran' => $request->input('tahun_ajaran'),
            'semester' => $request->input('nama_semester'),
            'total_data' => count($data),
            'berhasil' => $result['berhasil'],
            'gagal' => $result['gagal'],
            'diperbarui' => $result['diperbarui'],
            'dilewati' => $result['dilewati'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        SchoolDataAvailabilityService::forget(TenantContext::id());

        return response()->json([
            'success' => true,
            'modul' => $modul,
            'jumlah' => count($data),
            'berhasil' => $result['berhasil'],
            'diperbarui' => $result['diperbarui'],
            'dilewati' => $result['dilewati'],
            'gagal' => $result['gagal'],
        ]);
    }

    private function isMultiSchool(): bool
    {
        return TenantContext::check() || config('simantap.multi_tenant_enabled', false) || Tenant::hasAnyTenant();
    }

    /**
     * Eksekusi batch sinkronisasi secara atomik per-kelompok (default 50 baris)
     * menggunakan savepoint per-baris agar 1 baris korup tidak membatalkan baris valid lain (PostgreSQL 25P02).
     *
     * @param  array<int, mixed>  $items
     * @return array{berhasil: int, gagal: int, diperbarui: int, dilewati: int}
     */
    private function processInBatches(array $items, callable $rowHandler, ?callable $onError = null): array
    {
        $stats = ['berhasil' => 0, 'gagal' => 0, 'diperbarui' => 0, 'dilewati' => 0];
        $batchSize = max(1, (int) config('dapodik.batch_size', 50));

        foreach (array_chunk($items, $batchSize) as $chunk) {
            DB::transaction(function () use ($chunk, $rowHandler, $onError, &$stats) {
                foreach ($chunk as $item) {
                    try {
                        if (is_string($item) && is_array($decoded = json_decode($item, true))) {
                            $item = $decoded;
                        }
                        if (! is_array($item)) {
                            $stats['gagal']++;
                            if ($onError !== null) {
                                $onError($item, new \InvalidArgumentException('Format baris payload bukan array JSON yang valid.'));
                            }

                            continue;
                        }
                        $outcome = DB::transaction(fn () => $rowHandler($item));
                        if (isset($stats[$outcome])) {
                            $stats[$outcome]++;
                        }
                    } catch (\Throwable $e) {
                        $stats['gagal']++;
                        if ($onError !== null) {
                            $onError($item, $e);
                        }
                    }
                }
            }, 3);
        }

        return $stats;
    }

    private function normalizeJenjang(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }

        $rawUpper = strtoupper(trim($raw));
        if (str_contains($rawUpper, 'SD') || str_contains($rawUpper, 'SEKOLAH DASAR')) {
            return 'SD';
        }
        if (str_contains($rawUpper, 'MI') || str_contains($rawUpper, 'MADRASAH IBTIDAIYAH')) {
            return 'MI';
        }
        if (str_contains($rawUpper, 'SMP') || str_contains($rawUpper, 'SEKOLAH MENENGAH PERTAMA')) {
            return 'SMP';
        }
        if (str_contains($rawUpper, 'MTS') || str_contains($rawUpper, 'MADRASAH TSANAWIYAH')) {
            return 'MTs';
        }
        if (str_contains($rawUpper, 'SMA') || str_contains($rawUpper, 'SEKOLAH MENENGAH ATAS')) {
            return 'SMA';
        }
        if (str_contains($rawUpper, 'SMK') || str_contains($rawUpper, 'KEJURUAN')) {
            return 'SMK';
        }
        if (str_contains($rawUpper, 'MA') && ! str_contains($rawUpper, 'MAK')) {
            return 'MA';
        }
        if (str_contains($rawUpper, 'SLB') || str_contains($rawUpper, 'LUAR BIASA')) {
            return 'SLB';
        }

        return in_array($rawUpper, ['SD', 'MI', 'SMP', 'MTS', 'SMA', 'MA', 'SMK', 'SLB']) ? $rawUpper : null;
    }

    private function syncSekolah(array $data): array
    {
        $currentUserId = auth()->id();
        $tenant = TenantContext::check() ? Tenant::find(TenantContext::id()) : null;

        return $this->processInBatches(
            $data,
            function (array $s) use ($currentUserId, $tenant): string {
                // Bridge hanya boleh mengirim profil sekolah miliknya sendiri.
                if ($tenant && ! empty($tenant->npsn) && ! empty($s['npsn']) && (string) $s['npsn'] !== (string) $tenant->npsn) {
                    throw new \RuntimeException('NPSN Dapodik ('.$s['npsn'].') tidak sesuai dengan NPSN sekolah pemilik API Key.');
                }
                if ($tenant && empty($s['npsn'])) {
                    $s['npsn'] = $tenant->npsn;
                }

                $rawJenjang = $s['jenjang'] ?? $s['bentuk_pendidikan_id_str'] ?? $s['tipe_sekolah'] ?? $s['bentuk_pendidikan'] ?? null;
                $jenjang = $this->normalizeJenjang($rawJenjang);

                $mapped = [
                    'guru_id' => $currentUserId,
                    'npsn' => $s['npsn'] ?? '',
                    'nama_sekolah' => $s['nama'] ?? $s['nama_sekolah'] ?? '',
                    'alamat' => $s['alamat_jalan'] ?? $s['alamat'] ?? '',
                    'telepon' => $s['telepon'] ?? '',
                    'email' => $s['email'] ?? '',
                    'kepala_sekolah' => $s['nama_kepala_sekolah'] ?? $s['kepala_sekolah'] ?? null,
                    'kecamatan' => $s['nama_kecamatan'] ?? $s['kecamatan'] ?? null,
                    'kota' => $s['nama_kabupaten'] ?? $s['kota'] ?? null,
                    'provinsi' => $s['nama_propinsi'] ?? $s['provinsi'] ?? null,
                    'kode_pos' => $s['kode_pos'] ?? null,
                    'website' => $s['website'] ?? null,
                    'jenjang' => $jenjang,
                    'jenis_sekolah' => $s['status_sekolah'] ?? $s['jenis_sekolah'] ?? null,
                    'akreditasi' => $s['akreditasi'] ?? null,
                    'status' => 'aktif',
                ];

                // Query otomatis dibatasi tenant aktif (TenantScope). Satu sekolah hanya memiliki
                // satu baris pengaturan identitas, jadi fallback ke baris pertama bila NPSN belum terisi.
                $existing = SekolahSettings::where('npsn', $s['npsn'] ?? null)->first()
                    ?? SekolahSettings::first();
                if ($existing) {
                    $updateData = $mapped;
                    unset($updateData['guru_id']);
                    if ($existing->guru_id === null && $currentUserId !== null
                        && ! SekolahSettings::withoutGlobalScopes()->where('guru_id', $currentUserId)->exists()) {
                        $updateData['guru_id'] = $currentUserId;
                    }
                    $existing->update($updateData);

                    return 'diperbarui';
                }

                if ($currentUserId !== null && SekolahSettings::withoutGlobalScopes()->where('guru_id', $currentUserId)->exists()) {
                    $mapped['guru_id'] = null;
                }
                SekolahSettings::create($mapped);

                return 'berhasil';
            },
            function ($s, \Throwable $e): void {
                Log::warning('Gagal sync sekolah settings: '.$e->getMessage());
            }
        );
    }

    private function syncPesertaDidik(array $data, Semester $semester): array
    {
        return $this->processInBatches(
            $data,
            function (array $pd) use ($semester): string {
                $dapodikId = $pd['peserta_didik_id'] ?? $pd['dapodik_id'] ?? null;
                $nisn = $pd['nisn'] ?? null;
                $nipd = $pd['nis'] ?? $pd['nipd'] ?? null;

                $existing = Siswa::where('semester_id', $semester->id)
                    ->where(function ($query) use ($dapodikId, $nisn, $nipd) {
                        $hasCondition = false;
                        if (! empty($dapodikId)) {
                            $query->where('dapodik_id', $dapodikId);
                            $hasCondition = true;
                        }
                        if (! empty($nisn)) {
                            $hasCondition ? $query->orWhere('nisn', $nisn) : $query->where('nisn', $nisn);
                            $hasCondition = true;
                        }
                        if (! empty($nipd)) {
                            $hasCondition ? $query->orWhere('nis', $nipd) : $query->where('nis', $nipd);
                        }
                    })
                    ->first();

                $rombelId = null;
                $guruId = null;
                $rombelDapodikId = $pd['rombongan_belajar_id'] ?? null;
                $rombelName = $pd['nama_rombel'] ?? $pd['rombel'] ?? $pd['kelas'] ?? null;

                $rombel = null;
                if ($rombelDapodikId) {
                    $rombel = Rombel::where('dapodik_id', $rombelDapodikId)
                        ->where('semester_id', $semester->id)
                        ->first();
                }

                if (! $rombel && $rombelName) {
                    $rombel = Rombel::where('nama_rombel', $rombelName)
                        ->where('semester_id', $semester->id)
                        ->first();
                    if (! $rombel) {
                        $rombel = Rombel::where('nama_rombel', 'like', '%'.$rombelName.'%')
                            ->where('semester_id', $semester->id)
                            ->first();
                    }
                }

                if ($rombel) {
                    $rombelId = $rombel->id;
                    $guruId = $rombel->guru_id;
                    $rombelName = $rombel->nama_rombel;
                }

                $siswaData = [
                    'dapodik_id' => $dapodikId,
                    'semester_id' => $semester->id,
                    'nis' => $pd['nis'] ?? $nisn ?? 'DAPODIK-'.Str::random(8),
                    'nisn' => $nisn,
                    'nama_peserta_didik' => $pd['nama'] ?? $pd['nama_peserta_didik'] ?? '',
                    'kelas' => $rombelName ?? '',
                    'jenis_kelamin' => strtoupper(substr($pd['jenis_kelamin'] ?? 'L', 0, 1)),
                    'nama_orang_tua' => $pd['nama_ayah'] ?? $pd['nama_ibu'] ?? null,
                    'telepon' => $pd['telepon'] ?? null,
                    'aktif' => ($pd['status'] ?? 'aktif') === 'aktif',
                    'status_siswa' => $pd['status'] ?? 'aktif',
                    'guru_id' => $guruId,
                    'rombel_id' => $rombelId,
                    'rekaman' => $pd,
                ];

                if ($existing) {
                    $existing->update($siswaData);

                    return 'diperbarui';
                }

                $siswaData['uuid'] = Str::uuid();
                Siswa::create($siswaData);

                return 'berhasil';
            },
            function ($pd, \Throwable $e): void {
                $id = is_array($pd) ? ($pd['peserta_didik_id'] ?? $pd['dapodik_id'] ?? 'unknown') : 'unknown';
                Log::warning("Gagal sync peserta didik dapodik_id: {$id}: ".$e->getMessage());
            }
        );
    }

    private function syncPtk(array $data, Semester $semester): array
    {
        return $this->processInBatches(
            $data,
            function (array $p) use ($semester): string {
                $dapodikId = $p['ptk_id'] ?? $p['dapodik_id'] ?? null;
                $nama = trim($p['nama'] ?? '');
                $nip = trim($p['nip'] ?? '');
                $nuptk = trim($p['nuptk'] ?? '');
                $nik = trim($p['nik'] ?? '');
                $jenisPtk = strtolower(trim($p['jenis_ptk'] ?? $p['jenis_ptk_id'] ?? ''));
                $jabatan = trim($p['jabatan_ptk'] ?? $p['jabatan'] ?? $p['tugas_tambahan'] ?? $p['nama_tugas_tambahan'] ?? '');
                $jabatanLower = strtolower($jabatan);
                $jkRaw = strtoupper(substr(trim($p['jenis_kelamin'] ?? ''), 0, 1));
                $jk = in_array($jkRaw, ['L', 'P'], true) ? $jkRaw : null;

                // Tendik sering tidak punya NIP/NUPTK; jangan lewati jika memiliki NIK atau dapodikId
                if (empty($nama) || (empty($nip) && empty($nuptk) && empty($nik) && empty($dapodikId))) {
                    return 'dilewati';
                }

                $existing = null;
                if ($dapodikId) {
                    $existing = Ptk::where('dapodik_id', $dapodikId)
                        ->where('semester_id', $semester->id)
                        ->first();
                }

                $ptkData = [
                    'dapodik_id' => $dapodikId,
                    'semester_id' => $semester->id,
                    'nama' => $nama,
                    'nip' => $nip ?: null,
                    'nuptk' => $nuptk ?: null,
                    'jk' => $jk,
                    'gelar_depan' => isset($p['gelar_depan']) ? substr(trim($p['gelar_depan']), 0, 50) ?: null : null,
                    'gelar_belakang' => isset($p['gelar_belakang']) ? substr(trim($p['gelar_belakang']), 0, 100) ?: null : null,
                    'jenis_ptk' => $p['jenis_ptk'] ?? 'Guru',
                    'jabatan' => $jabatan ?: null,
                ];

                $outcome = 'berhasil';
                if ($existing) {
                    $existing->update($ptkData);
                    $outcome = 'diperbarui';
                } else {
                    Ptk::create($ptkData);
                }

                // Deteksi apakah ditugaskan sebagai Kepala Sekolah atau PLT / Pjs Kepala Sekolah
                $isKepsek = str_contains($jenisPtk, 'kepala sekolah')
                    || str_contains($jabatanLower, 'kepala sekolah')
                    || str_contains($jabatanLower, 'plt')
                    || str_contains($jabatanLower, 'pjs')
                    || str_contains($jabatanLower, 'pelaksana tugas');

                // Deteksi Tendik (Tenaga Kependidikan)
                $isTendik = in_array($jenisPtk, ['3', '4', 'tendik', 'tenaga kependidikan', 'tenaga'], true)
                    || str_contains($jenisPtk, 'tendik')
                    || str_contains($jenisPtk, 'tenaga')
                    || str_contains($jabatanLower, 'tata usaha')
                    || str_contains($jabatanLower, 'perpustakaan')
                    || str_contains($jabatanLower, 'laboratorium')
                    || str_contains($jabatanLower, 'operator')
                    || str_contains($jabatanLower, 'kebersihan')
                    || str_contains($jabatanLower, 'keamanan')
                    || str_contains($jabatanLower, 'penjaga');

                if ($isKepsek) {
                    $peran = 'kepsek';
                } elseif ($isTendik) {
                    $peran = 'tendik';
                } else {
                    $peran = 'guru';
                }

                // Jika terdeteksi Kepala Sekolah / PLT, simpan namanya juga di data identitas sekolah (SekolahSettings)
                if ($isKepsek) {
                    $namaGelar = trim(($ptkData['gelar_depan'] ? $ptkData['gelar_depan'].' ' : '').$nama);
                    if (! empty($ptkData['gelar_belakang'])) {
                        $namaGelar .= ', '.$ptkData['gelar_belakang'];
                    }

                    $sekolah = SekolahSettings::first();
                    if ($sekolah) {
                        $pengaturan = $sekolah->pengaturan ?? [];
                        if (! empty($nip)) {
                            $pengaturan['nip_kepala_sekolah'] = $nip;
                        }
                        $sekolah->update([
                            'kepala_sekolah' => $namaGelar,
                            'pengaturan' => $pengaturan,
                        ]);
                    }
                }

                $username = $nip ?: ($nuptk ?: ($nik ?: ('gtk_'.($dapodikId ? substr(md5($dapodikId), 0, 8) : Str::lower(Str::random(8))))));
                $isActive = strtolower($p['status_aktif'] ?? $p['status'] ?? 'aktif') === 'aktif';

                $existingUser = null;
                if ($dapodikId) {
                    $existingUser = User::forSchool()->withTrashed()->where('dapodik_id', $dapodikId)->first();
                }
                if (! $existingUser && $username) {
                    $existingUser = User::forSchool()->withTrashed()->where('nama_pengguna', $username)->first();
                }

                if ($existingUser) {
                    if ($existingUser->trashed()) {
                        $existingUser->restore();
                    }
                    $existingUser->update([
                        'nama_lengkap' => $nama,
                        'nama_pengguna' => $username,
                        'peran' => $peran,
                        'nip' => $nip ?: null,
                        'aktif' => $isActive,
                        'archived_at' => null,
                    ]);
                } else {
                    $base = $username;
                    $finalUsername = $base;
                    $counter = 1;
                    while (User::forSchool()->withTrashed()->where('nama_pengguna', $finalUsername)->exists()) {
                        $finalUsername = $base.'.'.$counter;
                        $counter++;
                    }

                    User::create([
                        'uuid' => Str::uuid(),
                        'nama_lengkap' => $nama,
                        'nama_pengguna' => $finalUsername,
                        'kata_sandi' => Hash::make(InitialPassword::generate()),
                        'peran' => $peran,
                        'nip' => $nip ?: null,
                        'aktif' => $isActive,
                        'force_password_change' => true,
                        'dapodik_id' => $dapodikId,
                        'archived_at' => null,
                    ]);
                }

                return $outcome;
            },
            function ($p, \Throwable $e): void {
                $id = is_array($p) ? ($p['ptk_id'] ?? $p['dapodik_id'] ?? 'unknown') : 'unknown';
                Log::warning("Gagal sync PTK dapodik_id {$id}: ".$e->getMessage());
            }
        );
    }

    private function syncRombel(array $data, Semester $semester): array
    {
        return $this->processInBatches(
            $data,
            function (array $r) use ($semester): string {
                $dapodikId = $r['rombongan_belajar_id'] ?? $r['dapodik_id'] ?? null;
                $namaRombel = $r['nama'] ?? $r['nama_rombel'] ?? null;

                $existing = Rombel::where('semester_id', $semester->id)
                    ->where(function ($query) use ($dapodikId, $namaRombel) {
                        $hasCondition = false;
                        if (! empty($dapodikId)) {
                            $query->where('dapodik_id', $dapodikId);
                            $hasCondition = true;
                        }
                        if (! empty($namaRombel)) {
                            $hasCondition ? $query->orWhere('nama_rombel', $namaRombel) : $query->where('nama_rombel', $namaRombel);
                        }
                    })
                    ->first();

                $guruId = null;
                $waliKelasPtkId = $r['ptk_id'] ?? $r['guru_id'] ?? $r['wali_kelas_ptk_id'] ?? null;
                if ($waliKelasPtkId) {
                    $waliUser = User::forSchool()->where('dapodik_id', $waliKelasPtkId)->first();
                    if ($waliUser) {
                        $guruId = $waliUser->id;
                    } else {
                        $ptk = Ptk::where('dapodik_id', $waliKelasPtkId)->first();
                        if ($ptk && $ptk->nip) {
                            $waliUser = User::forSchool()->where('nama_pengguna', $ptk->nip)->first();
                            if ($waliUser) {
                                $guruId = $waliUser->id;
                            }
                        }
                    }
                }

                $rombelData = [
                    'dapodik_id' => $dapodikId,
                    'semester_id' => $semester->id,
                    'nama_rombel' => $r['nama'] ?? $r['nama_rombel'] ?? '',
                    'tingkat' => $r['tingkat_pendidikan_id'] ?? $r['tingkat'] ?? null,
                    'kurikulum' => $r['kurikulum'] ?? $r['nama_kurikulum'] ?? null,
                    'jenis_rombel' => $r['jenis_rombel'] ?? $r['jenis_rombel_id'] ?? null,
                    'guru_id' => $guruId,
                ];

                if ($existing) {
                    $existing->update($rombelData);
                    $this->reconcileSiswaRombel($existing, $semester);

                    return 'diperbarui';
                }

                $newRombel = Rombel::create($rombelData);
                $this->reconcileSiswaRombel($newRombel, $semester);

                return 'berhasil';
            }
        );
    }

    /**
     * Otomatis mengaitkan siswa yang rombel_id masih kosong ke rombel yang sesuai.
     */
    private function reconcileSiswaRombel(Rombel $rombel, Semester $semester): void
    {
        $siswaQuery = Siswa::where('semester_id', $semester->id)
            ->whereNull('rombel_id');

        $siswaList = $siswaQuery->get();
        foreach ($siswaList as $siswa) {
            $matched = false;
            $recRombelId = $siswa->rekaman['rombongan_belajar_id'] ?? null;
            $recRombelName = $siswa->rekaman['nama_rombel'] ?? $siswa->rekaman['rombel'] ?? $siswa->rekaman['kelas'] ?? $siswa->kelas;

            if ($rombel->dapodik_id && $recRombelId && $rombel->dapodik_id === $recRombelId) {
                $matched = true;
            } elseif ($recRombelName && (
                strcasecmp($rombel->nama_rombel, $recRombelName) === 0
                || str_contains(strtolower($rombel->nama_rombel), strtolower($recRombelName))
                || str_contains(strtolower($recRombelName), strtolower($rombel->nama_rombel))
            )) {
                $matched = true;
            }

            if ($matched) {
                $siswa->update([
                    'rombel_id' => $rombel->id,
                    'guru_id' => $rombel->guru_id ?? $siswa->guru_id,
                    'kelas' => $rombel->nama_rombel,
                ]);
            }
        }
    }

    private function isClassName(string $name): bool
    {
        $lower = strtolower(trim($name));
        if (str_starts_with($lower, 'kelas')) {
            return true;
        }
        if (preg_match('/^kelas\s+\d+/i', $lower)) {
            return true;
        }
        if (preg_match('/^(xii|xi|x|ix|viii|vii|vi|v|iv|iii|ii|i)[\s\.]/i', $lower)) {
            return true;
        }
        if (preg_match('/^\d+\s*[a-zA-Z]/', $name)) {
            return true;
        }

        return false;
    }

    private function syncMataPelajaran(array $data, Semester $semester): array
    {
        $sekolah = SekolahSettings::first();
        $jenjangSekolah = $sekolah?->jenjang ?? 'SD';

        return $this->processInBatches(
            $data,
            function (array $m) use ($jenjangSekolah): string {
                $nama = trim($m['nama'] ?? $m['nama_mata_pelajaran'] ?? $m['mata_pelajaran'] ?? '');
                if ($nama === '' || $this->isClassName($nama)) {
                    return 'dilewati';
                }

                $kode = trim($m['kode'] ?? $m['kode_mata_pelajaran'] ?? $m['mata_pelajaran_id'] ?? '');

                $rawJenjang = $m['tingkat_pendidikan_id_str'] ?? $m['jenjang'] ?? null;
                $itemJenjang = $this->normalizeJenjang($rawJenjang) ?? $jenjangSekolah;

                $existing = MataPelajaran::where('nama', $nama)
                    ->where('jenjang', $itemJenjang)
                    ->first();

                $payload = [
                    'nama' => $nama,
                    'kode' => $kode ?: ($existing?->kode ?? null),
                    'jenjang' => $itemJenjang,
                    'kelompok' => $m['kelompok'] ?? $existing?->kelompok ?? 'A',
                    'aktif' => true,
                ];

                if ($existing) {
                    $existing->update($payload);

                    return 'diperbarui';
                }

                MataPelajaran::create($payload);

                return 'berhasil';
            }
        );
    }

    private function syncJadwal(array $data, Semester $semester): array
    {
        $sekolah = SekolahSettings::first();
        $jenjangSekolah = $sekolah?->jenjang ?? 'SD';

        return $this->processInBatches(
            $data,
            function (array $j) use ($semester, $jenjangSekolah): string {
                $dapodikId = $j['pembelajaran_id'] ?? $j['jadwal_id'] ?? $j['dapodik_id'] ?? null;
                $namaMapel = trim($j['nama_mata_pelajaran'] ?? $j['mata_pelajaran_id_str'] ?? $j['nama_mapel'] ?? $j['mata_pelajaran'] ?? '');
                $namaRombel = trim($j['nama_rombel'] ?? $j['rombongan_belajar_id_str'] ?? $j['kelas'] ?? '');

                // Selalu inisialisasi dan resolusi rombel bila rombongan_belajar_id tersedia
                $rombelObj = null;
                if (! empty($j['rombongan_belajar_id'])) {
                    $rombelObj = Rombel::where('dapodik_id', $j['rombongan_belajar_id'])
                        ->where('semester_id', $semester->id)
                        ->first();
                    if ($namaRombel === '' && $rombelObj) {
                        $namaRombel = $rombelObj->nama_rombel;
                    }
                }

                // Otomatis registrasi mata pelajaran ke tabel mata_pelajaran
                if ($namaMapel !== '' && ! $this->isClassName($namaMapel)) {
                    MataPelajaran::firstOrCreate(
                        [
                            'nama' => $namaMapel,
                            'jenjang' => $jenjangSekolah,
                        ],
                        [
                            'kode' => $j['kode_mata_pelajaran'] ?? null,
                            'kelompok' => 'A',
                            'aktif' => true,
                        ]
                    );
                }

                // Lookup guru jika ada PTK ID atau nama guru (terbatas sekolah aktif)
                $guruId = null;
                if (! empty($j['ptk_id'])) {
                    $ptk = Ptk::where('dapodik_id', $j['ptk_id'])->first();
                    $guruId = User::forSchool()->where(function ($q) use ($j, $ptk) {
                        $q->where('dapodik_id', $j['ptk_id']);
                        if ($ptk?->nip) {
                            $q->orWhere('nama_pengguna', $ptk->nip);
                        }
                        if ($ptk?->nuptk) {
                            $q->orWhere('nama_pengguna', $ptk->nuptk);
                        }
                    })->value('id');
                }
                $rawGuru = $j['ptk_id_str'] ?? $j['nama_guru'] ?? $j['nama_ptk'] ?? null;
                if (! $guruId && ! empty($rawGuru)) {
                    $namaGuru = trim((string) $rawGuru);
                    $guruId = User::forSchool()->whereRaw('LOWER(nama_lengkap) = ?', [strtolower($namaGuru)])->value('id');
                }
                if (! $guruId && ! empty($j['guru_id'])) {
                    $guruId = User::forSchool()->whereKey($j['guru_id'])->value('id');
                }
                if (! $guruId) {
                    $guruId = auth()->id() ?? User::forSchool()->where('peran', 'admin')->value('id') ?? User::forSchool()->value('id');
                }

                $hari = trim($j['hari'] ?? '');
                $validHari = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                if (! in_array($hari, $validHari)) {
                    $hari = 'Senin';
                }
                $jamMulai = ! empty($j['jam_mulai']) ? $j['jam_mulai'] : '07:00:00';
                $jamSelesai = ! empty($j['jam_selesai']) ? $j['jam_selesai'] : '08:00:00';

                $existing = null;
                if ($dapodikId) {
                    $existing = JadwalPelajaran::where('dapodik_id', $dapodikId)
                        ->where('semester_id', $semester->id)
                        ->first();
                }
                if (! $existing && ! empty($namaRombel) && ! empty($namaMapel)) {
                    $existingQuery = JadwalPelajaran::where('semester_id', $semester->id)
                        ->where('kelas', $namaRombel)
                        ->where('mata_pelajaran', $namaMapel);

                    if (! empty($hari)) {
                        $existingQuery->where('hari', $hari);
                    }
                    $existing = $existingQuery->first();
                }

                $jadwalData = [
                    'dapodik_id' => $dapodikId,
                    'semester_id' => $semester->id,
                    'rombel_id' => $rombelObj?->id,
                    'kelas' => $namaRombel,
                    'mata_pelajaran' => $namaMapel,
                    'hari' => $hari,
                    'jam_mulai' => $jamMulai,
                    'jam_selesai' => $jamSelesai,
                    'ruangan' => $j['ruangan'] ?? null,
                    'guru_id' => $guruId,
                ];

                if ($existing) {
                    $existing->update($jadwalData);

                    return 'diperbarui';
                }

                JadwalPelajaran::create($jadwalData);

                return 'berhasil';
            },
            function ($j, \Throwable $e): void {
                Log::error('Gagal sync jadwal/pembelajaran: '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine());
            }
        );
    }

    public function status(Request $request)
    {
        // Semua model di bawah ini sudah ter-scope per sekolah (tenant) otomatis.
        $semesters = Semester::orderByDesc('semester_id')->get();
        $stats = [
            'semesters' => $semesters->count(),
            'siswa' => Siswa::count(),
            'ptk' => Ptk::count(),
            'rombel' => Rombel::count(),
            'jadwal' => JadwalPelajaran::count(),
        ];

        $recentLogs = DapodikSyncLog::orderByDesc('created_at')->limit(10)->get();

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'semesters' => $semesters,
            'recent_logs' => $recentLogs,
        ]);
    }

    /**
     * Endpoint ekspor data SIMANTAP untuk dikirim ke Dapodik via Bridge.
     */
    public function export(Request $request, string $modul)
    {
        $semesterId = $request->query('semester_id');
        $semester = $semesterId ? Semester::where('semester_id', $semesterId)->first() : null;

        return match ($modul) {
            'nilai-rapor' => $this->exportNilaiRapor($semester),
            'kehadiran' => $this->exportKehadiran($semester),
            'sekolah' => $this->exportSekolah(),
            'peserta-didik' => $this->exportPesertaDidik($semester),
            default => response()->json([
                'success' => false,
                'message' => "Modul export '{$modul}' tidak didukung. Pilihan: nilai-rapor, kehadiran, sekolah, peserta-didik.",
            ], 404),
        };
    }

    private function exportNilaiRapor(?Semester $semester)
    {
        $query = NilaiErapot::with('siswa');
        if ($semester) {
            $siswaIds = Siswa::where('semester_id', $semester->id)->pluck('id');
            $query->whereIn('siswa_id', $siswaIds)
                ->where('tahun_ajaran', $semester->tahun_ajaran);
        }

        $items = $query->get()->map(function ($nilai) use ($semester) {
            return [
                'peserta_didik_id' => $nilai->siswa?->dapodik_id,
                'nisn' => $nilai->siswa?->nisn,
                'nama' => $nilai->siswa?->nama_peserta_didik,
                'semester_id' => $semester?->semester_id ?? $nilai->semester,
                'mata_pelajaran' => $nilai->mata_pelajaran,
                'kelas' => $nilai->kelas,
                'nilai_akhir' => (float) $nilai->nilai_akhir,
                'predikat' => $nilai->predikat,
                'deskripsi' => $nilai->deskripsi_capaian,
            ];
        });

        return response()->json([
            'success' => true,
            'modul' => 'nilai-rapor',
            'total' => $items->count(),
            'data' => $items,
        ]);
    }

    private function exportKehadiran(?Semester $semester)
    {
        $query = Kehadiran::with('siswa');
        if ($semester) {
            $siswaIds = Siswa::where('semester_id', $semester->id)->pluck('id');
            $query->whereIn('siswa_id', $siswaIds);
        }

        $items = $query->get()->map(function ($k) use ($semester) {
            return [
                'peserta_didik_id' => $k->siswa?->dapodik_id,
                'nisn' => $k->siswa?->nisn,
                'nama' => $k->siswa?->nama_peserta_didik,
                'semester_id' => $semester?->semester_id ?? '',
                'tanggal' => $k->tanggal?->format('Y-m-d'),
                'status' => $k->status,
                'keterangan' => $k->keterangan,
            ];
        });

        return response()->json([
            'success' => true,
            'modul' => 'kehadiran',
            'total' => $items->count(),
            'data' => $items,
        ]);
    }

    private function exportSekolah()
    {
        $sekolah = SekolahSettings::first();
        if (! $sekolah) {
            return response()->json([
                'success' => false,
                'message' => 'Data sekolah belum dikonfigurasi.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'modul' => 'sekolah',
            'total' => 1,
            'data' => [
                'npsn' => $sekolah->npsn,
                'nama' => $sekolah->nama_sekolah,
                'alamat' => $sekolah->alamat,
                'telepon' => $sekolah->telepon,
                'email' => $sekolah->email,
                'kepala_sekolah' => $sekolah->kepala_sekolah,
            ],
        ]);
    }

    private function exportPesertaDidik(?Semester $semester)
    {
        $query = Siswa::where('aktif', true)->whereNull('archived_at');
        if ($semester) {
            $query->where('semester_id', $semester->id);
        }

        $items = $query->get()->map(function ($s) {
            return [
                'peserta_didik_id' => $s->dapodik_id,
                'nisn' => $s->nisn,
                'nipd' => $s->nis,
                'nama' => $s->nama_peserta_didik,
                'jenis_kelamin' => $s->jenis_kelamin,
                'kelas' => $s->kelas,
                'tempat_lahir' => $s->tempat_lahir,
                'tanggal_lahir' => $s->tanggal_lahir?->format('Y-m-d'),
                'agama' => $s->agama,
            ];
        });

        return response()->json([
            'success' => true,
            'modul' => 'peserta-didik',
            'total' => $items->count(),
            'data' => $items,
        ]);
    }
}
