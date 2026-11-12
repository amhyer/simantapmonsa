<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Ptk;
use App\Models\Rombel;
use App\Models\SekolahSettings;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DapodikBridgeTenantSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    private function school(string $name, string $npsn): array
    {
        $tenant = Tenant::create(['uuid' => (string) Str::uuid(), 'nama_sekolah' => $name, 'npsn' => $npsn, 'subdomain' => 'sch-'.$npsn, 'aktif' => true]);
        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
        $key = ApiKey::generate($admin->id, 'Bridge '.$name)['plaintext_key'];

        return [$tenant, $key];
    }

    private function push(string $key, string $modul, array $data)
    {
        return $this->withHeaders(['X-API-Key' => $key])->postJson('/api/dapodik/sync/'.$modul, [
            'semester_id' => '20261', 'tahun_ajaran' => '2026/2027', 'nama_semester' => 'ganjil', 'data' => $data,
        ]);
    }

    public function test_bridge_dua_sekolah_tidak_bercampur(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);
        [$a, $keyA] = $this->school('Sekolah A', '10000001');
        [$b, $keyB] = $this->school('Sekolah B', '10000002');

        $this->push($keyA, 'sekolah', [['npsn' => '10000001', 'nama' => 'Sekolah A']])->assertOk()->assertJson(['success' => true, 'berhasil' => 1]);
        $this->push($keyB, 'sekolah', [['npsn' => '10000002', 'nama' => 'Sekolah B']])->assertOk()->assertJson(['success' => true, 'berhasil' => 1]);

        // NPSN dari sekolah lain ditolak (dihitung gagal), tidak menimpa data.
        $this->push($keyA, 'sekolah', [['npsn' => '10000002', 'nama' => 'Peretas']])->assertOk()->assertJson(['gagal' => 1]);

        // PTK dengan NIP sama di dua sekolah -> akun terpisah per sekolah.
        $ptk = fn (string $id) => [['ptk_id' => $id, 'nama' => 'Budi', 'nip' => '198001012005011001', 'jenis_ptk' => 'Guru Kelas']];
        $this->push($keyA, 'ptk', $ptk('ptk-a'))->assertOk()->assertJson(['berhasil' => 1]);
        $this->push($keyB, 'ptk', $ptk('ptk-b'))->assertOk()->assertJson(['berhasil' => 1]);

        $this->push($keyA, 'rombongan-belajar', [['rombongan_belajar_id' => 'r-a', 'nama' => 'Kelas 1A', 'tingkat' => '1']])->assertOk();
        $this->push($keyB, 'rombongan-belajar', [['rombongan_belajar_id' => 'r-b', 'nama' => 'Kelas 1A', 'tingkat' => '1']])->assertOk();

        $this->push($keyA, 'peserta-didik', [['peserta_didik_id' => 's-a', 'nama' => 'Siswa A', 'nisn' => '111', 'nis' => '111', 'nama_rombel' => 'Kelas 1A']])->assertOk()->assertJson(['berhasil' => 1]);
        $this->push($keyB, 'peserta-didik', [['peserta_didik_id' => 's-b', 'nama' => 'Siswa B', 'nisn' => '111', 'nis' => '111', 'nama_rombel' => 'Kelas 1A']])->assertOk()->assertJson(['berhasil' => 1]);

        foreach ([$a, $b] as $t) {
            TenantContext::run($t->id, function () {
                $this->assertSame(1, SekolahSettings::count());
                $this->assertSame(1, Ptk::count());
                $this->assertSame(1, Rombel::count());
                $this->assertSame(1, Siswa::count());
            });
        }

        $this->assertSame('Siswa A', TenantContext::run($a->id, fn () => Siswa::first()->nama_peserta_didik));
        $this->assertSame('Siswa B', TenantContext::run($b->id, fn () => Siswa::first()->nama_peserta_didik));
        $this->assertSame(2, User::where('nama_pengguna', '198001012005011001')->count());
        $this->assertSame(1, User::where('tenant_id', $a->id)->where('nama_pengguna', '198001012005011001')->count());

        $this->withHeaders(['X-API-Key' => $keyA])->getJson('/api/dapodik/sync/status')->assertOk()->assertJsonPath('stats.siswa', 1);
    }
}
