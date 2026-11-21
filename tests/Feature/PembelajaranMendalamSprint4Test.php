<?php

namespace Tests\Feature;

use App\Models\MataPelajaran;
use App\Models\PembelajaranMendalam\CapaianPembelajaran;
use App\Models\PembelajaranMendalam\TujuanPembelajaranPm;
use App\Models\PengaturanGuru;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PembelajaranMendalamSprint4Test extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $guru;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SDN Sprint 4 Uji',
            'subdomain' => 'sdn-sprint-4-uji',
            'aktif' => true,
        ]);
        TenantContext::set($this->tenant->id);

        $this->guru = User::factory()->guru()->create(['tenant_id' => $this->tenant->id]);
        MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK', 'aktif' => true, 'urutan' => 1]);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_sinkronisasi_bank_tp_pm_ke_tp_erapor_idempoten(): void
    {
        TujuanPembelajaranPm::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $this->guru->id,
            'nama_mapel' => 'Matematika',
            'fase' => 'A',
            'elemen' => 'Bilangan',
            'kode_tp' => 'MTK.1.1',
            'deskripsi' => 'Murid mampu membilang bilangan cacah sampai 20.',
            'semester_target' => 1,
            'urutan' => 1,
        ]);
        TujuanPembelajaranPm::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $this->guru->id,
            'nama_mapel' => 'Matematika',
            'fase' => 'A',
            'elemen' => 'Bilangan',
            'kode_tp' => 'MTK.1.2',
            'deskripsi' => 'Murid mampu melakukan penjumlahan sampai 20.',
            'semester_target' => 2,
            'urutan' => 2,
        ]);

        // Sinkronisasi pertama -> 2 TP masuk
        $this->actingAs($this->guru)
            ->post(route('guru.pm.tp.sinkron-erapor'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $peng = PengaturanGuru::where('guru_id', $this->guru->id)->firstOrFail();
        $eraporTp = $peng->pengaturan['tujuan_pembelajaran'] ?? [];
        $this->assertCount(2, $eraporTp);
        $this->assertSame('MTK.1.1', $eraporTp[0]['kode']);
        $this->assertSame('Fase A', $eraporTp[0]['fase']);
        $this->assertSame('Genap', $eraporTp[1]['semester']);

        // Sinkronisasi kedua -> tetap 2 (tidak duplikat)
        $this->actingAs($this->guru)
            ->post(route('guru.pm.tp.sinkron-erapor'))
            ->assertRedirect();

        $peng->refresh();
        $this->assertCount(2, $peng->pengaturan['tujuan_pembelajaran'] ?? []);

        // Terlihat langsung di halaman TP e-Rapor
        $this->actingAs($this->guru)
            ->get(route('guru.tp.index'))
            ->assertOk()
            ->assertSee('Murid mampu membilang bilangan cacah sampai 20.');
    }

    public function test_import_excel_cp_dan_tp_serta_edit_inline(): void
    {
        // Buat file XLSX CP sementara
        $cpPath = tempnam(sys_get_temp_dir(), 'cp_').'.xlsx';
        $spCp = new Spreadsheet;
        $spCp->getActiveSheet()->fromArray([
            ['No', 'Mata Pelajaran', 'Fase', 'Elemen', 'Capaian Pembelajaran'],
            [1, 'Matematika', 'Fase A', 'Bilangan', 'CP hasil impor Excel 1'],
            [2, 'Matematika', 'B', 'Pengukuran', 'CP hasil impor Excel 2'],
        ], null, 'A1');
        (new Xlsx($spCp))->save($cpPath);

        $cpFile = new UploadedFile($cpPath, 'cp.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $this->actingAs($this->guru)
            ->post(route('guru.pm.cp.import'), ['file_excel' => $cpFile])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('pm_capaian_pembelajaran', [
            'elemen' => 'Bilangan',
            'deskripsi' => 'CP hasil impor Excel 1',
            'sumber' => 'import',
        ]);

        // Edit inline CP (PUT)
        $cp = CapaianPembelajaran::where('elemen', 'Bilangan')->firstOrFail();
        $this->actingAs($this->guru)
            ->put(route('guru.pm.cp.update', $cp), [
                'nama_mapel' => 'Matematika',
                'fase' => 'A',
                'elemen' => 'Bilangan Revisi',
                'deskripsi' => 'CP hasil edit inline',
                'urutan' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pm_capaian_pembelajaran', [
            'id' => $cp->id,
            'elemen' => 'Bilangan Revisi',
            'deskripsi' => 'CP hasil edit inline',
        ]);

        // Buat file XLSX TP sementara
        $tpPath = tempnam(sys_get_temp_dir(), 'tp_').'.xlsx';
        $spTp = new Spreadsheet;
        $spTp->getActiveSheet()->fromArray([
            ['No', 'Mata Pelajaran', 'Fase', 'Elemen', 'Kode TP', 'Rumusan Tujuan Pembelajaran'],
            [1, 'Matematika', 'Fase A', 'Bilangan', 'IMP.1', 'TP hasil impor Excel'],
        ], null, 'A1');
        (new Xlsx($spTp))->save($tpPath);

        $tpFile = new UploadedFile($tpPath, 'tp.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $this->actingAs($this->guru)
            ->post(route('guru.pm.tp.import'), ['file_excel' => $tpFile])
            ->assertRedirect()
            ->assertSessionHas('success');

        $tp = TujuanPembelajaranPm::where('kode_tp', 'IMP.1')->firstOrFail();
        $this->assertSame('import', $tp->sumber);

        // Edit inline TP (PUT)
        $this->actingAs($this->guru)
            ->put(route('guru.pm.tp.update', $tp), [
                'nama_mapel' => 'Matematika',
                'fase' => 'A',
                'elemen' => 'Bilangan',
                'kode_tp' => 'IMP.1.REV',
                'deskripsi' => 'TP hasil edit inline',
                'urutan' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pm_tujuan_pembelajaran', [
            'id' => $tp->id,
            'kode_tp' => 'IMP.1.REV',
            'deskripsi' => 'TP hasil edit inline',
        ]);

        @unlink($cpPath);
        @unlink($tpPath);
    }
}
