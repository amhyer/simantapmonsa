<?php

namespace Tests\Feature;

use App\Models\MataPelajaran;
use App\Models\PembelajaranMendalam\CapaianPembelajaran;
use App\Models\PembelajaranMendalam\KokurikulerPm;
use App\Models\PembelajaranMendalam\ProsemItem;
use App\Models\PembelajaranMendalam\SupervisiPm;
use App\Models\Perangkat\JurnalMengajar;
use App\Models\Perangkat\PerangkatPembelajaran;
use App\Models\SekolahSettings;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PembelajaranMendalamPolishTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $guru;

    private User $kepsek;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SDN Polish Uji',
            'subdomain' => 'sdn-polish-uji',
            'aktif' => true,
        ]);
        TenantContext::set($this->tenant->id);

        $this->guru = User::factory()->guru()->create(['tenant_id' => $this->tenant->id]);
        $this->kepsek = User::factory()->kepsek()->create(['tenant_id' => $this->tenant->id]);
        MataPelajaran::create(['nama' => 'Matematika (Umum)', 'kode' => 'MTK', 'aktif' => true, 'urutan' => 1]);
        SekolahSettings::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $this->kepsek->id,
            'nama_sekolah' => 'SDN Polish Uji',
            'kepala_sekolah' => $this->kepsek->nama_lengkap,
        ]);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_1_context_switcher_persisten_lintas_halaman(): void
    {
        $this->actingAs($this->guru)
            ->post(route('guru.pm.konteks.simpan'), [
                'tahun_ajaran' => '2027/2028',
                'semester' => 2,
                'tingkat' => 5,
            ])
            ->assertRedirect();

        // Pindah ke Prota & Prosem tanpa query string -> otomatis mengikuti Kelas 5, Sem 2, 2027/2028
        $this->actingAs($this->guru)
            ->get(route('guru.pm.prosem.index'))
            ->assertOk()
            ->assertSee('Semester 2')
            ->assertSee('Kelas 5')
            ->assertSee('2027/2028');
    }

    public function test_2_banner_supervisi_dan_penguncian_otomatis_saat_disetujui_kepsek(): void
    {
        $cp = CapaianPembelajaran::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $this->guru->id,
            'nama_mapel' => 'Matematika (Umum)',
            'fase' => 'A',
            'elemen' => 'Bilangan',
            'deskripsi' => 'CP Sebelum Disetujui',
        ]);

        SupervisiPm::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $this->guru->id,
            'kepsek_id' => $this->kepsek->id,
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
            'status' => SupervisiPm::STATUS_DISETUJUI,
            'skor_kelengkapan' => 100,
            'catatan_kepsek' => 'Mantap, sudah disetujui!',
            'disetujui_at' => now(),
        ]);

        // Banner tampil di halaman guru
        $this->actingAs($this->guru)
            ->get(route('guru.pm.cp.index', ['tahun_ajaran' => '2026/2027', 'semester' => 1]))
            ->assertOk()
            ->assertSee('Disetujui Kepsek')
            ->assertSee('Mantap, sudah disetujui!');

        // Guru terkunci tidak bisa menghapus CP yang sudah disetujui
        $this->actingAs($this->guru)
            ->delete(route('guru.pm.cp.destroy', $cp))
            ->assertForbidden();
    }

    public function test_3_anti_duplikat_generator_batch_dan_normalisasi_mapel_alokasi(): void
    {
        $admin = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);

        // Normalisasi mapel oleh Admin: "Matematika" standar harus masuk ke "Matematika (Umum)" sekolah
        $this->actingAs($admin)
            ->post(route('admin.pm.alokasi.standar'), ['tahun_ajaran' => '2026/2027'])
            ->assertRedirect();

        $this->assertDatabaseHas('pm_alokasi_waktu', [
            'nama_mapel' => 'Matematika (Umum)',
            'tingkat' => 1,
            'jp_intra' => 4,
        ]);

        // Generator batch dijalankan 2 kali pada rentang sama -> jumlah tetap 1 (anti-duplikat)
        ProsemItem::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $this->guru->id,
            'tahun_ajaran' => '2026/2027',
            'tingkat' => 1,
            'nama_mapel' => 'Matematika (Umum)',
            'semester' => 1,
            'materi' => 'Bilangan Anti Duplikat',
            'bulan' => 8,
            'minggu_ke' => 1,
            'alokasi_jp' => 4,
        ]);

        $payload = [
            'tahun_ajaran' => '2026/2027',
            'tingkat' => 1,
            'kelas' => '1A',
            'semester' => 1,
            'mapel' => ['Matematika (Umum)'],
            'dari_tanggal' => '2026-08-18',
            'sampai_tanggal' => '2026-08-21',
            'mode' => 'cepat',
            'buat_modul' => 1,
            'buat_jurnal' => 1,
        ];

        $this->actingAs($this->guru)->post(route('guru.pm.generate.batch'), $payload)->assertRedirect();
        $this->actingAs($this->guru)->post(route('guru.pm.generate.batch'), $payload)->assertRedirect();

        $this->assertSame(1, PerangkatPembelajaran::where('jenis', PerangkatPembelajaran::JENIS_MODUL_AJAR)->count());
        $this->assertSame(1, JurnalMengajar::count());
    }

    public function test_4_edit_inline_kokurikuler(): void
    {
        $koku = KokurikulerPm::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $this->guru->id,
            'tahun_ajaran' => '2026/2027',
            'tingkat' => 2,
            'tema_judul' => 'Tema Awal',
            'bentuk_kegiatan' => 'kolaboratif_lintas_disiplin',
        ]);

        $this->actingAs($this->guru)
            ->put(route('guru.pm.kokurikuler.update', $koku), [
                'tingkat' => 3,
                'tema_judul' => 'Tema Revisi Inline',
                'bentuk_kegiatan' => 'tujuh_kebiasaan',
                'mitra' => 'Orang Tua',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pm_kokurikuler', [
            'id' => $koku->id,
            'tingkat' => 3,
            'tema_judul' => 'Tema Revisi Inline',
            'mitra' => 'Orang Tua',
        ]);
    }

    public function test_5_seeder_cp_bskap_2025_dan_ttd_mandiri_kepsek(): void
    {
        Storage::fake('local');

        // Seeder CP BSKAP 2025 (idempoten)
        $this->actingAs($this->guru)
            ->post(route('guru.pm.cp.standar'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $count1 = CapaianPembelajaran::where('sumber', 'bskap_2025')->count();
        $this->assertGreaterThanOrEqual(10, $count1);

        // Klik kedua tidak menduplikasi
        $this->actingAs($this->guru)->post(route('guru.pm.cp.standar'))->assertRedirect();
        $this->assertSame($count1, CapaianPembelajaran::where('sumber', 'bskap_2025')->count());

        // Kepsek unggah TTD sendiri ke disk privat
        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');

        $this->actingAs($this->kepsek)
            ->post(route('kepsek.ttd.store'), [
                'ttd_kepsek' => UploadedFile::fake()->createWithContent('kepsek-mandiri.png', $pngBytes),
                'nip_kepsek' => '19700101 199503 1 002',
            ])
            ->assertRedirect(route('kepsek.ttd.index'));

        $this->actingAs($this->kepsek)
            ->get(route('kepsek.ttd.index'))
            ->assertOk()
            ->assertSee('19700101 199503 1 002');

        $this->assertNotEmpty(Storage::disk('local')->allFiles('private/identitas'));
    }
}
