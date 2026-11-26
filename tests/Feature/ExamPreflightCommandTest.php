<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExamPreflightCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_exam_preflight_lulus_go_untuk_target_tka_cbt_dan_readers(): void
    {
        config([
            'queue.default' => 'database',
            'queue.connections.database.retry_after' => 600,
            'simantap.cbt_buffer_async' => false,
            'simantap.exam_engine_cbt' => true,
            'simantap.exam_engine_tryout' => true,
        ]);

        foreach (['tka', 'cbt', 'readers'] as $target) {
            $exitCode = Artisan::call('exam:preflight', [
                '--target' => $target,
                '--json' => true,
            ]);

            $this->assertSame(0, $exitCode);
            $decoded = json_decode(Artisan::output(), true);
            $this->assertIsArray($decoded);
            $this->assertSame($target, $decoded['target']);
            $this->assertSame('GO', $decoded['keputusan']);
            $this->assertTrue($decoded['ok']);
        }
    }

    public function test_exam_preflight_gagal_no_go_bila_cbt_buffer_async_tanpa_redis_atau_retry_after_rendah(): void
    {
        config([
            'cache.default' => 'database',
            'simantap.cbt_buffer_async' => true,
            'queue.default' => 'database',
            'queue.connections.database.retry_after' => 90,
        ]);

        $exitCode = Artisan::call('exam:preflight', [
            '--target' => 'cbt',
            '--json' => true,
        ]);

        $this->assertSame(1, $exitCode);
        $decoded = json_decode(Artisan::output(), true);
        $this->assertSame('NO-GO', $decoded['keputusan']);
        $this->assertFalse($decoded['ok']);

        $flagCheck = collect($decoded['checks'])->firstWhere('kode', 'flags');
        $this->assertFalse($flagCheck['lulus']);
        $this->assertStringContainsString('CACHE_STORE=redis', $flagCheck['detail']);
        $this->assertStringContainsString('DB_QUEUE_RETRY_AFTER', $flagCheck['detail']);
    }

    public function test_exam_preflight_gagal_no_go_bila_ada_failed_jobs(): void
    {
        config([
            'queue.default' => 'database',
            'queue.connections.database.retry_after' => 600,
            'simantap.cbt_buffer_async' => false,
        ]);

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'RuntimeException: simulasi gagal',
            'failed_at' => now(),
        ]);

        $this->artisan('exam:preflight', ['--target' => 'cbt'])
            ->assertExitCode(1);
    }

    public function test_exam_preflight_menolak_target_tidak_valid(): void
    {
        $this->artisan('exam:preflight', ['--target' => 'invalid'])
            ->assertExitCode(2);
    }
}
