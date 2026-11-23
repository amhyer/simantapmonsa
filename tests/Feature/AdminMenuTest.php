<?php

namespace Tests\Feature;

use App\Models\MataPelajaran;
use App\Models\Ptk;
use App\Models\Rombel;
use App\Models\SekolahSettings;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dapat_membuka_halaman_profile(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/profile')
            ->assertOk()
            ->assertSee('Profile Pengguna')
            ->assertSee($admin->nama_lengkap);
    }

    public function test_non_admin_ditolak_membuka_halaman_profile(): void
    {
        $guru = User::factory()->guru()->create();

        $this->actingAs($guru)->get('/admin/profile')->assertForbidden();
    }

    public function test_admin_dapat_mengubah_password_dari_profile(): void
    {
        $admin = User::factory()->admin()->create([
            'kata_sandi' => bcrypt('lama12345'),
        ]);

        $this->actingAs($admin)->from('/admin/profile')->put('/admin/profile/password', [
            'kata_sandi_saat_ini' => 'lama12345',
            'password' => 'baru12345',
            'password_confirmation' => 'baru12345',
        ])->assertRedirect('/admin/profile');

        $this->assertTrue(Hash::check('baru12345', $admin->fresh()->kata_sandi));
    }

    public function test_ubag_password_ditolak_jika_password_lama_salah(): void
    {
        $admin = User::factory()->admin()->create([
            'kata_sandi' => bcrypt('lama12345'),
        ]);

        $this->actingAs($admin)->put('/admin/profile/password', [
            'kata_sandi_saat_ini' => 'salah12345',
            'password' => 'baru12345',
            'password_confirmation' => 'baru12345',
        ])->assertSessionHasErrors('kata_sandi_saat_ini');
    }

    public function test_admin_dapat_membuka_halaman_kirim_nilai(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/dapodik/push')
            ->assertOk()
            ->assertSee('Kirim Nilai Ke Dapodik');
    }

    public function test_non_admin_ditolak_membuka_halaman_kirim_nilai(): void
    {
        $guru = User::factory()->guru()->create();

        $this->actingAs($guru)->get('/admin/dapodik/push')->assertForbidden();
    }

    public function test_sidebar_menyembunyikan_menu_tanpa_data_saat_kosong(): void
    {
        $admin = User::factory()->admin()->create();

        $htmlSetup = $this->actingAs($admin)->get('/admin/dashboard')
            ->assertOk()
            ->getContent();

        preg_match('/<nav class="sidebar-nav">(.*?)<\/nav>/s', $htmlSetup, $mSetup);
        $this->assertNotEmpty($mSetup, 'Blok sidebar-nav tab Setup tidak ditemukan.');
        $sidebarSetup = $mSetup[1];

        $this->assertStringContainsString('Dasbor', $sidebarSetup);
        $this->assertStringContainsString('Dapodik &amp; Impor Data', $sidebarSetup);
        $this->assertStringNotContainsString('Pendidik &amp; Peserta Didik', $sidebarSetup);
        $this->assertStringNotContainsString('Kelas &amp; Pembelajaran', $sidebarSetup);
        $this->assertStringNotContainsString('Data Sekolah &amp; Periode', $sidebarSetup);
    }

    /**
     * Sidebar admin: setiap grup lipat hanya memunculkan labelnya SATU kali
     * (header ringkasan saja, tanpa duplikat di dalam subitem) dan semua
     * submenu tertutup secara default (terbuka saat diklik / saat anak aktif).
     */
    public function test_sidebar_tanpa_label_ganda_dan_submenu_tertutup_default(): void
    {
        $admin = User::factory()->admin()->create();
        $sem = Semester::create([
            'semester_id' => '20261',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
        ]);
        SekolahSettings::create([
            'npsn' => '40404040',
            'nama_sekolah' => 'SD Negeri Uji Sidebar',
        ]);
        Ptk::create([
            'semester_id' => $sem->id,
            'nama' => 'Guru Uji Sidebar',
            'jenis_ptk' => 'Guru Kelas',
        ]);
        $rombel = Rombel::create([
            'dapodik_id' => (string) Str::uuid(),
            'semester_id' => $sem->id,
            'nama_rombel' => 'Kelas 1.A',
            'tingkat' => '1',
        ]);
        MataPelajaran::create([
            'kode' => 'MTK',
            'nama' => 'Matematika',
            'aktif' => true,
        ]);
        Siswa::create([
            'uuid' => (string) Str::uuid(),
            'semester_id' => $sem->id,
            'rombel_id' => $rombel->id,
            'nis' => 'SB001',
            'nama_peserta_didik' => 'Siswa Uji Sidebar',
            'kelas' => 'Kelas 1.A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);

        // Tab e-Rapor (diakses melalui admin.profile — item top-level UTAMA).
        $htmlErapor = $this->actingAs($admin)->get('/admin/profile')
            ->assertOk()
            ->getContent();

        preg_match('/<nav class="sidebar-nav">(.*?)<\/nav>/s', $htmlErapor, $mErapor);
        $this->assertNotEmpty($mErapor, 'Blok sidebar-nav tab e-Rapor tidak ditemukan.');
        $sidebarErapor = $mErapor[1];

        // Grup lipat di sidebar e-Rapor:
        // PERKEMBANGAN NILAI dicek terpisah di bawah (muncul 2x: induk + anak).
        foreach (['Pengaturan Cetak &amp; Rapor', 'Status Penilaian', 'Transkrip Ijazah', 'Cetak Nilai'] as $label) {
            $this->assertSame(1, substr_count($sidebarErapor, $label), "Label '{$label}' muncul tidak tepat 1x di sidebar e-Rapor.");
        }

        // Cermin e-Rapor live: induk "Perkembangan Nilai" + anak "Perkembangan Nilai".
        $this->assertSame(2, substr_count($sidebarErapor, 'Perkembangan Nilai'), "Label 'Perkembangan Nilai' harus muncul 2x (induk + anak).");
        $this->assertSame(0, substr_count($sidebarErapor, '<details class="nav-submenu" open'), 'Ada submenu tab e-Rapor yang terbuka by default.');

        // Tab Setup (diakses melalui admin.dashboard sejak pemindahan Dasbor ke tab Pengaturan/Setup).
        $htmlSetup = $this->actingAs($admin)->get('/admin/dashboard')
            ->assertOk()
            ->getContent();

        preg_match('/<nav class="sidebar-nav">(.*?)<\/nav>/s', $htmlSetup, $mSetup);
        $this->assertNotEmpty($mSetup, 'Blok sidebar-nav tab Setup tidak ditemukan.');
        $sidebarSetup = $mSetup[1];

        foreach (['Integrasi &amp; Impor Data', 'Data Sekolah &amp; Periode', 'Pendidik &amp; Peserta Didik', 'Kelas &amp; Pembelajaran'] as $label) {
            $this->assertSame(1, substr_count($sidebarSetup, $label), "Label '{$label}' muncul tidak tepat 1x di sidebar Setup.");
        }
        $this->assertSame(0, substr_count($sidebarSetup, '<details class="nav-submenu" open'), 'Ada submenu tab Setup yang terbuka by default.');
    }
}
