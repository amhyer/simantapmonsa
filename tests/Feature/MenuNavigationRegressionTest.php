<?php

namespace Tests\Feature;

use App\Models\DapodikSyncLog;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use App\Services\Dapodik\SidebarSyncStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class MenuNavigationRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_status_sinkron_terpisah_berdasarkan_tenant_bukan_npsn(): void
    {
        Cache::flush();
        config(['simantap.multi_tenant_enabled' => true]);
        $a = Tenant::create(['uuid' => Str::uuid(), 'nama_sekolah' => 'A', 'npsn' => '10000001', 'aktif' => true]);
        $b = Tenant::create(['uuid' => Str::uuid(), 'nama_sekolah' => 'B', 'npsn' => '10000002', 'aktif' => true]);
        $tanpaNpsn = Tenant::create(['uuid' => Str::uuid(), 'nama_sekolah' => 'C', 'aktif' => true]);
        TenantContext::run($a->id, fn () => DapodikSyncLog::create(['tipe' => 'siswa', 'sekolah_npsn' => $b->npsn, 'berhasil' => 11]));
        TenantContext::run($b->id, fn () => DapodikSyncLog::create(['tipe' => 'siswa', 'sekolah_npsn' => null, 'berhasil' => 22]));
        DapodikSyncLog::create(['tipe' => 'siswa', 'sekolah_npsn' => null, 'berhasil' => 33]);

        $this->assertSame(11, TenantContext::run($a->id, fn () => SidebarSyncStatus::latest()['berhasil']));
        $this->assertSame(22, TenantContext::run($b->id, fn () => SidebarSyncStatus::latest()['berhasil']));
        $this->assertSame(11, TenantContext::run($a->id, fn () => SidebarSyncStatus::latest()['berhasil']));
        $this->assertNull(TenantContext::run($tanpaNpsn->id, fn () => SidebarSyncStatus::latest()));
        $this->assertNull(SidebarSyncStatus::latest());
    }

    public function test_status_sekolah_tunggal_tetap_membaca_log_lama(): void
    {
        Cache::flush();
        config(['simantap.multi_tenant_enabled' => false]);
        DapodikSyncLog::create(['tipe' => 'siswa', 'berhasil' => 7]);
        $this->assertSame(7, SidebarSyncStatus::latest()['berhasil']);
    }

    public function test_menu_induk_aktif_pada_detail_tanpa_mengaktifkan_menu_saudara(): void
    {
        $request = Request::create('/test');
        app()->instance('request', $request);
        $route = (new RoutingRoute('GET', '/test', fn () => null))->name('siswa.materi.show');
        $request->setRouteResolver(function () use (&$route) { return $route; });
        $this->assertTrue(sidebarRouteActive('siswa.materi.index'));
        $this->assertFalse(sidebarRouteActive('siswa.kuis.index'));

        $route = (new RoutingRoute('GET', '/test', fn () => null))->name('kepsek.rekap.detail');
        $this->assertTrue(sidebarRouteActive('kepsek.rekap.index'));
        $route = (new RoutingRoute('GET', '/test', fn () => null))->name('superadmin.tenants.show');
        $this->assertTrue(sidebarRouteActive('superadmin.tenants.index'));
        $route = (new RoutingRoute('GET', '/test', fn () => null))->name('siswa.profil.edit');
        $this->assertTrue(sidebarRouteActive('siswa.profil.show'));
    }

    public function test_menu_tka_mandiri_tetap_ada_saat_cbt_nonaktif(): void
    {
        config(['simantap.cbt_enabled' => false]);
        $guru = view('guru.partials.sidebar')->render();
        $siswa = view('siswa.partials.sidebar')->render();
        $ortu = view('ortu.partials.sidebar')->render();

        $this->assertStringContainsString('Monitoring Kesiapan TKA', $guru);
        $this->assertStringContainsString('Analisis Kognitif Kelas', $guru);
        $this->assertStringNotContainsString('Sesi Ujian CBT', $guru);
        $this->assertStringNotContainsString('Dimensi &amp; Kisi-kisi TKA', $guru);
        $this->assertStringContainsString('Simulasi TKA Pusmendik', $siswa);
        $this->assertStringNotContainsString('Ujian CBT', $siswa);
        $this->assertStringNotContainsString('Laporan TKA', $ortu);
    }

    public function test_menu_asesmen_muncul_saat_flag_aktif(): void
    {
        // Route CBT didaftarkan saat aplikasi boot, sehingga tes flag yang
        // berubah setelah boot menyediakan route bernama apabila belum ada.
        foreach (['cbt.sessions.index', 'cbt.question-banks.index', 'cbt.student.index'] as $name) {
            if (! Route::has($name)) {
                Route::get('/__test-menu/'.str_replace('.', '-', $name), fn () => '')->name($name);
            }
        }
        config(['simantap.cbt_enabled' => true]);
        $guru = view('guru.partials.sidebar')->render();
        $siswa = view('siswa.partials.sidebar')->render();
        $ortu = view('ortu.partials.sidebar')->render();
        $this->assertStringContainsString('Sesi Ujian CBT', $guru);
        $this->assertStringContainsString('Dimensi &amp; Kisi-kisi TKA', $guru);
        $this->assertStringContainsString('Ujian CBT', $siswa);
        $this->assertStringContainsString('Simulasi TKA Pusmendik', $siswa);
        $this->assertStringContainsString('Laporan TKA', $ortu);
    }

    public function test_sidebar_semua_role_dapat_dirender_ketika_asesmen_nonaktif(): void
    {
        config(['simantap.cbt_enabled' => false]);
        foreach ([
            'superadmin.partials.sidebar' => 'Dasbor Platform',
            'admin.partials.sidebar' => 'Dasbor',
            'guru.partials.sidebar' => 'Materi Ajar',
            'siswa.partials.sidebar' => 'Profil Saya',
            'ortu.partials.sidebar' => 'Ringkasan',
            'kepsek.partials.sidebar' => 'Rekap Sekolah',
            'pegawai.partials.sidebar' => 'Kartu Identitas Saya',
        ] as $partial => $label) {
            $this->assertStringContainsString($label, view($partial)->render(), $partial);
        }
    }

    public function test_tautan_tambah_sekolah_membuka_form_dan_dibatasi_role(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $this->actingAs($superAdmin)->get(route('superadmin.tenants.index', ['tambah' => 1]))
            ->assertOk()
            ->assertSee('id="tambah" style="margin-bottom:20px" open', false);
        $guru = User::factory()->guru()->create();
        $this->actingAs($guru)->get(route('superadmin.tenants.index'))->assertForbidden();
    }
}
