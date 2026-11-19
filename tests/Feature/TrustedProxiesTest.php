<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_test/proxy-probe', function (Request $request) {
            return response()->json([
                'ip' => $request->ip(),
                'secure' => $request->isSecure(),
                'url' => url('/login'),
            ]);
        });
    }

    public function test_empty_config_ignores_x_forwarded_for_and_proto(): void
    {
        config(['security.trusted_proxies' => '']);

        $res = $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.42',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ])->get('/_test/proxy-probe')->assertOk();

        $res->assertJsonPath('ip', '203.0.113.10')
            ->assertJsonPath('secure', false);
        $this->assertFalse($res->headers->has('Strict-Transport-Security'));
    }

    public function test_trusted_proxy_ip_honors_x_forwarded_for_and_proto_enabling_hsts_and_https_url(): void
    {
        config(['security.trusted_proxies' => '10.0.0.1, 172.16.0.0/16']);

        $res = $this->withServerVariables([
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.77',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'simantap.example.test',
            'HTTP_X_FORWARDED_PORT' => '443',
        ])->get('/_test/proxy-probe')->assertOk();

        $res->assertJsonPath('ip', '198.51.100.77')
            ->assertJsonPath('secure', true)
            ->assertJsonPath('url', 'https://simantap.example.test/login');
        $this->assertTrue($res->headers->has('Strict-Transport-Security'));
    }

    public function test_untrusted_remote_addr_cannot_spoof_x_forwarded_for(): void
    {
        config(['security.trusted_proxies' => '10.0.0.1']);

        $res = $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.99',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.88',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ])->get('/_test/proxy-probe')->assertOk();

        $res->assertJsonPath('ip', '203.0.113.99')
            ->assertJsonPath('secure', false);
        $this->assertFalse($res->headers->has('Strict-Transport-Security'));
    }
}
