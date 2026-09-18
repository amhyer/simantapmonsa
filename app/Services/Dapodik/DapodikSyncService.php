<?php

namespace App\Services\Dapodik;

use App\Models\DapodikConfig;
use App\Models\DapodikSyncLog;
use App\Models\Rombel;
use App\Models\Semester;
use App\Models\SekolahSettings;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function App\Services\Dapodik\combine_parent_name;
use function App\Services\Dapodik\normalize_string;
use function App\Services\Dapodik\resolve_nis;

class DapodikSyncService
{
    private DapodikClient $client;
    private DapodikConfig $config;
    private array $seenNis = [];
    private array $seenNip = [];
    private array $seenRombel = [];

    public function __construct()
    {
        $this->config = DapodikConfig::getInstance();
        $this->client = new DapodikClient(
            npsn: $this->config->npsn ?? '',
            token: $this->config->token ?? '',
            host: $this->config->host ?? 'localhost',
            port: (int) ($this->config->port ?? 5774),
            protocol: $this->config->protocol ?? 'http',
            cfAccess: $this->config->cf_access ?? [],
        );
    }

    // ─── PREVIEW ───────────────────────────────────────────────

    public function preview(?string $semesterId = null): array
    {
        $semesterId = $semesterId ?? $this->detectActiveSemester();
        $raw = $this->fetchRemoteData($semesterId);

        // Count guru vs tendik
        $guruCount = 0;
        $tendikCount = 0;
        foreach ($raw['gtk'] as $gtk) {
            $jenisPtk = strtolower(trim($gtk['jenis_ptk_id'] ?? $gtk['jenis_ptk'] ?? ''));
            $isTendik = false;
            if (!empty($jenisPtk)) {
                if (in_array($jenisPtk, ['3', '4', 'tendik', 'tenaga kependidikan', 'tenaga'], true)) {
                    $isTendik = true;
                }
                if (str_contains($jenisPtk, 'tendik') || str_contains($jenisPtk, 'tenaga')) {
                    $isTendik = true;
                }
            }
            if ($isTendik) {
                $tendikCount++;
            } else {
                $guruCount++;
            }
        }

        return [
            'semester_id' => $semesterId,
            'sekolah' => $raw['sekolah'],
            'sekolah_count' => count($raw['sekolah']),
            'peserta_didik_count' => count($raw['peserta_didik']),
            'gtk_count' => count($raw['gtk']),
            'guru_count' => $guruCount,
            'tendik_count' => $tendikCount,
            'rombongan_belajar_count' => count($raw['rombongan_belajar']),
            'sample_peserta_didik' => array_slice($raw['peserta_didik'], 0, 5),
            'sample_gtk' => array_slice($raw['gtk'], 0, 5),
            'sample_rombel' => array_slice($raw['rombongan_belajar'], 0, 5),
        ];
    }

    // ─── DRY-RUN ───────────────────────────────────────────────

    public function dryRun(?string $semesterId = null): array
    {
        $semesterId = $semesterId ?? $this->detectActiveSemester();
        $raw = $this->fetchRemoteData($semesterId);
        $semester = $this->ensureSemester($semesterId);

        $this->resetDedup();

        // Rombel
        $rombelResult = $this->previewRombel($raw['rombongan_belajar'], $semester);

        // GTK
        $gtkResult = $this->previewGtk($raw['gtk'], $semester);

        // Siswa
        $siswaResult = $this->previewSiswa($raw['peserta_didik'], $semester, $rombelResult['valid_ids']);

        // Archive
        $archiveResult = $this->previewArchive(
            $siswaResult['existing_dapodik_ids'] ?? [],
            $raw['peserta_didik'],
            $gtkResult['existing_dapodik_ids'] ?? [],
            $raw['gtk'],
        );

        return [
            'mode' => 'dry-run',
            'semester_id' => $semesterId,
            'rombel' => $rombelResult['summary'],
            'gtk' => $gtkResult['summary'],
            'siswa' => $siswaResult['summary'],
            'archive' => $archiveResult,
            'errors' => array_merge(
                $rombelResult['errors'],
                $gtkResult['errors'],
                $siswaResult['errors'],
            ),
        ];
    }

    // ─── COMMIT ────────────────────────────────────────────────

