<?php

namespace Tests\Feature;

use App\Models\MataPelajaran;
use App\Models\PembelajaranMendalam\AlokasiWaktu;
use App\Models\PembelajaranMendalam\BukuPaketSibi;
use App\Models\PembelajaranMendalam\CapaianPembelajaran;
use App\Models\PembelajaranMendalam\KktpInterval;
use App\Models\PembelajaranMendalam\KktpItem;
use App\Models\PembelajaranMendalam\TujuanPembelajaranPm;
use App\Models\SekolahSettings;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PembelajaranMendalamFase1Test extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $guru;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SDN Unggulan Uji',
            'subdomain' => 'sdn-unggulan-uji',
            'aktif' => true,
        ]);

        TenantContext::set($this->tenant->id);

        $this->guru = User::factory()->guru()->create(['tenant_id' => $this->tenant->id]);
        MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK', 'aktif' => true, 'urutan' => 1]);
        MataPelajaran::create(['nama' => 'Bahasa Indonesia', 'kode' => 'BIND', 'aktif' => true, 'urutan' => 2]);
        SekolahSettings::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $this->guru->id,
            'nama_sekolah' => 'SDN Unggulan Uji',
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

    public function test_alokasi_waktu_standar_simpan_dan_cetak_pdf(): void
    {
        $this->actingAs($this->guru)
            ->post(route('guru.pm.alokasi.standar'), ['tahun_ajaran' => '2026/2027'])
            ->assertRedirect();

        $this->assertDatabaseHas('pm_alokasi_waktu', [
            'tenant_id' => $this->tenant->id,
            'nama_mapel' => 'Matematika',
            'tingkat' => 1,
            'jp_intra' => 4,
            'jp_kokurikuler' => 1,
        ]);

        $this->actingAs($this->guru)
            ->get(route('guru.pm.alokasi.index'))
            ->assertOk()
            ->assertSee('Matematika');

        $this->actingAs($this->guru)
            ->get(route('guru.pm.alokasi.pdf', ['tingkat' => 1]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_cp_crud_ai_excel_dan_pdf(): void
    {
        $this->actingAs($this->guru)
            ->post(route('guru.pm.cp.store'), [
                'nama_mapel' => 'Matematika',
                'fase' => 'A',
                'elemen' => 'Bilangan',
                'deskripsi' => 'Murid menunjukkan pemahaman bilangan cacah sampai 100.',
                'urutan' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pm_capaian_pembelajaran', [
            'nama_mapel' => 'Matematika',
            'fase' => 'A',
            'elemen' => 'Bilangan',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            ['elemen' => 'Aljabar', 'deskripsi' => 'Murid mengenal pola gambar dan bilangan sederhana.'],
                        ]),
                    ]]],
                ]],
            ], 200),
        ]);

        $this->actingAs($this->guru)
            ->post(route('guru.pm.cp.ai'), [
                'nama_mapel' => 'Matematika',
                'fase' => 'A',
                'catatan' => 'Kontekstual SD',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pm_capaian_pembelajaran', [
            'elemen' => 'Aljabar',
            'sumber' => 'ai',
        ]);

        $this->actingAs($this->guru)->get(route('guru.pm.cp.excel'))->assertOk();
        $this->actingAs($this->guru)->get(route('guru.pm.cp.pdf'))->assertOk();
    }

    public function test_tp_atp_dan_kktp_terhubung_penuh(): void
    {
        $this->actingAs($this->guru)
            ->post(route('guru.pm.tp.store'), [
                'nama_mapel' => 'Matematika',
                'fase' => 'A',
                'elemen' => 'Bilangan',
                'kode_tp' => 'MTK.A.1',
                'deskripsi' => 'Murid mampu membilang dan menulis bilangan cacah sampai 20.',
                'urutan' => 1,
            ])
            ->assertRedirect();

        $tp = TujuanPembelajaranPm::firstOrFail();

        // Simpan ATP
        $this->actingAs($this->guru)
            ->post(route('guru.pm.atp.simpan'), [
                'items' => [
                    $tp->id => [
                        'urutan' => 1,
                        'kelas_target' => 1,
                        'semester_target' => 1,
                        'materi_pokok' => 'Bilangan Cacah 1-20',
                        'alokasi_jp' => 8,
                    ],
                ],
            ])
            ->assertRedirect();

        $tp->refresh();
        $this->assertSame(1, $tp->kelas_target);
        $this->assertSame(8, $tp->alokasi_jp);
        $this->assertSame('Bilangan Cacah 1-20', $tp->materi_pokok);

        // Simpan Interval KKTP & Item KKTP per TP
        $this->actingAs($this->guru)
            ->post(route('guru.pm.kktp.interval'), [
                'nama_mapel' => 'Matematika',
                'tingkat' => 1,
                'batas_bawah' => 65,
                'nilai_kktp' => 75,
                'batas_atas' => 90,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pm_kktp_interval', [
            'nama_mapel' => 'Matematika',
            'tingkat' => 1,
            'nilai_kktp' => 75,
            'batas_atas' => 90,
        ]);

        $this->actingAs($this->guru)
            ->post(route('guru.pm.kktp.item'), [
                'items' => [
                    $tp->id => [
                        'pendekatan' => 'rubrik',
                        'kriteria' => "Membilang urut 1-20\nMenulis lambang bilangan",
                        'rubrik' => 'Mahir jika tuntas mandiri tanpa bantuan.',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pm_kktp_item', [
            'tp_id' => $tp->id,
            'pendekatan' => 'rubrik',
        ]);

        $this->actingAs($this->guru)->get(route('guru.pm.atp.pdf', ['fase' => 'A']))->assertOk();
        $this->actingAs($this->guru)->get(route('guru.pm.kktp.excel', ['tingkat' => 1]))->assertOk();
        $this->actingAs($this->guru)->get(route('guru.pm.kktp.pdf', ['tingkat' => 1]))->assertOk();
    }

    public function test_buku_paket_sibi_simpan_dan_ai(): void
    {
        $this->actingAs($this->guru)
            ->post(route('guru.pm.buku-paket.store'), [
                'nama_mapel' => 'Matematika',
                'tingkat' => 1,
                'judul' => 'Matematika untuk SD/MI Kelas I',
                'penulis' => 'Kemendikdasmen',
                'tautan_sibi' => 'https://buku.kemendikdasmen.go.id/katalog/matematika-sd-kelas-1',
                'daftar_isi' => "Bab 1: Ayo Membilang\nBab 2: Penjumlahan",
            ])
            ->assertRedirect();

        $buku = BukuPaketSibi::firstOrFail();
        $this->assertCount(2, $buku->babList());

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode(['Bab 1: Bilangan', 'Bab 2: Penjumlahan', 'Bab 3: Pengurangan']),
                    ]]],
                ]],
            ], 200),
        ]);

        $this->actingAs($this->guru)
            ->post(route('guru.pm.buku-paket.ai'), [
                'nama_mapel' => 'Matematika',
                'tingkat' => 1,
                'judul' => 'Matematika untuk SD/MI Kelas I',
            ])
            ->assertRedirect();

        $buku->refresh();
        $this->assertCount(3, $buku->babList());
    }

    public function test_isolasi_tenant_cp(): void
    {
        $cpTenant1 = CapaianPembelajaran::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $this->guru->id,
            'nama_mapel' => 'Matematika',
            'fase' => 'A',
            'elemen' => 'Bilangan',
            'deskripsi' => 'CP Tenant 1',
        ]);

        $tenant2 = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SDN Lain',
            'subdomain' => 'sdn-lain',
            'aktif' => true,
        ]);
        $guruTenant2 = User::factory()->guru()->create(['tenant_id' => $tenant2->id]);

        TenantContext::set($tenant2->id);
        $this->actingAs($guruTenant2)
            ->delete(route('guru.pm.cp.destroy', $cpTenant1))
            ->assertStatus(404);
    }
}
