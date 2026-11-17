<?php

namespace Tests\Feature;

use App\Models\Perangkat\PerangkatPembelajaran;
use App\Models\Rombel;
use App\Models\SekolahSettings;
use App\Models\Semester;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PerangkatPembelajaranTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    private function makeSemester(): Semester
    {
        return Semester::firstOrCreate(
            ['semester_id' => '20261'],
            ['tahun_ajaran' => '2026/2027', 'nama_semester' => 'ganjil']
        );
    }

    private function makeRombel(string $nama, int $tingkat, ?int $guruId = null, ?int $tenantId = null): Rombel
    {
        return Rombel::create([
            'tenant_id' => $tenantId,
            'dapodik_id' => (string) Str::uuid(),
            'semester_id' => $this->makeSemester()->id,
            'nama_rombel' => $nama,
            'tingkat' => $tingkat,
            'guru_id' => $guruId,
        ]);
    }

    public function test_guru_dapat_membuat_cp_tp_atp_prota_promes_modul_ajar_dan_kkm_serta_cetak_pdf(): void
    {
        $guru = User::factory()->guru()->create();
        SekolahSettings::create([
            'nama_sekolah' => 'SDN Uji Perangkat',
            'npsn' => '40301234',
            'alamat' => 'Jl. Pendidikan No. 1',
            'kepala_sekolah' => 'Dr. Kepala Sekolah',
        ]);

        // 1. CP (Capaian Pembelajaran)
        $this->actingAs($guru)->post(route('guru.perangkat.store'), [
            'jenis' => PerangkatPembelajaran::JENIS_CP,
            'judul' => 'CP Matematika Fase C',
            'mata_pelajaran' => 'Matematika',
            'fase' => 'C',
            'kelas' => '6A',
            'semester' => 'Ganjil',
            'tahun_ajaran' => '2026/2027',
            'konten' => [
                'elemen' => 'Bilangan',
                'capaian_pembelajaran' => 'Peserta didik memahami operasi pecahan.',
                'kompetensi_inti' => 'Mengidentifikasi dan menyelesaikan masalah.',
                'konten_materi' => 'Pecahan & Desimal',
            ],
        ])->assertRedirect();

        $cp = PerangkatPembelajaran::where('jenis', 'cp')->firstOrFail();
        $this->assertSame('Bilangan', $cp->konten['elemen']);

        // 2. TP (Tujuan Pembelajaran)
        $this->actingAs($guru)->post(route('guru.perangkat.store'), [
            'jenis' => PerangkatPembelajaran::JENIS_TP,
            'judul' => 'TP Matematika Kelas 6A',
            'mata_pelajaran' => 'Matematika',
            'fase' => 'C',
            'kelas' => '6A',
            'konten' => [
                'elemen' => 'Bilangan',
                'daftar_tp' => [
                    ['kode' => 'TP-1', 'deskripsi' => 'Menjumlahkan pecahan berpenyebut beda', 'materi' => 'Pecahan', 'jp' => '4 JP'],
                ],
            ],
        ])->assertRedirect();

        $tp = PerangkatPembelajaran::where('jenis', 'tp')->firstOrFail();
        $this->assertSame('TP-1', $tp->konten['daftar_tp'][0]['kode']);

        // 3. ATP (Alur Tujuan Pembelajaran)
        $this->actingAs($guru)->post(route('guru.perangkat.store'), [
            'jenis' => PerangkatPembelajaran::JENIS_ATP,
            'judul' => 'ATP Matematika Fase C 2026',
            'mata_pelajaran' => 'Matematika',
            'fase' => 'C',
            'kelas' => '6A',
            'konten' => [
                'elemen' => 'Bilangan & Aljabar',
                'alur_item' => [
                    [
                        'urutan' => '1',
                        'kode_tp' => 'TP-1',
                        'tujuan_pembelajaran' => 'Menyelesaikan masalah pecahan',
                        'materi_pokok' => 'Pecahan',
                        'alokasi_jp' => '6 JP',
                        'semester' => 'Ganjil',
                        'dimensi_profil' => 'Penalaran Kritis',
                    ],
                ],
            ],
        ])->assertRedirect();

        $atp = PerangkatPembelajaran::where('jenis', 'atp')->firstOrFail();
        $this->assertSame('TP-1', $atp->konten['alur_item'][0]['kode_tp']);

        // 4. Prota, Promes, Modul Ajar (Pembelajaran Mendalam), KKM
        foreach (['prota', 'promes', 'modul_ajar', 'kkm'] as $jenis) {
            $this->actingAs($guru)->post(route('guru.perangkat.store'), [
                'jenis' => $jenis,
                'judul' => 'Dokumen '.strtoupper($jenis),
                'mata_pelajaran' => 'IPAS',
                'kelas' => '6A',
                'konten' => $jenis === 'modul_ajar'
                    ? [
                        'tujuan_pembelajaran' => 'Memahami ekosistem.',
                        'pm' => ['berkesadaran' => 1, 'bermakna' => 1, 'menggembirakan' => 1],
                        'dimensi' => ['Penalaran Kritis', 'Kolaborasi'],
                        'pengalaman' => ['memahami' => 'Diskusi', 'mengaplikasi' => 'Proyek', 'merefleksi' => 'Jurnal'],
                    ]
                    : ['nilai_kkm' => 78],
            ])->assertRedirect();
        }

        $this->assertSame(7, PerangkatPembelajaran::where('guru_id', $guru->id)->count());

        // Cetak PDF DomPDF valid (%PDF-)
        $pdfRes = $this->actingAs($guru)->get(route('guru.perangkat.pdf', $atp));
        $pdfRes->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdfRes->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', (string) $pdfRes->getContent());

        // Guard non-UUID menghasilkan 404 bersih (tidak crash PG 22P02)
        $this->actingAs($guru)->get('/guru/perangkat/bukan-uuid-valid')->assertNotFound();
    }

    public function test_wali_kelas_punya_hak_baca_read_only_untuk_perangkat_pengajar_kelasnya(): void
    {
        $guruMapel = User::factory()->guru()->create(['is_wali_kelas' => false]);
        $walas6A = User::factory()->guru()->create(['is_wali_kelas' => true]);
        $walas6B = User::factory()->guru()->create(['is_wali_kelas' => true]);

        $rombel6A = $this->makeRombel('6A', 6, $walas6A->id);
        $this->makeRombel('6B', 6, $walas6B->id);

        $perangkatDibagikan = PerangkatPembelajaran::create([
            'guru_id' => $guruMapel->id,
            'rombel_id' => $rombel6A->id,
            'jenis' => 'atp',
            'judul' => 'ATP PJOK Kelas 6A',
            'mata_pelajaran' => 'PJOK',
            'kelas' => '6A',
            'dibagikan_ke_walas' => true,
        ]);

        $perangkatPrivat = PerangkatPembelajaran::create([
            'guru_id' => $guruMapel->id,
            'rombel_id' => $rombel6A->id,
            'jenis' => 'modul_ajar',
            'judul' => 'Draf Privat PJOK 6A',
            'mata_pelajaran' => 'PJOK',
            'kelas' => '6A',
            'dibagikan_ke_walas' => false,
        ]);

        // Wali kelas 6A bisa lihat & cetak PDF perangkat yang dibagikan
        $this->actingAs($walas6A)->get(route('guru.perangkat.show', $perangkatDibagikan))
            ->assertOk()
            ->assertSee('Mode Baca-Saja')
            ->assertSee('ATP PJOK Kelas 6A');

        $this->actingAs($walas6A)->get(route('guru.perangkat.pdf', $perangkatDibagikan))
            ->assertOk();

        // Wali kelas 6A TIDAK boleh edit atau hapus perangkat guru mapel
        $this->actingAs($walas6A)->get(route('guru.perangkat.edit', $perangkatDibagikan))->assertForbidden();
        $this->actingAs($walas6A)->delete(route('guru.perangkat.destroy', $perangkatDibagikan))->assertForbidden();

        // Perangkat privat tidak bisa dilihat wali kelas
        $this->actingAs($walas6A)->get(route('guru.perangkat.show', $perangkatPrivat))->assertForbidden();

        // Wali kelas 6B tidak bisa melihat perangkat kelas 6A
        $this->actingAs($walas6B)->get(route('guru.perangkat.show', $perangkatDibagikan))->assertForbidden();
    }

    public function test_jurnal_mengajar_crud_cetak_pdf_dan_isolasi_multi_tenant_serta_supervisi_kepsek(): void
    {
        $tenantA = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SDN Perangkat A',
            'subdomain' => 'sdn-perangkat-a',
            'aktif' => true,
        ]);
        $tenantB = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SDN Perangkat B',
            'subdomain' => 'sdn-perangkat-b',
            'aktif' => true,
        ]);

        $guruA = User::factory()->guru()->create(['tenant_id' => $tenantA->id]);
        $kepsekA = User::factory()->kepsek()->create(['tenant_id' => $tenantA->id]);
        $kepsekB = User::factory()->kepsek()->create(['tenant_id' => $tenantB->id]);
        $guruB = User::factory()->guru()->create(['tenant_id' => $tenantB->id]);

        TenantContext::set($tenantA->id);
        $perangkatA = PerangkatPembelajaran::create([
            'tenant_id' => $tenantA->id,
            'guru_id' => $guruA->id,
            'jenis' => 'cp',
            'judul' => 'CP IPA Tenant A',
            'mata_pelajaran' => 'IPAS',
            'kelas' => '5A',
        ]);

        // Guru A mencatat jurnal mengajar & mencetak rekap PDF
        $this->actingAs($guruA)->post(route('guru.perangkat.jurnal.store'), [
            'tanggal' => '2026-11-16',
            'jam_ke' => '1-2',
            'kelas' => '5A',
            'mata_pelajaran' => 'IPAS',
            'perangkat_id' => $perangkatA->id,
            'topik' => 'Rantai Makanan',
            'kegiatan' => 'Diskusi kelompok dan pengamatan bagan ekosistem.',
            'jumlah_hadir' => 28,
            'jumlah_tidak_hadir' => 1,
        ])->assertRedirect(route('guru.perangkat.jurnal.index'));

        $this->assertDatabaseHas('jurnal_mengajar', [
            'guru_id' => $guruA->id,
            'tenant_id' => $tenantA->id,
            'topik' => 'Rantai Makanan',
        ]);

        $pdfJurnal = $this->actingAs($guruA)->get(route('guru.perangkat.jurnal.pdf'));
        $pdfJurnal->assertOk();
        $this->assertStringStartsWith('%PDF-', (string) $pdfJurnal->getContent());

        // Kepsek A bisa supervisi perangkat A & cetak PDF
        $this->actingAs($kepsekA)->get(route('kepsek.perangkat.index'))
            ->assertOk()
            ->assertSee('CP IPA Tenant A');
        $this->actingAs($kepsekA)->get(route('kepsek.perangkat.pdf', $perangkatA))->assertOk();

        // Isolasi lintas tenant: Kepsek B & Guru B tidak boleh melihat perangkat A
        TenantContext::set($tenantB->id);
        $this->actingAs($kepsekB)->get(route('kepsek.perangkat.index'))
            ->assertOk()
            ->assertDontSee('CP IPA Tenant A');

        $this->actingAs($guruB)->get(route('guru.perangkat.show', $perangkatA))->assertNotFound();
    }

    public function test_lampiran_privat_menolak_php_dan_mengunduh_pdf_aman(): void
    {
        Storage::fake('local');
        $guru = User::factory()->guru()->create();

        // Tolak file berbahaya (.php)
        $badFile = UploadedFile::fake()->create('exploit.php', 10, 'application/x-php');
        $this->actingAs($guru)->postJson(route('guru.perangkat.store'), [
            'jenis' => 'modul_ajar',
            'judul' => 'Modul Uji Lampiran',
            'mata_pelajaran' => 'Matematika',
            'kelas' => '6A',
            'lampiran' => $badFile,
        ])->assertStatus(422);

        // Terima file PDF valid dan unduh dengan nosniff
        $goodPdf = UploadedFile::fake()->createWithContent(
            'modul.pdf',
            "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n"
        );
        $this->actingAs($guru)->post(route('guru.perangkat.store'), [
            'jenis' => 'modul_ajar',
            'judul' => 'Modul Lampiran PDF Valid',
            'mata_pelajaran' => 'Matematika',
            'kelas' => '6A',
            'lampiran' => $goodPdf,
        ])->assertRedirect();

        $dok = PerangkatPembelajaran::where('judul', 'Modul Lampiran PDF Valid')->firstOrFail();
        $this->assertNotNull($dok->lampiran_path);

        $dl = $this->actingAs($guru)->get(route('guru.perangkat.lampiran', $dok));
        $dl->assertOk();
        $this->assertSame('nosniff', $dl->headers->get('X-Content-Type-Options'));
    }
}