    public function commit(?string $semesterId = null): array
    {
        $semesterId = $semesterId ?? $this->detectActiveSemester();
        $raw = $this->fetchRemoteData($semesterId);
        $semester = $this->ensureSemester($semesterId);
        $startedAt = now();

        $this->resetDedup();

        $log = DapodikSyncLog::create([
            'uuid' => Str::uuid(),
            'user_id' => auth()->id(),
            'sekolah_npsn' => $this->config->npsn,
            'nama_sekolah' => $raw['sekolah'][0]['nama'] ?? 'Dapodik',
            'tipe' => 'semua',
            'tahun_ajaran' => $semester->tahun_ajaran,
            'semester' => $semester->nama_semester,
            'total_data' => count($raw['peserta_didik']) + count($raw['gtk']) + count($raw['rombongan_belajar']),
            'started_at' => $startedAt,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        $allErrors = [];
        $sekolahResult = ['berhasil' => 0, 'gagal' => 0, 'diperbarui' => 0, 'dilewati' => 0];
        $rombelResult = ['berhasil' => 0, 'gagal' => 0, 'diperbarui' => 0, 'dilewati' => 0];
        $gtkResult = ['berhasil' => 0, 'gagal' => 0, 'diperbarui' => 0, 'dilewati' => 0];
        $siswaResult = ['berhasil' => 0, 'gagal' => 0, 'diperbarui' => 0, 'dilewati' => 0];
        $archiveResult = ['archived' => 0, 'skipped' => 0, 'errors' => []];

        try {
            // ── TRX-1: sekolah + GTK + rombel (wali kelas) + siswa ──
            DB::beginTransaction();

            try {
                $sekolahResult = $this->syncSekolah($raw['sekolah']);
                $gtkResult = $this->syncGtk($raw['gtk'], $semester->semester_id);
                $rombelResult = $this->syncRombel($raw['rombongan_belajar'], $semester->semester_id);
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                $allErrors[] = 'Trx-1 (sekolah/gtk/rombel): ' . $e->getMessage();
            }

            // ── TRX-2..n: siswa 100/batch ──
            $siswaChunks = array_chunk($raw['peserta_didik'], (int) config('dapodik.batch_size', 100));
            $failedSiswaBatches = 0;

            foreach ($siswaChunks as $chunkIndex => $chunk) {
                DB::beginTransaction();

                try {
                    $batchResult = $this->syncSiswa($chunk, $semester->semester_id);
                    $siswaResult['berhasil'] += $batchResult['berhasil'];
                    $siswaResult['diperbarui'] += $batchResult['diperbarui'];
                    $siswaResult['dilewati'] += $batchResult['dilewati'];
                    $siswaResult['gagal'] += $batchResult['gagal'];
                    $allErrors = array_merge($allErrors, $batchResult['errors']);
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    $failedSiswaBatches++;
                    $siswaResult['gagal'] += count($chunk);
                    $allErrors[] = "Trx-siswa batch {$chunkIndex}: " . $e->getMessage();
                }
            }

            // ── TRX-last: archive (whereIn per 500) ──
            if ($this->config->archive_unlisted) {
                $archiveResult = $this->archiveUnlisted(
                    $siswaResult['existing_dapodik_ids'] ?? [],
                    $gtkResult['existing_dapodik_ids'] ?? [],
                    $raw['peserta_didik'],
                    $raw['gtk'],
                );
            } else {
                $archiveResult = ['archived' => 0, 'skipped' => 0, 'errors' => []];
            }
        } catch (\Exception $e) {
            $allErrors[] = 'Fatal: ' . $e->getMessage();
        }

        $finishedAt = now();
        $totalBerhasil = $sekolahResult['berhasil'] + $rombelResult['berhasil'] + $gtkResult['berhasil'] + $siswaResult['berhasil'];
        $totalDiperbarui = $sekolahResult['diperbarui'] + $rombelResult['diperbarui'] + $gtkResult['diperbarui'] + $siswaResult['diperbarui'];
        $totalGagal = $sekolahResult['gagal'] + $rombelResult['gagal'] + $gtkResult['gagal'] + $siswaResult['gagal'];
        $totalDilewati = $sekolahResult['dilewati'] + $rombelResult['dilewati'] + $gtkResult['dilewati'] + $siswaResult['dilewati'];

        $log->update([
            'berhasil' => $totalBerhasil,
            'gagal' => $totalGagal,
            'diperbarui' => $totalDiperbarui,
            'dilewati' => $totalDilewati,
            'errors' => $allErrors,
            'finished_at' => $finishedAt,
            'duration_seconds' => (int) $startedAt->diffInSeconds($finishedAt),
            'summary' => [
                'sekolah' => $sekolahResult,
                'rombel' => $rombelResult,
                'gtk' => $gtkResult,
                'siswa' => $siswaResult,
                'archive' => $archiveResult,
            ],
        ]);

        $this->config->update([
            'last_sync_at' => $finishedAt,
            'last_sync_by' => auth()->user()->nama_lengkap ?? 'System',
        ]);

        return [
            'mode' => 'commit',
            'semester_id' => $semesterId,
            'log_id' => $log->id,
            'sekolah' => $sekolahResult,
            'rombel' => $rombelResult,
            'gtk' => $gtkResult,
            'siswa' => $siswaResult,
            'archive' => $archiveResult,
            'errors' => $allErrors,
            'duration_seconds' => (int) $startedAt->diffInSeconds($finishedAt),
            'guru_count' => $gtkResult['guru_count'] ?? 0,
            'tendik_count' => $gtkResult['tendik_count'] ?? 0,
        ];
    }

    // ─── PRIVATE: SYNC SEKOLAH ────────────────────────────────

    private function syncSekolah(array $rows): array
    {
        $result = ['berhasil' => 0, 'gagal' => 0, 'diperbarui' => 0, 'dilewati' => 0];

        foreach ($rows as $row) {
            try {
                $npsn = normalize_string($row['npsn'] ?? null);
                if ($npsn === null) {
                    $result['dilewati']++;
                    continue;
                }

                $nama = normalize_string($row['nama'] ?? $row['nama_sekolah'] ?? null);
                if ($nama === null) {
                    $result['dilewati']++;
                    continue;
                }

                $existing = SekolahSettings::where('npsn', $npsn)->first();

                $data = [
                    'npsn' => $npsn,
                    'nama_sekolah' => $nama,
                    'alamat' => normalize_string($row['alamat_jalan'] ?? $row['alamat'] ?? null) ?? $existing?->alamat,
                    'telepon' => normalize_string($row['telepon'] ?? null) ?? $existing?->telepon,
                    'email' => normalize_string($row['email'] ?? null) ?? $existing?->email,
                    'kepala_sekolah' => normalize_string($row['nama_kepala_sekolah'] ?? $row['kepala_sekolah'] ?? null) ?? $existing?->kepala_sekolah,
                    'jenjang' => normalize_string($row['jenjang_pendidikan_id'] ?? $row['jenjang'] ?? null) ?? $existing?->jenjang,
                    'jenis_sekolah' => normalize_string($row['status_sekolah'] ?? $row['jenis_sekolah'] ?? null) ?? $existing?->jenis_sekolah,
                ];

                if ($existing) {
                    $existing->update($data);
                    $result['diperbarui']++;
                } else {
                    SekolahSettings::create(array_merge($data, [
                        'guru_id' => null,
                        'status' => 'aktif',
                    ]));
                    $result['berhasil']++;
                }
            } catch (\Exception $e) {
                $result['gagal']++;
            }
        }

        return $result;
    }

    // ─── PRIVATE: SYNC ROMBEL ─────────────────────────────────

    private function syncRombel(array $rows, string $semesterId): array
    {
        $result = [
            'berhasil' => 0,
            'gagal' => 0,
            'diperbarui' => 0,
            'dilewati' => 0,
            'valid_ids' => [],
            'errors' => [],
        ];

        foreach ($rows as $row) {
            try {
                $dapodikId = $row['rombongan_belajar_id'] ?? null;
                if ($dapodikId === null) {
                    $result['dilewati']++;
                    continue;
                }

                $namaRombel = normalize_string($row['nama'] ?? $row['nama_rombel'] ?? null);
                if ($namaRombel === null) {
                    $result['dilewati']++;
                    continue;
                }

                // Dedup
                if (isset($this->seenRombel[$dapodikId])) {
                    $result['dilewati']++;
                    continue;
                }
                $this->seenRombel[$dapodikId] = true;

                $tingkat = normalize_string($row['tingkat_pendidikan_id'] ?? $row['tingkat'] ?? null);
                $semester = $this->ensureSemester($semesterId);

                // Resolve wali kelas (guru_id) from Dapodik ptk_id
                $waliPtkId = $row['ptk_id'] ?? $row['guru_id'] ?? $row['wali_kelas_ptk_id'] ?? null;
                $guruId = null;
                if ($waliPtkId) {
                    $waliUser = User::where('dapodik_id', $waliPtkId)
                        ->where('peran', 'guru')
                        ->first();
                    $guruId = $waliUser?->id;
                }

                $existing = Rombel::where('dapodik_id', $dapodikId)
                    ->where('semester_id', $semester->id)
                    ->first();

                $data = [
                    'dapodik_id' => $dapodikId,
                    'semester_id' => $semester->id,
                    'nama_rombel' => $namaRombel,
                    'tingkat' => $tingkat,
                    'guru_id' => $guruId,
                ];

                if ($existing) {
                    $existing->update($data);
                    $result['diperbarui']++;
                } else {
                    Rombel::create($data);
                    $result['berhasil']++;
                }

                $result['valid_ids'][] = $dapodikId;
            } catch (\Exception $e) {
                $result['gagal']++;
                $result['errors'][] = "Rombel {$dapodikId}: " . $e->getMessage();
            }
        }

        return $result;
    }

    // ─── PRIVATE: SYNC GTK ────────────────────────────────────

    private function syncGtk(array $rows, string $semesterId): array
    {
        $result = [
            'berhasil' => 0,
            'gagal' => 0,
            'diperbarui' => 0,
            'dilewati' => 0,
            'guru_count' => 0,
            'tendik_count' => 0,
            'existing_ids' => [],
            'existing_dapodik_ids' => [],
            'errors' => [],
        ];

        foreach ($rows as $row) {
            try {
                $dapodikId = $row['ptk_id'] ?? null;
                $nuptk = normalize_string($row['nuptk'] ?? null);
                $nip = normalize_string($row['nip'] ?? null);

                // Skip guru without NUPTK & NIP
                if (empty($nuptk) && empty($nip)) {
                    $result['dilewati']++;
                    continue;
                }

                // Skip duplicate NIP
                $effectiveNip = $nuptk ?? $nip;
                if (isset($this->seenNip[$effectiveNip])) {
                    $result['dilewati']++;
                    continue;
                }
                $this->seenNip[$effectiveNip] = true;

                $nama = normalize_string($row['nama'] ?? null);
                if ($nama === null) {
                    $result['dilewati']++;
                    continue;
                }

                $semester = $this->ensureSemester($semesterId);

                // Match by dapodik_id first, then by nama_pengguna (nip)
                $existing = null;
                if ($dapodikId) {
                    $existing = User::where('dapodik_id', $dapodikId)->first();
                }
                if (!$existing && $nip) {
                    $existing = User::where('nama_pengguna', $nip)->first();
                }
                if (!$existing && $nuptk) {
                    $existing = User::where('nama_pengguna', $nuptk)->first();
                }

                // Determine username: prefer NIP, fallback NUPTK
                $username = $nip ?? $nuptk;
                if ($existing && $existing->nama_pengguna !== $username) {
                    // Ensure unique username
                    $base = $username;
                    $counter = 1;
                    while (User::where('nama_pengguna', $username)->where('id', '!=', $existing->id)->exists()) {
                        $username = $base . '.' . $counter;
                        $counter++;
                    }
                } elseif (!$existing) {
                    $base = $username ?? ('gtk_' . $dapodikId);
                    $username = $base;
                    $counter = 1;
                    while (User::where('nama_pengguna', $username)->exists()) {
                        $username = $base . '.' . $counter;
                        $counter++;
                    }
                }

                $isActive = strtolower($row['status_aktif'] ?? $row['status'] ?? 'aktif') === 'aktif';

                // Determine role: guru vs tendik
                $jenisPtk = strtolower(trim($row['jenis_ptk_id'] ?? $row['jenis_ptk'] ?? ''));
                $isTendik = false;

                // Check jenis_ptk_id for tendik indicators
                if (!empty($jenisPtk)) {
                    // Common tendik codes in Dapodik: 3, 'Tendik', 'Tenaga Kependidikan'
                    if (in_array($jenisPtk, ['3', '4', 'tendik', 'tenaga kependidikan', 'tenaga'], true)) {
                        $isTendik = true;
                    }
                    // Also check if contains 'tendik' or 'tenaga'
                    if (str_contains($jenisPtk, 'tendik') || str_contains($jenisPtk, 'tenaga')) {
                        $isTendik = true;
                    }
                }

                // Secondary heuristic: if no bidang_studi/mata_pelajaran, likely tendik
                if (!$isTendik && empty($row['bidang_studi'] ?? $row['mata_pelajaran'] ?? null)) {
                    // Could be tendik if no subject area
                    $namaLower = strtolower($nama ?? '');
                    if (str_contains($namaLower, 'tata usaha') || str_contains($namaLower, 'perpustakaan')
                        || str_contains($namaLower, 'laboratorium') || str_contains($namaLower, 'operator')
                        || str_contains($namaLower, 'keamanan') || str_contains($namaLower, 'kebersihan')) {
                        $isTendik = true;
                    }
                }

                $peran = $isTendik ? 'admin' : 'guru';

                if ($isTendik) {
                    $result['tendik_count']++;
                } else {
                    $result['guru_count']++;
                }

                $data = [
                    'nama_lengkap' => $nama,
                    'nama_pengguna' => $username,
                    'peran' => $peran,
                    'aktif' => $isActive,
                    'archived_at' => null,
                    'terhubung_dengan' => $row['terhubung_dengan'] ?? $existing?->terhubung_dengan ?? [],
                    'kelas_mata_pelajaran' => normalize_string($row['bidang_studi'] ?? $row['mata_pelajaran'] ?? null)
                        ?? $existing?->kelas_mata_pelajaran,
                ];

                if ($existing) {
                    $existing->update($data);
                    $existing->forceFill([
                        'aktif' => $isActive,
                        'archived_at' => null,
                        'dapodik_id' => $dapodikId,
                    ])->save();
                    $result['diperbarui']++;
                    $result['existing_ids'][] = $existing->id;
                } else {
                    $newUser = User::create(array_merge($data, [
                        'uuid' => Str::uuid(),
                        'kata_sandi' => bcrypt('guru123'),
                        'force_password_change' => true,
                    ]));
                    $newUser->forceFill([
                        'aktif' => $isActive,
                        'archived_at' => null,
                        'dapodik_id' => $dapodikId,
                    ])->save();
                    $result['berhasil']++;
                    $result['existing_ids'][] = $newUser->id;
                }

                if ($dapodikId) {
                    $result['existing_dapodik_ids'][] = $dapodikId;
                }
            } catch (\Exception $e) {
                $result['gagal']++;
                $result['errors'][] = "GTK " . ($row['nama'] ?? '?') . ": " . $e->getMessage();
            }
        }

        return $result;
    }

    // ─── PRIVATE: SYNC SISWA ──────────────────────────────────

    private function syncSiswa(array $rows, string $semesterId): array
    {
        $result = [
            'berhasil' => 0,
            'gagal' => 0,
            'diperbarui' => 0,
            'dilewati' => 0,
            'existing_dapodik_ids' => [],
            'errors' => [],
        ];

        $semester = $this->ensureSemester($semesterId);

        foreach ($rows as $index => $row) {
            try {
                $dapodikId = $row['peserta_didik_id'] ?? null;
                $nisn = normalize_string($row['nisn'] ?? null);
                $nipd = normalize_string($row['nipd'] ?? $row['nis'] ?? null);

                // Skip if no identifier at all
                if (empty($dapodikId) && empty($nisn) && empty($nipd)) {
                    $result['dilewati']++;
                    continue;
                }

                // Resolve rombel
                $rombelId = $row['rombongan_belajar_id'] ?? null;
                $namaRombel = normalize_string($row['nama_rombel'] ?? $row['rombel'] ?? $row['kelas'] ?? null);
                $tingkat = normalize_string($row['tingkat_pendidikan_id'] ?? $row['tingkat'] ?? null);

                if (empty($rombelId) && empty($namaRombel)) {
                    $result['gagal']++;
                    $result['errors'][] = "Siswa " . ($row['nama'] ?? $dapodikId ?? "baris " . ($index + 1)) . ": rombel tidak valid, dilewati";
                    continue;
                }

                // Resolve NIS: prefer nipd, fallback deterministic
                $nis = resolve_nis($nipd, $row['nama'] ?? '', $dapodikId ?? $nisn ?? '', (int) ($tingkat ?? 1));

                // Dedup NIS
                if (isset($this->seenNis[$nis])) {
                    $counter = 1;
                    while (isset($this->seenNis[$nis . '.' . $counter])) {
                        $counter++;
                    }
                    $nis = $nis . '.' . $counter;
                }
                $this->seenNis[$nis] = true;

                // Match existing: by dapodik_id first, then nisn
                $existing = null;
                if ($dapodikId) {
                    $existing = Siswa::where('dapodik_id', $dapodikId)
                        ->where('semester_id', $semester->id)
                        ->first();
                }
                if (!$existing && $nisn) {
                    $existing = Siswa::where('nisn', $nisn)
                        ->where('semester_id', $semester->id)
                        ->first();
                }
                if (!$existing && $nipd) {
                    $existing = Siswa::where('nis', $nipd)
                        ->where('semester_id', $semester->id)
                        ->first();
                }

                // Gender mapping
                $jkRaw = $row['jenis_kelamin'] ?? null;
                $jenisKelamin = $this->mapGender($jkRaw);

                // Parent name
                $namaOrangTua = combine_parent_name(
                    $row['nama_ayah'] ?? null,
                    $row['nama_ibu'] ?? null,
                );

                // Status
                $statusRaw = strtolower($row['status_peserta_didik'] ?? $row['status'] ?? 'aktif');
                $aktif = in_array($statusRaw, ['aktif', 'active', '1', 'true'], true);

                // Find the rombel ID from our DB
                $rombelDb = null;
                if ($rombelId) {
                    $rombelDb = Rombel::where('dapodik_id', $rombelId)
                        ->where('semester_id', $semester->id)
                        ->first();
                }
                if (!$rombelDb && $namaRombel) {
                    $rombelDb = Rombel::where('nama_rombel', $namaRombel)
                        ->where('semester_id', $semester->id)
                        ->first();
                }

                $kelasValue = $rombelDb?->nama_rombel ?? $namaRombel ?? '';

                // Ambil guru_id dari rombel (wali kelas)
                $guruId = $rombelDb?->guru_id ?? null;

                $siswaData = [
                    'dapodik_id' => $dapodikId,
                    'semester_id' => $semester->id,
                    'rombel_id' => $rombelDb?->id,
                    'guru_id' => $guruId,
                    'nis' => $nis,
                    'nisn' => $nisn,
                    'nama_peserta_didik' => normalize_string($row['nama'] ?? $row['nama_peserta_didik'] ?? '') ?? '',
                    'kelas' => $kelasValue,
                    'jenis_kelamin' => $jenisKelamin,
                    'nama_orang_tua' => $namaOrangTua,
                    'aktif' => $aktif,
                    'status_siswa' => $statusRaw,
                    'archived_at' => null,
                    'rekaman' => $row,
                ];

                if ($existing) {
                    $existing->update($siswaData);
                    $existing->forceFill([
                        'aktif' => $aktif,
                        'archived_at' => null,
                    ])->save();
                    $result['diperbarui']++;
                } else {
                    $newSiswa = Siswa::create(array_merge($siswaData, [
                        'uuid' => Str::uuid(),
                        'nama_guru' => null,
                    ]));
                    $newSiswa->forceFill([
                        'aktif' => $aktif,
                        'archived_at' => null,
                    ])->save();
                    $result['berhasil']++;
                }

                if ($dapodikId) {
                    $result['existing_dapodik_ids'][] = $dapodikId;
                }
            } catch (\Exception $e) {
                $result['gagal']++;
                $result['errors'][] = "Siswa " . ($row['nama'] ?? $dapodikId ?? "baris " . ($index + 1)) . ": " . $e->getMessage();
            }
        }

        return $result;
    }

    // ─── PRIVATE: ARCHIVE UNLISTED ────────────────────────────

    private function archiveUnlisted(
        array $currentSiswaDapodikIds,
        array $currentGtkDapodikIds,
        array $remoteSiswa,
        array $remoteGtk,
    ): array {
        $result = ['archived' => 0, 'skipped' => 0, 'errors' => []];

        try {
            $archiveNow = now();

            // Build sets of Dapodik IDs present in remote data
            $remoteSiswaIds = array_flip($currentSiswaDapodikIds);
            $remoteGtkIds = array_flip($currentGtkDapodikIds);

            // ── Archive siswa: batch 500 ──
            $siswaQuery = Siswa::where('aktif', true)
                ->whereNotNull('dapodik_id')
                ->whereNull('archived_at');

            $siswaChunkSize = (int) config('dapodik.archive_chunk', 500);
            $siswaIdsToArchive = $siswaQuery->pluck('dapodik_id')->filter()
                ->filter(fn ($id) => !isset($remoteSiswaIds[$id]))
                ->toArray();

            if (!empty($siswaIdsToArchive)) {
                $chunks = array_chunk($siswaIdsToArchive, $siswaChunkSize);
                foreach ($chunks as $chunk) {
                    DB::beginTransaction();
                    try {
                        $siswaToArchive = Siswa::whereIn('dapodik_id', $chunk)
                            ->whereNull('archived_at')
                            ->get();

                        foreach ($siswaToArchive as $siswa) {
                            $siswa->forceFill([
                                'aktif' => false,
                                'archived_at' => $archiveNow,
                            ])->save();
                        }

                        $result['archived'] += $siswaToArchive->count();
                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollBack();
                        $result['errors'][] = 'Archive siswa chunk: ' . $e->getMessage();
                    }
                }
            }

            // ── Archive GTK: batch 500 ──
            // Guru without NUPTK & NIP should NOT be archived
            $gtkQuery = User::where('aktif', true)
                ->whereNotNull('dapodik_id')
                ->whereNull('archived_at');

            $gtkIdsToArchive = $gtkQuery->pluck('dapodik_id')->filter()
                ->filter(fn ($id) => !isset($remoteGtkIds[$id]))
                ->toArray();

            if (!empty($gtkIdsToArchive)) {
                $chunks = array_chunk($gtkIdsToArchive, $siswaChunkSize);
                foreach ($chunks as $chunk) {
                    DB::beginTransaction();
                    try {
                        // Only archive GTK that have NUPTK or NIP (non-empty nama_pengguna)
                        $usersToArchive = User::whereIn('dapodik_id', $chunk)
                            ->whereNull('archived_at')
                            ->where(function ($q) {
                                $q->whereNotNull('nama_pengguna')
                                  ->where('nama_pengguna', '!=', '');
                            })
                            ->get();

                        foreach ($usersToArchive as $user) {
                            $user->forceFill([
                                'aktif' => false,
                                'archived_at' => $archiveNow,
                            ])->save();
                        }

                        $result['archived'] += $usersToArchive->count();
                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollBack();
                        $result['errors'][] = 'Archive GTK chunk: ' . $e->getMessage();
                    }
                }
            }
        } catch (\Exception $e) {
            $result['errors'][] = 'Archive fatal: ' . $e->getMessage();
        }

        return $result;
    }

    // ─── PRIVATE: PREVIEW HELPERS ─────────────────────────────

    private function previewRombel(array $rows, Semester $semester): array
    {
        $result = [
            'summary' => ['baru' => 0, 'update' => 0, 'dilewati' => 0],
            'valid_ids' => [],
            'errors' => [],
        ];

        foreach ($rows as $row) {
            $dapodikId = $row['rombongan_belajar_id'] ?? null;
            $namaRombel = normalize_string($row['nama'] ?? $row['nama_rombel'] ?? null);

            if (!$dapodikId || !$namaRombel) {
                $result['summary']['dilewati']++;
                continue;
            }

            if (isset($this->seenRombel[$dapodikId])) {
                $result['summary']['dilewati']++;
                continue;
            }
            $this->seenRombel[$dapodikId] = true;

            $existing = Rombel::where('dapodik_id', $dapodikId)
                ->where('semester_id', $semester->id)
                ->first();

            if ($existing) {
                $result['summary']['update']++;
            } else {
                $result['summary']['baru']++;
            }

            $result['valid_ids'][] = $dapodikId;
        }

        return $result;
    }

    private function previewGtk(array $rows, Semester $semester): array
    {
        $result = [
            'summary' => ['baru' => 0, 'update' => 0, 'dilewati' => 0],
            'guru_count' => 0,
            'tendik_count' => 0,
            'existing_ids' => [],
            'existing_dapodik_ids' => [],
            'errors' => [],
        ];

        foreach ($rows as $row) {
            $dapodikId = $row['ptk_id'] ?? null;
            $nuptk = normalize_string($row['nuptk'] ?? null);
            $nip = normalize_string($row['nip'] ?? null);

            if (empty($nuptk) && empty($nip)) {
                $result['summary']['dilewati']++;
                continue;
            }

            $effectiveNip = $nuptk ?? $nip;
            if (isset($this->seenNip[$effectiveNip])) {
                $result['summary']['dilewati']++;
                continue;
            }
            $this->seenNip[$effectiveNip] = true;

            $nama = normalize_string($row['nama'] ?? null);
            if (!$nama) {
                $result['summary']['dilewati']++;
                continue;
            }

            // Determine if guru or tendik
            $jenisPtk = strtolower(trim($row['jenis_ptk_id'] ?? $row['jenis_ptk'] ?? ''));
            $isTendik = false;
            if (!empty($jenisPtk)) {
                if (in_array($jenisPtk, ['3', '4', 'tendik', 'tenaga kependidikan', 'tenaga'], true)) {
                    $isTendik = true;
                }
                if (str_contains($jenisPtk, 'tendik') || str_contains($jenisPtk, 'tenaga')) {
                    $isTendik = true;
                }
            }
            if ($isTendik) {
                $result['tendik_count']++;
            } else {
                $result['guru_count']++;
            }

            $existing = null;
            if ($dapodikId) {
                $existing = User::where('dapodik_id', $dapodikId)->first();
            }
            if (!$existing && $nip) {
                $existing = User::where('nama_pengguna', $nip)->first();
            }
            if (!$existing && $nuptk) {
                $existing = User::where('nama_pengguna', $nuptk)->first();
            }

            if ($existing) {
                $result['summary']['update']++;
                $result['existing_ids'][] = $existing->id;
            } else {
                $result['summary']['baru']++;
            }

            if ($dapodikId) {
                $result['existing_dapodik_ids'][] = $dapodikId;
            }
        }

        return $result;
    }

    private function previewSiswa(array $rows, Semester $semester, array $validRombelIds): array
    {
        $result = [
            'summary' => ['baru' => 0, 'update' => 0, 'dilewati' => 0, 'error_rombel' => 0],
            'existing_ids' => [],
            'existing_dapodik_ids' => [],
            'errors' => [],
        ];

        foreach ($rows as $index => $row) {
            $dapodikId = $row['peserta_didik_id'] ?? null;
            $nisn = normalize_string($row['nisn'] ?? null);
            $nipd = normalize_string($row['nipd'] ?? $row['nis'] ?? null);

            if (empty($dapodikId) && empty($nisn) && empty($nipd)) {
                $result['summary']['dilewati']++;
                continue;
            }

            $rombelId = $row['rombongan_belajar_id'] ?? null;
            $namaRombel = normalize_string($row['nama_rombel'] ?? $row['rombel'] ?? $row['kelas'] ?? null);

            if (empty($rombelId) && empty($namaRombel)) {
                $result['summary']['error_rombel']++;
                $result['errors'][] = "Siswa " . ($row['nama'] ?? $dapodikId ?? "baris " . ($index + 1)) . ": rombel tidak valid";
                continue;
            }

            $existing = null;
            if ($dapodikId) {
                $existing = Siswa::where('dapodik_id', $dapodikId)
                    ->where('semester_id', $semester->id)
                    ->first();
            }
            if (!$existing && $nisn) {
                $existing = Siswa::where('nisn', $nisn)
                    ->where('semester_id', $semester->id)
                    ->first();
            }

            if ($existing) {
                $result['summary']['update']++;
                $result['existing_ids'][] = $existing->id;
            } else {
                $result['summary']['baru']++;
            }

            if ($dapodikId) {
                $result['existing_dapodik_ids'][] = $dapodikId;
            }
        }

        return $result;
    }

    private function previewArchive(
        array $siswaExistingDapodikIds,
        array $remoteSiswa,
        array $gtkExistingDapodikIds,
        array $remoteGtk,
    ): array {
        if (!$this->config->archive_unlisted) {
            return ['enabled' => false, 'siswa_to_archive' => 0, 'gtk_to_archive' => 0];
        }

        $siswaDapodikIdSet = array_flip($siswaExistingDapodikIds);
        $gtkDapodikIdSet = array_flip($gtkExistingDapodikIds);

        $siswaToArchive = Siswa::where('aktif', true)
            ->whereNotNull('dapodik_id')
            ->whereNull('archived_at')
            ->pluck('dapodik_id')
            ->filter()
            ->filter(fn ($id) => !isset($siswaDapodikIdSet[$id]))
            ->count();

        $gtkToArchive = User::where('aktif', true)
            ->whereNotNull('dapodik_id')
            ->whereNull('archived_at')
            ->where(function ($q) {
                $q->whereNotNull('nama_pengguna')
                  ->where('nama_pengguna', '!=', '');
            })
            ->pluck('dapodik_id')
            ->filter()
            ->filter(fn ($id) => !isset($gtkDapodikIdSet[$id]))
            ->count();

        return [
            'enabled' => true,
            'siswa_to_archive' => $siswaToArchive,
            'gtk_to_archive' => $gtkToArchive,
        ];
    }

    // ─── PRIVATE: HELPERS ─────────────────────────────────────

    private function ensureSemester(string $semesterId): Semester
    {
        $year = (int) mb_substr($semesterId, 0, 4);
        $semesterDigit = (int) mb_substr($semesterId, -1);

        if ($semesterDigit === 2) {
            $tahunAjaran = ($year - 1) . '/' . $year;
            $namaSemester = 'genap';
        } else {
            $tahunAjaran = $year . '/' . ($year + 1);
            $namaSemester = 'ganjil';
        }

        return Semester::firstOrCreate(
            ['semester_id' => $semesterId],
            [
                'tahun_ajaran' => $tahunAjaran,
                'nama_semester' => $namaSemester,
            ]
        );
    }

    private function detectActiveSemester(): string
    {
        $month = (int) date('n');
        $year = (int) date('Y');

        if ($month >= 7) {
            return $year . '1';
        }

        return $year . '2';
    }

    private function fetchRemoteData(string $semesterId): array
    {
        return [
            'sekolah' => $this->client->getSekolah(),
            'peserta_didik' => $this->client->getPesertaDidik($semesterId),
            'gtk' => $this->client->getGtk($semesterId),
            'rombongan_belajar' => $this->client->getRombonganBelajar($semesterId),
        ];
    }

    private function resetDedup(): void
    {
        $this->seenNis = [];
        $this->seenNip = [];
        $this->seenRombel = [];
    }

    private function mapGender(?string $jk): ?string
    {
        if ($jk === null) {
            return null;
        }

        $lower = mb_strtolower(trim($jk));

        if (str_contains($lower, 'laki') || $lower === 'l' || $lower === 'lk') {
            return 'L';
        }

        if (str_contains($lower, 'perempuan') || $lower === 'p' || $lower === 'pr') {
            return 'P';
        }

        return null;
    }
}
