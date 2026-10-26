<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Fixture dengan ID dan payload stabil untuk RouteSweepTest dan Playwright;
 * waktu ujian relatif terhadap waktu seed dan hash password berasal dari env.
 *
 * Jangan panggil di staging/produksi. Pada `APP_ENV=e2e`, seeder hanya menerima
 * SQLite bernama e2e; di PHPUnit (`APP_ENV=testing`) ia memakai DB terisolasi
 * yang disediakan RefreshDatabase.
 */
class E2eFixtureSeeder extends Seeder
{
    public const USER_IDS = [
        'admin' => 9000,
        'guru_a' => 9001,
        'guru_b' => 9002,
        'siswa_a1' => 9003,
        'siswa_a2' => 9004,
        'siswa_b1' => 9005,
        'ortu_a1' => 9201,
        'ortu_b1' => 9202,
    ];

    /** IDs untuk seluruh akun yang dipakai oleh sweep lintas peran. */
    public const ROLE_IDS = self::USER_IDS;

    public const STUDENT_IDS = [
        'siswa_a1' => 9101,
        'siswa_a2' => 9102,
        'siswa_b1' => 9103,
    ];

    public const CBT_BANK_ID = 9301;
    public const CBT_SESSION_ID = 9311;
    public const CBT_RESULT_ID = 9321;

    public const TRYOUT_CATEGORY_ID = 9400;
    public const TRYOUT_PACKAGE_ID = 9401;
    public const TRYOUT_SUBJECT_ID = 9402;
    public const MATA_PELAJARAN_ID = 9403;
    public const TRYOUT_QUESTION_ID = 9404;
    public const TRYOUT_SESSION_ID = 9407;
    public const TRYOUT_RESULT_ID = 9411;

    public const TKA_ASSESSMENT_A_ID = 9501;
    public const TKA_ASSESSMENT_B_ID = 9502;
    public const TKA_PARENT_REPORT_ID = 9561;
    public const TKA_SEMESTER_A_ID = 9541;
    public const TKA_SEMESTER_B_ID = 9542;
    public const TKA_DIMENSION_ID = 9551;
    public const TKA_ITEM_LIGHT_ID = 9552;
    public const TKA_ITEM_HEAVY_ID = 9553;
    public const TKA_OTHER_DIMENSION_ID = 9554;
    public const TKA_OTHER_ITEM_ID = 9555;

    public const USERNAMES = [
        'admin' => 'e2e.admin',
        'guru_a' => 'e2e.guru.a',
        'guru_b' => 'e2e.guru.b',
        'siswa_a1' => 'e2e.siswa.a1',
        'siswa_a2' => 'e2e.siswa.a2',
        'siswa_b1' => 'e2e.siswa.b1',
        'ortu_a1' => 'e2e.ortu.a1',
        'ortu_b1' => 'e2e.ortu.b1',
    ];

    public function run(): void
    {
        $this->assertSafeEnvironment();

        $now = now();
        $this->seedSemesters($now);
        $this->seedUsers($now);
        $this->seedStudents($now);
        $this->seedReports($now);
        $this->seedCbt($now);
        $this->seedTryout($now);
        $this->seedTka($now);
    }

    private function assertSafeEnvironment(): void
    {
        if (! app()->environment('testing', 'e2e')) {
            throw new RuntimeException('E2eFixtureSeeder hanya boleh dijalankan di APP_ENV=testing atau e2e.');
        }

        if (app()->environment('e2e')) {
            $connection = DB::connection();
            $database = basename((string) $connection->getDatabaseName());

            if ($connection->getDriverName() !== 'sqlite' || ! str_contains($database, 'e2e')) {
                throw new RuntimeException('APP_ENV=e2e mewajibkan database SQLite terisolasi dengan nama yang memuat "e2e".');
            }
        }
    }

