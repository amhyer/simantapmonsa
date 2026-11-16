<?php

namespace Tests\Feature;

use App\Models\JadwalPelajaran;
use App\Models\Materi;
use App\Models\Rombel;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SiswaMateriFilterTest extends TestCase
{
    use RefreshDatabase;

    private function makeSemester(): Semester
    {
        return Semester::firstOrCreate(
            ['semester_id' => '20261'],
            ['tahun_ajaran' => '2026/2027', 'nama_semester' => 'ganjil']
        );
    }

    public function test_siswa_melihat_materi_dari_guru_mapel_sesuai_jadwal_dan_wali_kelas_tanpa_bocor_kelas_lain(): void
    {
        $semester = $this->makeSemester();
        $guruWali = User::factory()->guru()->create();
        $guruMapelA = User::factory()->guru()->create();
        $guruMapelLain = User::factory()->guru()->create();

        $rombel6A = Rombel::create([
            'dapodik_id' => (string) Str::uuid(),
            'semester_id' => $semester->id,
            'nama_rombel' => 'Kelas 6A',
            'tingkat' => 6,
            'guru_id' => $guruWali->id,
        ]);

        $userSiswa = User::factory()->siswa()->create(['nisn' => '1234567890']);
        Siswa::create([
            'user_id' => $userSiswa->id,
            'guru_id' => $guruWali->id,
            'rombel_id' => $rombel6A->id,
            'nama_peserta_didik' => 'Budi Santoso',
            'nisn' => '1234567890',
            'kelas' => 'Kelas 6A',
            'aktif' => true,
        ]);

        // Jadwal: Guru Mapel A mengajar Matematika di Kelas 6A.
        JadwalPelajaran::create([
            'rombel_id' => $rombel6A->id,
            'guru_id' => $guruMapelA->id,
            'kelas' => 'Kelas 6A',
            'mata_pelajaran' => 'Matematika',
            'hari' => 'Senin',
            'jam_mulai' => '07:30',
            'jam_selesai' => '09:00',
        ]);

        // 1. HARUS TAMPIL: Materi Matematika kelas 6A oleh Guru Mapel A (case-insensitive).
        $materiMapelValid = Materi::create([
            'guru_id' => $guruMapelA->id,
            'judul' => 'Pecahan Senilai 6A',
            'mata_pelajaran' => 'matematika',
            'kelas' => 'kelas 6a',
            'tanggal' => now()->toDateString(),
        ]);

        // 2. HARUS TAMPIL: Materi oleh Wali Kelas pada Kelas 6A.
        $materiWaliValid = Materi::create([
            'guru_id' => $guruWali->id,
            'judul' => 'Bahasa Indonesia 6A',
            'mata_pelajaran' => 'Bahasa Indonesia',
            'kelas' => 'Kelas 6A',
            'tanggal' => now()->toDateString(),
        ]);

        // 3. TIDAK TAMPIL: Mapel berbeda oleh Guru Mapel A (tidak dijadwalkan di 6A).
        $materiMapelBeda = Materi::create([
            'guru_id' => $guruMapelA->id,
            'judul' => 'IPS Rahasia 6A',
            'mata_pelajaran' => 'IPS',
            'kelas' => 'Kelas 6A',
            'tanggal' => now()->toDateString(),
        ]);

        // 4. TIDAK TAMPIL: Kelas berbeda (6B) oleh Guru Mapel A.
        $materiKelasBeda = Materi::create([
            'guru_id' => $guruMapelA->id,
            'judul' => 'Pecahan Kelas 6B',
            'mata_pelajaran' => 'Matematika',
            'kelas' => 'Kelas 6B',
            'tanggal' => now()->toDateString(),
        ]);

        // 5. TIDAK TAMPIL: Guru lain yang tidak dijadwalkan di 6A dan bukan wali kelas.
        $materiGuruLain = Materi::create([
            'guru_id' => $guruMapelLain->id,
            'judul' => 'Materi Guru Tidak Terjadwal',
            'mata_pelajaran' => 'Matematika',
            'kelas' => 'Kelas 6A',
            'tanggal' => now()->toDateString(),
        ]);

        $response = $this->actingAs($userSiswa)->get(route('siswa.materi.index'));
        $response->assertOk();
        $response->assertSee($materiMapelValid->judul);
        $response->assertSee($materiWaliValid->judul);
        $response->assertDontSee($materiMapelBeda->judul);
        $response->assertDontSee($materiKelasBeda->judul);
        $response->assertDontSee($materiGuruLain->judul);

        // Detail: materi valid -> 200, materi tidak valid -> 404, non-angka -> 404.
        $this->actingAs($userSiswa)->get(route('siswa.materi.show', $materiMapelValid->id))->assertOk();
        $this->actingAs($userSiswa)->get(route('siswa.materi.show', $materiWaliValid->id))->assertOk();
        $this->actingAs($userSiswa)->get(route('siswa.materi.show', $materiMapelBeda->id))->assertNotFound();
        $this->actingAs($userSiswa)->get(route('siswa.materi.show', $materiKelasBeda->id))->assertNotFound();
        $this->actingAs($userSiswa)->get(route('siswa.materi.show', $materiGuruLain->id))->assertNotFound();
        $this->actingAs($userSiswa)->get('/siswa/materi/bukan-angka')->assertNotFound();
    }

    public function test_materi_terisolasi_antar_tenant(): void
    {
        $semester = $this->makeSemester();
        $tenantA = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SDN Alpha',
            'subdomain' => 'sdn-alpha',
            'aktif' => true,
        ]);
        $tenantB = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SDN Beta',
            'subdomain' => 'sdn-beta',
            'aktif' => true,
        ]);

        $guruA = User::factory()->guru()->create(['tenant_id' => $tenantA->id]);
        $guruB = User::factory()->guru()->create(['tenant_id' => $tenantB->id]);

        $rombelA = Rombel::create([
            'tenant_id' => $tenantA->id,
            'dapodik_id' => (string) Str::uuid(),
            'semester_id' => $semester->id,
            'nama_rombel' => 'Kelas 6A',
            'tingkat' => 6,
            'guru_id' => $guruA->id,
        ]);
        $userSiswaA = User::factory()->siswa()->create(['tenant_id' => $tenantA->id, 'nisn' => '5555566666']);
        Siswa::create([
            'tenant_id' => $tenantA->id,
            'user_id' => $userSiswaA->id,
            'guru_id' => $guruA->id,
            'rombel_id' => $rombelA->id,
            'nama_peserta_didik' => 'Siswa Alpha',
            'nisn' => '5555566666',
            'kelas' => 'Kelas 6A',
            'aktif' => true,
        ]);

        $materiTenantA = Materi::create([
            'tenant_id' => $tenantA->id,
            'guru_id' => $guruA->id,
            'judul' => 'Materi Sekolah Alpha',
            'mata_pelajaran' => 'IPAS',
            'kelas' => 'Kelas 6A',
            'tanggal' => now()->toDateString(),
        ]);

        $materiTenantB = Materi::create([
            'tenant_id' => $tenantB->id,
            'guru_id' => $guruB->id,
            'judul' => 'Materi Sekolah Beta Rahasia',
            'mata_pelajaran' => 'IPAS',
            'kelas' => 'Kelas 6A',
            'tanggal' => now()->toDateString(),
        ]);

        TenantContext::clear();

        $this->actingAs($userSiswaA)
            ->get(route('siswa.materi.index'))
            ->assertOk()
            ->assertSee($materiTenantA->judul)
            ->assertDontSee($materiTenantB->judul);

        $this->actingAs($userSiswaA)
            ->get(route('siswa.materi.show', $materiTenantB->id))
            ->assertNotFound();
    }
}
