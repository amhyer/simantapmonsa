<?php

namespace Tests\Feature;

use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TkaSimulasiMandiriTest extends TestCase
{
    use RefreshDatabase;

    private User $guru;
    private User $studentAccount;
    private Siswa $siswa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->guru = User::factory()->guru()->create();
        $this->siswa = Siswa::create([
            'uuid' => (string) Str::uuid(),
            'guru_id' => $this->guru->id,
            'nama_guru' => $this->guru->nama_lengkap,
            'nis' => 'TKA001',
            'nama_peserta_didik' => 'Siswa Pusmendik',
            'kelas' => '6A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
        $this->studentAccount = User::factory()->siswa()->create([
            'nama_pengguna' => 'TKA001',
            'terhubung_dengan' => [$this->siswa->id],
        ]);
    }

    public function test_siswa_dapat_melihat_daftar_subtes_simulasi_tka(): void
    {
        $response = $this->actingAs($this->studentAccount)
            ->get(route('siswa.tka-simulasi.index'));

        $response->assertOk();
        $response->assertSee('Simulasi TKA Mandiri');
        $response->assertSee('Literasi Membaca');
        $response->assertSee('Numerasi & Penalaran Kuantitatif');
        $response->assertSee('Penalaran Sains & Logika Ilmiah');
    }

    public function test_halaman_konfirmasi_menampilkan_data_peserta_dan_token(): void
    {
        $response = $this->actingAs($this->studentAccount)
            ->get(route('siswa.tka-simulasi.confirm', 'literasi'));

        $response->assertOk();
        $response->assertSee('Konfirmasi Data Peserta TKA');
        $response->assertSee('Siswa Pusmendik');
        $response->assertSee('Token Ujian Simulasi');
    }

    public function test_gagal_mulai_jika_token_salah(): void
    {
        $response = $this->actingAs($this->studentAccount)
            ->post(route('siswa.tka-simulasi.start', 'literasi'), [
                'token' => 'SALAH123',
            ]);

        $response->assertSessionHas('error');
    }

    public function test_berhasil_mulai_ujian_dengan_token_dan_autosave(): void
    {
        $token = 'TKA-' . strtoupper(substr(md5('literasi' . $this->studentAccount->id . date('Ymd')), 0, 5));

        $start = $this->actingAs($this->studentAccount)
            ->post(route('siswa.tka-simulasi.start', 'literasi'), [
                'token' => $token,
            ]);

        $start->assertRedirect(route('siswa.tka-simulasi.exam', 'literasi'));

        $exam = $this->actingAs($this->studentAccount)
            ->get(route('siswa.tka-simulasi.exam', 'literasi'));

        $exam->assertOk();
        $exam->assertSee('Wacana');
        $exam->assertSee('Stimulus Asesmen');
        $exam->assertSee('Terumbu karang');

        // Test autosave AJAX
        $save = $this->actingAs($this->studentAccount)
            ->postJson(route('siswa.tka-simulasi.answer', 'literasi'), [
                'question_id' => 1,
                'answer' => 'B',
            ]);

        $save->assertOk();
        $save->assertJson(['saved' => true]);

        // Submit ujian
        $submit = $this->actingAs($this->studentAccount)
            ->post(route('siswa.tka-simulasi.submit', 'literasi'), [
                'answers' => [
                    1 => 'B', // Benar
                    2 => 'C', // Benar
                    3 => 'C', // Benar
                    4 => 'B', // Benar
                    5 => 'B', // Benar
                ],
            ]);

        $submit->assertRedirect(route('siswa.tka-simulasi.result', 'literasi'));

        // Cek hasil evaluasi
        $result = $this->actingAs($this->studentAccount)
            ->get(route('siswa.tka-simulasi.result', 'literasi'));

        $result->assertOk();
        $result->assertSee('100.0');
        $result->assertSee('Mahir');
        $result->assertSee('Pembahasan');
    }
}
