<?php

namespace Tests\Feature;

use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CspReportOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_csp_headers_are_absent_when_disabled(): void
    {
        config([
            'security.csp.enabled' => false,
            'security.content_security_policy' => '',
        ]);

        $response = $this->get(route('login'))->assertOk();
        $this->assertFalse($response->headers->has('Content-Security-Policy-Report-Only'));
        $this->assertFalse($response->headers->has('Content-Security-Policy'));
    }

    public function test_csp_report_only_header_is_sent_on_html_without_enforce_and_skipped_on_binary_media(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        config([
            'security.csp.enabled' => true,
            'security.content_security_policy' => '',
        ]);

        $response = $this->get(route('login'))->assertOk();
        $this->assertTrue($response->headers->has('Content-Security-Policy-Report-Only'));
        $this->assertFalse($response->headers->has('Content-Security-Policy'));

        $header = (string) $response->headers->get('Content-Security-Policy-Report-Only');
        $this->assertStringContainsString("default-src 'self'", $header);
        $this->assertStringContainsString("script-src 'self' 'unsafe-inline' 'unsafe-eval'", $header);
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline'", $header);
        $this->assertStringContainsString("img-src 'self' data: blob:", $header);
        $this->assertStringContainsString("frame-src 'self' https://www.youtube-nocookie.com", $header);
        $this->assertStringContainsString("frame-ancestors 'self'", $header);
        $this->assertStringContainsString("base-uri 'self'", $header);
        $this->assertStringContainsString("form-action 'self'", $header);
        $this->assertStringContainsString("object-src 'none'", $header);
        $this->assertStringContainsString('report-uri /csp-report', $header);

        // Respons gambar /media tidak dipasangi header CSP Report-Only
        $admin = User::factory()->create(['peran' => 'admin', 'aktif' => true, 'force_password_change' => false]);
        $siswa = Siswa::create([
            'nis' => '20269901',
            'nama_peserta_didik' => 'Siswa CSP Media',
            'jenis_kelamin' => 'L',
            'kelas' => '4A',
            'foto' => 'foto-siswa/cspmedia123.png',
            'aktif' => true,
        ]);
        $png = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        Storage::disk('local')->put($siswa->foto, $png);

        $mediaRes = $this->actingAs($admin)->get(route('media.siswa.foto', $siswa))->assertOk();
        $this->assertFalse($mediaRes->headers->has('Content-Security-Policy-Report-Only'));
    }

    public function test_csp_report_endpoint_logs_sanitized_fields_without_query_strings_and_enforces_limits(): void
    {
        Cache::flush();

        // GET /csp-report -> 405 Method Not Allowed
        $this->get('/csp-report')->assertStatus(405);

        // Content-Type salah -> 415
        $this->call('POST', '/csp-report', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['csp-report' => []]))->assertStatus(415);

        // Payload > 10 kB -> 413
        $oversized = json_encode(['csp-report' => ['blocked-uri' => str_repeat('a', 11000)]]);
        $this->call('POST', '/csp-report', [], [], [], [
            'CONTENT_TYPE' => 'application/csp-report',
        ], $oversized)->assertStatus(413);

        // Payload valid application/csp-report -> 204 & log tanpa query string / cookie / IP
        $captured = [];
        Log::shouldReceive('channel')
            ->with('csp')
            ->twice()
            ->andReturn(new class($captured)
            {
                public function __construct(private array &$captured) {}

                public function warning(string $message, array $context = []): void
                {
                    $this->captured[] = ['message' => $message, 'context' => $context];
                }
            });

        $validPayload = json_encode([
            'csp-report' => [
                'document-uri' => 'https://simantap.example.test/admin/users?token=rahasia123#frag',
                'blocked-uri' => 'https://evil.example.test/script.js?key=secret456',
                'violated-directive' => 'script-src',
                'effective-directive' => 'script-src',
                'disposition' => 'report',
                'original-policy' => 'should-not-be-stored',
            ],
        ]);

        $this->call('POST', '/csp-report', [], [], [], [
            'CONTENT_TYPE' => 'application/csp-report; charset=utf-8',
        ], $validPayload)->assertStatus(204);

        $this->assertCount(1, $captured);
        $ctx = $captured[0]['context'];
        $this->assertSame('https://simantap.example.test/admin/users', $ctx['document-uri']);
        $this->assertSame('https://evil.example.test/script.js', $ctx['blocked-uri']);
        $this->assertSame('script-src', $ctx['violated-directive']);
        $this->assertSame('report', $ctx['disposition']);
        $this->assertArrayNotHasKey('original-policy', $ctx);
        $this->assertArrayNotHasKey('ip', $ctx);
        $this->assertArrayNotHasKey('cookie', $ctx);

        // Payload valid Reporting API (application/reports+json) -> 204
        $reportingApiPayload = json_encode([
            [
                'type' => 'csp-violation',
                'url' => 'https://simantap.example.test/login?next=/admin',
                'body' => [
                    'documentURL' => 'https://simantap.example.test/login?next=/admin',
                    'blockedURL' => 'https://cdn.evil.test/x.css?v=1',
                    'effectiveDirective' => 'style-src',
                    'disposition' => 'report',
                ],
            ],
        ]);

        $this->call('POST', '/csp-report', [], [], [], [
            'CONTENT_TYPE' => 'application/reports+json',
        ], $reportingApiPayload)->assertStatus(204);

        $this->assertCount(2, $captured);
        $this->assertSame('https://simantap.example.test/login', $captured[1]['context']['document-uri']);
        $this->assertSame('https://cdn.evil.test/x.css', $captured[1]['context']['blocked-uri']);
    }

    public function test_csp_report_endpoint_is_rate_limited_at_30_per_minute(): void
    {
        Cache::flush();

        // Kirim 30 permintaan (content-type tidak valid sudah melewati throttle:csp-report) -> ke-31 harus 429
        for ($i = 0; $i < 30; $i++) {
            $this->call('POST', '/csp-report', [], [], [], [
                'CONTENT_TYPE' => 'text/plain',
            ], 'x')->assertStatus(415);
        }

        $this->call('POST', '/csp-report', [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
        ], 'x')->assertStatus(429);
    }

    public function test_csp_report_does_not_set_cookie_or_write_database_session_and_other_post_routes_keep_csrf(): void
    {
        Cache::flush();
        config(['session.driver' => 'database']);

        $sessionsBefore = DB::table('sessions')->count();

        $payload = json_encode([
            'csp-report' => [
                'document-uri' => 'https://simantap.example.test/login',
                'blocked-uri' => 'inline',
                'violated-directive' => 'script-src',
            ],
        ]);

        $res = $this->call('POST', '/csp-report', [], [], [], [
            'CONTENT_TYPE' => 'application/csp-report; charset=utf-8',
        ], $payload)->assertStatus(204);

        $this->assertSame([], $res->headers->getCookies(), 'POST /csp-report tidak boleh mengirim Set-Cookie.');
        $this->assertFalse($res->headers->has('Set-Cookie'));
        $this->assertSame($sessionsBefore, DB::table('sessions')->count(), 'POST /csp-report tidak boleh menulis baris ke tabel sessions.');

        // Pastikan pengecualian CSRF HANYA untuk csp-report saat CSRF aktif (bukan bypass unit-test):
        // POST /csp-report tanpa token tetap 204, sedangkan POST /login tanpa token wajib 419.
        $prevEnv = $this->app['env'];
        $this->app['env'] = 'production';
        try {
            $this->call('POST', '/csp-report', [], [], [], [
                'CONTENT_TYPE' => 'application/csp-report; charset=utf-8',
            ], $payload)->assertStatus(204);

            $this->post('/login', [
                'login' => 'admin',
                'kata_sandi' => 'salah',
            ])->assertStatus(419);
        } finally {
            $this->app['env'] = $prevEnv;
        }
    }

    public function test_csp_report_enforces_actual_body_bytes_sanitizes_control_chars_and_caps_reports_array_at_10(): void
    {
        Cache::flush();

        // Header Content-Length dipalsukan kecil (50), tetapi body nyata > 10 kB -> tetap 413
        $oversized = json_encode(['csp-report' => ['blocked-uri' => str_repeat('z', 11000)]]);
        $this->call('POST', '/csp-report', [], [], [], [
            'CONTENT_TYPE' => 'application/csp-report',
            'CONTENT_LENGTH' => '50',
        ], $oversized)->assertStatus(413);

        $captured = [];
        Log::shouldReceive('channel')
            ->with('csp')
            ->times(11)
            ->andReturn(new class($captured)
            {
                public function __construct(private array &$captured) {}

                public function warning(string $message, array $context = []): void
                {
                    $this->captured[] = $context;
                }
            });

        // 1) Payload beracun: newline, control chars, nested array, dan string 1000 karakter
        $poisonPayload = json_encode([
            'csp-report' => [
                'violated-directive' => ['nested' => 'evil'],
                'effective-directive' => "script-src\r\nFAKE LOG ENTRY\x00",
                'document-uri' => 'https://simantap.example.test/'.str_repeat('a', 1000).'?secret=1',
                'blocked-uri' => "https://evil.test/x.js\nInjected?k=v",
                'disposition' => "report\r\n",
            ],
        ]);

        $this->call('POST', '/csp-report', [], [], [], [
            'CONTENT_TYPE' => 'application/csp-report; charset=utf-8',
        ], $poisonPayload)->assertStatus(204);

        $first = $captured[0];
        $this->assertSame('', $first['violated-directive']);
        $this->assertDoesNotMatchRegularExpression('/[\x00-\x1F\x7F]/', $first['effective-directive']);
        $this->assertDoesNotMatchRegularExpression('/[\x00-\x1F\x7F]/', $first['blocked-uri']);
        $this->assertLessThanOrEqual(500, mb_strlen($first['document-uri']));
        $this->assertStringNotContainsString('secret=1', $first['document-uri']);

        // 2) Reporting API array berisi 15 item -> dibatasi maksimal 10 entri
        $batch = [];
        for ($i = 0; $i < 15; $i++) {
            $batch[] = [
                'type' => 'csp-violation',
                'url' => 'https://simantap.example.test/page-'.$i,
                'body' => [
                    'documentURL' => 'https://simantap.example.test/page-'.$i,
                    'blockedURL' => 'inline',
                    'effectiveDirective' => 'script-src',
                    'disposition' => 'report',
                ],
            ];
        }

        $this->call('POST', '/csp-report', [], [], [], [
            'CONTENT_TYPE' => 'application/reports+json; charset=utf-8',
        ], json_encode($batch))->assertStatus(204);

        $this->assertCount(11, $captured);
    }
}
