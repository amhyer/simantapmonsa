<?php

namespace Tests\Feature;

use App\Models\Aktivitas;
use App\Models\Ekstrakurikuler;
use App\Models\JadwalPelajaran;
use App\Models\Ptk;
use App\Models\RingkasanGuru;
use App\Models\Semester;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SchoolReferenceIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    private function school(string $npsn): Tenant
    {
        return Tenant::create(['uuid' => Str::uuid(), 'nama_sekolah' => 'Sekolah '.$npsn, 'npsn' => $npsn, 'subdomain' => 'uji-'.$npsn, 'aktif' => true]);
    }

    public function test_kepsek_melihat_aktivitas_dan_ringkasan_hanya_milik_sekolahnya(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);
        $a = $this->school('10000001');
        $b = $this->school('10000002');
        $kepsek = User::factory()->kepsek()->create(['tenant_id' => $a->id]);
        $guruA = User::factory()->guru()->create(['tenant_id' => $a->id, 'nama_lengkap' => 'Guru Unik A']);
        $guruB = User::factory()->guru()->create(['tenant_id' => $b->id, 'nama_lengkap' => 'Guru Rahasia B']);
        foreach ([[$a, $guruA, 'Aktivitas A'], [$b, $guruB, 'Aktivitas Rahasia B']] as [$school, $guru, $judul]) {
            TenantContext::run($school->id, function () use ($guru, $judul) {
                Aktivitas::create(['guru_id' => $guru->id, 'nama_guru' => $guru->nama_lengkap, 'jenis' => 'lihat', 'judul' => $judul]);
                RingkasanGuru::create(['guru_id' => $guru->id, 'nama_guru' => $guru->nama_lengkap]);
            });
        }

        $this->actingAs($kepsek)->get(route('kepsek.aktivitas.index'))->assertOk()->assertSee('Guru Unik A')->assertDontSee('Guru Rahasia B')->assertDontSee('Aktivitas Rahasia B');
        $this->actingAs($kepsek)->get(route('kepsek.rekap.index'))->assertOk()->assertSee('Guru Unik A')->assertDontSee('Guru Rahasia B');
        $this->actingAs($kepsek)->get(route('kepsek.rekap.detail', $guruB->id))->assertNotFound();
        $this->actingAs($kepsek)->get(route('kepsek.rekap.detail', $guruA->id))->assertOk();
        $this->actingAs($kepsek)->get(route('kepsek.peta-kelas.detail', $guruB->id))->assertStatus(503);
    }

    public function test_ptk_gelar_dan_pilihan_guru_tidak_dapat_menyilang_sekolah(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);
        $a = $this->school('10000001');
        $b = $this->school('10000002');
        $admin = User::factory()->admin()->create(['tenant_id' => $a->id]);
        $guruA = User::factory()->guru()->create(['tenant_id' => $a->id, 'nama_lengkap' => 'Guru Unik A']);
        $guruB = User::factory()->guru()->create(['tenant_id' => $b->id, 'nama_lengkap' => 'Guru Rahasia B']);
        $semester = Semester::create(['semester_id' => '20261', 'tahun_ajaran' => '2026/2027', 'nama_semester' => 'ganjil']);
        $ptkA = TenantContext::run($a->id, fn () => Ptk::create(['dapodik_id' => 'a', 'semester_id' => $semester->id, 'nama' => 'PTK Unik A', 'jenis_ptk' => 'Guru']));
        $ptkB = TenantContext::run($b->id, fn () => Ptk::create(['dapodik_id' => 'b', 'semester_id' => $semester->id, 'nama' => 'PTK Rahasia B', 'jenis_ptk' => 'Guru']));
        $jadwalB = TenantContext::run($b->id, fn () => JadwalPelajaran::create(['guru_id' => $guruB->id, 'hari' => 'Senin', 'mata_pelajaran' => 'Bahasa', 'kelas' => '1', 'jam_mulai' => '08:00', 'jam_selesai' => '09:00']));
        $ekskulB = TenantContext::run($b->id, fn () => Ekstrakurikuler::create(['nama' => 'Ekstra B', 'pembina_id' => $guruB->id]));

        $this->actingAs($admin)->get(route('admin.referensi.guru'))->assertOk()->assertSee('PTK Unik A')->assertDontSee('PTK Rahasia B');
        $this->actingAs($admin)->put(route('admin.referensi.gelar-ptk.update'), ['ptk' => [$ptkA->id => ['gelar_depan' => 'Dr'], $ptkB->id => ['gelar_depan' => 'Dr']]])->assertNotFound();
        $this->assertDatabaseHas('ptk', ['id' => $ptkA->id, 'gelar_depan' => null]);
        $this->actingAs($admin)->put(route('admin.referensi.gelar-ptk.update'), ['ptk' => [$ptkA->id => ['gelar_depan' => 'Dr']]])->assertRedirect();
        $this->assertDatabaseHas('ptk', ['id' => $ptkA->id, 'gelar_depan' => 'Dr']);
        $this->actingAs($admin)->get(route('admin.referensi.pembelajaran'))->assertOk()->assertSee('Guru Unik A')->assertDontSee('Guru Rahasia B');
        $post = ['hari' => 'Senin', 'mata_pelajaran' => 'Bahasa', 'kelas' => '1', 'jam_mulai' => '08:00', 'jam_selesai' => '09:00'];
        $this->actingAs($admin)->post(route('admin.referensi.pembelajaran.store'), $post + ['guru_id' => $guruB->id])->assertSessionHasErrors('guru_id');
        $this->flushSession();
        $this->actingAs($admin)->post(route('admin.referensi.pembelajaran.store'), $post + ['guru_id' => $guruA->id])->assertRedirect();
        $this->assertDatabaseHas('jadwal_pelajaran', ['guru_id' => $guruA->id, 'tenant_id' => $a->id]);
        $this->actingAs($admin)->delete(route('admin.referensi.pembelajaran.destroy', $jadwalB->id))->assertNotFound();
        $this->actingAs($admin)->post(route('admin.referensi.ekstrakurikuler.store'), ['nama' => 'Uji', 'pembina_id' => $guruB->id])->assertSessionHasErrors('pembina_id');
        $this->actingAs($admin)->delete(route('admin.referensi.ekstrakurikuler.destroy', $ekskulB->id))->assertNotFound();
    }

    public function test_data_lama_tidak_diklaim_dan_data_tenant_terhapus_tidak_menjadi_sekolah_tunggal(): void
    {
        config(['simantap.multi_tenant_enabled' => false]);
        $guru = User::factory()->guru()->create();
        $legacy = RingkasanGuru::create(['guru_id' => $guru->id, 'nama_guru' => 'Lama']);
        $this->assertNotNull(RingkasanGuru::find($legacy->id));
        $school = $this->school('10000001');
        $kepsek = User::factory()->kepsek()->create(['tenant_id' => $school->id]);
        $guruTenant = User::factory()->guru()->create(['tenant_id' => $school->id]);
        $new = TenantContext::run($school->id, fn () => RingkasanGuru::create(['guru_id' => $guruTenant->id, 'nama_guru' => 'Baru']));
        $this->assertNull(TenantContext::run($school->id, fn () => RingkasanGuru::find($legacy->id)));
        $this->assertNull(RingkasanGuru::find($legacy->id));
        $school->delete();
        $this->assertDatabaseHas('ringkasan_guru', ['id' => $new->id, 'tenant_id' => null, 'tenant_owned' => true]);
        $this->assertNull(RingkasanGuru::find($new->id));
        $this->actingAs($kepsek->fresh())->get(route('kepsek.aktivitas.index'))->assertForbidden();
        $this->assertNotNull(RingkasanGuru::find($legacy->id));
    }

    public function test_akun_tanpa_tenant_tidak_bisa_mengambil_konteks_melalui_header(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);
        $school = $this->school('10000001');
        $user = User::factory()->kepsek()->create();
        $this->actingAs($user)->withHeader('X-Tenant-Subdomain', $school->subdomain)
            ->get(route('kepsek.aktivitas.index'))->assertForbidden();
    }
    public function test_login_menggunakan_sekolah_akun_bukan_konteks_header(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);
        $a = $this->school('10000001');
        $b = $this->school('10000002');
        $guru = User::factory()->guru()->create(['tenant_id' => $b->id]);
        $this->actingAs($guru);
        TenantContext::run($a->id, fn () => \App\Services\AktivitasService::login('Login uji'));
        $this->assertDatabaseHas('aktivitas', ['guru_id' => $guru->id, 'tenant_id' => $b->id, 'tenant_owned' => true]);
        $this->assertSame(0, TenantContext::run($a->id, fn () => Aktivitas::where('guru_id', $guru->id)->count()));
    }

    public function test_pemindahan_akun_antar_sekolah_dan_penghapusan_sekolah_ditolak(): void
    {
        $a = $this->school('10000001');
        $b = $this->school('10000002');
        $superadmin = User::factory()->superAdmin()->create();
        $guru = User::factory()->guru()->create(['tenant_id' => $a->id]);
        $this->actingAs($superadmin)->post(route('superadmin.users.assign-tenant', $guru->id), ['tenant_id' => $b->id])->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $guru->id, 'tenant_id' => $a->id]);
        $this->actingAs($superadmin)->delete(route('superadmin.tenants.destroy', $a->uuid))->assertSessionHas('error');
        $this->assertDatabaseHas('tenants', ['id' => $a->id]);
    }

}
