<?php

namespace Tests\Feature;

use App\Models\JadwalPelajaran;
use App\Models\MataPelajaran;
use App\Models\PembelajaranMendalam\CapaianPembelajaran;
use App\Models\PembelajaranMendalam\KokurikulerPm;
use App\Models\PembelajaranMendalam\ProsemItem;
use App\Models\PembelajaranMendalam\ProtaItem;
use App\Models\PembelajaranMendalam\SupervisiPm;
use App\Models\PembelajaranMendalam\TujuanPembelajaranPm;
use App\Models\PengaturanGuru;
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

    public function test_guru_kelas_1_ditolak_membuat_cp_dan_tp_fase_c(): void
    {
        $this->actingAs($this->guruKelas1)->post(route('guru.pm.cp.store'), [
            'nama_mapel' => 'Matematika',
            'fase' => 'C',
            'elemen' => 'Aljabar',
            'deskripsi' => 'CP Fase C ilegal bagi Guru Kelas 1',
        ])->assertForbidden();

        $this->actingAs($this->guruKelas1)->post(route('guru.pm.tp.store'), [
            'nama_mapel' => 'Matematika',
            'fase' => 'C',
            'elemen' => 'Aljabar',
            'deskripsi' => 'TP Fase C ilegal bagi Guru Kelas 1',
        ])->assertForbidden();

        $this->actingAs($this->guruKelas1)->post(route('guru.pm.kktp.interval'), [
            'nama_mapel' => 'Matematika',
            'tingkat' => 6,
            'batas_bawah' => 60,
            'nilai_kktp' => 75,
            'batas_atas' => 88,
        ])->assertForbidden();
    }

    public function test_guru_kelas_paralel_1a_dan_1b_tidak_saling_menimpa_prota_dan_prosem(): void
    {
        $guruKelas1B = User::factory()->guru()->create(['tenant_id' => $this->tenant->id, 'is_wali_kelas' => true]);
        Rombel::create([
            'tenant_id' => $this->tenant->id,
            'dapodik_id' => (string) Str::uuid(),
            'semester_id' => $this->semester->id,
            'nama_rombel' => '1B',
            'tingkat' => 1,
            'guru_id' => $guruKelas1B->id,
        ]);

        // Buat TP Fase A untuk acuan ATP
        $tp = TujuanPembelajaranPm::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $this->guruKelas1->id,
            'nama_mapel' => 'Matematika',
            'fase' => 'A',
            'elemen' => 'Bilangan',
            'kode_tp' => 'TP.1',
            'deskripsi' => 'Mengenal bilangan 1-20',
            'kelas_target' => 1,
            'semester_target' => 1,
            'materi_pokok' => 'Bilangan Cacah 1-20',
            'alokasi_jp' => 8,
            'urutan' => 1,
        ]);

        // Guru 1B sudah punya Prota & Prosem sendiri untuk Kelas 1
        $prota1B = ProtaItem::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $guruKelas1B->id,
            'tahun_ajaran' => '2026/2027',
            'tingkat' => 1,
            'nama_mapel' => 'Matematika',
            'semester' => 1,
            'tp_id' => $tp->id,
            'urutan' => 1,
            'materi' => 'Materi Khusus Guru 1B',
            'alokasi_jp' => 8,
        ]);
        $prosem1B = ProsemItem::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $guruKelas1B->id,
            'tahun_ajaran' => '2026/2027',
            'tingkat' => 1,
            'nama_mapel' => 'Matematika',
            'semester' => 1,
            'prota_id' => $prota1B->id,
            'tp_id' => $tp->id,
            'bab' => 'Bab 1',
            'materi' => 'Materi Khusus Guru 1B',
            'bulan' => 7,
            'minggu_ke' => 3,
            'alokasi_jp' => 4,
        ]);

        // Guru 1A menjalankan Ambil dari ATP & Susun Prosem Otomatis untuk Kelas 1
        $this->actingAs($this->guruKelas1)->post(route('guru.pm.prota.ambil-atp'), [
            'tahun_ajaran' => '2026/2027',
            'tingkat' => 1,
            'nama_mapel' => 'Matematika',
        ])->assertRedirect();

        $this->actingAs($this->guruKelas1)->post(route('guru.pm.prosem.susun'), [
            'tahun_ajaran' => '2026/2027',
            'tingkat' => 1,
            'nama_mapel' => 'Matematika',
            'semester' => 1,
            'jp_per_minggu' => 4,
        ])->assertRedirect();

        // Pastikan Prota & Prosem milik Guru 1B tetap utuh dan tidak terhapus!
        $this->assertDatabaseHas('pm_prota', [
            'id' => $prota1B->id,
            'guru_id' => $guruKelas1B->id,
            'materi' => 'Materi Khusus Guru 1B',
        ]);
        $this->assertDatabaseHas('pm_prosem', [
            'id' => $prosem1B->id,
            'guru_id' => $guruKelas1B->id,
            'materi' => 'Materi Khusus Guru 1B',
        ]);
    }

    public function test_guru_terkunci_supervisi_kepsek_ditolak_pada_endpoint_mutasi_dan_bulk(): void
    {
        SupervisiPm::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $this->guruKelas1->id,
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
            'status' => SupervisiPm::STATUS_DISETUJUI,
        ]);

        $this->actingAs($this->guruKelas1)->post(route('guru.pm.cp.store'), [
            'nama_mapel' => 'Matematika',
            'fase' => 'A',
            'elemen' => 'Bilangan',
            'deskripsi' => 'CP baru saat sudah disetujui',
        ])->assertForbidden();

        $this->actingAs($this->guruKelas1)->post(route('guru.pm.prota.ambil-atp'), [
            'tahun_ajaran' => '2026/2027',
            'tingkat' => 1,
            'nama_mapel' => 'Matematika',
        ])->assertForbidden();

        $this->actingAs($this->guruKelas1)->post(route('guru.pm.prosem.susun'), [
            'tahun_ajaran' => '2026/2027',
            'tingkat' => 1,
            'nama_mapel' => 'Matematika',
            'semester' => 1,
            'jp_per_minggu' => 4,
        ])->assertForbidden();
    }

    public function test_simpan_interval_kktp_otomatis_sinkron_ke_kkm_pengaturan_guru(): void
    {
        $this->actingAs($this->guruKelas1)->post(route('guru.pm.kktp.interval'), [
            'nama_mapel' => 'Matematika',
            'tingkat' => 1,
            'batas_bawah' => 65,
            'nilai_kktp' => 78,
            'batas_atas' => 90,
        ])->assertRedirect();

        $pengaturan = PengaturanGuru::where('guru_id', $this->guruKelas1->id)->first();
        $this->assertNotNull($pengaturan);
        $this->assertSame(78, (int) $pengaturan->kkm);
    }
}
