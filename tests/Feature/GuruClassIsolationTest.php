<?php

namespace Tests\Feature;

use App\Models\JadwalPelajaran;
use App\Models\MataPelajaran;
use App\Models\PembelajaranMendalam\CapaianPembelajaran;
use App\Models\PembelajaranMendalam\KokurikulerPm;
use App\Models\Rombel;
use App\Models\Semester;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuruClassIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Semester $semester;

    private User $guruKelas1;

    private User $guruKelas6;

    private User $guruMapelPjok;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SDN Isolasi Kelas',
            'subdomain' => 'sdn-isolasi-kelas',
            'aktif' => true,
        ]);
        TenantContext::set($this->tenant->id);

        $this->semester = Semester::create([
            'semester_id' => '20261',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
        ]);

        MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK', 'aktif' => true, 'urutan' => 1]);
        MataPelajaran::create(['nama' => 'PJOK', 'kode' => 'PJOK', 'aktif' => true, 'urutan' => 2]);

        $this->guruKelas1 = User::factory()->guru()->create(['tenant_id' => $this->tenant->id, 'is_wali_kelas' => true]);
        $this->guruKelas6 = User::factory()->guru()->create(['tenant_id' => $this->tenant->id, 'is_wali_kelas' => true]);
        $this->guruMapelPjok = User::factory()->guru()->create(['tenant_id' => $this->tenant->id, 'is_wali_kelas' => false]);

        Rombel::create([
            'tenant_id' => $this->tenant->id,
            'dapodik_id' => (string) Str::uuid(),
            'semester_id' => $this->semester->id,
            'nama_rombel' => '1A',
            'tingkat' => 1,
            'guru_id' => $this->guruKelas1->id,
        ]);
        $rombel4 = Rombel::create([
            'tenant_id' => $this->tenant->id,
            'dapodik_id' => (string) Str::uuid(),
            'semester_id' => $this->semester->id,
            'nama_rombel' => '4A',
            'tingkat' => 4,
            'guru_id' => null,
        ]);
        Rombel::create([
            'tenant_id' => $this->tenant->id,
            'dapodik_id' => (string) Str::uuid(),
            'semester_id' => $this->semester->id,
            'nama_rombel' => '6A',
            'tingkat' => 6,
            'guru_id' => $this->guruKelas6->id,
        ]);

        // Guru Mapel PJOK hanya mengajar PJOK di Kelas 4A
        JadwalPelajaran::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $this->guruMapelPjok->id,
            'rombel_id' => $rombel4->id,
            'kelas' => '4A',
            'mata_pelajaran' => 'PJOK',
            'hari' => 'Selasa',
            'jam_mulai' => '07:30',
            'jam_selesai' => '08:40',
        ]);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_guru_kelas_1_tidak_dapat_mengakses_atau_mengubah_kelas_6(): void
    {
        // Boleh mengakses Kelas 1 & Fase A
        $this->actingAs($this->guruKelas1)->get(route('guru.pm.prota.index', ['tingkat' => 1]))->assertOk();
        $this->actingAs($this->guruKelas1)->get(route('guru.pm.prosem.index', ['tingkat' => 1]))->assertOk();
        $this->actingAs($this->guruKelas1)->get(route('guru.pm.kktp.index', ['tingkat' => 1]))->assertOk();
        $this->actingAs($this->guruKelas1)->get(route('guru.pm.atp.index', ['fase' => 'A']))->assertOk();

        // Ditolak 403 bila mencoba mengakses Kelas 6 atau Fase C
        $this->actingAs($this->guruKelas1)->get(route('guru.pm.prota.index', ['tingkat' => 6]))->assertForbidden();
        $this->actingAs($this->guruKelas1)->get(route('guru.pm.prosem.index', ['tingkat' => 6]))->assertForbidden();
        $this->actingAs($this->guruKelas1)->get(route('guru.pm.kktp.index', ['tingkat' => 6]))->assertForbidden();
        $this->actingAs($this->guruKelas1)->get(route('guru.pm.buku-paket.index', ['tingkat' => 6]))->assertForbidden();
        $this->actingAs($this->guruKelas1)->get(route('guru.pm.atp.index', ['fase' => 'C']))->assertForbidden();

        // Ditolak 403 bila mencoba mengubah konteks / menyimpan data ke Kelas 6
        $this->actingAs($this->guruKelas1)->post(route('guru.pm.konteks.simpan'), [
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
            'tingkat' => 6,
        ])->assertForbidden();

        $this->actingAs($this->guruKelas1)->post(route('guru.pm.kokurikuler.store'), [
            'tahun_ajaran' => '2026/2027',
            'tingkat' => 6,
            'tema_judul' => 'Coba Ganggu Kelas 6',
            'bentuk_kegiatan' => 'lainnya',
        ])->assertForbidden();

        // Ditolak 403 bila mencoba menghapus CP atau Kokurikuler milik Guru Kelas 6 (strict ownership default)
        $cp6 = CapaianPembelajaran::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $this->guruKelas6->id,
            'nama_mapel' => 'Matematika',
            'fase' => 'C',
            'elemen' => 'Bilangan',
            'deskripsi' => 'CP Milik Guru Kelas 6',
        ]);
        $koku6 = KokurikulerPm::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $this->guruKelas6->id,
            'tahun_ajaran' => '2026/2027',
            'tingkat' => 6,
            'tema_judul' => 'Proyek Kelas 6',
        ]);

        $this->actingAs($this->guruKelas1)->delete(route('guru.pm.cp.destroy', $cp6))->assertForbidden();
        $this->actingAs($this->guruKelas1)->delete(route('guru.pm.kokurikuler.destroy', $koku6))->assertForbidden();
    }

    public function test_guru_mapel_pjok_hanya_bisa_mengakses_pjok_kelas_4(): void
    {
        // Boleh mengakses PJOK di Kelas 4 (Fase B)
        $this->actingAs($this->guruMapelPjok)
            ->get(route('guru.pm.prota.index', ['tingkat' => 4, 'mapel' => 'PJOK']))
            ->assertOk();

        // Ditolak 403 bila mencoba mengakses Matematika atau Kelas 1
        $this->actingAs($this->guruMapelPjok)
            ->get(route('guru.pm.prota.index', ['tingkat' => 4, 'mapel' => 'Matematika']))
            ->assertForbidden();

        $this->actingAs($this->guruMapelPjok)
            ->get(route('guru.pm.prota.index', ['tingkat' => 1, 'mapel' => 'PJOK']))
            ->assertForbidden();
    }
}
