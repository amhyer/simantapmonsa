<?php

namespace App\Console\Commands;

use App\Services\BackupRestoreService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Drill pemulihan backup: restore berkas JSON ke database SQLite sementara,
 * lalu bandingkan jumlah baris, kelengkapan ID, dan relasi induk-anak.
 *
 * Database aplikasi tidak disentuh sama sekali. Prosedur restore yang diuji
 * adalah `BackupRestoreService` yang sama dengan tombol Import di admin.
 */
class BackupRestoreDrill extends Command
{
    protected $signature = 'simantap:backup:drill
                            {--file= : Berkas backup relatif terhadap disk (default: terbaru)}
                            {--disk= : Disk penyimpanan (default: backup.disk)}
                            {--keep : Simpan database sementara untuk pemeriksaan manual}
                            {--json : Keluarkan laporan JSON}';

    protected $description = 'Uji restore backup ke database sementara dan cocokkan jumlah/relasi (tanpa menyentuh database aplikasi)';

    private const KONEKSI = 'simantap_backup_drill';

    public function handle(BackupRestoreService $service): int
    {
        $diskName = (string) ($this->option('disk') ?: config('backup.disk', 'local'));
        $direktori = trim((string) config('backup.direktori', 'backups'), '/');

        try {
            $disk = Storage::disk($diskName);
        } catch (\Throwable $e) {
            $this->error("Disk '{$diskName}' tidak dapat dipakai: {$e->getMessage()}");

            return self::FAILURE;
        }

        $file = $this->pilihBerkas($disk, $direktori);

        if ($file === null) {
            $this->error("Tidak ada berkas backup di disk '{$diskName}' folder '{$direktori}'.");

            return self::FAILURE;
        }

        if (! $disk->exists($file)) {
            $this->error("Berkas backup '{$file}' tidak ditemukan di disk '{$diskName}'.");

            return self::FAILURE;
        }

        $ukuran = $disk->size($file);

        if ($ukuran > 100 * 1024 * 1024) {
            $this->error('Berkas backup lebih dari 100 MB; drill menolak untuk menjaga memori.');

            return self::FAILURE;
        }

        try {
            $data = json_decode((string) $disk->get($file), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $this->error('Berkas backup bukan JSON valid: ' . $e->getMessage());

            return self::FAILURE;
        }

        if (! is_array($data)) {
            $this->error('Struktur backup tidak valid (bukan objek JSON).');

            return self::FAILURE;
        }

        $validasi = $service->validasi($data);

        if ($validasi['tidak_dikenal'] !== []) {
            $this->error("Backup memuat tabel tidak dikenal: " . implode(', ', $validasi['tidak_dikenal']));

            return self::FAILURE;
        }

        $tabel = $validasi['tabel'];

        if ($tabel === []) {
            $this->error('Backup tidak memuat satu pun tabel yang dikenal.');

            return self::FAILURE;
        }

        [$berhasil, $laporan] = $this->jalankanDrill($service, $data, $tabel);

        if ($this->option('json')) {
            $laporan['berkas'] = $file;
            $laporan['disk'] = $diskName;
            $laporan['ukuran_byte'] = $ukuran;
            $this->line((string) json_encode($laporan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $berhasil ? self::SUCCESS : self::FAILURE;
        }

        $this->info("Drill backup: {$file} (disk: {$diskName}, " . number_format($ukuran / 1024, 1) . ' KB)');
        $this->newLine();

        $this->table(
            ['Tabel', 'Baris backup', 'Baris restore', 'ID hilang', 'Status'],
            array_map(fn (array $b) => [
                $b['tabel'],
                $b['harapan'],
                $b['aktual'],
                $b['id_hilang'],
                $b['status'],
            ], $laporan['tabel'])
        );

        if ($laporan['relasi'] !== []) {
            $this->table(
                ['Relasi', 'Yatim', 'Status'],
                array_map(fn (array $r) => [$r['nama'], $r['yatim'], $r['yatim'] === 0 ? 'OK' : 'GAGAL'], $laporan['relasi'])
            );
        }

        if ($laporan['ok']) {
            $this->info('✅ Drill restore cocok: jumlah baris, ID, dan relasi konsisten.');
        } else {
            $this->error('❌ Drill restore gagal: lihat baris berstatus GAGAL di atas.');
        }

        if ($this->option('keep')) {
            $this->warn('Database sementara disimpan: ' . $laporan['database_sementara']);
        }

        return $berhasil ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<int, string>  $tabel
     * @return array{0: bool, 1: array<string, mixed>}
     */
    private function jalankanDrill(BackupRestoreService $service, array $data, array $tabel): array
    {
        $sementara = tempnam(sys_get_temp_dir(), 'simantap-drill-');

        if ($sementara === false) {
            $this->error('Gagal membuat berkas database sementara.');

            return [false, ['ok' => false, 'tabel' => [], 'relasi' => [], 'database_sementara' => null]];
        }

        $sebelum = DB::getDefaultConnection();
        $telescopeConnection = config('telescope.storage.database.connection');

        config(["database.connections." . self::KONEKSI => [
            'driver' => 'sqlite',
            'database' => $sementara,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);

        // Migrasi Telescope memakai koneksi eksplisit dari config, bukan
        // koneksi default. Arahkan ke database drill agar tidak menimpa
        // database aplikasi (dan tidak bentrok dengan tabel yang sudah ada).
        config(['telescope.storage.database.connection' => self::KONEKSI]);

        DB::purge(self::KONEKSI);

        try {
            DB::setDefaultConnection(self::KONEKSI);

            $kode = Artisan::call('migrate', [
                '--database' => self::KONEKSI,
                '--force' => true,
            ]);

            if ($kode !== 0) {
                $this->error('Migrasi database sementara gagal: ' . trim(Artisan::output()));

                return [false, ['ok' => false, 'tabel' => [], 'relasi' => [], 'database_sementara' => $sementara]];
            }

            $service->restore($data, self::KONEKSI);

            $laporanTabel = $this->bandingkanTabel($data, $tabel);
            $relasi = $this->periksaRelasi($tabel);

            $ok = $laporanTabel !== [] && collect($laporanTabel)->every(fn (array $b) => $b['status'] === 'OK')
                && collect($relasi)->every(fn (array $r) => $r['yatim'] === 0);

            return [$ok, [
                'ok' => $ok,
                'tabel' => $laporanTabel,
                'relasi' => $relasi,
                'database_sementara' => $this->option('keep') ? $sementara : null,
            ]];
        } catch (\Throwable $e) {
            $this->error('Drill gagal: ' . $e->getMessage());

            return [false, ['ok' => false, 'tabel' => [], 'relasi' => [], 'database_sementara' => $sementara]];
        } finally {
            DB::purge(self::KONEKSI);
            DB::setDefaultConnection($sebelum);
            config(['telescope.storage.database.connection' => $telescopeConnection]);
            config(["database.connections." . self::KONEKSI => null]);

            if (! $this->option('keep')) {
                @unlink($sementara);
            }
        }
    }

    /**
     * @param  array<int, string>  $tabel
     * @return array<int, array<string, mixed>>
     */
    private function bandingkanTabel(array $data, array $tabel): array
    {
        $hasil = [];

        foreach ($tabel as $table) {
            $records = $data[$table] ?? [];
            $records = is_array($records) ? array_values(array_filter($records, 'is_array')) : [];
            $harapan = count($records);

            if (! Schema::connection(self::KONEKSI)->hasTable($table)) {
                $hasil[] = [
                    'tabel' => $table,
                    'harapan' => $harapan,
                    'aktual' => 0,
                    'id_hilang' => $harapan > 0 ? $harapan : 0,
                    'status' => 'GAGAL (tabel tidak ada)',
                ];

                continue;
            }

            $aktual = DB::connection(self::KONEKSI)->table($table)->count();
            $idHilang = 0;

            if (Schema::connection(self::KONEKSI)->hasColumn($table, 'id')) {
                $idBackup = [];
                foreach ($records as $record) {
                    if (array_key_exists('id', $record) && $record['id'] !== null) {
                        $idBackup[(string) $record['id']] = true;
                    }
                }

                $idDb = DB::connection(self::KONEKSI)
                    ->table($table)
                    ->pluck('id')
                    ->map(fn ($id) => (string) $id)
                    ->all();

                $idHilang = count(array_diff(array_keys($idBackup), $idDb));
            }

            $status = $harapan === $aktual && $idHilang === 0 ? 'OK' : 'GAGAL';

            $hasil[] = [
                'tabel' => $table,
                'harapan' => $harapan,
                'aktual' => $aktual,
                'id_hilang' => $idHilang,
                'status' => $status,
            ];
        }

        return $hasil;
    }

    /**
     * @param  array<int, string>  $tabel
     * @return array<int, array<string, mixed>>
     */
    private function periksaRelasi(array $tabel): array
    {
        $hasil = [];

        foreach ($this->relasi() as $relasi) {
            if (! in_array($relasi['tabel'], $tabel, true) || ! in_array($relasi['induk'], $tabel, true)) {
                continue;
            }

            if (! Schema::connection(self::KONEKSI)->hasTable($relasi['tabel'])
                || ! Schema::connection(self::KONEKSI)->hasTable($relasi['induk'])
                || ! Schema::connection(self::KONEKSI)->hasColumn($relasi['tabel'], $relasi['kolom'])) {
                continue;
            }

            $yatim = DB::connection(self::KONEKSI)
                ->table($relasi['tabel'])
                ->whereNotNull($relasi['kolom'])
                ->whereNotIn($relasi['kolom'], function ($query) use ($relasi) {
                    $query->select('id')->from($relasi['induk']);
                })
                ->count();

            $hasil[] = [
                'nama' => $relasi['nama'],
                'yatim' => $yatim,
            ];
        }

        return $hasil;
    }

    /**
     * @return array<int, array{tabel: string, kolom: string, induk: string, nama: string}>
     */
    private function relasi(): array
    {
        return [
            ['tabel' => 'siswa', 'kolom' => 'guru_id', 'induk' => 'users', 'nama' => 'siswa.guru_id → users'],
            ['tabel' => 'cbt_questions', 'kolom' => 'bank_id', 'induk' => 'cbt_question_banks', 'nama' => 'cbt_questions.bank_id → cbt_question_banks'],
            ['tabel' => 'cbt_test_sessions', 'kolom' => 'bank_id', 'induk' => 'cbt_question_banks', 'nama' => 'cbt_test_sessions.bank_id → cbt_question_banks'],
            ['tabel' => 'cbt_results', 'kolom' => 'session_id', 'induk' => 'cbt_test_sessions', 'nama' => 'cbt_results.session_id → cbt_test_sessions'],
            ['tabel' => 'cbt_violations', 'kolom' => 'result_id', 'induk' => 'cbt_results', 'nama' => 'cbt_violations.result_id → cbt_results'],
            ['tabel' => 'tryout_subjects', 'kolom' => 'package_id', 'induk' => 'tryout_packages', 'nama' => 'tryout_subjects.package_id → tryout_packages'],
            ['tabel' => 'tryout_questions', 'kolom' => 'tryout_subject_id', 'induk' => 'tryout_subjects', 'nama' => 'tryout_questions.tryout_subject_id → tryout_subjects'],
            ['tabel' => 'tryout_question_options', 'kolom' => 'tryout_question_id', 'induk' => 'tryout_questions', 'nama' => 'tryout_question_options.tryout_question_id → tryout_questions'],
            ['tabel' => 'tryout_sessions', 'kolom' => 'package_id', 'induk' => 'tryout_packages', 'nama' => 'tryout_sessions.package_id → tryout_packages'],
            ['tabel' => 'tryout_results', 'kolom' => 'tryout_session_id', 'induk' => 'tryout_sessions', 'nama' => 'tryout_results.tryout_session_id → tryout_sessions'],
            ['tabel' => 'tryout_student_answers', 'kolom' => 'tryout_session_id', 'induk' => 'tryout_sessions', 'nama' => 'tryout_student_answers.tryout_session_id → tryout_sessions'],
            ['tabel' => 'tryout_student_answers', 'kolom' => 'question_id', 'induk' => 'tryout_questions', 'nama' => 'tryout_student_answers.question_id → tryout_questions'],
            ['tabel' => 'exam_banks', 'kolom' => 'engine_id', 'induk' => 'exam_engines', 'nama' => 'exam_banks.engine_id → exam_engines'],
            ['tabel' => 'exam_sections', 'kolom' => 'bank_id', 'induk' => 'exam_banks', 'nama' => 'exam_sections.bank_id → exam_banks'],
            ['tabel' => 'exam_questions', 'kolom' => 'bank_id', 'induk' => 'exam_banks', 'nama' => 'exam_questions.bank_id → exam_banks'],
            ['tabel' => 'exam_questions', 'kolom' => 'section_id', 'induk' => 'exam_sections', 'nama' => 'exam_questions.section_id → exam_sections'],
            ['tabel' => 'exam_sessions', 'kolom' => 'bank_id', 'induk' => 'exam_banks', 'nama' => 'exam_sessions.bank_id → exam_banks'],
            ['tabel' => 'exam_attempts', 'kolom' => 'session_id', 'induk' => 'exam_sessions', 'nama' => 'exam_attempts.session_id → exam_sessions'],
            ['tabel' => 'exam_attempts', 'kolom' => 'siswa_id', 'induk' => 'siswa', 'nama' => 'exam_attempts.siswa_id → siswa'],
            ['tabel' => 'exam_answers', 'kolom' => 'attempt_id', 'induk' => 'exam_attempts', 'nama' => 'exam_answers.attempt_id → exam_attempts'],
            ['tabel' => 'exam_answers', 'kolom' => 'question_id', 'induk' => 'exam_questions', 'nama' => 'exam_answers.question_id → exam_questions'],
            ['tabel' => 'exam_results', 'kolom' => 'attempt_id', 'induk' => 'exam_attempts', 'nama' => 'exam_results.attempt_id → exam_attempts'],
            ['tabel' => 'exam_violations', 'kolom' => 'attempt_id', 'induk' => 'exam_attempts', 'nama' => 'exam_violations.attempt_id → exam_attempts'],
        ];
    }

    private function pilihBerkas(\Illuminate\Contracts\Filesystem\Filesystem $disk, string $direktori): ?string
    {
        $file = $this->option('file');

        if (is_string($file) && $file !== '') {
            $file = str_replace('\\', '/', $file);

            return str_contains($file, '/') ? $file : trim($direktori . '/' . $file, '/');
        }

        $berkas = collect($disk->files($direktori))
            ->filter(fn (string $path) => str_ends_with($path, '.json'))
            ->sortByDesc(fn (string $path) => $disk->lastModified($path))
            ->values();

        return $berkas->first();
    }
}
