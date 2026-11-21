<?php

namespace Tests\Feature;

use App\Models\MataPelajaran;
use App\Models\PembelajaranMendalam\HariLibur;
use App\Models\PembelajaranMendalam\KokurikulerPm;
use App\Models\PembelajaranMendalam\ProsemItem;
use App\Models\Perangkat\JurnalMengajar;
use App\Models\Perangkat\PerangkatPembelajaran;
use App\Models\SekolahSettings;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PembelajaranMendalamFase3Test extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $guru;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SDN Fase 3 Uji',
            'subdomain' => 'sdn-fase-3-uji',
            'aktif' => true,
        ]);

        TenantContext::set($this->tenant->id);

        $this->guru = User::factory()->guru()->create(['tenant_id' => $this->tenant->id]);
        MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK', 'aktif' => true, 'urutan' => 1]);
        SekolahSettings::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $this->guru->id,
            'nama_sekolah' => 'SDN Fase 3 Uji',
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

    public function test_generator_batch_mode_cepat_melewati_hari_libur_dan_buat_modul_serta_jurnal(): void
    {
        // Siapkan 2 materi di Prosem
        ProsemItem::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $this->guru->id,
            'tahun_ajaran' => '2026/2027',
            'tingkat' => 1,
            'nama_mapel' => 'Matematika',
            'semester' => 1,
            'bab' => 'Bab 1',
            'materi' => 'Bilangan 1-10',
            'bulan' => 8,
            'minggu_ke' => 1,
            'alokasi_jp' => 4,
        ]);
        ProsemItem::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $this->guru->id,
            'tahun_ajaran' => '2026/2027',
            'tingkat' => 1,
            'nama_mapel' => 'Matematika',
            'semester' => 1,
            'bab' => 'Bab 2',
            'materi' => 'Penjumlahan Sederhana',
            'bulan' => 8,
            'minggu_ke' => 2,
            'alokasi_jp' => 4,
        ]);

        // Tandai 2026-08-17 (Senin) sebagai hari libur nasional
        HariLibur::create([
            'tenant_id' => $this->tenant->id,
            'tahun_ajaran' => '2026/2027',
            'tanggal_mulai' => '2026-08-17',
            'tanggal_selesai' => '2026-08-17',
            'nama' => 'Hari Kemerdekaan RI',
            'jenis' => 'libur_nasional',
        ]);

        $this->actingAs($this->guru)
            ->post(route('guru.pm.generate.batch'), [
                'tahun_ajaran' => '2026/2027',
                'tingkat' => 1,
                'kelas' => '1A',
                'semester' => 1,
                'mapel' => ['Matematika'],
                'dari_tanggal' => '2026-08-17',
                'sampai_tanggal' => '2026-08-21',
                'mode' => 'cepat',
                'buat_modul' => 1,
                'buat_jurnal' => 1,
            ])
            ->assertRedirect(route('guru.pm.generate.index'));

        $this->assertSame(2, PerangkatPembelajaran::where('jenis', PerangkatPembelajaran::JENIS_MODUL_AJAR)->count());
        $this->assertSame(2, JurnalMengajar::count());
        // Tanggal 2026-08-17 harus dilewati -> jurnal pertama jatuh pada 2026-08-18
        $this->assertDatabaseMissing('jurnal_mengajar', ['tanggal' => '2026-08-17 00:00:00']);
        $this->assertSame('2026-08-18', JurnalMengajar::orderBy('tanggal')->firstOrFail()->tanggal->toDateString());
    }

    public function test_ai_rpm_dan_ai_jurnal(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [[
                            'text' => json_encode([
                                'tujuan_pembelajaran' => 'Murid memahami konsep pecahan setengah.',
                                'dimensi' => ['Penalaran Kritis', 'Kolaborasi'],
                                'pengalaman' => ['memahami' => 'Amati kertas lipat', 'mengaplikasi' => 'Bagi pizza kertas', 'merefleksi' => 'Tulis refleksi'],
                                'pendahuluan' => 'Salam & apersepsi',
                                'kegiatan_inti' => 'Eksplorasi pecahan',
                                'penutup' => 'Refleksi',
                                'asesmen' => 'Formatif observasi',
                                'lkpd' => 'LKPD Pecahan 1/2',
                                'rubrik' => 'Rubrik 4 level',
                                'media_sumber' => 'Kertas origami',
                                'refleksi_guru' => 'Murid aktif',
                            ]),
                        ]]],
                    ]],
                ], 200)
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [[
                            'text' => json_encode([
                                'praktik_pedagogis' => 'Discovery Learning',
                                'prinsip_pm' => ['Berkesadaran (Mindful)', 'Menggembirakan (Joyful)'],
                                'pengalaman_belajar' => ['Memahami', 'Mengaplikasi', 'Merefleksi'],
                                'kegiatan' => 'Murid melipat kertas origami menjadi dua bagian sama besar.',
                                'asesmen' => 'Observasi praktik lipat kertas',
                                'catatan' => 'Sebagian besar murid sudah paham.',
                                'tindak_lanjut' => 'Lanjut pecahan seperempat.',
                            ]),
                        ]]],
                    ]],
                ], 200),
        ]);

        $this->actingAs($this->guru)
            ->post(route('guru.pm.generate.rpm-ai'), [
                'nama_mapel' => 'Matematika',
                'tingkat' => 2,
                'kelas' => '2A',
                'materi' => 'Pecahan Setengah',
                'model_pembelajaran' => 'Discovery Learning',
                'sertakan_lkpd' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('perangkat_pembelajaran', [
            'jenis' => PerangkatPembelajaran::JENIS_MODUL_AJAR,
            'judul' => 'RPM Matematika — Pecahan Setengah',
        ]);

        $this->actingAs($this->guru)
            ->post(route('guru.pm.generate.jurnal-ai'), [
                'tanggal' => '2026-08-20',
                'jam_ke' => '1-2',
                'kelas' => '2A',
                'nama_mapel' => 'Matematika',
                'topik' => 'Pecahan Setengah',
                'kejadian_kelas' => 'Murid antusias melipat kertas',
            ])
            ->assertRedirect(route('guru.perangkat.jurnal.index'));

        $jurnal = JurnalMengajar::firstOrFail();
        $this->assertSame('Discovery Learning', $jurnal->praktik_pedagogis);
        $this->assertContains('Memahami', $jurnal->pengalaman_belajar ?? []);
    }

    public function test_kokurikuler_crud_ai_excel_dan_pdf(): void
    {
        $this->actingAs($this->guru)
            ->post(route('guru.pm.kokurikuler.store'), [
                'tahun_ajaran' => '2026/2027',
                'tingkat' => 4,
                'tema_judul' => 'Kebun Sayur Sekolahku',
                'bentuk_kegiatan' => 'kolaboratif_lintas_disiplin',
                'dimensi_profil' => ['Kolaborasi', 'Kemandirian'],
                'tanggal_mulai' => '2026-09-01',
                'tujuan' => 'Melatih kepedulian lingkungan.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pm_kokurikuler', [
            'tema_judul' => 'Kebun Sayur Sekolahku',
            'tingkat' => 4,
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'tema_judul' => 'Gerakan Bangun Pagi & Sarapan Sehat',
                            'dimensi_profil' => ['Kesehatan', 'Kemandirian'],
                            'tujuan' => 'Membiasakan pola hidup sehat.',
                            'rangkaian_kegiatan' => 'Tahap 1: Sosialisasi, Tahap 2: Jurnal Harian',
                            'mitra' => 'Orang tua murid',
                            'refleksi_hasil' => 'Murid hadir tepat waktu dan bugar.',
                        ]),
                    ]]],
                ]],
            ], 200),
        ]);

        $this->actingAs($this->guru)
            ->post(route('guru.pm.kokurikuler.ai'), [
                'tahun_ajaran' => '2026/2027',
                'tingkat' => 1,
                'bentuk_kegiatan' => 'tujuh_kebiasaan',
                'tema_ide' => '7 Kebiasaan Anak Indonesia Hebat',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pm_kokurikuler', [
            'tema_judul' => 'Gerakan Bangun Pagi & Sarapan Sehat',
            'sumber' => 'ai',
        ]);

        $this->actingAs($this->guru)->get(route('guru.pm.kokurikuler.excel'))->assertOk();
        $this->actingAs($this->guru)->get(route('guru.pm.kokurikuler.pdf'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $koku = KokurikulerPm::firstOrFail();
        $this->actingAs($this->guru)->delete(route('guru.pm.kokurikuler.destroy', $koku))->assertRedirect();
    }

    public function test_tanda_tangan_digital_guru_stempel_sekolah_dan_render_pdf(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        // 1x1 transparent PNG bytes
        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');

        // 1. Admin unggah TTD Kepsek, Stempel Sekolah & NIP Kepsek
        $admin = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
        $this->actingAs($admin)
            ->post(route('admin.referensi.logo-ttd.store'), [
                'ttd_kepsek' => UploadedFile::fake()->createWithContent('ttd-kepsek.png', $pngBytes),
                'stempel_sekolah' => UploadedFile::fake()->createWithContent('stempel.png', $pngBytes),
                'nip_kepsek' => '19750101 200003 1 001',
            ])
            ->assertRedirect(route('admin.referensi.logo-ttd'));

        // 2. Guru unggah TTD Guru, NIP & Jabatan
        $this->actingAs($this->guru)
            ->post(route('guru.pm.ttd.store'), [
                'ttd_guru' => UploadedFile::fake()->createWithContent('ttd-guru.png', $pngBytes),
                'nip_guru' => '19880512 201503 2 004',
                'jabatan_guru' => 'Guru Kelas 1A',
            ])
            ->assertRedirect(route('guru.pm.ttd.index'));

        $this->actingAs($this->guru)
            ->get(route('guru.pm.ttd.index'))
            ->assertOk()
            ->assertSee('Guru Kelas 1A')
            ->assertSee('19880512 201503 2 004');

        // 3. Pastikan TandaTanganPdfService menghasilkan base64 data URI untuk ketiganya
        $resolved = app(\App\Services\PembelajaranMendalam\TandaTanganPdfService::class)
            ->resolveForPdf(null, $this->guru);
        $this->assertStringStartsWith('data:image/png;base64,', (string) $resolved['ttd_kepsek_uri']);
        $this->assertStringStartsWith('data:image/png;base64,', (string) $resolved['stempel_uri']);
        $this->assertStringStartsWith('data:image/png;base64,', (string) $resolved['ttd_guru_uri']);
        $this->assertSame('19750101 200003 1 001', $resolved['nip_kepsek']);
        $this->assertSame('19880512 201503 2 004', $resolved['nip_guru']);

        // 4. Cetak PDF dengan gambar PNG tanda tangan & stempel terpasang
        $pdfRes = $this->actingAs($this->guru)->get(route('guru.pm.prota.pdf', ['tingkat' => 1]));
        $pdfRes->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', (string) $pdfRes->getContent());

        // 5. Hapus TTD Guru
        $this->actingAs($this->guru)->delete(route('guru.pm.ttd.hapus'))->assertRedirect(route('guru.pm.ttd.index'));
        $resolvedAfter = app(\App\Services\PembelajaranMendalam\TandaTanganPdfService::class)
            ->resolveForPdf(null, $this->guru);
        $this->assertNull($resolvedAfter['ttd_guru_uri']);
    }
}
