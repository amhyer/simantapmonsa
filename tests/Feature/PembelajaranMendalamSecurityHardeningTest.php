<?php

namespace Tests\Feature;

use App\Models\MataPelajaran;
use App\Models\PembelajaranMendalam\AiUsageLog;
use App\Models\PembelajaranMendalam\CapaianPembelajaran;
use App\Models\PembelajaranMendalam\KokurikulerPm;
use App\Models\SekolahSettings;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PembelajaranMendalamSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private User $guruA;

    private User $guruB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SDN Hardening Uji',
            'subdomain' => 'sdn-hardening-uji',
            'aktif' => true,
        ]);
        TenantContext::set($this->tenant->id);

        $this->admin = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
        $this->guruA = User::factory()->guru()->create(['tenant_id' => $this->tenant->id]);
        $this->guruB = User::factory()->guru()->create(['tenant_id' => $this->tenant->id]);
        MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK', 'aktif' => true, 'urutan' => 1]);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_gemini_api_key_terenkripsi_di_database_dan_dikirim_via_header_bukan_query_string(): void
    {
        $rawKey = 'AIzaSy-SUPER-SECRET-TEST-KEY-999';

        $this->actingAs($this->admin)
            ->post(route('admin.sekolah.update'), [
                'nama_sekolah' => 'SDN Hardening Uji',
                'gemini_api_key' => $rawKey,
                'gemini_model' => 'gemini-2.5-flash',
                'gemini_daily_quota' => 50,
            ])
            ->assertRedirect(route('admin.sekolah.index'));

        $sekolah = SekolahSettings::where('tenant_id', $this->tenant->id)->firstOrFail();
        $pengaturan = $sekolah->pengaturan;

        // Pastikan tidak tersimpan plaintext
        $this->assertArrayNotHasKey('gemini_api_key', $pengaturan);
        $this->assertArrayHasKey('gemini_api_key_encrypted', $pengaturan);
        $this->assertNotSame($rawKey, $pengaturan['gemini_api_key_encrypted']);
        $this->assertStringNotContainsString($rawKey, (string) json_encode($pengaturan));

        // Pastikan request HTTP mengirim header x-goog-api-key dan TIDAK menaruh key di URL
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([['elemen' => 'Bilangan', 'deskripsi' => 'CP terenkripsi aman']]),
                    ]]],
                ]],
            ], 200),
        ]);

        $this->actingAs($this->guruA)
            ->post(route('guru.pm.cp.ai'), [
                'nama_mapel' => 'Matematika',
                'fase' => 'A',
            ])
            ->assertRedirect();

        Http::assertSent(function (HttpClientRequest $req) use ($rawKey): bool {
            return $req->hasHeader('x-goog-api-key', $rawKey)
                && ! str_contains($req->url(), 'key=')
                && ! str_contains($req->url(), $rawKey);
        });
    }

    public function test_ai_menangani_kuota_habis_dan_error_http_gemini_dengan_aman(): void
    {
        SekolahSettings::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $this->admin->id,
            'nama_sekolah' => 'SDN Hardening Uji',
            'pengaturan' => [
                'gemini_api_key_encrypted' => encrypt('dummy-key'),
                'gemini_daily_quota' => 1,
            ],
        ]);

        // Simulasikan kuota harian 1 sudah terpakai
        AiUsageLog::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->guruA->id,
            'fitur' => 'susun_cp',
            'model' => 'gemini-2.5-flash',
            'status' => 'ok',
            'tanggal' => now()->toDateString(),
        ]);

        $this->actingAs($this->guruA)
            ->post(route('guru.pm.cp.ai'), [
                'nama_mapel' => 'Matematika',
                'fase' => 'A',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_ttd_dan_stempel_disimpan_di_disk_privat_bukan_public_dan_tolak_file_berbahaya(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        SekolahSettings::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $this->admin->id,
            'nama_sekolah' => 'SDN Hardening Uji',
        ]);

        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');

        // Tolak file PHP pada TTD Guru
        $badFile = UploadedFile::fake()->create('shell.php', 10, 'application/x-php');
        $this->actingAs($this->guruA)
            ->post(route('guru.pm.ttd.store'), ['ttd_guru' => $badFile])
            ->assertSessionHasErrors('ttd_guru');

        // Upload PNG valid untuk TTD Guru -> harus masuk disk local (private), BUKAN public
        $this->actingAs($this->guruA)
            ->post(route('guru.pm.ttd.store'), [
                'ttd_guru' => UploadedFile::fake()->createWithContent('ttd.png', $pngBytes),
            ])
            ->assertRedirect(route('guru.pm.ttd.index'));

        // Upload TTD Kepsek & Stempel Sekolah -> harus masuk disk local (private), BUKAN public
        $this->actingAs($this->admin)
            ->post(route('admin.referensi.logo-ttd.store'), [
                'ttd_kepsek' => UploadedFile::fake()->createWithContent('kepsek.png', $pngBytes),
                'stempel_sekolah' => UploadedFile::fake()->createWithContent('stempel.png', $pngBytes),
            ])
            ->assertRedirect(route('admin.referensi.logo-ttd'));

        $this->assertNotEmpty(Storage::disk('local')->allFiles('private/identitas'));
        $this->assertEmpty(Storage::disk('public')->allFiles('identitas'));
    }

    public function test_policy_mengunci_kepemilikan_guru_saat_mode_kolaboratif_nonaktif(): void
    {
        SekolahSettings::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $this->admin->id,
            'nama_sekolah' => 'SDN Hardening Uji',
            'pengaturan' => [
                'pm_mode_kolaboratif' => false,
            ],
        ]);

        $cpMilikA = CapaianPembelajaran::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $this->guruA->id,
            'nama_mapel' => 'Matematika',
            'fase' => 'A',
            'elemen' => 'Bilangan',
            'deskripsi' => 'CP Milik Guru A',
        ]);

        $kokuMilikA = KokurikulerPm::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $this->guruA->id,
            'tahun_ajaran' => '2026/2027',
            'tingkat' => 1,
            'tema_judul' => 'Proyek Guru A',
        ]);

        // Guru B dalam tenant sama DILARANG menghapus milik Guru A saat mode kolaboratif = false
        $this->actingAs($this->guruB)
            ->delete(route('guru.pm.cp.destroy', $cpMilikA))
            ->assertForbidden();

        $this->actingAs($this->guruB)
            ->delete(route('guru.pm.kokurikuler.destroy', $kokuMilikA))
            ->assertForbidden();

        // Guru A (pemilik) BOLEH menghapus miliknya sendiri
        $this->actingAs($this->guruA)
            ->delete(route('guru.pm.cp.destroy', $cpMilikA))
            ->assertRedirect();
    }

    public function test_endpoint_mutasi_kritis_memiliki_middleware_throttle(): void
    {
        $criticalRoutes = [
            'tka.refresh',
            'force-password-change.update',
            'superadmin.tenants.store',
            'superadmin.tenants.bulk-destroy-expired',
            'superadmin.tenants.reset-admin-password',
            'superadmin.users.bulk-destroy',
            'admin.api-keys.store',
            'admin.dapodik.import',
            'admin.dapodik.api.pull',
            'admin.dapodik.api.sync',
            'guru.nilai-erapor.import',
            'guru.erapor.pdf-bulk',
            'guru.pm.cp.ai',
            'guru.pm.ttd.store',
        ];

        foreach ($criticalRoutes as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Route {$name} tidak ditemukan.");
            $mw = implode(',', $route->gatherMiddleware());
            $this->assertMatchesRegularExpression(
                '/throttle|ThrottleRequests/',
                $mw,
                "Route kritis {$name} belum dilindungi middleware throttle."
            );
        }
    }
}
