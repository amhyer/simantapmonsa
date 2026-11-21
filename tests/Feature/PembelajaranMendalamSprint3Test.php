<?php

namespace Tests\Feature;

use App\Models\JadwalPelajaran;
use App\Models\MataPelajaran;
use App\Models\PembelajaranMendalam\HariLibur;
use App\Models\PembelajaranMendalam\ProsemItem;
use App\Models\Perangkat\JurnalMengajar;
use App\Models\Perangkat\PerangkatPembelajaran;
use App\Models\SekolahSettings;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PembelajaranMendalamSprint3Test extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $guru;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SDN Sprint 3 Uji',
            'subdomain' => 'sdn-sprint-3-uji',
            'aktif' => true,
        ]);
        TenantContext::set($this->tenant->id);

        $this->guru = User::factory()->guru()->create(['tenant_id' => $this->tenant->id]);
        MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK', 'aktif' => true, 'urutan' => 1]);
        SekolahSettings::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $this->guru->id,
            'nama_sekolah' => 'SDN Sprint 3 Uji',
            'npsn' => '40313912',
            'kepala_sekolah' => 'Dr. Kepala Sekolah',
        ]);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_integrasi_jadwal_pelajaran_pratinjau_dan_generate_batch(): void
    {
        // Jadwal Matematika hanya Senin & Rabu jam 07:30-08:40
        JadwalPelajaran::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $this->guru->id,
            'kelas' => '1A',
            'mata_pelajaran' => 'Matematika',
            'hari' => 'Senin',
            'jam_mulai' => '07:30',
            'jam_selesai' => '08:40',
        ]);
        JadwalPelajaran::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $this->guru->id,
            'kelas' => '1A',
            'mata_pelajaran' => 'Matematika',
            'hari' => 'Rabu',
            'jam_mulai' => '07:30',
            'jam_selesai' => '08:40',
        ]);

        // 2 Materi di Prosem
        ProsemItem::create([
            'tenant_id' => $this->tenant->id,
            'guru_id' => $this->guru->id,
            'tahun_ajaran' => '2026/2027',
            'tingkat' => 1,
            'nama_mapel' => 'Matematika',
            'semester' => 1,
            'bab' => 'Bab 1',
            'materi' => 'Bilangan Cacah 1-10',
            'bulan' => 8,
            'minggu_ke' => 3,
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
            'materi' => 'Penjumlahan Dasar',
            'bulan' => 8,
            'minggu_ke' => 4,
            'alokasi_jp' => 4,
        ]);

        // Senin 2026-08-17 adalah Hari Libur -> jadwal berikutnya adalah Rabu 2026-08-19 dan Senin 2026-08-24
        HariLibur::create([
            'tenant_id' => $this->tenant->id,
            'tahun_ajaran' => '2026/2027',
            'tanggal_mulai' => '2026-08-17',
            'tanggal_selesai' => '2026-08-17',
            'nama' => 'HUT RI',
            'jenis' => 'libur_nasional',
        ]);

        $payload = [
            'tahun_ajaran' => '2026/2027',
            'tingkat' => 1,
            'kelas' => '1A',
            'semester' => 1,
            'mapel' => ['Matematika'],
            'dari_tanggal' => '2026-08-17',
            'sampai_tanggal' => '2026-08-28',
            'mode' => 'cepat',
            'buat_modul' => 1,
            'buat_jurnal' => 1,
        ];

        // 1. Pratinjau tidak boleh menulis ke DB
        $this->actingAs($this->guru)
            ->post(route('guru.pm.generate.pratinjau'), $payload)
            ->assertOk()
            ->assertSee('Pratinjau Rencana Pertemuan')
            ->assertSee('19/08/2026')
            ->assertSee('24/08/2026')
            ->assertSee('07:30-08:40')
            ->assertSee('Jadwal Asli');

        $this->assertSame(0, PerangkatPembelajaran::count());
        $this->assertSame(0, JurnalMengajar::count());

        // 2. Eksekusi batch -> harus jatuh persis di 2026-08-19 (Rabu) & 2026-08-24 (Senin)
        $this->actingAs($this->guru)
            ->post(route('guru.pm.generate.batch'), $payload)
            ->assertRedirect(route('guru.pm.generate.index'));

        $jurnals = JurnalMengajar::orderBy('tanggal')->get();
        $this->assertCount(2, $jurnals);
        $this->assertSame('2026-08-19', $jurnals[0]->tanggal->toDateString());
        $this->assertSame('07:30-08:40', $jurnals[0]->jam_ke);
        $this->assertSame('2026-08-24', $jurnals[1]->tanggal->toDateString());
        $this->assertSame('07:30-08:40', $jurnals[1]->jam_ke);
    }

    public function test_cetak_bundel_administrasi_1_semester_pdf(): void
    {
        $res = $this->actingAs($this->guru)
            ->get(route('guru.pm.bundel.pdf', [
                'tahun_ajaran' => '2026/2027',
                'tingkat' => 1,
                'semester' => 1,
            ]));

        $res->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', (string) $res->getContent());
    }
}
