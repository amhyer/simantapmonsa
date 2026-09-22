<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    /**
     * Sidebar admin: setiap grup lipat hanya memunculkan labelnya SATU kali
     * (header ringkasan saja, tanpa duplikat di dalam subitem) dan semua
     * submenu tertutup secara default (terbuka saat diklik / saat anak aktif).
     */
    public function test_sidebar_tanpa_label_ganda_dan_submenu_tertutup_default(): void
    {
        $admin = User::factory()->admin()->create();

        $html = $this->actingAs($admin)->get('/admin/dashboard')
            ->assertOk()
            ->getContent();

        preg_match('/<nav class="sidebar-nav">(.*?)<\/nav>/s', $html, $m);
        $this->assertNotEmpty($m, 'Blok sidebar-nav tidak ditemukan.');
        $sidebar = $m[1];

        foreach (['Data Referensi', 'Status Penilaian', 'Perkembangan Nilai', 'Transkrip Ijazah', 'Cetak Nilai'] as $label) {
            $this->assertSame(1, substr_count($sidebar, $label), "Label '{$label}' muncul lebih dari sekali di sidebar.");
        }

        $this->assertSame(0, substr_count($sidebar, '<details class="nav-submenu" open'), 'Ada submenu yang terbuka by default.');
    }
}