    private function seedSemesters($now): void
    {
        $this->put('semesters', self::TKA_SEMESTER_A_ID, [
            'semester_id' => 'E2E-2026-GANJIL',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->put('semesters', self::TKA_SEMESTER_B_ID, [
            'semester_id' => 'E2E-2026-GENAP',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'genap',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function seedUsers($now): void
    {
        $accounts = [
            'admin' => ['Administrator E2E', 'admin', []],
            'guru_a' => ['Guru E2E A', 'guru', []],
            'guru_b' => ['Guru E2E B', 'guru', []],
            'siswa_a1' => ['Siswa E2E A1', 'siswa', []],
            'siswa_a2' => ['Siswa E2E A2', 'siswa', []],
            'siswa_b1' => ['Siswa E2E B1', 'siswa', []],
            // String ID sengaja dipakai untuk menguji normalisasi relasi JSON ortu.
            'ortu_a1' => ['Orang Tua E2E A1', 'ortu', [(string) self::STUDENT_IDS['siswa_a1']]],
            'ortu_b1' => ['Orang Tua E2E B1', 'ortu', [(string) self::STUDENT_IDS['siswa_b1']]],
        ];

        foreach ($accounts as $key => [$name, $role, $linkedStudents]) {
            $this->put('users', self::USER_IDS[$key], [
                'uuid' => $this->uuid(self::USER_IDS[$key]),
                'nama_lengkap' => $name,
                'nama_pengguna' => self::USERNAMES[$key],
                'kata_sandi' => Hash::make($this->password($key)),
                'peran' => $role,
                'terhubung_dengan' => json_encode($linkedStudents, JSON_THROW_ON_ERROR),
                'kelas_mata_pelajaran' => null,
                'aktif' => true,
                'terakhir_masuk' => null,
                'remember_token' => null,
                'force_password_change' => false,
                'archived_at' => null,
                'dapodik_id' => null,
                'deleted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function seedStudents($now): void
    {
        $students = [
            'siswa_a1' => [self::USER_IDS['guru_a'], 'Siswa E2E A1', '6A', 'L'],
            'siswa_a2' => [self::USER_IDS['guru_a'], 'Siswa E2E A2', '6A', 'P'],
            'siswa_b1' => [self::USER_IDS['guru_b'], 'Siswa E2E B1', '6A', 'L'],
        ];

        foreach ($students as $key => [$guruId, $name, $kelas, $gender]) {
            $studentId = self::STUDENT_IDS[$key];
            $username = self::USERNAMES[$key];

            $this->put('siswa', $studentId, [
                'uuid' => $this->uuid($studentId),
                'dapodik_id' => null,
                'guru_id' => $guruId,
                'rombel_id' => null,
                'nama_guru' => $key === 'siswa_b1' ? 'Guru E2E B' : 'Guru E2E A',
                'semester_id' => self::TKA_SEMESTER_A_ID,
                'nis' => $username,
                'nisn' => 'E2E'.str_pad((string) $studentId, 10, '0', STR_PAD_LEFT),
                'nomor_ijazah' => null,
                'nama_peserta_didik' => $name,
                'kelas' => $kelas,
                'jenis_kelamin' => $gender,
                'nama_orang_tua' => 'Orang Tua '.$name,
                'aktif' => true,
                'status_siswa' => 'aktif',
                'rekaman' => null,
                'archived_at' => null,
                'deleted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function seedReports($now): void
    {
        $guruId = self::USER_IDS['guru_a'];
        $studentId = self::STUDENT_IDS['siswa_a1'];

        // Tidak ada baris pengaturan_guru: laporan harus dapat dirender tanpa
        // setelan guru (uji regresi ekspor e-Rapor yang sudah ada).
        foreach ([
            ['Matematika', 90],
            ['Bahasa Indonesia', 85],
        ] as $index => [$subject, $score]) {
            $this->put('nilai', 9600 + $index, [
                'uuid' => $this->uuid(9600 + $index),
                'guru_id' => $guruId,
                'siswa_id' => $studentId,
                'nama_guru' => 'Guru E2E A',
                'nama_siswa' => 'Siswa E2E A1',
                'tanggal' => $now->toDateString(),
                'jenis' => 'Ulangan Harian',
                'judul_penilaian' => 'Penilaian E2E '.($index + 1),
                'mata_pelajaran' => $subject,
                'nilai' => $score,
                'rekaman' => null,
                'deleted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->put('kehadiran', 9610, [
            'uuid' => $this->uuid(9610),
            'guru_id' => $guruId,
            'siswa_id' => $studentId,
            'tanggal' => $now->toDateString(),
            'status' => 'H',
            'keterangan' => null,
            'rekaman' => null,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function seedCbt($now): void
    {
        $bankId = self::CBT_BANK_ID;
        $guruId = self::USER_IDS['guru_a'];

        $this->put('cbt_question_banks', $bankId, [
            'guru_id' => $guruId,
            'title' => 'CBT E2E Matematika',
            'subject' => 'Matematika',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $questions = [];
        for ($i = 0; $i < 5; $i++) {
            $questionId = 9302 + $i;
            $questions[] = [
                'id' => $questionId,
                'prompt' => 'E2E soal '.($i + 1).': berapakah 2 + 2?',
                'options' => ['A' => '4', 'B' => '5', 'C' => '6', 'D' => '7'],
                'correct_answer' => 'A',
            ];
            $this->put('cbt_questions', $questionId, [
                'bank_id' => $bankId,
                'prompt' => 'E2E soal '.($i + 1).': berapakah 2 + 2?',
                'options' => json_encode(['A' => '4', 'B' => '5', 'C' => '6', 'D' => '7'], JSON_THROW_ON_ERROR),
                'correct_answer' => 'A',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $sessionId = self::CBT_SESSION_ID;
        $this->put('cbt_test_sessions', $sessionId, [
            'guru_id' => $guruId,
            'bank_id' => $bankId,
            'title' => 'CBT E2E Matematika',
            'kelas' => '6A',
            'opens_at' => $now->copy()->subMinutes(5),
            'closes_at' => $now->copy()->addHours(2),
            'duration_minutes' => 60,
            'status' => 'running',
            'question_snapshot' => json_encode($questions, JSON_THROW_ON_ERROR),
            'participant_ids' => json_encode([
                self::STUDENT_IDS['siswa_a1'],
                self::STUDENT_IDS['siswa_a2'],
            ], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function seedTryout($now): void
    {
        $this->put('tryout_categories', self::TRYOUT_CATEGORY_ID, [
            'uuid' => $this->uuid(self::TRYOUT_CATEGORY_ID),
            'nama_kategori' => 'E2E Akademik',
            'deskripsi' => 'Fixture uji otomatis',
            'jenis' => 'akademik',
            'aktif' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->put('mata_pelajaran', self::MATA_PELAJARAN_ID, [
            'nama' => 'Matematika E2E',
            'kode' => 'E2E-MTK',
            'jenjang' => 'SD',
            'aktif' => true,
            'kelompok' => null,
            'urutan' => 1,
            'masuk_transkrip' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->put('tryout_packages', self::TRYOUT_PACKAGE_ID, [
            'uuid' => $this->uuid(self::TRYOUT_PACKAGE_ID),
            'category_id' => self::TRYOUT_CATEGORY_ID,
            'kode_paket' => 'E2E-TRYOUT-1',
            'nama_paket' => 'Paket Tryout E2E',
            'deskripsi' => 'Fixture uji otomatis',
            'jenjang' => 'SD',
            'tahun_paket' => 2026,
            'jumlah_mapel' => 1,
            'durasi_total_menit' => 60,
            'passing_grade' => 60,
            'standar_nilai' => null,
            'aktif' => true,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->put('tryout_subjects', self::TRYOUT_SUBJECT_ID, [
            'uuid' => $this->uuid(self::TRYOUT_SUBJECT_ID),
            'package_id' => self::TRYOUT_PACKAGE_ID,
            'mata_pelajaran_id' => self::MATA_PELAJARAN_ID,
            'urutan' => 1,
            'bobot' => 1,
            'jumlah_soal' => 1,
            'durasi_menit' => 60,
            'passing_grade_mapel' => 60,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->put('tryout_questions', self::TRYOUT_QUESTION_ID, [
            'uuid' => $this->uuid(self::TRYOUT_QUESTION_ID),
            'tryout_subject_id' => self::TRYOUT_SUBJECT_ID,
            'urutan' => 1,
            'tipe_soal' => 'pilihan_ganda',
            'pertanyaan' => 'E2E Tryout: berapakah 2 + 2?',
            'media_type' => 'text',
            'media_url' => null,
            'poin' => 1,
            'tingkat_kesulitan' => 'mudah',
            'pembahasan' => 'Empat.',
            'aktif' => true,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ([
            [9405, 'A', '4', true],
            [9406, 'B', '5', false],
        ] as [$id, $letter, $text, $correct]) {
            $this->put('tryout_question_options', $id, [
                'question_id' => self::TRYOUT_QUESTION_ID,
                'huruf' => $letter,
                'isi_opsi' => $text,
                'adalah_benar' => $correct,
                'urutan' => $letter === 'A' ? 1 : 2,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->put('tryout_sessions', self::TRYOUT_SESSION_ID, [
            'uuid' => $this->uuid(self::TRYOUT_SESSION_ID),
            'kode_sesi' => 'E2E-TRYOUT-SESSION-1',
            'nama_sesi' => 'Tryout E2E A',
            'package_id' => self::TRYOUT_PACKAGE_ID,
            'semester_id' => self::TKA_SEMESTER_A_ID,
            'guru_id' => self::USER_IDS['guru_a'],
            'kelas' => '6A',
            'rombel_id' => null,
            'tanggal_mulai' => $now->copy()->subMinutes(5),
            'tanggal_selesai' => $now->copy()->addHours(2),
            'durasi_menit' => 60,
            'instruksi' => 'Jawab satu soal fixture.',
            'status' => 'ongoing',
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Percobaan in-progress dengan ID stabil; bridge akan memasangkan row
        // ini ke exam_attempt saat siswa memulai, lalu mencerminkan hasilnya.
        $this->put('tryout_results', self::TRYOUT_RESULT_ID, [
            'uuid' => $this->uuid(self::TRYOUT_RESULT_ID),
            'tryout_session_id' => self::TRYOUT_SESSION_ID,
            'siswa_id' => self::STUDENT_IDS['siswa_a1'],
            'attempt_number' => 1,
            'nilai_total' => 0,
            'nilai_maksimal' => 1,
            'persentase' => 0,
            'peringkat' => null,
            'lulus' => false,
            'prediksi_peminatan' => null,
            'detail_nilai_mapel' => json_encode([], JSON_THROW_ON_ERROR),
            'analisis_kelemahan' => json_encode([], JSON_THROW_ON_ERROR),
            'rekomendasi_pembelajaran' => null,
            'waktu_mulai' => $now->copy()->subMinute(),
            'waktu_selesai' => null,
            'durasi_total_detik' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function seedTka($now): void
    {
        $this->put('tka_dimensions_enhanced', self::TKA_DIMENSION_ID, [
            'uuid' => $this->uuid(self::TKA_DIMENSION_ID),
            'semester_id' => self::TKA_SEMESTER_A_ID,
            'kode_dimensi' => 'E2E-DIM-01',
            'nama_dimensi' => 'Bernalar Kritis E2E',
            'deskripsi' => 'Dimensi fixture untuk tes skor berbobot.',
            'kategori' => 'mandiri',
            'bobot' => 1,
            'rubrik_penilaian' => json_encode([1 => 'Perlu Bimbingan', 2 => 'Cukup', 3 => 'Baik', 4 => 'Sangat Baik'], JSON_THROW_ON_ERROR),
            'threshold_siap' => 3,
            'aktif' => true,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ([
            [self::TKA_ITEM_LIGHT_ID, 'E2E indikator bobot ringan', 1],
            [self::TKA_ITEM_HEAVY_ID, 'E2E indikator bobot berat', 3],
        ] as [$id, $indicator, $weight]) {
            $this->put('tka_assessment_items', $id, [
                'uuid' => $this->uuid($id),
                'dimension_id' => self::TKA_DIMENSION_ID,
                'urutan' => $id === self::TKA_ITEM_LIGHT_ID ? 1 : 2,
                'indikator' => $indicator,
                'pertanyaan_penilaian' => "Nilai indikator {$indicator}.",
                'tipe_jawaban' => 'skala_1_4',
                'bobot_item' => $weight,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->put('tka_dimensions_enhanced', self::TKA_OTHER_DIMENSION_ID, [
            'uuid' => $this->uuid(self::TKA_OTHER_DIMENSION_ID),
            'semester_id' => self::TKA_SEMESTER_A_ID,
            'kode_dimensi' => 'E2E-DIM-02',
            'nama_dimensi' => 'Gotong Royong E2E',
            'deskripsi' => 'Dimensi kedua untuk tes URL nested.',
            'kategori' => 'gotong_royong',
            'bobot' => 1,
            'rubrik_penilaian' => json_encode([1 => 'Perlu Bimbingan', 2 => 'Cukup', 3 => 'Baik', 4 => 'Sangat Baik'], JSON_THROW_ON_ERROR),
            'threshold_siap' => 3,
            'aktif' => true,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->put('tka_assessment_items', self::TKA_OTHER_ITEM_ID, [
            'uuid' => $this->uuid(self::TKA_OTHER_ITEM_ID),
            'dimension_id' => self::TKA_OTHER_DIMENSION_ID,
            'urutan' => 1,
            'indikator' => 'E2E item dimensi lain',
            'pertanyaan_penilaian' => 'Item ini tidak boleh dihapus melalui dimensi lain.',
            'tipe_jawaban' => 'skala_1_4',
            'bobot_item' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->seedStudentAssessment(
            self::TKA_ASSESSMENT_A_ID,
            self::STUDENT_IDS['siswa_a1'],
            self::USER_IDS['guru_a'],
            $now,
            13,
            16,
            81.25,
            'siap'
        );
        $this->seedStudentAssessment(
            self::TKA_ASSESSMENT_B_ID,
            self::STUDENT_IDS['siswa_b1'],
            self::USER_IDS['guru_b'],
            $now,
            4,
            4,
            100,
            'sangat_siap'
        );

        $this->put('tka_parent_reports', self::TKA_PARENT_REPORT_ID, [
            'uuid' => $this->uuid(self::TKA_PARENT_REPORT_ID),
            'assessment_id' => self::TKA_ASSESSMENT_A_ID,
            'siswa_id' => self::STUDENT_IDS['siswa_a1'],
            'ortu_user_id' => null,
            'dibaca_oleh_ortu' => false,
            'tanggal_dibaca' => null,
            'rekomendasi_rumah' => 'Dampingi latihan bernalar kritis.',
            'aktivitas_suggested' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function seedStudentAssessment(
        int $id,
        int $studentId,
        int $guruId,
        $now,
        float $total,
        float $max,
        float $percentage,
        string $status
    ): void {
        $this->put('tka_student_assessments', $id, [
            'uuid' => $this->uuid($id),
            'siswa_id' => $studentId,
            'semester_id' => self::TKA_SEMESTER_A_ID,
            'guru_id' => $guruId,
            'tanggal_penilaian' => $now->toDateString(),
            'total_score' => $total,
            'max_score' => $max,
            'persentase' => $percentage,
            'status_kesiapan' => $status,
            'skor_per_dimensi' => json_encode([[
                'dimension_id' => self::TKA_DIMENSION_ID,
                'nama_dimensi' => 'Bernalar Kritis E2E',
                'kategori' => 'mandiri',
                'total_skor' => $total,
                'maksimal' => $max,
            ]], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function password(string $account): string
    {
        $envName = 'E2E_'.strtoupper($account).'_PASSWORD';
        $value = env($envName);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (app()->environment('testing')) {
            // Feature tests use actingAs(); no fixture password is needed.
            return Str::random(48);
        }

        throw new RuntimeException("Environment {$envName} wajib diisi untuk APP_ENV=e2e.");
    }

    private function put(string $table, int $id, array $values): void
    {
        DB::table($table)->updateOrInsert(['id' => $id], ['id' => $id] + $values);
    }

    private function uuid(int $id): string
    {
        return 'e2e00000-0000-4000-8000-'.str_pad(dechex($id), 12, '0', STR_PAD_LEFT);
    }
}
