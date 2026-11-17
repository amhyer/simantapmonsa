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

    private function makeMateri(User $guru, array $attrs): Materi
    {
        return Materi::create(array_merge([
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'tanggal' => now()->toDateString(),
            'tujuan_pembelajaran' => 'Memahami konsep inti pelajaran.',
            'materi_pokok' => 'Ringkasan materi pembelajaran.',
        ], $attrs));
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

        $siswa = Siswa::create([
            'guru_id' => $guruWali->id,
            'nama_guru' => $guruWali->nama_lengkap,
            'rombel_id' => $rombel6A->id,
            'nis' => 'NIS-6A-001',
            'nisn' => '1234567890',
            'nama_peserta_didik' => 'Budi Santoso',
            'kelas' => 'Kelas 6A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);

        $userSiswa = User::factory()->siswa()->create([
            'terhubung_dengan' => [$siswa->id],
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
        $materiMapelValid = $this->makeMateri($guruMapelA, [
            'judul' => 'Pecahan Senilai 6A',
            'mata_pelajaran' => 'matematika',
            'kelas' => 'kelas 6a',
        ]);

        // 2. HARUS TAMPIL: Materi oleh Wali Kelas pada Kelas 6A.
        $materiWaliValid = $this->makeMateri($guruWali, [
            'judul' => 'Bahasa Indonesia 6A',
            'mata_pelajaran' => 'Bahasa Indonesia',
            'kelas' => 'Kelas 6A',
        ]);

        // 3. TIDAK TAMPIL: Mapel berbeda oleh Guru Mapel A (tidak dijadwalkan di 6A).
        $materiMapelBeda = $this->makeMateri($guruMapelA, [
            'judul' => 'IPS Rahasia 6A',
            'mata_pelajaran' => 'IPS',
            'kelas' => 'Kelas 6A',
        ]);

        // 4. TIDAK TAMPIL: Kelas berbeda (6B) oleh Guru Mapel A.
        $materiKelasBeda = $this->makeMateri($guruMapelA, [
            'judul' => 'Pecahan Kelas 6B',
            'mata_pelajaran' => 'Matematika',
            'kelas' => 'Kelas 6B',
        ]);

        // 5. TIDAK TAMPIL: Guru lain yang tidak dijadwalkan di 6A dan bukan wali kelas.
        $materiGuruLain = $this->makeMateri($guruMapelLain, [
            'judul' => 'Materi Guru Tidak Terjadwal',
            'mata_pelajaran' => 'Matematika',
            'kelas' => 'Kelas 6A',
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

        $siswaA = Siswa::create([
            'tenant_id' => $tenantA->id,
            'guru_id' => $guruA->id,
            'nama_guru' => $guruA->nama_lengkap,
            'rombel_id' => $rombelA->id,
            'nis' => 'NIS-ALPHA-01',
            'nisn' => '5555566666',
            'nama_peserta_didik' => 'Siswa Alpha',
            'kelas' => 'Kelas 6A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);

        $userSiswaA = User::factory()->siswa()->create([
            'tenant_id' => $tenantA->id,
            'terhubung_dengan' => [$siswaA->id],
        ]);

        $materiTenantA = $this->makeMateri($guruA, [
            'tenant_id' => $tenantA->id,
            'judul' => 'Materi Sekolah Alpha',
            'mata_pelajaran' => 'IPAS',
            'kelas' => 'Kelas 6A',
        ]);

        $materiTenantB = $this->makeMateri($guruB, [
            'tenant_id' => $tenantB->id,
            'judul' => 'Materi Sekolah Beta Rahasia',
            'mata_pelajaran' => 'IPAS',
            'kelas' => 'Kelas 6A',
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
