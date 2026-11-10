<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\DapodikSyncLog;
use App\Models\Ptk;
use App\Models\Rombel;
use App\Models\SekolahSettings;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DapodikBridgeSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $apiKeyPlaintext;
    private ApiKey $apiKeyModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'uuid' => (string) Str::uuid(),
            'nama_lengkap' => 'Admin Sekolah',
            'nama_pengguna' => 'admin_bridge',
            'kata_sandi' => bcrypt('password123'),
            'peran' => 'admin',
            'aktif' => true,
        ]);

        $keyGen = ApiKey::generate($this->admin->id, 'Bridge Key Test');
        $this->apiKeyModel = $keyGen['model'];
        $this->apiKeyPlaintext = $keyGen['plaintext_key'];
    }

    public function test_ping_endpoint_returns_ok_without_auth(): void
    {
        $response = $this->postJson('/api/dapodik/ping');

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_sync_fails_without_api_key(): void
    {
        $response = $this->postJson('/api/dapodik/sync/sekolah', [
            'semester_id' => '20261',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
            'data' => [],
        ]);

        $response->assertStatus(401);
    }

    public function test_sync_fails_with_invalid_api_key(): void
    {
        $response = $this->withHeaders([
            'X-API-Key' => 'invalid-key-12345',
        ])->postJson('/api/dapodik/sync/sekolah', [
            'semester_id' => '20261',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
            'data' => [],
        ]);

        $response->assertStatus(401);
    }

    public function test_sync_sekolah(): void
    {
        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKeyPlaintext,
        ])->postJson('/api/dapodik/sync/sekolah', [
            'semester_id' => '20261',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
            'data' => [
                [
                    'npsn' => '40313912',
                    'nama' => 'SD Negeri Unggulan Mongisidi 1',
                    'alamat_jalan' => 'Jl. Mongisidi No. 1',
                    'telepon' => '0411-123456',
                    'email' => 'sdnungmongisidi1@gmail.com',
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'modul' => 'sekolah',
                'berhasil' => 1,
            ]);

        $this->assertDatabaseHas('sekolah_settings', [
            'npsn' => '40313912',
            'nama_sekolah' => 'SD Negeri Unggulan Mongisidi 1',
        ]);
    }

    public function test_sync_ptk_creates_ptk_and_teacher_user(): void
    {
        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKeyPlaintext,
        ])->postJson('/api/dapodik/sync/ptk', [
            'semester_id' => '20261',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
            'data' => [
                [
                    'ptk_id' => 'ptk-uuid-001',
                    'nama' => 'Budi Santoso, S.Pd',
                    'nip' => '198001012005011001',
                    'nuptk' => '1234567890123456',
                    'jenis_ptk' => 'Guru Kelas',
                    'jenis_kelamin' => 'L',
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'modul' => 'ptk',
                'berhasil' => 1,
            ]);

        $this->assertDatabaseHas('ptk', [
            'dapodik_id' => 'ptk-uuid-001',
            'nip' => '198001012005011001',
        ]);

        $this->assertDatabaseHas('users', [
            'nama_pengguna' => '198001012005011001',
            'peran' => 'guru',
        ]);
    }

    public function test_sync_rombongan_belajar(): void
    {
        // First sync PTK so wali kelas can be resolved
        $this->withHeaders([
            'X-API-Key' => $this->apiKeyPlaintext,
        ])->postJson('/api/dapodik/sync/ptk', [
            'semester_id' => '20261',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
            'data' => [
                [
                    'ptk_id' => 'ptk-uuid-001',
                    'nama' => 'Budi Santoso, S.Pd',
                    'nip' => '198001012005011001',
                    'nuptk' => '1234567890123456',
                    'jenis_ptk' => 'Guru Kelas',
                ],
            ],
        ]);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKeyPlaintext,
        ])->postJson('/api/dapodik/sync/rombongan-belajar', [
            'semester_id' => '20261',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
            'data' => [
                [
                    'rombongan_belajar_id' => 'rombel-uuid-1a',
                    'nama' => 'Kelas 1A',
                    'tingkat' => '1',
                    'kurikulum' => 'Kurikulum Merdeka',
                    'ptk_id' => 'ptk-uuid-001',
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'modul' => 'rombongan-belajar',
                'berhasil' => 1,
            ]);

        $this->assertDatabaseHas('rombel', [
            'dapodik_id' => 'rombel-uuid-1a',
            'nama_rombel' => 'Kelas 1A',
        ]);
    }

    public function test_sync_peserta_didik_links_rombel_and_creates_student(): void
    {
        // Setup semester & rombel first
        $semester = Semester::create([
            'semester_id' => '20261',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
        ]);

        $rombel = Rombel::create([
            'dapodik_id' => 'rombel-uuid-1a',
            'semester_id' => $semester->id,
            'nama_rombel' => '1A',
            'tingkat' => '1',
        ]);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKeyPlaintext,
        ])->postJson('/api/dapodik/sync/peserta-didik', [
            'semester_id' => '20261',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
            'data' => [
                [
                    'peserta_didik_id' => 'pd-uuid-001',
                    'nama' => 'Ahmad Rizki',
                    'nisn' => '0081234001',
                    'nis' => '2026001',
                    'jenis_kelamin' => 'L',
                    'rombel' => '1A',
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'modul' => 'peserta-didik',
                'berhasil' => 1,
            ]);

        $this->assertDatabaseHas('siswa', [
            'dapodik_id' => 'pd-uuid-001',
            'nama_peserta_didik' => 'Ahmad Rizki',
            'rombel_id' => $rombel->id,
        ]);
    }

    public function test_sync_status_endpoint(): void
    {
        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKeyPlaintext,
        ])->getJson('/api/dapodik/sync/status');

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'stats' => ['semesters', 'siswa', 'ptk', 'rombel', 'jadwal'],
            ]);
    }

    public function test_admin_can_download_bridge_exe(): void
    {
        // Binary build tidak disimpan dalam repository. Buat fixture kecil jika
        // artefak sungguhan tidak ada, dan jangan menimpa artefak yang sudah ada.
        $path = public_path('bridge/SIMANTAP-Bridge.exe');
        $created = !is_file($path);
        if ($created) {
            \Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($path));
            file_put_contents($path, 'bridge-test-fixture');
        }
        try {
            $response = $this->actingAs($this->admin)->get(route('admin.dapodik.download-bridge'));
            $response->assertOk();
            $this->assertStringContainsString('SIMANTAP-Bridge.exe', $response->headers->get('content-disposition'));
        } finally {
            if ($created) unlink($path);
        }
    }

    public function test_export_nilai_rapor_returns_data_for_bridge(): void
    {
        $semester = Semester::firstOrCreate(
            ['semester_id' => '20261'],
            ['tahun_ajaran' => '2026/2027', 'nama_semester' => 'ganjil']
        );

        $siswa = Siswa::create([
            'semester_id' => $semester->id,
            'dapodik_id' => 'pd-export-001',
            'nis' => 'EXP001',
            'nisn' => '1234567890',
            'nama_peserta_didik' => 'Siswa Export',
            'kelas' => '6A',
            'jenis_kelamin' => 'L',
        ]);

        \App\Models\NilaiErapot::create([
            'guru_id' => $this->admin->id,
            'siswa_id' => $siswa->id,
            'mata_pelajaran' => 'Matematika',
            'kelas' => '6A',
            'semester' => 'ganjil',
            'tahun_ajaran' => '2026/2027',
            'nilai_akhir' => 88.5,
            'predikat' => 'A',
            'deskripsi_capaian' => 'Sangat menguasai konsep matematika dasar.',
        ]);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKeyPlaintext,
        ])->getJson('/api/dapodik/sync/export/nilai-rapor?semester_id=20261');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'modul' => 'nilai-rapor',
                'total' => 1,
            ])
            ->assertJsonPath('data.0.peserta_didik_id', 'pd-export-001')
            ->assertJsonPath('data.0.mata_pelajaran', 'Matematika')
            ->assertJsonPath('data.0.nilai_akhir', 88.5);
    }

    public function test_export_kehadiran_returns_data_for_bridge(): void
    {
        $semester = Semester::firstOrCreate(
            ['semester_id' => '20261'],
            ['tahun_ajaran' => '2026/2027', 'nama_semester' => 'ganjil']
        );

        $siswa = Siswa::create([
            'semester_id' => $semester->id,
            'dapodik_id' => 'pd-export-002',
            'nis' => 'EXP002',
            'nama_peserta_didik' => 'Siswa Kehadiran',
            'kelas' => '6A',
            'jenis_kelamin' => 'P',
        ]);

        \App\Models\Kehadiran::create([
            'guru_id' => $this->admin->id,
            'siswa_id' => $siswa->id,
            'tanggal' => '2026-11-09',
            'status' => 'H',
            'keterangan' => 'Hadir tepat waktu',
        ]);

        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKeyPlaintext,
        ])->getJson('/api/dapodik/sync/export/kehadiran?semester_id=20261');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'modul' => 'kehadiran',
                'total' => 1,
            ])
            ->assertJsonPath('data.0.peserta_didik_id', 'pd-export-002')
            ->assertJsonPath('data.0.status', 'H');
    }

    public function test_export_rejects_unknown_module(): void
    {
        $response = $this->withHeaders([
            'X-API-Key' => $this->apiKeyPlaintext,
        ])->getJson('/api/dapodik/sync/export/modul-asing');

        $response->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_export_requires_api_key(): void
    {
        $this->getJson('/api/dapodik/sync/export/nilai-rapor')
            ->assertUnauthorized();
    }
}
