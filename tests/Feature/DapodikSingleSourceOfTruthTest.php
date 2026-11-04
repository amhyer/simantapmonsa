<?php

namespace Tests\Feature;

use App\Models\CbtQuestionBank;
use App\Models\CbtTestSession;
use App\Models\Nilai;
use App\Models\Rombel;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use App\Services\CbtExamService;
use App\Services\Exam\CbtStudentReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DapodikSingleSourceOfTruthTest extends TestCase
{
    use RefreshDatabase;

    private User $guruMapel;
    private User $waliKelas;
    private Siswa $siswaDapodik;
    private Semester $semester;
    private Rombel $rombel;

    protected function setUp(): void
    {
        putenv('SIMANTAP_CBT_ENABLED=true');
        $_ENV['SIMANTAP_CBT_ENABLED'] = $_SERVER['SIMANTAP_CBT_ENABLED'] = 'true';
        \Illuminate\Support\Env::enablePutenv();

        parent::setUp();
        $this->withoutVite();

        $this->waliKelas = User::factory()->guru()->create(['nama_lengkap' => 'Wali Kelas 7A']);
        $this->guruMapel = User::factory()->guru()->create(['nama_lengkap' => 'Guru IPA 7A']);

        $this->semester = Semester::create([
            'semester_id' => '20261',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
        ]);

        $this->rombel = Rombel::create([
            'dapodik_id' => 'rombel-7a-uuid',
            'semester_id' => $this->semester->id,
            'nama_rombel' => 'VII-A',
            'tingkat' => '7',
            'guru_id' => $this->waliKelas->id,
        ]);

        // Siswa ditarik dari Dapodik (terhubung ke rombel dan wali kelas)
        $this->siswaDapodik = Siswa::create([
            'dapodik_id' => 'siswa-dapodik-001',
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel->id,
            'guru_id' => $this->waliKelas->id,
            'nis' => 'DAPO001',
            'nisn' => '0012345678',
            'nama_peserta_didik' => 'Budi Dapodik',
            'kelas' => 'VII-A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
    }

    public function test_guru_mapel_dapat_melihat_siswa_dapodik_di_daftar_siswa(): void
    {
        // Guru IPA (bukan wali kelas) membuka data siswa
        $response = $this->actingAs($this->guruMapel)
            ->get(route('guru.siswa.index', ['kelas' => 'VII-A']));

        $response->assertOk();
        $response->assertSee('Budi Dapodik');
        $response->assertSee('DAPO001');
        $response->assertSee('Single Source of Truth');
    }

    public function test_guru_mapel_dapat_membuat_dan_mempublikasikan_cbt_untuk_siswa_dapodik(): void
    {
        // 1. Guru IPA membuat bank soal
        $bank = CbtQuestionBank::create([
            'guru_id' => $this->guruMapel->id,
            'title' => 'IPA Kelas 7',
            'subject' => 'IPA',
        ]);
        $bank->questions()->create([
            'prompt' => 'Apa rumus fotosintesis?',
            'options' => ['A' => '6CO2 + 6H2O', 'B' => 'H2O', 'C' => 'NaCl', 'D' => 'O2'],
            'correct_answer' => 'A',
        ]);

        // 2. Guru IPA membuat sesi ujian CBT untuk kelas VII-A
        $session = CbtTestSession::create([
            'guru_id' => $this->guruMapel->id,
            'bank_id' => $bank->id,
            'title' => 'Ulangan Harian IPA VII-A',
            'kelas' => 'VII-A',
            'opens_at' => now()->subMinute(),
            'closes_at' => now()->addHours(2),
            'duration_minutes' => 60,
            'status' => 'draft',
        ]);

        // 3. Publikasikan sesi (sebelumnya gagal karena siswa.guru_id != session.guru_id)
        $service = app(CbtExamService::class);
        $service->publish($session);

        $session->refresh();
        $this->assertSame('published', $session->status);
        $this->assertContains($this->siswaDapodik->id, $session->participant_ids);

        // 4. Verifikasi siswa eligible untuk ujian
        $this->assertTrue($service->eligible($session, $this->siswaDapodik));

        // 5. Verifikasi sesi terbaca di daftar ujian siswa
        $reader = app(CbtStudentReader::class);
        $daftarSesi = $reader->sesiSiswa($this->siswaDapodik);
        $this->assertCount(1, $daftarSesi->items());
    }

    public function test_guru_mapel_dapat_memberi_nilai_untuk_siswa_dapodik(): void
    {
        // Guru IPA memberi nilai untuk siswa Dapodik
        $response = $this->actingAs($this->guruMapel)->post(route('guru.nilai.store'), [
            'siswa_id' => $this->siswaDapodik->id,
            'tanggal' => now()->toDateString(),
            'jenis' => 'Ulangan Harian',
            'judul_penilaian' => 'UH IPA Bab 1',
            'mata_pelajaran' => 'IPA',
            'nilai' => 88.5,
        ]);

        $response->assertRedirect(route('guru.nilai.index'));
        $this->assertDatabaseHas('nilai', [
            'guru_id' => $this->guruMapel->id,
            'siswa_id' => $this->siswaDapodik->id,
            'nilai' => 88.5,
        ]);
    }
}
