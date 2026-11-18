<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\E2eFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DataReferensiGuruPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(E2eFixtureSeeder::class);
    }

    public function test_store_non_dapodik_guru_generates_unique_random_password_and_forces_change(): void
    {
        $admin = User::findOrFail(E2eFixtureSeeder::USER_IDS['admin']);

        $res1 = $this->actingAs($admin)->post(route('admin.referensi.guru.store'), [
            'nama' => 'Guru Acak Satu',
            'jenis_ptk' => 'Guru Kelas',
            'buat_akun' => '1',
            'nama_pengguna' => 'guru.acak.1',
        ]);
        $res1->assertRedirect(route('admin.referensi.guru'));
        $res1->assertSessionHas('success');
        $msg1 = (string) session('success');
        $this->assertStringContainsString('Kata sandi awal (tampil sekali):', $msg1);

        $res2 = $this->actingAs($admin)->post(route('admin.referensi.guru.store'), [
            'nama' => 'Guru Acak Dua',
            'jenis_ptk' => 'Guru Mapel',
            'buat_akun' => '1',
            'nama_pengguna' => 'guru.acak.2',
        ]);
        $res2->assertRedirect(route('admin.referensi.guru'));
        $msg2 = (string) session('success');
        $this->assertNotSame($msg1, $msg2);

        $user1 = User::where('nama_pengguna', 'guru.acak.1')->firstOrFail();
        $user2 = User::where('nama_pengguna', 'guru.acak.2')->firstOrFail();

        $this->assertTrue((bool) $user1->force_password_change);
        $this->assertTrue((bool) $user2->force_password_change);
        $this->assertFalse(Hash::check('guru123', $user1->kata_sandi));
        $this->assertFalse(Hash::check('guru123', $user2->kata_sandi));
    }
}
