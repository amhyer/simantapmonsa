<?php

namespace Tests\Feature;

use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FotoSiswaPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_foto_siswa_paginates_120_students_into_50_50_20_and_adds_lazy_loading(): void
    {
        $admin = User::factory()->create([
            'peran' => 'admin',
            'aktif' => true,
            'force_password_change' => false,
        ]);

        for ($i = 1; $i <= 120; $i++) {
            Siswa::create([
                'nis' => '2026'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'nisn' => '0012'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'nama_peserta_didik' => 'Siswa '.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'jenis_kelamin' => $i % 2 === 0 ? 'P' : 'L',
                'kelas' => $i <= 60 ? '4A' : '4B',
                'foto' => $i === 1 ? 'foto-siswa/contoh.jpg' : null,
                'aktif' => true,
            ]);
        }

        // Halaman 1: 50 siswa + loading="lazy" pada foto
        $p1 = $this->actingAs($admin)->get(route('admin.referensi.foto-siswa'))->assertOk();
        $p1->assertViewHas('daftar', fn ($paginator) => $paginator->count() === 50 && $paginator->total() === 120);
        $p1->assertSee('loading="lazy"', false)
            ->assertSee('Siswa 001')
            ->assertSee('Siswa 050')
            ->assertDontSee('Siswa 051');

        // Halaman 2: 50 siswa
        $p2 = $this->actingAs($admin)->get(route('admin.referensi.foto-siswa', ['page' => 2]))->assertOk();
        $p2->assertViewHas('daftar', fn ($paginator) => $paginator->count() === 50);
        $p2->assertSee('Siswa 051')
            ->assertSee('Siswa 100')
            ->assertDontSee('Siswa 101');

        // Halaman 3: 20 siswa
        $p3 = $this->actingAs($admin)->get(route('admin.referensi.foto-siswa', ['page' => 3]))->assertOk();
        $p3->assertViewHas('daftar', fn ($paginator) => $paginator->count() === 20);
        $p3->assertSee('Siswa 101')
            ->assertSee('Siswa 120');

        // Filter kelas 4A (60 siswa -> 50 di halaman 1, query string kelas=4A dipertahankan)
        $filtered = $this->actingAs($admin)->get(route('admin.referensi.foto-siswa', ['kelas' => '4A']))->assertOk();
        $filtered->assertViewHas('daftar', fn ($paginator) => $paginator->count() === 50 && $paginator->total() === 60);
        $filtered->assertSee('kelas=4A', false)
            ->assertDontSee('Siswa 061');
    }

    public function test_foto_siswa_isolates_other_tenants_and_blocks_non_admin(): void
    {
        $tenantA = Tenant::create(['npsn' => '61001111', 'nama_sekolah' => 'Sekolah Foto A', 'subdomain' => 'foto-a', 'aktif' => true]);
        $tenantB = Tenant::create(['npsn' => '61002222', 'nama_sekolah' => 'Sekolah Foto B', 'subdomain' => 'foto-b', 'aktif' => true]);

        $adminA = User::factory()->create([
            'peran' => 'admin',
            'tenant_id' => $tenantA->id,
            'tenant_owned' => true,
            'aktif' => true,
            'force_password_change' => false,
        ]);
        $guruA = User::factory()->create([
            'peran' => 'guru',
            'tenant_id' => $tenantA->id,
            'tenant_owned' => true,
            'aktif' => true,
            'force_password_change' => false,
        ]);

        Siswa::create([
            'tenant_id' => $tenantA->id,
            'nis' => '61000001',
            'nama_peserta_didik' => 'Siswa Foto Tenant A',
            'jenis_kelamin' => 'L',
            'kelas' => '5A',
            'aktif' => true,
        ]);
        Siswa::create([
            'tenant_id' => $tenantB->id,
            'nis' => '61000002',
            'nama_peserta_didik' => 'Siswa Foto Tenant B',
            'jenis_kelamin' => 'P',
            'kelas' => '5A',
            'aktif' => true,
        ]);

        $this->actingAs($adminA)
            ->get(route('admin.referensi.foto-siswa'))
            ->assertOk()
            ->assertSee('Siswa Foto Tenant A')
            ->assertDontSee('Siswa Foto Tenant B');

        $this->actingAs($guruA)
            ->get(route('admin.referensi.foto-siswa'))
            ->assertForbidden();
    }

    public function test_foto_siswa_query_count_does_not_grow_with_student_count(): void
    {
        $admin = User::factory()->create([
            'peran' => 'admin',
            'aktif' => true,
            'force_password_change' => false,
        ]);

        for ($i = 1; $i <= 10; $i++) {
            Siswa::create([
                'nis' => '7100'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'nama_peserta_didik' => "Siswa Q {$i}",
                'jenis_kelamin' => 'L',
                'kelas' => '3A',
                'aktif' => true,
            ]);
        }

        // Warm-up cache layout
        $this->actingAs($admin)->get(route('admin.referensi.foto-siswa'))->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)->get(route('admin.referensi.foto-siswa'))->assertOk();
        $queries10 = count(DB::getQueryLog());
        DB::disableQueryLog();

        for ($i = 11; $i <= 60; $i++) {
            Siswa::create([
                'nis' => '7100'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'nama_peserta_didik' => "Siswa Q {$i}",
                'jenis_kelamin' => 'P',
                'kelas' => '3A',
                'aktif' => true,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)->get(route('admin.referensi.foto-siswa'))->assertOk();
        $queries60 = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($queries10, $queries60, "Query bertumbuh dari {$queries10} (10 siswa) menjadi {$queries60} (60 siswa).");
        fwrite(STDOUT, "FOTO_SISWA_QUERIES: 10_siswa={$queries10}, 60_siswa={$queries60}\n");
    }
}
