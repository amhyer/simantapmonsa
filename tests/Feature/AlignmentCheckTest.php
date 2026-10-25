<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresi bug "Class App\Services\JadwalPelajaranResolver not found" dan
 * "View [admin.referensi.alignment-check] not found" — halaman Cek Alignment
 * sebelumnya melempar HTTP 500.
 */
class AlignmentCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dapat_membuka_halaman_cek_alignment(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.referensi.alignment'))
            ->assertOk()
            ->assertSee('Cek Alignment')
            ->assertSee(route('admin.referensi.alignment.refresh'))
            ->assertSee(route('admin.referensi.alignment.export'));
    }

    public function test_guest_dialihkan_ke_login(): void
    {
        $this->get(route('admin.referensi.alignment'))->assertRedirect(route('login'));
    }

    public function test_non_admin_ditolak(): void
    {
        $guru = User::factory()->guru()->create();

        $this->actingAs($guru)->get(route('admin.referensi.alignment'))->assertForbidden();
    }
}
