<?php

namespace Tests\Feature;

use App\Services\Backup\BackupStreamWriter;
use App\Support\BackupTables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackupStreamingTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_covers_tryout_tka_and_engine_tables_and_wipe_keeps_identity_data(): void
    {
        $tables = BackupTables::tabel();
        $wipeTables = BackupTables::tabelUntukWipe();

        foreach ([
            'tryout_categories', 'tryout_packages', 'tryout_subjects', 'tryout_questions',
            'tryout_question_options', 'tryout_sessions', 'tryout_results', 'tryout_student_answers',
            'tka_dimensions_enhanced', 'tka_assessment_items', 'tka_student_assessments',
            'tka_assessment_responses', 'tka_parent_reports', 'tka_historical_tracking',
            'tka_early_warnings', 'exam_engines', 'exam_results',
        ] as $table) {
            $this->assertContains($table, $tables);
            $this->assertContains($table, $wipeTables);
        }

        foreach (['users', 'semesters', 'sekolah_settings', 'api_keys'] as $table) {
            $this->assertNotContains($table, $wipeTables);
        }

        foreach (BackupTables::tidakDicadangkan() as $table) {
            $this->assertContains($table, $wipeTables);
        }
    }

    public function test_stream_writer_emits_valid_json_and_chunks_rows_without_losing_records(): void
    {
        $rows = [];
        for ($i = 1; $i <= 205; $i++) {
            $rows[] = [
                'uuid' => (string) Str::uuid(),
                'nama_kategori' => 'Kategori '.$i,
                'deskripsi' => null,
                'jenis' => 'akademik',
                'aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('tryout_categories')->insert($rows);

        $stream = fopen('php://temp/maxmemory:2097152', 'w+b');
        $summary = app(BackupStreamWriter::class)->writeTo($stream);
        rewind($stream);
        $payload = json_decode(stream_get_contents($stream), true, 512, JSON_THROW_ON_ERROR);
        fclose($stream);

        $this->assertSame('1.0', $payload['metadata']['version']);
        $this->assertCount(205, $payload['tryout_categories']);
        $this->assertSame(205, $summary['rows_by_table']['tryout_categories']);
        $this->assertGreaterThanOrEqual(1, $summary['tables']);
        $this->assertSame('Kategori 1', $payload['tryout_categories'][0]['nama_kategori']);
        $this->assertArrayNotHasKey('api_keys', $payload);
    }

    public function test_restore_replays_rows_in_registry_order_even_when_json_tables_are_shuffled(): void
    {
        $payload = [
            'tryout_packages' => [[
                'id' => 92,
                'uuid' => (string) Str::uuid(),
                'category_id' => 91,
                'kode_paket' => 'RESTORE-ORDER',
                'nama_paket' => 'Paket restore order',
                'deskripsi' => null,
                'jenjang' => 'SMA',
                'tahun_paket' => 2026,
                'jumlah_mapel' => 0,
                'durasi_total_menit' => 60,
                'passing_grade' => 60,
                'standar_nilai' => null,
                'aktif' => true,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
                'deleted_at' => null,
            ]],
            'metadata' => ['version' => '1.0'],
            'tryout_categories' => [[
                'id' => 91,
                'uuid' => (string) Str::uuid(),
                'nama_kategori' => 'Kategori restore order',
                'deskripsi' => null,
                'jenis' => 'akademik',
                'aktif' => true,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]],
        ];
        $path = tempnam(sys_get_temp_dir(), 'backup-order-');
        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));

        try {
            $upload = new \Illuminate\Http\UploadedFile($path, 'backup.json', 'application/json', null, true);
            $request = \Illuminate\Http\Request::create(
                '/admin/backup/import',
                'POST',
                [],
                [],
                ['backup_file' => $upload]
            );
            app(\App\Http\Controllers\Admin\BackupController::class)->import($request);
        } finally {
            @unlink($path);
        }

        $this->assertDatabaseHas('tryout_categories', ['id' => 91, 'nama_kategori' => 'Kategori restore order']);
        $this->assertDatabaseHas('tryout_packages', ['id' => 92, 'category_id' => 91, 'kode_paket' => 'RESTORE-ORDER']);
    }

    public function test_stream_writer_keeps_heap_growth_bounded_for_large_fixture(): void
    {
        $description = str_repeat('x', 4096);
        for ($batch = 0; $batch < 30; $batch++) {
            $rows = [];
            for ($i = 0; $i < 100; $i++) {
                $number = ($batch * 100) + $i + 1;
                $rows[] = [
                    'uuid' => (string) Str::uuid(),
                    'nama_kategori' => 'Fixture '.$number,
                    'deskripsi' => $description,
                    'jenis' => 'akademik',
                    'aktif' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('tryout_categories')->insert($rows);
        }
        unset($rows, $description);

        $stream = fopen('php://temp/maxmemory:2097152', 'w+b');
        memory_reset_peak_usage();
        $baseline = memory_get_usage(true);
        $summary = app(BackupStreamWriter::class)->writeTo($stream);
        $heapGrowth = memory_get_peak_usage(true) - $baseline;
        $bytesWritten = ftell($stream);
        fclose($stream);

        $this->assertSame(3000, $summary['rows_by_table']['tryout_categories']);
        $this->assertGreaterThan(10 * 1024 * 1024, $bytesWritten);
        $this->assertLessThan(32 * 1024 * 1024, $heapGrowth, 'Streaming backup heap growth exceeded the fixture threshold.');
    }
}
