<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use App\Services\Presensi\IdentityCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PegawaiFotoPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private string $tinyPng;

    protected function setUp(): void
    {
        parent::setUp();
        // PNG 1x1 valid
        $this->tinyPng = (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        );
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_foto_pegawai_disajikan_dari_disk_local_hanya_untuk_pemilik_admin_dan_kepsek_satu_tenant(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        config(['simantap.multi_tenant_enabled' => true]);

        $tenantA = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => '40370001',
            'nama_sekolah' => 'SDN Foto A',
            'subdomain' => 'sdnfotoa',
            'aktif' => true,
        ]);
        $tenantB = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'npsn' => '40370002',
            'nama_sekolah' => 'SDN Foto B',
            'subdomain' => 'sdnfotob',
            'aktif' => true,
        ]);

        $guru = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'peran' => 'guru',
            'aktif' => true,
            'foto' => 'foto-pegawai/guru01.png',
        ]);
        $guruLain = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'peran' => 'guru',
            'aktif' => true,
        ]);
        $adminA = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'peran' => 'admin',
            'aktif' => true,
        ]);
        $kepsekA = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'peran' => 'kepsek',
            'aktif' => true,
        ]);
        $adminB = User::factory()->create([
            'tenant_id' => $tenantB->id,
            'peran' => 'admin',
            'aktif' => true,
        ]);

        Storage::disk('local')->put('foto-pegawai/guru01.png', $this->tinyPng);

        $url = route('media.pegawai.foto', ['user' => $guru->uuid]);

        // Guest ditolak
        $this->get($url)->assertRedirect(route('login'));

        // Pemilik sendiri, Admin A, dan Kepsek A diizinkan
        foreach ([$guru, $adminA, $kepsekA] as $viewer) {
            $res = $this->actingAs($viewer)->get($url);
            $res->assertOk();
            $res->assertHeader('Content-Type', 'image/png');
            $res->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertStringContainsString('private', (string) $res->headers->get('Cache-Control'));
        }

        // Guru lain di sekolah yang sama & Admin dari sekolah lain ditolak 403
        $this->actingAs($guruLain)->get($url)->assertForbidden();
        $this->actingAs($adminB)->get($url)->assertForbidden();

        // IdentityCardService memakai URL privat /media/pegawai/{uuid}/foto, bukan /storage/
        $card = TenantContext::run($tenantA->id, fn () => app(IdentityCardService::class)->buildPegawaiCardViewModel($guru, null, null, false));
        $this->assertSame($url, $card['foto_url']);
        $this->assertStringNotContainsString('/storage/', (string) $card['foto_url']);
    }

    public function test_path_traversal_atau_mime_tidak_valid_ditolak_404(): void
    {
        Storage::fake('local');
        config(['simantap.multi_tenant_enabled' => false]);

        $admin = User::factory()->create([
            'tenant_id' => null,
            'peran' => 'admin',
            'aktif' => true,
            'foto' => 'foto-pegawai/../../.env',
        ]);

        $this->actingAs($admin)
            ->get(route('media.pegawai.foto', ['user' => $admin->uuid]))
            ->assertNotFound();

        $admin->update(['foto' => 'foto-pegawai/bukan-gambar.png']);
        Storage::disk('local')->put('foto-pegawai/bukan-gambar.png', '<?php echo "evil";');

        $this->actingAs($admin)
            ->get(route('media.pegawai.foto', ['user' => $admin->uuid]))
            ->assertNotFound();
    }
}
