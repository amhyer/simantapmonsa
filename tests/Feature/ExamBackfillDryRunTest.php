<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExamBackfillDryRunTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_legacy_counts_without_mutating_engine_tables(): void
    {
        $targetTables = [
            'exam_engines', 'exam_banks', 'exam_sections', 'exam_questions', 'exam_sessions',
            'exam_attempts', 'exam_answers', 'exam_results', 'exam_violations',
        ];
        $before = collect($targetTables)->mapWithKeys(
            fn (string $table) => [$table => DB::table($table)->count()]
        )->all();

        $exitCode = Artisan::call('exam:backfill', ['--dry-run' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('DRY RUN', Artisan::output());
        foreach ($targetTables as $table) {
            $this->assertSame($before[$table], DB::table($table)->count(), "{$table} changed during dry-run");
        }
    }

    public function test_invalid_source_filter_fails_before_running_backfill(): void
    {
        $exitCode = Artisan::call('exam:backfill', ['--only' => 'unknown', '--dry-run' => true]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Opsi --only harus cbt atau tryout', Artisan::output());
        $this->assertSame(0, DB::table('exam_engines')->count());
    }
}
