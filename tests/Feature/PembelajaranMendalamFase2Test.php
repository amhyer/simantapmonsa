<?php

namespace Tests\Feature;

use App\Models\MataPelajaran;
use App\Models\PembelajaranMendalam\HariLibur;
use App\Models\PembelajaranMendalam\KalenderPendidikan;
use App\Models\PembelajaranMendalam\ProsemItem;
use App\Models\PembelajaranMendalam\ProtaItem;
use App\Models\PembelajaranMendalam\TujuanPembelajaranPm;
use App\Models\SekolahSettings;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PembelajaranMendalamFase2Test extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $guru;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SDN Fase 2 Uji',
            'subdomain' => 'sdn-fase-2-uji',
            'aktif' => true,
        ]);

        TenantContext::set($this->tenant->id);

        $this->guru = User::factory()->guru()->create(['tenant_id' => $this->tenant->id]);
        MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK', 'aktif' => true, 'urutan' => 1]);
        SekolahSettings::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $this->guru->id,
            'nama_sekolah' => 'SDN Fase 2 Uji',
            'pengaturan' => [
                'gemini_api_key' => 'test-gemini-key',
                'gemini_model' => 'gemini-2.5-flash',
                'gemini_daily_quota' => 60,
            ],
        ]);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_kalender_pendidikan_default_simpan_dan_hari_libur(): void
    {
        $this->actingAs($this->guru)
            ->get(route('guru.pm.kalender.index', ['tahun_ajaran' => '2026/2027']))
            ->assertOk()
            ->assertSee('Juli');

        $this->assertSame(12, KalenderPendidikan::where('tahun_ajaran', '2026/2027')->count());

        $juli = KalenderPendidikan::where('tahun_ajaran', '2026/2027')->where('bulan', 7)->firstOrFail();

        $this->actingAs($this->guru)
            ->post(route('guru.pm.kalender.simpan'), [
                'kalender' => [
                    $juli->id => [
                        'jumlah_minggu' => 5,
                        'minggu_tidak_efektif' => 2,
                        'hari_efektif' => 14,
                        'minggu_libur' => '1,2',
                        'keterangan' => 'MPLS & Libur',
                    ],
                ],
            ])
            ->assertRedirect();

        $juli->refresh();
        $this->assertSame([1, 2], $juli->minggu_libur);
        $this->assertSame(14, $juli->hari_efektif);

        $this->actingAs($this->guru)
            ->post(route('guru.pm.kalender.libur.store'), [
                'tahun_ajaran' => '2026/2027',
                'nama' => 'Hari Kemerdekaan RI',
                'tanggal_mulai' => '2026-08-17',
                'tanggal_selesai' => '2026-08-17',
                'jenis' => 'libur_nasional',
            ])
            ->assertRedirect();

        $libur = HariLibur::firstOrFail();
        $this->assertSame('Hari Kemerdekaan RI', $libur->nama);

        $this->actingAs($this->guru)
            ->delete(route('guru.pm.kalender.libur.destroy', $libur))
            ->assertRedirect();

        $this->assertDatabaseCount('pm_hari_libur', 0);
    }

    public function test_alur_atp_ke_prota_ke_prosem_matriks_otomatis_dan_pdf(): void
    {
        // 1. Buat 2 TP di ATP untuk Kelas 1 Semester 1
        TujuanPembelajaranPm::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $this->guru->id,
            'nama_mapel' => 'Matematika',
            'fase' => 'A',
            'elemen' => 'Bilangan',
            'kode_tp' => 'MTK.1.1',
            'deskripsi' => 'Membilang bilangan cacah sampai 10',
            'urutan' => 1,
            'kelas_target' => 1,
            'semester_target' => 1,
            'materi_pokok' => 'Bilangan Cacah 1-10',
            'alokasi_jp' => 8,
        ]);
        TujuanPembelajaranPm::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $this->guru->id,
            'nama_mapel' => 'Matematika',
            'fase' => 'A',
            'elemen' => 'Bilangan',
            'kode_tp' => 'MTK.1.2',
            'deskripsi' => 'Penjumlahan bilangan sampai 10',
            'urutan' => 2,
            'kelas_target' => 1,
            'semester_target' => 1,
            'materi_pokok' => 'Penjumlahan 1-10',
            'alokasi_jp' => 8,
        ]);

        // 2. Tarik ATP ke Prota
        $this->actingAs($this->guru)
            ->post(route('guru.pm.prota.ambil-atp'), [
                'tahun_ajaran' => '2026/2027',
                'tingkat' => 1,
                'nama_mapel' => 'Matematika',
            ])
            ->assertRedirect();

        $this->assertSame(2, ProtaItem::count());
        $this->assertSame(16, (int) ProtaItem::sum('alokasi_jp'));

        // 3. Susun Prosem otomatis dari Prota (4 JP/minggu -> 16 JP = 4 minggu efektif, melewati minggu libur 1 & 2 Juli)
        $this->actingAs($this->guru)
            ->post(route('guru.pm.prosem.susun'), [
                'tahun_ajaran' => '2026/2027',
                'tingkat' => 1,
                'nama_mapel' => 'Matematika',
                'semester' => 1,
                'jp_per_minggu' => 4,
            ])
            ->assertRedirect();

        $this->assertSame(4, ProsemItem::count());
        $this->assertSame(16, (int) ProsemItem::sum('alokasi_jp'));
        // Pastikan minggu 1 & 2 Juli (minggu libur default) TIDAK terisi
        $this->assertDatabaseMissing('pm_prosem', ['bulan' => 7, 'minggu_ke' => 1]);
        $this->assertDatabaseMissing('pm_prosem', ['bulan' => 7, 'minggu_ke' => 2]);
        $this->assertDatabaseHas('pm_prosem', ['bulan' => 7, 'minggu_ke' => 3, 'alokasi_jp' => 4]);

        // 4. Verifikasi tampilan matriks & PDF Prota + Prosem
        $this->actingAs($this->guru)
            ->get(route('guru.pm.prosem.index', ['tahun_ajaran' => '2026/2027', 'tingkat' => 1, 'semester' => 1, 'mapel' => 'Matematika']))
            ->assertOk()
            ->assertSee('Bilangan Cacah 1-10');

        $this->actingAs($this->guru)
            ->get(route('guru.pm.prota.pdf', ['tahun_ajaran' => '2026/2027', 'tingkat' => 1, 'mapel' => 'Matematika']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->guru)
            ->get(route('guru.pm.prosem.pdf', ['tahun_ajaran' => '2026/2027', 'tingkat' => 1, 'semester' => 1, 'mapel' => 'Matematika']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_prota_ai_generate(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            ['semester' => 1, 'tp_id' => null, 'materi' => 'Bab 1: Bilangan', 'alokasi_jp' => 16, 'keterangan' => 'AI'],
                            ['semester' => 2, 'tp_id' => null, 'materi' => 'Bab 2: Bangun Datar', 'alokasi_jp' => 12, 'keterangan' => 'AI'],
                        ]),
                    ]]],
                ]],
            ], 200),
        ]);

        $this->actingAs($this->guru)
            ->post(route('guru.pm.prota.ai'), [
                'tahun_ajaran' => '2026/2027',
                'tingkat' => 1,
                'nama_mapel' => 'Matematika',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pm_prota', ['materi' => 'Bab 1: Bilangan', 'semester' => 1, 'alokasi_jp' => 16]);
        $this->assertDatabaseHas('pm_prota', ['materi' => 'Bab 2: Bangun Datar', 'semester' => 2, 'alokasi_jp' => 12]);
    }
}
