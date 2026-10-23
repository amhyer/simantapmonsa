<?php

namespace Tests\Feature;

use App\Models\Siswa;
use App\Models\Tryout\Category;
use App\Models\Tryout\Package;
use App\Models\Tryout\Result as TryoutResult;
use App\Models\Tryout\Session as TryoutSession;
use App\Models\User;
use App\Support\BackupTables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Bug yang dikunci di sini: tabel `tryout_*` dan `transkrip_nilai` tidak pernah
 * masuk cakupan backup maupun "Hapus Semua Data". Akibatnya wipe menyisakan
 * data Tryout/transkrip sementara data lain (dan engine `exam_*`) kosong, dan
 * backup tidak bisa memulihkannya.
 */
class BackupCoverageTest extends TestCase
{
    use RefreshDatabase;

    private const TABEL_TRYOUT = [
        'tryout_categories',
        'tryout_packages',
        'tryout_subjects',
        'tryout_questions',
        'tryout_question_options',
        'tryout_sessions',
        'tryout_results',
        'tryout_student_answers',
    ];

    public function test_tryout_dan_transkrip_masuk_cakupan_backup_dan_wipe(): void
    {
        $dicadangkan = BackupTables::tabel();
        $dikosongkan = BackupTables::dikosongkan();

        foreach (array_merge(self::TABEL_TRYOUT, ['transkrip_nilai']) as $tabel) {
            $this->assertContains($tabel, $dicadangkan, "$tabel harus dicadangkan.");
            $this->assertContains($tabel, $dikosongkan, "$tabel harus ikut dikosongkan.");
        }

        // Identitas inti tetap dipertahankan agar admin masih bisa login.
        foreach (BackupTables::tidakDikosongkan() as $tabel) {
            $this->assertContains($tabel, $dicadangkan);
            $this->assertNotContains($tabel, $dikosongkan);
        }
    }

    public function test_hapus_semua_data_mengosongkan_tryout_transkrip_dan_mempertahankan_akun(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create();
        $siswa = Siswa::create([
            'guru_id' => $guru->id, 'nama_guru' => $guru->nama_lengkap,
            'nis' => 'WIPE001', 'nama_peserta_didik' => 'Siswa Wipe', 'kelas' => '6A',
            'jenis_kelamin' => 'L', 'aktif' => true,
        ]);

        $kategori = Category::create(['nama_kategori' => 'Akademik']);
        $paket = Package::create([
            'category_id' => $kategori->id, 'kode_paket' => 'PKT-WIPE', 'nama_paket' => 'Paket Wipe',
            'jenjang' => 'SD', 'tahun_paket' => 2026, 'durasi_total_menit' => 60, 'passing_grade' => 60,
        ]);
        $sesi = TryoutSession::create([
            'kode_sesi' => 'SESI-WIPE', 'nama_sesi' => 'Sesi Wipe', 'package_id' => $paket->id,
            'guru_id' => $guru->id, 'kelas' => '6A',
            'tanggal_mulai' => now()->subMinute(), 'tanggal_selesai' => now()->addHour(),
            'durasi_menit' => 30, 'status' => 'ongoing',
        ]);
        TryoutResult::create([
            'tryout_session_id' => $sesi->id, 'siswa_id' => $siswa->id, 'attempt_number' => 1,
            'nilai_total' => 1, 'nilai_maksimal' => 1, 'persentase' => 100, 'lulus' => true,
            'detail_nilai_mapel' => [], 'waktu_mulai' => now()->subMinute(),
            'waktu_selesai' => now(), 'durasi_total_detik' => 60,
        ]);
        DB::table('transkrip_nilai')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'siswa_id' => $siswa->id, 'nama_mapel' => 'IPA', 'nilai_akhir' => 90,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.backup.wipe'), [
            'konfirmasi' => 'HAPUS SEMUA DATA',
            'password' => 'password',
        ])->assertRedirect(route('admin.backup.index'));

        foreach (array_merge(self::TABEL_TRYOUT, ['transkrip_nilai']) as $tabel) {
            $this->assertDatabaseCount($tabel, 0);
        }

        $this->assertDatabaseCount('users', 2); // admin + guru tetap ada
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
