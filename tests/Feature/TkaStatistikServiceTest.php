<?php

namespace Tests\Feature;

use App\Services\TkaStatistikService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TkaStatistikServiceTest extends TestCase
{
    public function test_get_returns_cached_statistics_without_refetching_them(): void
    {
        $cached = [
            'peserta' => ['total' => ['jm_pes_t' => 42]],
            'last_update' => '2026-10-25 08:00:00',
        ];

        Cache::put('tka_statistik', $cached);
        Http::fake();

        $this->assertSame($cached, app(TkaStatistikService::class)->get());
        Http::assertNothingSent();
    }

    public function test_get_cached_does_not_call_external_api_when_no_snapshot_exists(): void
    {
        Http::fake();

        $this->assertSame([
            'error' => true,
            'message' => 'Data TKA belum tersedia.',
            'last_update' => null,
        ], app(TkaStatistikService::class)->getCached());

        Http::assertNothingSent();
    }

    public function test_landing_page_renders_without_calling_external_tka_api(): void
    {
        Http::fake();

        $this->get('/')
            ->assertOk()
            ->assertSee('Data TKA belum tersedia.');

        Http::assertNothingSent();
    }
}
