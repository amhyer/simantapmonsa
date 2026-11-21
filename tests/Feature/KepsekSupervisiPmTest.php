<?php

namespace Tests\Feature;

use App\Models\PembelajaranMendalam\CapaianPembelajaran;
use App\Models\PembelajaranMendalam\KktpItem;
use App\Models\PembelajaranMendalam\KokurikulerPm;
use App\Models\PembelajaranMendalam\ProsemItem;
use App\Models\PembelajaranMendalam\ProtaItem;
use App\Models\PembelajaranMendalam\SupervisiPm;
use App\Models\PembelajaranMendalam\TujuanPembelajaranPm;
use App\Models\Perangkat\JurnalMengajar;
use App\Models\Perangkat\PerangkatPembelajaran;
use App\Models\SekolahSettings;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class KepsekSupervisiPmTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private User $kepsekA;

    private User $guruA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SDN Supervisi A',
            'subdomain' => 'sdn-supervisi-a',
            'aktif' => true,
        ]);
        TenantContext::set($this->tenantA->id);

        $this->kepsekA = User::factory()->kepsek()->create(['tenant_id' => $this->tenantA->id]);
        $this->guruA = User::factory()->guru()->create([
            'tenant_id' => $this->tenantA->id,
            'nama_lengkap' => 'Ahmad Supervisi',
            'aktif' => true,
        ]);

        SekolahSettings::create([
            'tenant_id' => $this->tenantA->id,
            'guru_id' => $this->kepsekA->id,
            'nama_sekolah' => 'SDN Supervisi A',
            'kepala_sekolah' => $this->kepsekA->nama_lengkap,
        ]);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_kepsek_melihat_matriks_9_dokumen_memberi_persetujuan_dan_cetak_rekap_pdf(): void
    {
        // Isi 9 dari 9 dokumen Pembelajaran Mendalam untuk Guru A -> skor harus 100%
        CapaianPembelajaran::create([
            'tenant_id' => $this->tenantA->id,
            'guru_id' => $this->guruA->id,
            'nama_mapel' => 'Matematika',
            'fase' => 'A',
            'elemen' => 'Bilangan',
            'deskripsi' => 'CP Uji',
        ]);

        $tp = TujuanPembelajaranPm::create([
            'tenant_id' => $this->tenantA->id,
            'guru_id' => $this->guruA->id,
            'nama_mapel' => 'Matematika',
            'fase' => 'A',
            'kode_tp' => 'MTK.1',
            'deskripsi' => 'TP Uji',
            'kelas_target' => 1,
            'semester_target' => 1,
            'materi_pokok' => 'Bilangan',
            'alokasi_jp' => 8,
        ]);

        KktpItem::create([
            'tenant_id' => $this->tenantA->id,
            'guru_id' => $this->guruA->id,
            'tp_id' => $tp->id,
            'pendekatan' => 'deskripsi',
            'kriteria' => 'Tuntas membilang',
        ]);

        ProtaItem::create([
            'tenant_id' => $this->tenantA->id,
            'guru_id' => $this->guruA->id,
            'tahun_ajaran' => '2026/2027',
            'tingkat' => 1,
            'nama_mapel' => 'Matematika',
            'semester' => 1,
            'materi' => 'Bilangan',
            'alokasi_jp' => 8,
        ]);

        ProsemItem::create([
            'tenant_id' => $this->tenantA->id,
            'guru_id' => $this->guruA->id,
            'tahun_ajaran' => '2026/2027',
            'tingkat' => 1,
            'nama_mapel' => 'Matematika',
            'semester' => 1,
            'materi' => 'Bilangan',
            'bulan' => 8,
            'minggu_ke' => 1,
            'alokasi_jp' => 4,
        ]);

        PerangkatPembelajaran::create([
            'tenant_id' => $this->tenantA->id,
            'guru_id' => $this->guruA->id,
            'jenis' => PerangkatPembelajaran::JENIS_MODUL_AJAR,
            'judul' => 'RPM Bilangan 1-10',
            'mata_pelajaran' => 'Matematika',
            'kelas' => '1A',
            'tahun_ajaran' => '2026/2027',
            'semester' => 'ganjil',
        ]);

        JurnalMengajar::create([
            'tenant_id' => $this->tenantA->id,
            'guru_id' => $this->guruA->id,
            'tanggal' => '2026-08-18',
            'kelas' => '1A',
            'mata_pelajaran' => 'Matematika',
            'topik' => 'Bilangan',
            'kegiatan' => 'Diskusi bilangan',
        ]);

        KokurikulerPm::create([
            'tenant_id' => $this->tenantA->id,
            'guru_id' => $this->guruA->id,
            'tahun_ajaran' => '2026/2027',
            'tingkat' => 1,
            'tema_judul' => 'Kebun Sekolah',
        ]);

        // 1. Cek Dashboard Matriks
        $this->actingAs($this->kepsekA)
            ->get(route('kepsek.supervisi-pm.index', ['tahun_ajaran' => '2026/2027', 'semester' => 1]))
            ->assertOk()
            ->assertSee('Ahmad Supervisi')
            ->assertSee('100%');

        // 2. Cek Detail Guru
        $this->actingAs($this->kepsekA)
            ->get(route('kepsek.supervisi-pm.show', ['guru' => $this->guruA, 'tahun_ajaran' => '2026/2027', 'semester' => 1]))
            ->assertOk()
            ->assertSee('RPM Bilangan 1-10')
            ->assertSee('Kebun Sekolah');

        // 3. Simpan Keputusan Persetujuan Digital Kepsek
        $this->actingAs($this->kepsekA)
            ->post(route('kepsek.supervisi-pm.keputusan', $this->guruA), [
                'tahun_ajaran' => '2026/2027',
                'semester' => 1,
                'status' => SupervisiPm::STATUS_DISETUJUI,
                'catatan_kepsek' => 'Dokumen pembelajaran mendalam lengkap dan siap akreditasi.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pm_supervisi', [
            'tenant_id' => $this->tenantA->id,
            'guru_id' => $this->guruA->id,
            'kepsek_id' => $this->kepsekA->id,
            'status' => SupervisiPm::STATUS_DISETUJUI,
            'skor_kelengkapan' => 100,
        ]);

        // 4. Cetak Rekap Supervisi PDF
        $pdfRes = $this->actingAs($this->kepsekA)
            ->get(route('kepsek.supervisi-pm.pdf', ['tahun_ajaran' => '2026/2027', 'semester' => 1]));
        $pdfRes->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', (string) $pdfRes->getContent());
    }

    public function test_isolasi_tenant_dan_role_supervisi_pm(): void
    {
        // Guru tidak boleh mengakses rute supervisi kepsek
        $this->actingAs($this->guruA)
            ->get(route('kepsek.supervisi-pm.index'))
            ->assertForbidden();

        // Kepsek tenant B tidak boleh memeriksa/menyetujui guru tenant A
        $tenantB = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SDN Supervisi B',
            'subdomain' => 'sdn-supervisi-b',
            'aktif' => true,
        ]);
        $kepsekB = User::factory()->kepsek()->create(['tenant_id' => $tenantB->id]);

        TenantContext::set($tenantB->id);
        $this->actingAs($kepsekB)
            ->get(route('kepsek.supervisi-pm.show', $this->guruA))
            ->assertForbidden();
    }
}
