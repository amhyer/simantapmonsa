# CBT - TRYOUT - TKA MODULE DESIGN
## Detail Implementation Plan for SIMANTAP Assessment Modules

---

## 📋 TABLE OF CONTENTS

1. [Database Schema Design](#1-database-schema-design)
2. [Models & Relationships](#2-models--relationships)
3. [Controllers & API Endpoints](#3-controllers--api-endpoints)
4. [UI/UX Flow & Frontend Structure](#4-uiux-flow--frontend-structure)
5. [Security & Anti-Cheat Measures](#5-security--anti-cheat-measures)
6. [Implementation Roadmap](#6-implementation-roadmap)
7. [Testing Strategy](#7-testing-strategy)
8. [Performance Optimization](#8-performance-optimization)

---

## 1. DATABASE SCHEMA DESIGN

### 1.1 CBT MODULE TABLES

#### **cbt_question_banks** (Bank Soal CBT)
```sql
CREATE TABLE cbt_question_banks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    mata_pelajaran_id BIGINT UNSIGNED NOT NULL,
    semester_id BIGINT UNSIGNED NOT NULL,
    guru_id BIGINT UNSIGNED NOT NULL,
    kode_bank VARCHAR(50) UNIQUE NOT NULL,
    nama_bank VARCHAR(255) NOT NULL,
    deskripsi TEXT,
    tingkat_kesulitan ENUM('mudah', 'sedang', 'sulit') DEFAULT 'sedang',
    kategori_soal VARCHAR(100) COMMENT 'Kategori: materi, hots, dsb',
    jumlah_soal INT DEFAULT 0,
    aktif BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,

    INDEX idx_mapel_semester (mata_pelajaran_id, semester_id),
    INDEX idx_guru (guru_id),
    INDEX idx_tingkat (tingkat_kesulitan),
    INDEX idx_aktif (aktif),
    FOREIGN KEY (mata_pelajaran_id) REFERENCES mata_pelajaran(id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES semester(id) ON DELETE CASCADE,
    FOREIGN KEY (guru_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### **cbt_questions** (Soal Individual)
```sql
CREATE TABLE cbt_questions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    question_bank_id BIGINT UNSIGNED NOT NULL,
    urutan INT DEFAULT 0,
    tipe_soal ENUM('pilihan_ganda', 'isian_singkat', 'benar_salah', 'menjodohkan', 'essay') DEFAULT 'pilihan_ganda',
    pertanyaan TEXT NOT NULL,
    media_type ENUM('text', 'image', 'audio', 'video') DEFAULT 'text',
    media_url VARCHAR(500) NULL COMMENT 'URL gambar/audio/video',
    poin FLOAT DEFAULT 1.0,
    waktu_jawab_detik INT DEFAULT 0 COMMENT '0 = unlimited',
    pembahasan TEXT NULL,
    aktif BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,

    INDEX idx_bank (question_bank_id),
    INDEX idx_tipe (tipe_soal),
    INDEX idx_aktif (aktif),
    FOREIGN KEY (question_bank_id) REFERENCES cbt_question_banks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### **cbt_question_options** (Opsi Jawaban untuk Pilihan Ganda)
```sql
CREATE TABLE cbt_question_options (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id BIGINT UNSIGNED NOT NULL,
    huruf ENUM('A', 'B', 'C', 'D', 'E') NOT NULL,
    isi_opsi TEXT NOT NULL,
    adalah_benar BOOLEAN DEFAULT false,
    urutan INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_huruf_per_question (question_id, huruf),
    INDEX idx_question (question_id),
    FOREIGN KEY (question_id) REFERENCES cbt_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### **cbt_test_sessions** (Sesi Ujian CBT)
```sql
CREATE TABLE cbt_test_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    kode_sesi VARCHAR(20) UNIQUE NOT NULL,
    nama_sesi VARCHAR(255) NOT NULL,
    question_bank_id BIGINT UNSIGNED NOT NULL,
    mata_pelajaran_id BIGINT UNSIGNED NOT NULL,
    semester_id BIGINT UNSIGNED NOT NULL,
    guru_id BIGINT UNSIGNED NOT NULL,
    kelas VARCHAR(50) NULL COMMENT 'Kosong = multi-kelas',
    rombel_id BIGINT UNSIGNED NULL,
    tanggal_mulai DATETIME NOT NULL,
    tanggal_selesai DATETIME NOT NULL,
    durasi_menit INT NOT NULL,
    jumlah_soal_tampil INT NOT NULL COMMENT 'Jumlah soal yang ditampilkan ke siswa',
    acak_soal BOOLEAN DEFAULT true,
    acak_opsi BOOLEAN DEFAULT true,
    tampilkan_nilai BOOLEAN DEFAULT false,
    boleh_ulang BOOLEAN DEFAULT false,
    maksimal_percobaan INT DEFAULT 1,
    passing_grade FLOAT DEFAULT 60.0,
    status ENUM('draft', 'published', 'ongoing', 'completed', 'archived') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,

    INDEX idx_bank (question_bank_id),
    INDEX idx_mapel_semester (mata_pelajaran_id, semester_id),
    INDEX idx_guru (guru_id),
    INDEX idx_status (status),
    INDEX idx_tanggal (tanggal_mulai, tanggal_selesai),
    FOREIGN KEY (question_bank_id) REFERENCES cbt_question_banks(id) ON DELETE CASCADE,
    FOREIGN KEY (mata_pelajaran_id) REFERENCES mata_pelajaran(id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES semester(id) ON DELETE CASCADE,
    FOREIGN KEY (guru_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (rombel_id) REFERENCES rombel(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### **cbt_student_answers** (Jawaban Siswa)
```sql
CREATE TABLE cbt_student_answers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    test_session_id BIGINT UNSIGNED NOT NULL,
    siswa_id BIGINT UNSIGNED NOT NULL,
    question_id BIGINT UNSIGNED NOT NULL,
    jawaban TEXT NULL COMMENT 'Untuk pilihan ganda: huruf, untuk essay: text',
    adalah_benar BOOLEAN NULL COMMENT 'NULL = belum dinilai (essay)',
    poin_diperoleh FLOAT DEFAULT 0.0,
    waktu_mulai_jawab DATETIME NULL,
    waktu_selesai_jawab DATETIME NULL,
    durasi_detik INT NULL,
    attempt_number INT DEFAULT 1,
    ragu_ragu BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_siswa_question_attempt (test_session_id, siswa_id, question_id, attempt_number),
    INDEX idx_session_siswa (test_session_id, siswa_id),
    INDEX idx_question (question_id),
    INDEX idx_benar (adalah_benar),
    FOREIGN KEY (test_session_id) REFERENCES cbt_test_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES cbt_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### **cbt_test_results** (Hasil Ujian Siswa)
```sql
CREATE TABLE cbt_test_results (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    test_session_id BIGINT UNSIGNED NOT NULL,
    siswa_id BIGINT UNSIGNED NOT NULL,
    attempt_number INT DEFAULT 1,
    nilai_akhir FLOAT NOT NULL,
    nilai_maksimal FLOAT NOT NULL,
    persentase FLOAT NOT NULL,
    lulus BOOLEAN NOT NULL,
    jumlah_benar INT DEFAULT 0,
    jumlah_salah INT DEFAULT 0,
    jumlah_tidak_dijawab INT DEFAULT 0,
    waktu_mulai DATETIME NOT NULL,
    waktu_selesai DATETIME NOT NULL,
    durasi_detik INT NOT NULL,
    submitted_by ENUM('siswa', 'auto', 'guru') DEFAULT 'siswa',
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    integrity_score FLOAT DEFAULT 100.0 COMMENT 'Score kejujuran (0-100)',
    integrity_violations JSON NULL COMMENT 'Log pelanggaran kejujuran',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_siswa_session_attempt (test_session_id, siswa_id, attempt_number),
    INDEX idx_session (test_session_id),
    INDEX idx_siswa (siswa_id),
    INDEX idx_lulus (lulus),
    INDEX idx_nilai (nilai_akhir),
    INDEX idx_tanggal (waktu_mulai),
    FOREIGN KEY (test_session_id) REFERENCES cbt_test_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### **cbt_integrity_logs** (Log Keamanan/Anti-Cheat)
```sql
CREATE TABLE cbt_integrity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    test_result_id BIGINT UNSIGNED NOT NULL,
    siswa_id BIGINT UNSIGNED NOT NULL,
    jenis_pelanggaran ENUM('tab_switch', 'copy_paste', 'screen_capture', 'focus_lost', 'multiple_tabs', 'suspicious_activity') NOT NULL,
    timestamp DATETIME NOT NULL,
    deskripsi TEXT NULL,
    data_tambahan JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_result (test_result_id),
    INDEX idx_siswa (siswa_id),
    INDEX idx_jenis (jenis_pelanggaran),
    INDEX idx_timestamp (timestamp),
    FOREIGN KEY (test_result_id) REFERENCES cbt_test_results(id) ON DELETE CASCADE,
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 1.2 TRYOUT MODULE TABLES

#### **tryout_categories** (Kategori Tryout)
```sql
CREATE TABLE tryout_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    nama_kategori VARCHAR(255) NOT NULL,
    deskripsi TEXT,
    jenis ENUM('akademik', 'masuk_ptn', 'karir', 'umum') DEFAULT 'akademik',
    aktif BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_jenis (jenis),
    INDEX idx_aktif (aktif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### **tryout_packages** (Paket Tryout)
```sql
CREATE TABLE tryout_packages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    kode_paket VARCHAR(50) UNIQUE NOT NULL,
    nama_paket VARCHAR(255) NOT NULL,
    deskripsi TEXT,
    jenjang ENUM('SD', 'SMP', 'SMA', 'SMK', 'UMUM') NOT NULL,
    tahun_paket YEAR NOT NULL,
    jumlah_mapel INT DEFAULT 0,
    durasi_total_menit INT NOT NULL,
    passing_grade FLOAT DEFAULT 60.0,
    standar_nilai JSON NULL COMMENT 'Standar nilai per mapel',
    aktif BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,

    INDEX idx_category (category_id),
    INDEX idx_jenjang (jenjang),
    INDEX idx_tahun (tahun_paket),
    INDEX idx_aktif (aktif),
    FOREIGN KEY (category_id) REFERENCES tryout_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### **tryout_subjects** (Mata Pelajaran dalam Tryout)
```sql
CREATE TABLE tryout_subjects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    package_id BIGINT UNSIGNED NOT NULL,
    mata_pelajaran_id BIGINT UNSIGNED NOT NULL,
    urutan INT DEFAULT 0,
    bobot FLOAT DEFAULT 1.0 COMMENT 'Bobot penilaian',
    jumlah_soal INT DEFAULT 0,
    durasi_menit INT NOT NULL,
    passing_grade_mapel FLOAT DEFAULT 60.0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_package (package_id),
    INDEX idx_mapel (mata_pelajaran_id),
    FOREIGN KEY (package_id) REFERENCES tryout_packages(id) ON DELETE CASCADE,
    FOREIGN KEY (mata_pelajaran_id) REFERENCES mata_pelajaran(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### **tryout_questions** (Soal Tryout - separate dari CBT)
```sql
CREATE TABLE tryout_questions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    tryout_subject_id BIGINT UNSIGNED NOT NULL,
    urutan INT DEFAULT 0,
    tipe_soal ENUM('pilihan_ganda', 'isian_singkat', 'benar_salah') DEFAULT 'pilihan_ganda',
    pertanyaan TEXT NOT NULL,
    media_type ENUM('text', 'image', 'audio', 'video') DEFAULT 'text',
    media_url VARCHAR(500) NULL,
    poin FLOAT DEFAULT 1.0,
    tingkat_kesulitan ENUM('mudah', 'sedang', 'sulit') DEFAULT 'sedang',
    pembahasan TEXT NULL,
    aktif BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,

    INDEX idx_subject (tryout_subject_id),
    INDEX idx_tingkat (tingkat_kesulitan),
    INDEX idx_aktif (aktif),
    FOREIGN KEY (tryout_subject_id) REFERENCES tryout_subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### **tryout_question_options** (Opsi Tryout)
```sql
CREATE TABLE tryout_question_options (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id BIGINT UNSIGNED NOT NULL,
    huruf ENUM('A', 'B', 'C', 'D', 'E') NOT NULL,
    isi_opsi TEXT NOT NULL,
    adalah_benar BOOLEAN DEFAULT false,
    urutan INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_huruf_per_question (question_id, huruf),
    INDEX idx_question (question_id),
    FOREIGN KEY (question_id) REFERENCES tryout_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### **tryout_sessions** (Sesi Tryout)
```sql
CREATE TABLE tryout_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    kode_sesi VARCHAR(20) UNIQUE NOT NULL,
    nama_sesi VARCHAR(255) NOT NULL,
    package_id BIGINT UNSIGNED NOT NULL,
    semester_id BIGINT UNSIGNED NOT NULL,
    guru_id BIGINT UNSIGNED NOT NULL,
    kelas VARCHAR(50) NULL,
    rombel_id BIGINT UNSIGNED NULL,
    tanggal_mulai DATETIME NOT NULL,
    tanggal_selesai DATETIME NOT NULL,
    status ENUM('draft', 'published', 'ongoing', 'completed', 'archived') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,

    INDEX idx_package (package_id),
    INDEX idx_semester (semester_id),
    INDEX idx_guru (guru_id),
    INDEX idx_status (status),
    INDEX idx_tanggal (tanggal_mulai, tanggal_selesai),
    FOREIGN KEY (package_id) REFERENCES tryout_packages(id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES semester(id) ON DELETE CASCADE,
    FOREIGN KEY (guru_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (rombel_id) REFERENCES rombel(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### **tryout_student_answers** (Jawaban Tryout)
```sql
CREATE TABLE tryout_student_answers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    tryout_session_id BIGINT UNSIGNED NOT NULL,
    tryout_subject_id BIGINT UNSIGNED NOT NULL,
    siswa_id BIGINT UNSIGNED NOT NULL,
    question_id BIGINT UNSIGNED NOT NULL,
    jawaban TEXT NULL,
    adalah_benar BOOLEAN NULL,
    poin_diperoleh FLOAT DEFAULT 0.0,
    waktu_mulai_jawab DATETIME NULL,
    waktu_selesai_jawab DATETIME NULL,
    durasi_detik INT NULL,
    attempt_number INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_siswa_question_attempt (tryout_session_id, siswa_id, question_id, attempt_number),
    INDEX idx_session_siswa (tryout_session_id, siswa_id),
    INDEX idx_subject (tryout_subject_id),
    INDEX idx_question (question_id),
    FOREIGN KEY (tryout_session_id) REFERENCES tryout_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (tryout_subject_id) REFERENCES tryout_subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES tryout_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### **tryout_results** (Hasil Tryout)
```sql
CREATE TABLE tryout_results (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    tryout_session_id BIGINT UNSIGNED NOT NULL,
    siswa_id BIGINT UNSIGNED NOT NULL,
    attempt_number INT DEFAULT 1,
    nilai_total FLOAT NOT NULL,
    nilai_maksimal FLOAT NOT NULL,
    persentase FLOAT NOT NULL,
    peringkat INT NULL COMMENT 'Peringkat dalam sesi',
    lulus BOOLEAN NOT NULL,
    prediksi_peminatan JSON NULL COMMENT 'Prediksi jurusan/minat',
    detail_nilai_mapel JSON NOT NULL COMMENT 'Nilai per mata pelajaran',
    analisis_kelemahan JSON NULL COMMENT 'Mapel yang lemah',
    rekomendasi_pembelajaran TEXT NULL,
    waktu_mulai DATETIME NOT NULL,
    waktu_selesai DATETIME NOT NULL,
    durasi_total_detik INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_siswa_session_attempt (tryout_session_id, siswa_id, attempt_number),
    INDEX idx_session (tryout_session_id),
    INDEX idx_siswa (siswa_id),
    INDEX idx_lulus (lulus),
    INDEX idx_peringkat (peringkat),
    INDEX idx_tanggal (waktu_mulai),
    FOREIGN KEY (tryout_session_id) REFERENCES tryout_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### **tryout_norm_references** (Referensi Norma/Standar)
```sql
CREATE TABLE tryout_norm_references (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    package_id BIGINT UNSIGNED NOT NULL,
    jenis_norma ENUM('nasional', 'provinsi', 'kabupaten', 'sekolah') NOT NULL,
    tahun_pelajaran VARCHAR(20) NOT NULL,
    nilai_rata_rata FLOAT NOT NULL,
    nilai_minimal FLOAT NOT NULL,
    nilai_maksimal FLOAT NOT NULL,
    distribusi_nilai JSON NULL COMMENT 'Percentile distribution',
    jumlah_peserta INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_package (package_id),
    INDEX idx_jenis (jenis_norma),
    INDEX idx_tahun (tahun_pelajaran),
    FOREIGN KEY (package_id) REFERENCES tryout_packages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 1.3 TKA ENHANCEMENT TABLES

#### **tka_dimensions_enhanced** (Enhanced Dimensi/Profil Lulusan)
```sql
CREATE TABLE tka_dimensions_enhanced (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    semester_id BIGINT UNSIGNED NOT NULL,
    kode_dimensi VARCHAR(50) UNIQUE NOT NULL,
    nama_dimensi VARCHAR(255) NOT NULL,
    deskripsi TEXT,
    kategori ENUM('religius', 'nasionalis', 'mandiri', 'gotong_royong', 'integritas', 'kreatif', 'berdaya_saing') NOT NULL,
    bobot FLOAT DEFAULT 1.0,
    rubrik_penilaian JSON NOT NULL COMMENT 'Rubrik scoring: {1: "belum", 2: "sedang", 3: "baik", 4: "sangat baik"}',
    threshold_siap FLOAT DEFAULT 3.0 COMMENT 'Threshold untuk dianggap siap',
    aktif BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,

    INDEX idx_semester (semester_id),
    INDEX idx_kategori (kategori),
    INDEX idx_aktif (aktif),
    FOREIGN KEY (semester_id) REFERENCES semester(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### **tka_assessment_items** (Item Penilaian per Dimensi)
```sql
CREATE TABLE tka_assessment_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    dimension_id BIGINT UNSIGNED NOT NULL,
    urutan INT DEFAULT 0,
    indikator VARCHAR(500) NOT NULL,
    pertanyaan_penilaian TEXT NOT NULL,
    tipe_jawaban ENUM('skala_1_4', 'skala_1_5', 'ya_tidak', 'checklist') DEFAULT 'skala_1_4',
    bobot_item FLOAT DEFAULT 1.0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_dimension (dimension_id),
    FOREIGN KEY (dimension_id) REFERENCES tka_dimensions_enhanced(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### **tka_student_assessments** (Penilaian TKA Siswa)
```sql
CREATE TABLE tka_student_assessments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    siswa_id BIGINT UNSIGNED NOT NULL,
    semester_id BIGINT UNSIGNED NOT NULL,
    guru_id BIGINT UNSIGNED NOT NULL,
    tanggal_penilaian DATE NOT NULL,
    total_score FLOAT NOT NULL,
    max_score FLOAT NOT NULL,
    persentase FLOAT NOT NULL,
    status_kesiapan ENUM('belum_siap', 'perlu_bimbingan', 'siap', 'sangat_siap') NOT NULL,
    skor_per_dimensi JSON NOT NULL COMMENT 'Score per dimension',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_siswa_semester (siswa_id, semester_id),
    INDEX idx_siswa (siswa_id),
    INDEX idx_semester (semester_id),
    INDEX idx_guru (guru_id),
    INDEX idx_status (status_kesiapan),
    INDEX idx_tanggal (tanggal_penilaian),
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES semester(id) ON DELETE CASCADE,
    FOREIGN KEY (guru_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### **tka_assessment_responses** (Jawaban per Item)
```sql
CREATE TABLE tka_assessment_responses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    assessment_id BIGINT UNSIGNED NOT NULL,
    assessment_item_id BIGINT UNSIGNED NOT NULL,
    skor FLOAT NOT NULL,
    catatan_guru TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_assessment_item (assessment_id, assessment_item_id),
    INDEX idx_assessment (assessment_id),
    INDEX idx_item (assessment_item_id),
    FOREIGN KEY (assessment_id) REFERENCES tka_student_assessments(id) ON DELETE CASCADE,
    FOREIGN KEY (assessment_item_id) REFERENCES tka_assessment_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### **tka_parent_reports** (Laporan untuk Orang Tua)
```sql
CREATE TABLE tka_parent_reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    assessment_id BIGINT UNSIGNED NOT NULL,
    siswa_id BIGINT UNSIGNED NOT NULL,
    ortu_user_id BIGINT UNSIGNED NULL,
    dibaca_oleh_ortu BOOLEAN DEFAULT false,
    tanggal_dibaca DATETIME NULL,
    rekomendasi_rumah TEXT NULL,
    aktivitas_suggested JSON NULL COMMENT 'Aktivitas yang disarankan di rumah',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_assessment (assessment_id),
    INDEX idx_siswa (siswa_id),
    INDEX idx_ortu (ortu_user_id),
    INDEX idx_dibaca (dibaca_oleh_ortu),
    FOREIGN KEY (assessment_id) REFERENCES tka_student_assessments(id) ON DELETE CASCADE,
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    FOREIGN KEY (ortu_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### **tka_historical_tracking** (Tracking Perkembangan)
```sql
CREATE TABLE tka_historical_tracking (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    siswa_id BIGINT UNSIGNED NOT NULL,
    dimension_id BIGINT UNSIGNED NOT NULL,
    semester_id BIGINT UNSIGNED NOT NULL,
    skor FLOAT NOT NULL,
    perubahan_skor FLOAT NULL COMMENT 'Perubahan dari semester sebelumnya',
    trend ENUM('meningkat', 'stabil', 'menurun') NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_siswa (siswa_id),
    INDEX idx_dimension (dimension_id),
    INDEX idx_semester (semester_id),
    INDEX idx_trend (trend),
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    FOREIGN KEY (dimension_id) REFERENCES tka_dimensions_enhanced(id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES semester(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### **tka_early_warnings** (Early Warning System)
```sql
CREATE TABLE tka_early_warnings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    siswa_id BIGINT UNSIGNED NOT NULL,
    semester_id BIGINT UNSIGNED NOT NULL,
    dimension_id BIGINT UNSIGNED NOT NULL,
    tingkat_peringatan ENUM('rendah', 'sedang', 'tinggi', 'kritis') NOT NULL,
    alasan_peringatan TEXT NOT NULL,
    rekomendasi_tindakan TEXT NOT NULL,
    status ENUM('pending', 'sedang_diproses', 'selesai') DEFAULT 'pending',
    ditangani_oleh BIGINT UNSIGNED NULL COMMENT 'Guru yang menangani',
    tanggal_selesai DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_siswa (siswa_id),
    INDEX idx_semester (semester_id),
    INDEX idx_dimension (dimension_id),
    INDEX idx_tingkat (tingkat_peringatan),
    INDEX idx_status (status),
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES semester(id) ON DELETE CASCADE,
    FOREIGN KEY (dimension_id) REFERENCES tka_dimensions_enhanced(id) ON DELETE CASCADE,
    FOREIGN KEY (ditangani_oleh) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 2. MODELS & RELATIONSHIPS

### 2.1 CBT Models

```php
// app/Models/Cbt/QuestionBank.php
namespace App\Models\Cbt;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionBank extends Model
{
    use SoftDeletes;

    protected $table = 'cbt_question_banks';
    protected $fillable = [
        'uuid', 'mata_pelajaran_id', 'semester_id', 'guru_id',
        'kode_bank', 'nama_bank', 'deskripsi', 'tingkat_kesulitan',
        'kategori_soal', 'jumlah_soal', 'aktif'
    ];

    protected $casts = [
        'aktif' => 'boolean',
    ];

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'question_bank_id');
    }

    public function testSessions(): HasMany
    {
        return $this->hasMany(TestSession::class, 'question_bank_id');
    }
}

// app/Models/Cbt/Question.php
namespace App\Models\Cbt;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use SoftDeletes;

    protected $table = 'cbt_questions';
    protected $fillable = [
        'uuid', 'question_bank_id', 'urutan', 'tipe_soal',
        'pertanyaan', 'media_type', 'media_url', 'poin',
        'waktu_jawab_detik', 'pembahasan', 'aktif'
    ];

    protected $casts = [
        'aktif' => 'boolean',
        'poin' => 'float',
    ];

    public function questionBank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'question_bank_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class, 'question_id');
    }

    public function studentAnswers(): HasMany
    {
        return $this->hasMany(StudentAnswer::class, 'question_id');
    }
}

// app/Models/Cbt/QuestionOption.php
namespace App\Models\Cbt;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionOption extends Model
{
    protected $table = 'cbt_question_options';
    protected $fillable = [
        'question_id', 'huruf', 'isi_opsi', 'adalah_benar', 'urutan'
    ];

    protected $casts = [
        'adalah_benar' => 'boolean',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}

// app/Models/Cbt/TestSession.php
namespace App\Models\Cbt;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestSession extends Model
{
    use SoftDeletes;

    protected $table = 'cbt_test_sessions';
    protected $fillable = [
        'uuid', 'kode_sesi', 'nama_sesi', 'question_bank_id',
        'mata_pelajaran_id', 'semester_id', 'guru_id', 'kelas',
        'rombel_id', 'tanggal_mulai', 'tanggal_selesai',
        'durasi_menit', 'jumlah_soal_tampil', 'acak_soal',
        'acak_opsi', 'tampilkan_nilai', 'boleh_ulang',
        'maksimal_percobaan', 'passing_grade', 'status'
    ];

    protected $casts = [
        'acak_soal' => 'boolean',
        'acak_opsi' => 'boolean',
        'tampilkan_nilai' => 'boolean',
        'boleh_ulang' => 'boolean',
        'passing_grade' => 'float',
        'tanggal_mulai' => 'datetime',
        'tanggal_selesai' => 'datetime',
    ];

    public function questionBank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'question_bank_id');
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function rombel(): BelongsTo
    {
        return $this->belongsTo(Rombel::class);
    }

    public function studentAnswers(): HasMany
    {
        return $this->hasMany(StudentAnswer::class, 'test_session_id');
    }

    public function testResults(): HasMany
    {
        return $this->hasMany(TestResult::class, 'test_session_id');
    }

    // Helper methods
    public function isOngoing(): bool
    {
        return $this->status === 'ongoing' &&
               now()->between($this->tanggal_mulai, $this->tanggal_selesai);
    }

    public function canStudentTake($siswaId): bool
    {
        if (!$this->isOngoing()) return false;

        $attempts = TestResult::where('test_session_id', $this->id)
                             ->where('siswa_id', $siswaId)
                             ->count();

        if (!$this->boleh_ulang && $attempts > 0) return false;
        if ($this->maksimal_percobaan > 0 && $attempts >= $this->maksimal_percobaan) return false;

        return true;
    }
}

// app/Models/Cbt/StudentAnswer.php
namespace App\Models\Cbt;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAnswer extends Model
{
    protected $table = 'cbt_student_answers';
    protected $fillable = [
        'uuid', 'test_session_id', 'siswa_id', 'question_id',
        'jawaban', 'adalah_benar', 'poin_diperoleh',
        'waktu_mulai_jawab', 'waktu_selesai_jawab', 'durasi_detik',
        'attempt_number', 'ragu_ragu'
    ];

    protected $casts = [
        'adalah_benar' => 'boolean',
        'poin_diperoleh' => 'float',
        'waktu_mulai_jawab' => 'datetime',
        'waktu_selesai_jawab' => 'datetime',
        'ragu_ragu' => 'boolean',
    ];

    public function testSession(): BelongsTo
    {
        return $this->belongsTo(TestSession::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}

// app/Models/Cbt/TestResult.php
namespace App\Models\Cbt;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestResult extends Model
{
    protected $table = 'cbt_test_results';
    protected $fillable = [
        'uuid', 'test_session_id', 'siswa_id', 'attempt_number',
        'nilai_akhir', 'nilai_maksimal', 'persentase', 'lulus',
        'jumlah_benar', 'jumlah_salah', 'jumlah_tidak_dijawab',
        'waktu_mulai', 'waktu_selesai', 'durasi_detik',
        'submitted_by', 'ip_address', 'user_agent',
        'integrity_score', 'integrity_violations'
    ];

    protected $casts = [
        'lulus' => 'boolean',
        'nilai_akhir' => 'float',
        'nilai_maksimal' => 'float',
        'persentase' => 'float',
        'waktu_mulai' => 'datetime',
        'waktu_selesai' => 'datetime',
        'integrity_score' => 'float',
        'integrity_violations' => 'array',
    ];

    public function testSession(): BelongsTo
    {
        return $this->belongsTo(TestSession::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function studentAnswers(): HasMany
    {
        return $this->hasMany(StudentAnswer::class, 'test_session_id', 'test_session_id')
                    ->where('siswa_id', $this->siswa_id);
    }

    public function integrityLogs(): HasMany
    {
        return $this->hasMany(IntegrityLog::class, 'test_result_id');
    }
}

// app/Models/Cbt/IntegrityLog.php
namespace App\Models\Cbt;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrityLog extends Model
{
    protected $table = 'cbt_integrity_logs';
    protected $fillable = [
        'test_result_id', 'siswa_id', 'jenis_pelanggaran',
        'timestamp', 'deskripsi', 'data_tambahan'
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'data_tambahan' => 'array',
    ];

    public function testResult(): BelongsTo
    {
        return $this->belongsTo(TestResult::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }
}
```

### 2.2 Tryout Models

```php
// app/Models/Tryout/Category.php
namespace App\Models\Tryout;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $table = 'tryout_categories';
    protected $fillable = [
        'uuid', 'nama_kategori', 'deskripsi', 'jenis', 'aktif'
    ];

    protected $casts = [
        'aktif' => 'boolean',
    ];

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class, 'category_id');
    }
}

// app/Models/Tryout/Package.php
namespace App\Models\Tryout;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    use SoftDeletes;

    protected $table = 'tryout_packages';
    protected $fillable = [
        'uuid', 'category_id', 'kode_paket', 'nama_paket',
        'deskripsi', 'jenjang', 'tahun_paket', 'jumlah_mapel',
        'durasi_total_menit', 'passing_grade', 'standar_nilai', 'aktif'
    ];

    protected $casts = [
        'aktif' => 'boolean',
        'passing_grade' => 'float',
        'standar_nilai' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class, 'package_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class, 'package_id');
    }

    public function normReferences(): HasMany
    {
        return $this->hasMany(NormReference::class, 'package_id');
    }
}

// app/Models/Tryout/Subject.php
namespace App\Models\Tryout;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    protected $table = 'tryout_subjects';
    protected $fillable = [
        'uuid', 'package_id', 'mata_pelajaran_id', 'urutan',
        'bobot', 'jumlah_soal', 'durasi_menit', 'passing_grade_mapel'
    ];

    protected $casts = [
        'bobot' => 'float',
        'passing_grade_mapel' => 'float',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(TryoutQuestion::class, 'tryout_subject_id');
    }

    public function studentAnswers(): HasMany
    {
        return $this->hasMany(StudentAnswer::class, 'tryout_subject_id');
    }
}

// app/Models/Tryout/TryoutQuestion.php
namespace App\Models\Tryout;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TryoutQuestion extends Model
{
    use SoftDeletes;

    protected $table = 'tryout_questions';
    protected $fillable = [
        'uuid', 'tryout_subject_id', 'urutan', 'tipe_soal',
        'pertanyaan', 'media_type', 'media_url', 'poin',
        'tingkat_kesulitan', 'pembahasan', 'aktif'
    ];

    protected $casts = [
        'aktif' => 'boolean',
        'poin' => 'float',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'tryout_subject_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(TryoutQuestionOption::class, 'question_id');
    }
}

// app/Models/Tryout/TryoutQuestionOption.php
namespace App\Models\Tryout;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TryoutQuestionOption extends Model
{
    protected $table = 'tryout_question_options';
    protected $fillable = [
        'question_id', 'huruf', 'isi_opsi', 'adalah_benar', 'urutan'
    ];

    protected $casts = [
        'adalah_benar' => 'boolean',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(TryoutQuestion::class);
    }
}

// app/Models/Tryout/Session.php
namespace App\Models\Tryout;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Session extends Model
{
    use SoftDeletes;

    protected $table = 'tryout_sessions';
    protected $fillable = [
        'uuid', 'kode_sesi', 'nama_sesi', 'package_id',
        'semester_id', 'guru_id', 'kelas', 'rombel_id',
        'tanggal_mulai', 'tanggal_selesai', 'status'
    ];

    protected $casts = [
        'tanggal_mulai' => 'datetime',
        'tanggal_selesai' => 'datetime',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function rombel(): BelongsTo
    {
        return $this->belongsTo(Rombel::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(Result::class, 'tryout_session_id');
    }
}

// app/Models/Tryout/StudentAnswer.php
namespace App\Models\Tryout;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAnswer extends Model
{
    protected $table = 'tryout_student_answers';
    protected $fillable = [
        'uuid', 'tryout_session_id', 'tryout_subject_id',
        'siswa_id', 'question_id', 'jawaban', 'adalah_benar',
        'poin_diperoleh', 'waktu_mulai_jawab', 'waktu_selesai_jawab',
        'durasi_detik', 'attempt_number'
    ];

    protected $casts = [
        'adalah_benar' => 'boolean',
        'poin_diperoleh' => 'float',
        'waktu_mulai_jawab' => 'datetime',
        'waktu_selesai_jawab' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'tryout_session_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'tryout_subject_id');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(TryoutQuestion::class);
    }
}

// app/Models/Tryout/Result.php
namespace App\Models\Tryout;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Result extends Model
{
    protected $table = 'tryout_results';
    protected $fillable = [
        'uuid', 'tryout_session_id', 'siswa_id', 'attempt_number',
        'nilai_total', 'nilai_maksimal', 'persentase', 'peringkat',
        'lulus', 'prediksi_peminatan', 'detail_nilai_mapel',
        'analisis_kelemahan', 'rekomendasi_pembelajaran',
        'waktu_mulai', 'waktu_selesai', 'durasi_total_detik'
    ];

    protected $casts = [
        'lulus' => 'boolean',
        'nilai_total' => 'float',
        'nilai_maksimal' => 'float',
        'persentase' => 'float',
        'prediksi_peminatan' => 'array',
        'detail_nilai_mapel' => 'array',
        'analisis_kelemahan' => 'array',
        'waktu_mulai' => 'datetime',
        'waktu_selesai' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'tryout_session_id');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }
}

// app/Models/Tryout/NormReference.php
namespace App\Models\Tryout;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NormReference extends Model
{
    protected $table = 'tryout_norm_references';
    protected $fillable = [
        'uuid', 'package_id', 'jenis_norma', 'tahun_pelajaran',
        'nilai_rata_rata', 'nilai_minimal', 'nilai_maksimal',
        'distribusi_nilai', 'jumlah_peserta'
    ];

    protected $casts = [
        'nilai_rata_rata' => 'float',
        'nilai_minimal' => 'float',
        'nilai_maksimal' => 'float',
        'distribusi_nilai' => 'array',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
```

### 2.3 TKA Enhanced Models

```php
// app/Models/Tka/DimensionEnhanced.php
namespace App\Models\Tka;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DimensionEnhanced extends Model
{
    use SoftDeletes;

    protected $table = 'tka_dimensions_enhanced';
    protected $fillable = [
        'uuid', 'semester_id', 'kode_dimensi', 'nama_dimensi',
        'deskripsi', 'kategori', 'bobot', 'rubrik_penilaian',
        'threshold_siap', 'aktif'
    ];

    protected $casts = [
        'bobot' => 'float',
        'threshold_siap' => 'float',
        'rubrik_penilaian' => 'array',
        'aktif' => 'boolean',
    ];

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function assessmentItems(): HasMany
    {
        return $this->hasMany(AssessmentItem::class, 'dimension_id');
    }

    public function studentAssessments(): HasMany
    {
        return $this->hasMany(StudentAssessment::class);
    }

    public function historicalTracking(): HasMany
    {
        return $this->hasMany(HistoricalTracking::class, 'dimension_id');
    }

    public function earlyWarnings(): HasMany
    {
        return $this->hasMany(EarlyWarning::class, 'dimension_id');
    }
}

// app/Models/Tka/AssessmentItem.php
namespace App\Models\Tka;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentItem extends Model
{
    protected $table = 'tka_assessment_items';
    protected $fillable = [
        'uuid', 'dimension_id', 'urutan', 'indikator',
        'pertanyaan_penilaian', 'tipe_jawaban', 'bobot_item'
    ];

    protected $casts = [
        'bobot_item' => 'float',
    ];

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(DimensionEnhanced::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(AssessmentResponse::class, 'assessment_item_id');
    }
}

// app/Models/Tka/StudentAssessment.php
namespace App\Models\Tka;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentAssessment extends Model
{
    protected $table = 'tka_student_assessments';
    protected $fillable = [
        'uuid', 'siswa_id', 'semester_id', 'guru_id',
        'tanggal_penilaian', 'total_score', 'max_score',
        'persentase', 'status_kesiapan', 'skor_per_dimensi'
    ];

    protected $casts = [
        'tanggal_penilaian' => 'date',
        'total_score' => 'float',
        'max_score' => 'float',
        'persentase' => 'float',
        'skor_per_dimensi' => 'array',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(AssessmentResponse::class, 'assessment_id');
    }

    public function parentReport(): HasOne
    {
        return $this->hasOne(ParentReport::class, 'assessment_id');
    }
}

// app/Models/Tka/AssessmentResponse.php
namespace App\Models\Tka;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentResponse extends Model
{
    protected $table = 'tka_assessment_responses';
    protected $fillable = [
        'uuid', 'assessment_id', 'assessment_item_id',
        'skor', 'catatan_guru'
    ];

    protected $casts = [
        'skor' => 'float',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(StudentAssessment::class);
    }

    public function assessmentItem(): BelongsTo
    {
        return $this->belongsTo(AssessmentItem::class);
    }
}

// app/Models/Tka/ParentReport.php
namespace App\Models\Tka;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentReport extends Model
{
    protected $table = 'tka_parent_reports';
    protected $fillable = [
        'uuid', 'assessment_id', 'siswa_id', 'ortu_user_id',
        'dibaca_oleh_ortu', 'tanggal_dibaca',
        'rekomendasi_rumah', 'aktivitas_suggested'
    ];

    protected $casts = [
        'dibaca_oleh_ortu' => 'boolean',
        'tanggal_dibaca' => 'datetime',
        'aktivitas_suggested' => 'array',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(StudentAssessment::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function ortu(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ortu_user_id');
    }
}

// app/Models/Tka/HistoricalTracking.php
namespace App\Models\Tka;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoricalTracking extends Model
{
    protected $table = 'tka_historical_tracking';
    protected $fillable = [
        'uuid', 'siswa_id', 'dimension_id', 'semester_id',
        'skor', 'perubahan_skor', 'trend'
    ];

    protected $casts = [
        'skor' => 'float',
        'perubahan_skor' => 'float',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(DimensionEnhanced::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }
}

// app/Models/Tka/EarlyWarning.php
namespace App\Models\Tka;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EarlyWarning extends Model
{
    protected $table = 'tka_early_warnings';
    protected $fillable = [
        'uuid', 'siswa_id', 'semester_id', 'dimension_id',
        'tingkat_peringatan', 'alasan_peringatan',
        'rekomendasi_tindakan', 'status', 'ditangani_oleh',
        'tanggal_selesai'
    ];

    protected $casts = [
        'tanggal_selesai' => 'datetime',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(DimensionEnhanced::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditangani_oleh');
    }
}
```

---

## 3. CONTROLLERS & API ENDPOINTS

### 3.1 CBT Controllers

#### **CbtQuestionBankController** (Guru Only)
```php
namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\Cbt\QuestionBank;
use Illuminate\Http\Request;

class CbtQuestionBankController extends Controller
{
    public function index()
    {
        $banks = QuestionBank::with(['mataPelajaran', 'semester', 'guru'])
                             ->where('guru_id', auth()->id())
                             ->latest()
                             ->paginate(20);
        return view('cbt.question-banks.index', compact('banks'));
    }

    public function create()
    {
        $mapel = MataPelajaran::where('aktif', true)->get();
        $semester = Semester::where('aktif', true)->get();
        return view('cbt.question-banks.create', compact('mapel', 'semester'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'mata_pelajaran_id' => 'required|exists:mata_pelajaran,id',
            'semester_id' => 'required|exists:semester,id',
            'kode_bank' => 'required|string|max:50|unique:cbt_question_banks',
            'nama_bank' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'tingkat_kesulitan' => 'required|in:mudah,sedang,sulit',
            'kategori_soal' => 'nullable|string|max:100',
        ]);

        $bank = QuestionBank::create([
            'uuid' => Str::uuid(),
            'guru_id' => auth()->id(),
            ...$validated,
            'jumlah_soal' => 0,
            'aktif' => true,
        ]);

        return redirect()->route('cbt.question-banks.show', $bank)
            ->with('success', 'Bank soal berhasil dibuat');
    }

    public function show(QuestionBank $bank)
    {
        $bank->load(['questions.options', 'mataPelajaran', 'semester']);
        return view('cbt.question-banks.show', compact('bank'));
    }

    public function edit(QuestionBank $bank)
    {
        $this->authorize('update', $bank);
        $mapel = MataPelajaran::where('aktif', true)->get();
        $semester = Semester::where('aktif', true)->get();
        return view('cbt.question-banks.edit', compact('bank', 'mapel', 'semester'));
    }

    public function update(Request $request, QuestionBank $bank)
    {
        $this->authorize('update', $bank);

        $validated = $request->validate([
            'nama_bank' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'tingkat_kesulitan' => 'required|in:mudah,sedang,sulit',
            'kategori_soal' => 'nullable|string|max:100',
            'aktif' => 'boolean',
        ]);

        $bank->update($validated);

        return redirect()->route('cbt.question-banks.show', $bank)
            ->with('success', 'Bank soal berhasil diperbarui');
    }

    public function destroy(QuestionBank $bank)
    {
        $this->authorize('delete', $bank);
        $bank->delete();
        return redirect()->route('cbt.question-banks.index')
            ->with('success', 'Bank soal berhasil dihapus');
    }

    // API: Get questions for session
    public function apiGetQuestions(Request $request, QuestionBank $bank)
    {
        $jumlah = $request->input('jumlah', $bank->jumlah_soal);
        $acak = $request->input('acak', true);

        $query = $bank->questions()->where('aktif', true);

        if ($acak) {
            $questions = $query->inRandomOrder()->limit($jumlah)->get();
        } else {
            $questions = $query->orderBy('urutan')->limit($jumlah)->get();
        }

        return response()->json([
            'questions' => $questions->load('options'),
            'total' => $questions->count(),
        ]);
    }
}
```

#### **CbtQuestionController** (Guru Only)
```php
namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\Cbt\Question;
use App\Models\Cbt\QuestionOption;
use Illuminate\Http\Request;

class CbtQuestionController extends Controller
{
    public function store(Request $request, QuestionBank $bank)
    {
        $this->authorize('update', $bank);

        $validated = $request->validate([
            'tipe_soal' => 'required|in:pilihan_ganda,isian_singkat,benar_salah,menjodohkan,essay',
            'pertanyaan' => 'required|string',
            'media_type' => 'required|in:text,image,audio,video',
            'media_url' => 'nullable|string|max:500',
            'poin' => 'required|numeric|min:0',
            'waktu_jawab_detik' => 'nullable|integer|min:0',
            'pembahasan' => 'nullable|string',
            'opsi' => 'required_if:tipe_soal,pilihan_ganda|array',
            'opsi.*.huruf' => 'required_if:tipe_soal,pilihan_ganda|in:A,B,C,D,E',
            'opsi.*.isi' => 'required_if:tipe_soal,pilihan_ganda|string',
            'opsi.*.benar' => 'required_if:tipe_soal,pilihan_ganda|boolean',
        ]);

        $question = Question::create([
            'uuid' => Str::uuid(),
            'question_bank_id' => $bank->id,
            'urutan' => $bank->questions()->count() + 1,
            'tipe_soal' => $validated['tipe_soal'],
            'pertanyaan' => $validated['pertanyaan'],
            'media_type' => $validated['media_type'],
            'media_url' => $validated['media_url'] ?? null,
            'poin' => $validated['poin'],
            'waktu_jawab_detik' => $validated['waktu_jawab_detik'] ?? 0,
            'pembahasan' => $validated['pembahasan'] ?? null,
            'aktif' => true,
        ]);

        // Create options for multiple choice
        if ($validated['tipe_soal'] === 'pilihan_ganda' && isset($validated['opsi'])) {
            foreach ($validated['opsi'] as $opsi) {
                QuestionOption::create([
                    'question_id' => $question->id,
                    'huruf' => $opsi['huruf'],
                    'isi_opsi' => $opsi['isi'],
                    'adalah_benar' => $opsi['benar'],
                    'urutan' => 0,
                ]);
            }
        }

        // Update bank question count
        $bank->update(['jumlah_soal' => $bank->questions()->count()]);

        return redirect()->route('cbt.question-banks.show', $bank)
            ->with('success', 'Soal berhasil ditambahkan');
    }

    public function update(Request $request, Question $question)
    {
        $this->authorize('update', $question->questionBank);

        $validated = $request->validate([
            'pertanyaan' => 'required|string',
            'media_url' => 'nullable|string|max:500',
            'poin' => 'required|numeric|min:0',
            'waktu_jawab_detik' => 'nullable|integer|min:0',
            'pembahasan' => 'nullable|string',
            'opsi' => 'array',
        ]);

        $question->update([
            'pertanyaan' => $validated['pertanyaan'],
            'media_url' => $validated['media_url'] ?? $question->media_url,
            'poin' => $validated['poin'],
            'waktu_jawab_detik' => $validated['waktu_jawab_detik'] ?? $question->waktu_jawab_detik,
            'pembahasan' => $validated['pembahasan'] ?? $question->pembahasan,
        ]);

        // Update options if provided
        if (isset($validated['opsi'])) {
            foreach ($validated['opsi'] as $opsiData) {
                $option = QuestionOption::where('question_id', $question->id)
                                       ->where('huruf', $opsiData['huruf'])
                                       ->first();
                if ($option) {
                    $option->update([
                        'isi_opsi' => $opsiData['isi'],
                        'adalah_benar' => $opsiData['benar'],
                    ]);
                }
            }
        }

        return back()->with('success', 'Soal berhasil diperbarui');
    }

    public function destroy(Question $question)
    {
        $this->authorize('delete', $question->questionBank);
        $bank = $question->questionBank;
        $question->delete();

        // Update bank question count
        $bank->update(['jumlah_soal' => $bank->questions()->count()]);

        return back()->with('success', 'Soal berhasil dihapus');
    }
}
```

#### **CbtTestSessionController** (Guru)
```php
namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\Cbt\TestSession;
use App\Models\Cbt\QuestionBank;
use Illuminate\Http\Request;

class CbtTestSessionController extends Controller
{
    public function index()
    {
        $sessions = TestSession::with(['questionBank', 'mataPelajaran', 'semester'])
                               ->where('guru_id', auth()->id())
                               ->latest()
                               ->paginate(20);
        return view('cbt.sessions.index', compact('sessions'));
    }

    public function create()
    {
        $banks = QuestionBank::where('guru_id', auth()->id())
                            ->where('aktif', true)
                            ->with('mataPelajaran', 'semester')
                            ->get();
        return view('cbt.sessions.create', compact('banks'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'question_bank_id' => 'required|exists:cbt_question_banks,id',
            'nama_sesi' => 'required|string|max:255',
            'kelas' => 'nullable|string|max:50',
            'rombel_id' => 'nullable|exists:rombel,id',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after:tanggal_mulai',
            'durasi_menit' => 'required|integer|min:1',
            'jumlah_soal_tampil' => 'required|integer|min:1',
            'acak_soal' => 'boolean',
            'acak_opsi' => 'boolean',
            'tampilkan_nilai' => 'boolean',
            'boleh_ulang' => 'boolean',
            'maksimal_percobaan' => 'nullable|integer|min:1',
            'passing_grade' => 'required|numeric|min:0|max:100',
        ]);

        $bank = QuestionBank::findOrFail($validated['question_bank_id']);

        $session = TestSession::create([
            'uuid' => Str::uuid(),
            'kode_sesi' => 'CBT-' . strtoupper(Str::random(8)),
            'guru_id' => auth()->id(),
            'mata_pelajaran_id' => $bank->mata_pelajaran_id,
            'semester_id' => $bank->semester_id,
            ...$validated,
            'status' => 'draft',
        ]);

        return redirect()->route('cbt.sessions.show', $session)
            ->with('success', 'Sesi ujian berhasil dibuat');
    }

    public function show(TestSession $session)
    {
        $session->load(['questionBank', 'mataPelajaran', 'semester', 'guru', 'rombel']);
        $results = $session->testResults()->with('siswa')->paginate(20);
        return view('cbt.sessions.show', compact('session', 'results'));
    }

    public function publish(TestSession $session)
    {
        $this->authorize('update', $session);
        $session->update(['status' => 'published']);
        return back()->with('success', 'Sesi ujian dipublikasikan');
    }

    public function start(TestSession $session)
    {
        $this->authorize('update', $session);
        $session->update(['status' => 'ongoing']);
        return back()->with('success', 'Sesi ujian dimulai');
    }

    public function complete(TestSession $session)
    {
        $this->authorize('update', $session);
        $session->update(['status' => 'completed']);
        return back()->with('success', 'Sesi ujian selesai');
    }

    // Live monitoring
    public function monitoring(TestSession $session)
    {
        $this->authorize('view', $session);

        $activeStudents = $session->testResults()
                                ->whereNull('waktu_selesai')
                                ->with('siswa')
                                ->get();

        $completedStudents = $session->testResults()
                                   ->whereNotNull('waktu_selesai')
                                   ->with('siswa')
                                   ->get();

        return view('cbt.sessions.monitoring', compact('session', 'activeStudents', 'completedStudents'));
    }

    // API: Get active students for live monitoring
    public function apiMonitoring(TestSession $session)
    {
        $active = $session->testResults()
                         ->whereNull('waktu_selesai')
                         ->with('siswa')
                         ->get()
                         ->map(function ($result) {
                             return [
                                 'id' => $result->id,
                                 'siswa' => $result->siswa->nama_peserta_didik,
                                 'waktu_mulai' => $result->waktu_mulai,
                                 'durasi_sudah' => now()->diffInSeconds($result->waktu_mulai),
                                 'integrity_score' => $result->integrity_score,
                             ];
                         });

        return response()->json(['active_students' => $active]);
    }
}
```

#### **CbtStudentController** (Siswa)
```php
namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\Cbt\TestSession;
use App\Models\Cbt\TestResult;
use App\Models\Cbt\Question;
use App\Models\Cbt\StudentAnswer;
use Illuminate\Http\Request;

class CbtStudentController extends Controller
{
    public function index()
    {
        $siswa = Siswa::where('user_id', auth()->id())->first();
        $availableSessions = TestSession::where('status', 'ongoing')
                                        ->where('tanggal_mulai', '<=', now())
                                        ->where('tanggal_selesai', '>=', now())
                                        ->where(function ($q) use ($siswa) {
                                            $q->whereNull('kelas')
                                              ->orWhere('kelas', $siswa->kelas);
                                        })
                                        ->get();

        $myResults = TestResult::where('siswa_id', $siswa->id)
                              ->with('testSession')
                              ->latest()
                              ->get();

        return view('cbt.student.index', compact('availableSessions', 'myResults'));
    }

    public function start(TestSession $session)
    {
        $siswa = Siswa::where('user_id', auth()->id())->first();

        if (!$session->canStudentTake($siswa->id)) {
            return back()->with('error', 'Anda tidak dapat mengikuti ujian ini');
        }

        // Create test result record
        $result = TestResult::create([
            'uuid' => Str::uuid(),
            'test_session_id' => $session->id,
            'siswa_id' => $siswa->id,
            'attempt_number' => $session->testResults()->where('siswa_id', $siswa->id)->count() + 1,
            'nilai_akhir' => 0,
            'nilai_maksimal' => 0,
            'persentase' => 0,
            'lulus' => false,
            'jumlah_benar' => 0,
            'jumlah_salah' => 0,
            'jumlah_tidak_dijawab' => 0,
            'waktu_mulai' => now(),
            'waktu_selesai' => null,
            'durasi_detik' => 0,
            'submitted_by' => 'siswa',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'integrity_score' => 100.0,
        ]);

        // Get questions for this session
        $questions = $session->questionBank->questions()
                                          ->where('aktif', true)
                                          ->limit($session->jumlah_soal_tampil)
                                          ->get();

        if ($session->acak_soal) {
            $questions = $questions->shuffle();
        }

        // Randomize options if enabled
        if ($session->acak_opsi) {
            $questions->each(function ($q) {
                $q->options = $q->options->shuffle();
            });
        }

        return view('cbt.student.take', compact('session', 'result', 'questions'));
    }

    public function submitAnswer(Request $request, TestResult $result)
    {
        $this->authorize('update', $result);

        $validated = $request->validate([
            'question_id' => 'required|exists:cbt_questions,id',
            'jawaban' => 'required|string',
            'ragu_ragu' => 'boolean',
        ]);

        $question = Question::findOrFail($validated['question_id']);

        // Check if already answered
        $existing = StudentAnswer::where('test_result_id', $result->id)
                                ->where('question_id', $question->id)
                                ->first();

        if ($existing) {
            $existing->update([
                'jawaban' => $validated['jawaban'],
                'ragu_ragu' => $validated['ragu_ragu'] ?? false,
                'waktu_selesai_jawab' => now(),
                'durasi_detik' => $existing->waktu_mulai_jawab
                    ? now()->diffInSeconds($existing->waktu_mulai_jawab)
                    : 0,
            ]);
        } else {
            $answer = StudentAnswer::create([
                'uuid' => Str::uuid(),
                'test_result_id' => $result->id,
                'siswa_id' => $result->siswa_id,
                'question_id' => $question->id,
                'jawaban' => $validated['jawaban'],
                'adalah_benar' => null,
                'poin_diperoleh' => 0,
                'waktu_mulai_jawab' => now(),
                'waktu_selesai_jawab' => now(),
                'durasi_detik' => 0,
                'attempt_number' => $result->attempt_number,
                'ragu_ragu' => $validated['ragu_ragu'] ?? false,
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function submit(TestResult $result)
    {
        $this->authorize('update', $result);

        if ($result->waktu_selesai) {
            return back()->with('error', 'Ujian sudah dikumpulkan');
        }

        // Calculate score
        $answers = $result->studentAnswers;
        $totalPoin = 0;
        $benar = 0;
        $salah = 0;
        $tidak_dijawab = 0;

        foreach ($answers as $answer) {
            $question = $answer->question;
            $isCorrect = false;

            if ($question->tipe_soal === 'pilihan_ganda') {
                $correctOption = $question->options->where('adalah_benar', true)->first();
                $isCorrect = $correctOption && $answer->jawaban === $correctOption->huruf;
            } elseif ($question->tipe_soal === 'benar_salah') {
                $isCorrect = strtolower($answer->jawaban) === 'benar';
            }

            $answer->update([
                'adalah_benar' => $isCorrect,
                'poin_diperoleh' => $isCorrect ? $question->poin : 0,
            ]);

            if ($isCorrect) {
                $benar++;
                $totalPoin += $question->poin;
            } elseif ($answer->jawaban) {
                $salah++;
            } else {
                $tidak_dijawab++;
            }
        }

        $maxPoin = $result->testSession->questionBank->questions->sum('poin');
        $persentase = $maxPoin > 0 ? ($totalPoin / $maxPoin) * 100 : 0;
        $lulus = $persentase >= $result->testSession->passing_grade;

        $result->update([
            'nilai_akhir' => $totalPoin,
            'nilai_maksimal' => $maxPoin,
            'persentase' => $persentase,
            'lulus' => $lulus,
            'jumlah_benar' => $benar,
            'jumlah_salah' => $salah,
            'jumlah_tidak_dijawab' => $tidak_dijawab,
            'waktu_selesai' => now(),
            'durasi_detik' => now()->diffInSeconds($result->waktu_mulai),
            'submitted_by' => 'siswa',
        ]);

        return redirect()->route('cbt.student.result', $result)
            ->with('success', 'Jawaban berhasil dikumpulkan');
    }

    public function result(TestResult $result)
    {
        $this->authorize('view', $result);

        if (!$result->testSession->tampilkan_nilai && $result->submitted_by !== 'guru') {
            return back()->with('error', 'Nilai belum ditampilkan oleh guru');
        }

        $result->load(['testSession', 'siswa', 'studentAnswers.question.options']);

        return view('cbt.student.result', compact('result'));
    }
}
```

#### **CbtIntegrityController** (Anti-Cheat - API)
```php
namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Models\Cbt\TestResult;
use App\Models\Cbt\IntegrityLog;
use Illuminate\Http\Request;

class CbtIntegrityController extends Controller
{
    public function logViolation(Request $request, TestResult $result)
    {
        $this->authorize('update', $result);

        $validated = $request->validate([
            'jenis_pelanggaran' => 'required|in:tab_switch,copy_paste,screen_capture,focus_lost,multiple_tabs,suspicious_activity',
            'deskripsi' => 'nullable|string',
            'data_tambahan' => 'nullable|array',
        ]);

        IntegrityLog::create([
            'test_result_id' => $result->id,
            'siswa_id' => $result->siswa_id,
            'jenis_pelanggaran' => $validated['jenis_pelanggaran'],
            'timestamp' => now(),
            'deskripsi' => $validated['deskripsi'] ?? null,
            'data_tambahan' => $validated['data_tambahan'] ?? null,
        ]);

        // Update integrity score
        $violations = $result->integrityLogs()->count();
        $score = max(0, 100 - ($violations * 10)); // 10 points per violation

        $result->update([
            'integrity_score' => $score,
            'integrity_violations' => $result->integrity_violations ?? [],
        ]);

        return response()->json(['success' => true, 'integrity_score' => $score]);
    }

    public function getViolations(TestResult $result)
    {
        $this->authorize('view', $result);

        $violations = $result->integrityLogs()->latest()->get();

        return response()->json(['violations' => $violations]);
    }
}
```

---

### 3.2 TRYOUT Controllers

#### **TryoutPackageController** (Guru/Admin)
```php
namespace App\Http\Controllers\Tryout;

use App\Http\Controllers\Controller;
use App\Models\Tryout\Package;
use App\Models\Tryout\Category;
use Illuminate\Http\Request;

class TryoutPackageController extends Controller
{
    public function index()
    {
        $packages = Package::with(['category', 'subjects'])->latest()->paginate(20);
        return view('tryout.packages.index', compact('packages'));
    }

    public function create()
    {
        $categories = Category::where('aktif', true)->get();
        $mapel = MataPelajaran::where('aktif', true)->get();
        return view('tryout.packages.create', compact('categories', 'mapel'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:tryout_categories,id',
            'kode_paket' => 'required|string|max:50|unique:tryout_packages',
            'nama_paket' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'jenjang' => 'required|in:SD,SMP,SMA,SMK,UMUM',
            'tahun_paket' => 'required|integer|min:2020|max:2100',
            'durasi_total_menit' => 'required|integer|min:1',
            'passing_grade' => 'required|numeric|min:0|max:100',
            'standar_nilai' => 'nullable|array',
            'subjects' => 'required|array',
            'subjects.*.mapel_id' => 'required|exists:mata_pelajaran,id',
            'subjects.*.bobot' => 'required|numeric|min:0',
            'subjects.*.jumlah_soal' => 'required|integer|min:1',
            'subjects.*.durasi_menit' => 'required|integer|min:1',
        ]);

        $package = Package::create([
            'uuid' => Str::uuid(),
            ...$validated,
            'jumlah_mapel' => count($validated['subjects']),
            'aktif' => true,
        ]);

        // Create subjects
        foreach ($validated['subjects'] as $subjectData) {
            $package->subjects()->create([
                'uuid' => Str::uuid(),
                'mata_pelajaran_id' => $subjectData['mapel_id'],
                'urutan' => 0,
                'bobot' => $subjectData['bobot'],
                'jumlah_soal' => $subjectData['jumlah_soal'],
                'durasi_menit' => $subjectData['durasi_menit'],
                'passing_grade_mapel' => 60.0,
            ]);
        }

        return redirect()->route('tryout.packages.show', $package)
            ->with('success', 'Paket tryout berhasil dibuat');
    }

    public function show(Package $package)
    {
        $package->load(['category', 'subjects.mataPelajaran', 'subjects.questions']);
        return view('tryout.packages.show', compact('package'));
    }
}
```

#### **TryoutSessionController** (Guru)
```php
namespace App\Http\Controllers\Tryout;

use App\Http\Controllers\Controller;
use App\Models\Tryout\Session;
use App\Models\Tryout\Package;
use Illuminate\Http\Request;

class TryoutSessionController extends Controller
{
    public function index()
    {
        $sessions = Session::with(['package', 'semester', 'guru'])
                          ->where('guru_id', auth()->id())
                          ->latest()
                          ->paginate(20);
        return view('tryout.sessions.index', compact('sessions'));
    }

    public function create()
    {
        $packages = Package::where('aktif', true)->with('category')->get();
        $semester = Semester::where('aktif', true)->get();
        return view('tryout.sessions.create', compact('packages', 'semester'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'package_id' => 'required|exists:tryout_packages,id',
            'nama_sesi' => 'required|string|max:255',
            'semester_id' => 'required|exists:semester,id',
            'kelas' => 'nullable|string|max:50',
            'rombel_id' => 'nullable|exists:rombel,id',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after:tanggal_mulai',
        ]);

        $session = Session::create([
            'uuid' => Str::uuid(),
            'kode_sesi' => 'TRY-' . strtoupper(Str::random(8)),
            'guru_id' => auth()->id(),
            ...$validated,
            'status' => 'draft',
        ]);

        return redirect()->route('tryout.sessions.show', $session)
            ->with('success', 'Sesi tryout berhasil dibuat');
    }

    public function show(Session $session)
    {
        $session->load(['package', 'package.subjects', 'semester', 'guru']);
        $results = $session->results()->with('siswa')->paginate(20);
        return view('tryout.sessions.show', compact('session', 'results'));
    }

    public function publish(Session $session)
    {
        $this->authorize('update', $session);
        $session->update(['status' => 'published']);
        return back()->with('success', 'Sesi tryout dipublikasikan');
    }
}
```

#### **TryoutStudentController** (Siswa)
```php
namespace App\Http\Controllers\Tryout;

use App\Http\Controllers\Controller;
use App\Models\Tryout\Session;
use App\Models\Tryout\Result;
use App\Models\Tryout\StudentAnswer;
use Illuminate\Http\Request;

class TryoutStudentController extends Controller
{
    public function index()
    {
        $siswa = Siswa::where('user_id', auth()->id())->first();
        $availableSessions = Session::where('status', 'ongoing')
                                   ->where('tanggal_mulai', '<=', now())
                                   ->where('tanggal_selesai', '>=', now())
                                   ->where(function ($q) use ($siswa) {
                                       $q->whereNull('kelas')
                                         ->orWhere('kelas', $siswa->kelas);
                                   })
                                   ->get();

        $myResults = Result::where('siswa_id', $siswa->id)
                          ->with('session.package')
                          ->latest()
                          ->get();

        return view('tryout.student.index', compact('availableSessions', 'myResults'));
    }

    public function start(Session $session)
    {
        $siswa = Siswa::where('user_id', auth()->id())->first();

        // Create result record
        $result = Result::create([
            'uuid' => Str::uuid(),
            'tryout_session_id' => $session->id,
            'siswa_id' => $siswa->id,
            'attempt_number' => 1,
            'nilai_total' => 0,
            'nilai_maksimal' => 0,
            'persentase' => 0,
            'peringkat' => null,
            'lulus' => false,
            'prediksi_peminatan' => null,
            'detail_nilai_mapel' => [],
            'analisis_kelemahan' => [],
            'rekomendasi_pembelajaran' => null,
            'waktu_mulai' => now(),
            'waktu_selesai' => null,
            'durasi_total_detik' => 0,
        ]);

        // Get subjects for this tryout
        $subjects = $session->package->subjects;

        return view('tryout.student.take', compact('session', 'result', 'subjects'));
    }

    public function submitSubject(Request $request, Result $result)
    {
        $this->authorize('update', $result);

        $validated = $request->validate([
            'subject_id' => 'required|exists:tryout_subjects,id',
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:tryout_questions,id',
            'answers.*.jawaban' => 'required|string',
        ]);

        $subject = $result->session->package->subjects->find($validated['subject_id']);

        foreach ($validated['answers'] as $answerData) {
            StudentAnswer::create([
                'uuid' => Str::uuid(),
                'tryout_session_id' => $result->tryout_session_id,
                'tryout_subject_id' => $subject->id,
                'siswa_id' => $result->siswa_id,
                'question_id' => $answerData['question_id'],
                'jawaban' => $answerData['jawaban'],
                'adalah_benar' => null,
                'poin_diperoleh' => 0,
                'attempt_number' => $result->attempt_number,
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function complete(Result $result)
    {
        $this->authorize('update', $result);

        // Calculate scores per subject
        $detailNilai = [];
        $totalNilai = 0;
        $totalMax = 0;

        foreach ($result->session->package->subjects as $subject) {
            $answers = $result->studentAnswers->where('tryout_subject_id', $subject->id);
            $benar = 0;
            $poin = 0;

            foreach ($answers as $answer) {
                $question = $answer->question;
                $isCorrect = false;

                if ($question->tipe_soal === 'pilihan_ganda') {
                    $correctOption = $question->options->where('adalah_benar', true)->first();
                    $isCorrect = $correctOption && $answer->jawaban === $correctOption->huruf;
                }

                $answer->update(['adalah_benar' => $isCorrect, 'poin_diperoleh' => $isCorrect ? $question->poin : 0]);

                if ($isCorrect) {
                    $benar++;
                    $poin += $question->poin;
                }
            }

            $subjectMax = $subject->questions->sum('poin');
            $subjectPct = $subjectMax > 0 ? ($poin / $subjectMax) * 100 : 0;

            $detailNilai[] = [
                'mapel' => $subject->mataPelajaran->nama,
                'bobot' => $subject->bobot,
                'nilai' => $poin,
                'max' => $subjectMax,
                'persentase' => $subjectPct,
                'benar' => $benar,
                'total_soal' => $subject->jumlah_soal,
            ];

            $totalNilai += $poin * $subject->bobot;
            $totalMax += $subjectMax * $subject->bobot;
        }

        $persentase = $totalMax > 0 ? ($totalNilai / $totalMax) * 100 : 0;
        $lulus = $persentase >= $result->session->package->passing_grade;

        // Calculate ranking
        $peringkat = Result::where('tryout_session_id', $result->tryout_session_id)
                          ->where('persentase', '>', $persentase)
                          ->count() + 1;

        // Analyze weaknesses
        $analisisKelemahan = collect($detailNilai)
            ->filter(fn($d) => $d['persentase'] < 60)
            ->map(fn($d) => $d['mapel'])
            ->values()
            ->toArray();

        $result->update([
            'nilai_total' => $totalNilai,
            'nilai_maksimal' => $totalMax,
            'persentase' => $persentase,
            'peringkat' => $peringkat,
            'lulus' => $lulus,
            'detail_nilai_mapel' => $detailNilai,
            'analisis_kelemahan' => $analisisKelemahan,
            'waktu_selesai' => now(),
            'durasi_total_detik' => now()->diffInSeconds($result->waktu_mulai),
        ]);

        return redirect()->route('tryout.student.result', $result)
            ->with('success', 'Tryout selesai');
    }

    public function result(Result $result)
    {
        $this->authorize('view', $result);

        $result->load(['session.package', 'siswa', 'studentAnswers.question']);

        // Get norm reference if available
        $norm = $result->session->package->normReferences->first();

        return view('tryout.student.result', compact('result', 'norm'));
    }
}
```

---

### 3.3 TKA Enhanced Controllers

#### **TkaDimensionController** (Guru)
```php
namespace App\Http\Controllers\Tka;

use App\Http\Controllers\Controller;
use App\Models\Tka\DimensionEnhanced;
use App\Models\Tka\AssessmentItem;
use Illuminate\Http\Request;

class TkaDimensionController extends Controller
{
    public function index()
    {
        $dimensions = DimensionEnhanced::with('semester')
                                      ->where('aktif', true)
                                      ->latest()
                                      ->get();
        return view('tka.dimensions.index', compact('dimensions'));
    }

    public function create()
    {
        $semester = Semester::where('aktif', true)->get();
        return view('tka.dimensions.create', compact('semester'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'semester_id' => 'required|exists:semester,id',
            'kode_dimensi' => 'required|string|max:50|unique:tka_dimensions_enhanced',
            'nama_dimensi' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'kategori' => 'required|in:religius,nasionalis,mandiri,gotong_royong,integritas,kreatif,berdaya_saing',
            'bobot' => 'required|numeric|min:0',
            'rubrik_penilaian' => 'required|array',
            'threshold_siap' => 'required|numeric|min:0|max:4',
        ]);

        $dimension = DimensionEnhanced::create([
            'uuid' => Str::uuid(),
            ...$validated,
            'aktif' => true,
        ]);

        return redirect()->route('tka.dimensions.show', $dimension)
            ->with('success', 'Dimensi berhasil dibuat');
    }

    public function show(DimensionEnhanced $dimension)
    {
        $dimension->load(['semester', 'assessmentItems']);
        return view('tka.dimensions.show', compact('dimension'));
    }

    public function addItem(Request $request, DimensionEnhanced $dimension)
    {
        $validated = $request->validate([
            'indikator' => 'required|string|max:500',
            'pertanyaan_penilaian' => 'required|string',
            'tipe_jawaban' => 'required|in:skala_1_4,skala_1_5,ya_tidak,checklist',
            'bobot_item' => 'required|numeric|min:0',
        ]);

        $item = AssessmentItem::create([
            'uuid' => Str::uuid(),
            'dimension_id' => $dimension->id,
            'urutan' => $dimension->assessmentItems->count() + 1,
            ...$validated,
        ]);

        return back()->with('success', 'Item penilaian berhasil ditambahkan');
    }
}
```

#### **TkaAssessmentController** (Guru)
```php
namespace App\Http\Controllers\Tka;

use App\Http\Controllers\Controller;
use App\Models\Tka\StudentAssessment;
use App\Models\Tka\AssessmentResponse;
use App\Models\Tka\ParentReport;
use App\Models\Tka\HistoricalTracking;
use App\Models\Tka\EarlyWarning;
use App\Models\Siswa;
use Illuminate\Http\Request;

class TkaAssessmentController extends Controller
{
    public function index()
    {
        $assessments = StudentAssessment::with(['siswa', 'semester', 'guru'])
                                       ->latest()
                                       ->paginate(20);
        return view('tka.assessments.index', compact('assessments'));
    }

    public function create()
    {
        $semester = Semester::where('aktif', true)->first();
        $siswa = Siswa::where('aktif', true)->get();
        $dimensions = DimensionEnhanced::where('aktif', true)
                                      ->where('semester_id', $semester->id)
                                      ->with('assessmentItems')
                                      ->get();

        return view('tka.assessments.create', compact('semester', 'siswa', 'dimensions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'siswa_id' => 'required|exists:siswa,id',
            'semester_id' => 'required|exists:semester,id',
            'tanggal_penilaian' => 'required|date',
            'skor_per_dimensi' => 'required|array',
            'skor_per_dimensi.*.dimension_id' => 'required|exists:tka_dimensions_enhanced,id',
            'skor_per_dimensi.*.skor' => 'required|numeric|min:0',
            'responses' => 'required|array',
            'responses.*.item_id' => 'required|exists:tka_assessment_items,id',
            'responses.*.skor' => 'required|numeric',
            'responses.*.catatan' => 'nullable|string',
        ]);

        // Calculate total score
        $totalScore = collect($validated['skor_per_dimensi'])->sum('skor');
        $maxScore = DimensionEnhanced::where('semester_id', $validated['semester_id'])
                                    ->sum('threshold_siap') * count($validated['skor_per_dimensi']);
        $persentase = $maxScore > 0 ? ($totalScore / $maxScore) * 100 : 0;

        // Determine readiness status
        if ($persentase >= 75) {
            $status = 'sangat_siap';
        } elseif ($persentase >= 60) {
            $status = 'siap';
        } elseif ($persentase >= 40) {
            $status = 'perlu_bimbingan';
        } else {
            $status = 'belum_siap';
        }

        $assessment = StudentAssessment::create([
            'uuid' => Str::uuid(),
            'siswa_id' => $validated['siswa_id'],
            'semester_id' => $validated['semester_id'],
            'guru_id' => auth()->id(),
            'tanggal_penilaian' => $validated['tanggal_penilaian'],
            'total_score' => $totalScore,
            'max_score' => $maxScore,
            'persentase' => $persentase,
            'status_kesiapan' => $status,
            'skor_per_dimensi' => $validated['skor_per_dimensi'],
        ]);

        // Create responses
        foreach ($validated['responses'] as $responseData) {
            AssessmentResponse::create([
                'uuid' => Str::uuid(),
                'assessment_id' => $assessment->id,
                'assessment_item_id' => $responseData['item_id'],
                'skor' => $responseData['skor'],
                'catatan_guru' => $responseData['catatan'] ?? null,
            ]);
        }

        // Create parent report
        ParentReport::create([
            'uuid' => Str::uuid(),
            'assessment_id' => $assessment->id,
            'siswa_id' => $assessment->siswa_id,
            'rekomendasi_rumah' => $this->generateRecommendation($status),
            'aktivitas_suggested' => $this->suggestActivities($assessment),
        ]);

        // Track historical data
        foreach ($validated['skor_per_dimensi'] as $dimScore) {
            $previous = HistoricalTracking::where('siswa_id', $assessment->siswa_id)
                                        ->where('dimension_id', $dimScore['dimension_id'])
                                        ->where('semester_id', '<', $assessment->semester_id)
                                        ->latest()
                                        ->first();

            $perubahan = $previous ? $dimScore['skor'] - $previous->skor : null;
            $trend = $perubahan > 0 ? 'meningkat' : ($perubahan < 0 ? 'menurun' : 'stabil');

            HistoricalTracking::create([
                'uuid' => Str::uuid(),
                'siswa_id' => $assessment->siswa_id,
                'dimension_id' => $dimScore['dimension_id'],
                'semester_id' => $assessment->semester_id,
                'skor' => $dimScore['skor'],
                'perubahan_skor' => $perubahan,
                'trend' => $trend,
            ]);
        }

        // Check for early warnings
        foreach ($validated['skor_per_dimensi'] as $dimScore) {
            $dimension = DimensionEnhanced::find($dimScore['dimension_id']);
            if ($dimScore['skor'] < $dimension->threshold_siap * 0.6) {
                EarlyWarning::create([
                    'uuid' => Str::uuid(),
                    'siswa_id' => $assessment->siswa_id,
                    'semester_id' => $assessment->semester_id,
                    'dimension_id' => $dimScore['dimension_id'],
                    'tingkat_peringatan' => $dimScore['skor'] < $dimension->threshold_siap * 0.4 ? 'kritis' : 'sedang',
                    'alasan_peringatan' => "Skor {$dimScore['skor']} di bawah threshold {$dimension->threshold_siap}",
                    'rekomendasi_tindakan' => "Perlu bimbingan khusus untuk dimensi {$dimension->nama_dimensi}",
                    'status' => 'pending',
                ]);
            }
        }

        return redirect()->route('tka.assessments.show', $assessment)
            ->with('success', 'Penilaian TKA berhasil disimpan');
    }

    protected function generateRecommendation($status)
    {
        $recommendations = [
            'sangat_siap' => 'Anak menunjukkan kematangan yang sangat baik. Terus dukung dengan aktivitas positif.',
            'siap' => 'Anak siap untuk tahap berikutnya. Berikan dukungan dan motivasi.',
            'perlu_bimbingan' => 'Anak membutuhkan bimbingan tambahan di beberapa aspek. Perhatikan area yang perlu ditingkatkan.',
            'belum_siap' => 'Anak membutuhkan perhatian khusus dan bimbingan intensif. Konsultasikan dengan guru.',
        ];

        return $recommendations[$status] ?? '';
    }

    protected function suggestActivities($assessment)
    {
        // Generate activity suggestions based on dimension scores
        $activities = [];
        foreach ($assessment->skor_per_dimensi as $dimScore) {
            $dimension = DimensionEnhanced::find($dimScore['dimension_id']);
            if ($dimScore['skor'] < $dimension->threshold_siap) {
                $activities[] = [
                    'dimension' => $dimension->nama_dimensi,
                    'activity' => "Lakukan aktivitas yang mengembangkan {$dimension->nama_dimensi}",
                ];
            }
        }
        return $activities;
    }

    public function show(StudentAssessment $assessment)
    {
        $assessment->load(['siswa', 'semester', 'guru', 'responses.assessmentItem.dimension', 'parentReport']);

        // Get historical data
        $historical = HistoricalTracking::where('siswa_id', $assessment->siswa_id)
                                       ->with('dimension')
                                       ->orderBy('semester_id')
                                       ->get();

        // Get early warnings
        $warnings = EarlyWarning::where('siswa_id', $assessment->siswa_id)
                              ->where('semester_id', $assessment->semester_id)
                              ->with('dimension')
                              ->get();

        return view('tka.assessments.show', compact('assessment', 'historical', 'warnings'));
    }
}
```

#### **TkaParentController** (Orang Tua)
```php
namespace App\Http\Controllers\Tka;

use App\Http\Controllers\Controller;
use App\Models\Tka\ParentReport;
use App\Models\Tka\StudentAssessment;
use App\Models\Siswa;
use Illuminate\Http\Request;

class TkaParentController extends Controller
{
    public function index()
    {
        $ortu = auth()->user();
        $children = Siswa::where('nama_orang_tua', 'like', "%{$ortu->nama_lengkap}%")->get();

        $reports = ParentReport::whereIn('siswa_id', $children->pluck('id'))
                              ->with(['assessment.siswa', 'assessment.semester'])
                              ->latest()
                              ->get();

        return view('tka.parent.index', compact('reports', 'children'));
    }

    public function show(ParentReport $report)
    {
        $this->authorize('view', $report);

        // Mark as read
        if (!$report->dibaca_oleh_ortu) {
            $report->update([
                'dibaca_oleh_ortu' => true,
                'tanggal_dibaca' => now(),
                'ortu_user_id' => auth()->id(),
            ]);
        }

        $report->load(['assessment.siswa', 'assessment.responses.assessmentItem.dimension']);

        return view('tka.parent.show', compact('report'));
    }
}
```

---

### 3.4 ROUTE DEFINITIONS

Add to `routes/web.php`:

```php
// ==================== CBT ROUTES ====================
Route::middleware(['auth', 'role:guru'])->prefix('cbt')->name('cbt.')->group(function () {
    // Question Banks
    Route::prefix('question-banks')->name('question-banks.')->group(function () {
        Route::get('/', [CbtQuestionBankController::class, 'index'])->name('index');
        Route::get('/create', [CbtQuestionBankController::class, 'create'])->name('create');
        Route::post('/', [CbtQuestionBankController::class, 'store'])->name('store');
        Route::get('/{bank}', [CbtQuestionBankController::class, 'show'])->name('show');
        Route::get('/{bank}/edit', [CbtQuestionBankController::class, 'edit'])->name('edit');
        Route::put('/{bank}', [CbtQuestionBankController::class, 'update'])->name('update');
        Route::delete('/{bank}', [CbtQuestionBankController::class, 'destroy'])->name('destroy');
        Route::get('/{bank}/api/questions', [CbtQuestionBankController::class, 'apiGetQuestions'])->name('api.questions');
    });

    // Questions
    Route::prefix('questions')->name('questions.')->group(function () {
        Route::post('/{bank}', [CbtQuestionController::class, 'store'])->name('store');
        Route::put('/{question}', [CbtQuestionController::class, 'update'])->name('update');
        Route::delete('/{question}', [CbtQuestionController::class, 'destroy'])->name('destroy');
    });

    // Test Sessions
    Route::prefix('sessions')->name('sessions.')->group(function () {
        Route::get('/', [CbtTestSessionController::class, 'index'])->name('index');
        Route::get('/create', [CbtTestSessionController::class, 'create'])->name('create');
        Route::post('/', [CbtTestSessionController::class, 'store'])->name('store');
        Route::get('/{session}', [CbtTestSessionController::class, 'show'])->name('show');
        Route::post('/{session}/publish', [CbtTestSessionController::class, 'publish'])->name('publish');
        Route::post('/{session}/start', [CbtTestSessionController::class, 'start'])->name('start');
        Route::post('/{session}/complete', [CbtTestSessionController::class, 'complete'])->name('complete');
        Route::get('/{session}/monitoring', [CbtTestSessionController::class, 'monitoring'])->name('monitoring');
        Route::get('/{session}/api/monitoring', [CbtTestSessionController::class, 'apiMonitoring'])->name('api.monitoring');
    });

    // Integrity (Anti-Cheat)
    Route::prefix('integrity')->name('integrity.')->group(function () {
        Route::post('/{result}/log', [CbtIntegrityController::class, 'logViolation'])->name('log');
        Route::get('/{result}/violations', [CbtIntegrityController::class, 'getViolations'])->name('violations');
    });
});

// CBT Student Routes
Route::middleware(['auth', 'role:siswa'])->prefix('cbt/student')->name('cbt.student.')->group(function () {
    Route::get('/', [CbtStudentController::class, 'index'])->name('index');
    Route::get('/{session}/start', [CbtStudentController::class, 'start'])->name('start');
    Route::post('/{result}/answer', [CbtStudentController::class, 'submitAnswer'])->name('answer');
    Route::post('/{result}/submit', [CbtStudentController::class, 'submit'])->name('submit');
    Route::get('/{result}/result', [CbtStudentController::class, 'result'])->name('result');
});

// ==================== TRYOUT ROUTES ====================
Route::middleware(['auth', 'role:guru'])->prefix('tryout')->name('tryout.')->group(function () {
    // Packages
    Route::prefix('packages')->name('packages.')->group(function () {
        Route::get('/', [TryoutPackageController::class, 'index'])->name('index');
        Route::get('/create', [TryoutPackageController::class, 'create'])->name('create');
        Route::post('/', [TryoutPackageController::class, 'store'])->name('store');
        Route::get('/{package}', [TryoutPackageController::class, 'show'])->name('show');
    });

    // Sessions
    Route::prefix('sessions')->name('sessions.')->group(function () {
        Route::get('/', [TryoutSessionController::class, 'index'])->name('index');
        Route::get('/create', [TryoutSessionController::class, 'create'])->name('create');
        Route::post('/', [TryoutSessionController::class, 'store'])->name('store');
        Route::get('/{session}', [TryoutSessionController::class, 'show'])->name('show');
        Route::post('/{session}/publish', [TryoutSessionController::class, 'publish'])->name('publish');
    });
});

// Tryout Student Routes
Route::middleware(['auth', 'role:siswa'])->prefix('tryout/student')->name('tryout.student.')->group(function () {
    Route::get('/', [TryoutStudentController::class, 'index'])->name('index');
    Route::get('/{session}/start', [TryoutStudentController::class, 'start'])->name('start');
    Route::post('/{result}/subject', [TryoutStudentController::class, 'submitSubject'])->name('subject');
    Route::post('/{result}/complete', [TryoutStudentController::class, 'complete'])->name('complete');
    Route::get('/{result}/result', [TryoutStudentController::class, 'result'])->name('result');
});

// ==================== TKA ROUTES ====================
Route::middleware(['auth', 'role:guru'])->prefix('tka')->name('tka.')->group(function () {
    // Dimensions
    Route::prefix('dimensions')->name('dimensions.')->group(function () {
        Route::get('/', [TkaDimensionController::class, 'index'])->name('index');
        Route::get('/create', [TkaDimensionController::class, 'create'])->name('create');
        Route::post('/', [TkaDimensionController::class, 'store'])->name('store');
        Route::get('/{dimension}', [TkaDimensionController::class, 'show'])->name('show');
        Route::post('/{dimension}/item', [TkaDimensionController::class, 'addItem'])->name('add-item');
    });

    // Assessments
    Route::prefix('assessments')->name('assessments.')->group(function () {
        Route::get('/', [TkaAssessmentController::class, 'index'])->name('index');
        Route::get('/create', [TkaAssessmentController::class, 'create'])->name('create');
        Route::post('/', [TkaAssessmentController::class, 'store'])->name('store');
        Route::get('/{assessment}', [TkaAssessmentController::class, 'show'])->name('show');
    });
});

// TKA Parent Routes
Route::middleware(['auth', 'role:ortu'])->prefix('tka/parent')->name('tka.parent.')->group(function () {
    Route::get('/', [TkaParentController::class, 'index'])->name('index');
    Route::get('/{report}', [TkaParentController::class, 'show'])->name('show');
});
```

---

## 4. UI/UX FLOW & FRONTEND STRUCTURE

### 4.1 CBT UI/UX FLOW

#### **Guru Dashboard - CBT Management**

```
┌─────────────────────────────────────────────────────────┐
│  SIMANTAP - GURU DASHBOARD                              │
├─────────────────────────────────────────────────────────┤
│  Navigation: Dashboard | Materi | Kuis | e-Rapor | CBT  │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  CBT MENU                                               │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐ │
│  │ 📚 Bank Soal │  │ 📝 Sesi Ujian│  │ 📊 Monitoring│ │
│  │              │  │              │  │              │ │
│  │ 15 Bank      │  │ 8 Sesi Aktif │  │ 3 Sedang     │ │
│  └──────────────┘  └──────────────┘  └──────────────┘ │
│                                                         │
│  Recent Activity:                                       │
│  • Bank Soal Matematika - Baru saja                    │
│  • Sesi Ujian Bahasa Indonesia - 3 menit lalu         │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

#### **Bank Soal Management UI**

```
┌─────────────────────────────────────────────────────────┐
│  Bank Soal CBT - Matematika                            │
├─────────────────────────────────────────────────────────┤
│  [Kembali]  [Edit]  [Tambah Soal]  [Import CSV]       │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Info:                                                 │
│  • Kode: MTK-001                                      │
│  • Tingkat: Sedang                                    │
│  • Jumlah Soal: 25                                    │
│  • Status: Aktif                                      │
│                                                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │ Daftar Soal (25 soal)                            │   │
│  ├─────────────────────────────────────────────────┤   │
│  │ No.  Tipe    Poin   Pertanyaan          Aksi    │   │
│  ├─────────────────────────────────────────────────┤   │
│  │ 1    P.Ganda  2.0    Suku ke-5...       [Edit] │   │
│  │ 2    Essay   5.0    Jelaskan...        [Edit] │   │
│  │ 3    B/S     1.0    2+2=4?            [Edit] │   │
│  │ ...                                           │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
│  [Tambah Soal Baru]  [Acak Urutan]  [Export]           │
└─────────────────────────────────────────────────────────┘
```

#### **Tambah/Edit Soal UI**

```
┌─────────────────────────────────────────────────────────┐
│  Tambah Soal - Bank Matematika                          │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Tipe Soal: [Pilihan Ganda ▼]                           │
│                                                         │
│  Pertanyaan:                                            │
│  ┌─────────────────────────────────────────────────┐   │
│  │ Tentukan suku ke-5 dari barisan: 2, 6, 10, 14... │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
│  Media: [Upload Gambar/Audio/Video]                    │
│                                                         │
│  Poin: [2.0]    Waktu Jawab: [0 detik = unlimited]    │
│                                                         │
│  Opsi Jawaban:                                          │
│  ┌─────────────────────────────────────────────────┐   │
│  │ ☑ A. 18                                         │   │
│  │ ☐ B. 20  [✓ Benar]                             │   │
│  │ ☐ C. 22                                         │   │
│  │ ☐ D. 24                                         │   │
│  │ ☐ E. 26                                         │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
│  Pembahasan:                                            │
│  ┌─────────────────────────────────────────────────┐   │
│  │ Un = a + (n-1)b                                 │   │
│  │ U5 = 2 + (5-1)4 = 2 + 16 = 18                  │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
│  [Batal]  [Simpan]  [Simpan & Tambah Lagi]            │
└─────────────────────────────────────────────────────────┘
```

#### **Sesi Ujian Configuration UI**

```
┌─────────────────────────────────────────────────────────┐
│  Buat Sesi Ujian Baru                                   │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Nama Sesi: [Ujian Harian Matematika Kelas X]          │
│                                                         │
│  Bank Soal: [Matematika - MTK-001 ▼]                   │
│                                                         │
│  Jadwal:                                               │
│  Tanggal Mulai: [📅 2026-10-15 08:00]                 │
│  Tanggal Selesai: [📅 2026-10-15 10:00]               │
│  Durasi: [120 menit]                                   │
│                                                         │
│  Konfigurasi:                                          │
│  ☑ Acak Soal (dari bank)                              │
│  ☑ Acak Opsi Jawaban                                  │
│  ☐ Tampilkan Nilai ke Siswa Setelah Selesai            │
│  ☐ Boleh Ulang Ujian                                  │
│  Maksimal Percobaan: [1]                               │
│  Passing Grade: [60.0]                                │
│                                                         │
│  Target:                                               │
│  Kelas: [X ▼]  atau  Rombel: [X-A ▼]                  │
│                                                         │
│  Jumlah Soal Tampil: [20] dari [25] tersedia          │
│                                                         │
│  [Batal]  [Simpan Draft]  [Publikasikan & Mulai]      │
└─────────────────────────────────────────────────────────┘
```

#### **Live Monitoring UI (Guru)**

```
┌─────────────────────────────────────────────────────────┐
│  Monitoring Sesi: Ujian Matematika Kelas X             │
├─────────────────────────────────────────────────────────┤
│  Status: ONGOING  |  Waktu: 08:30 / 10:00 (90 menit) │
│  [Auto-refresh: 5 detik ▼]  [Selesaikan Sesi]         │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Statistik:                                             │
│  Total Siswa: 30  |  Sedang Mengerjakan: 25           │
│  Selesai: 3  |  Belum Mulai: 2                         │
│                                                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │ Siswa Sedang Mengerjakan (25)                    │   │
│  ├─────────────────────────────────────────────────┤   │
│  │ Nama              Soal  Waktu  Integrity  Aksi  │   │
│  ├─────────────────────────────────────────────────┤   │
│  │ Ahmad Fauzi      12/20  45:30  100%       [👁] │   │
│  │ Budi Santoso     8/20   60:15  90%        [👁] │   │
│  │ Citra Dewi      15/20  30:00  95%        [👁] │   │
│  │ ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━  │   │
│  │ ⚠️ Dian Pratama   5/20   75:00  70%  [❌ Stop]│   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │ Siswa Selesai (3)                                │   │
│  ├─────────────────────────────────────────────────┤   │
│  │ Nama              Nilai  Waktu  Status          │   │
│  ├─────────────────────────────────────────────────┤   │
│  │ Eko Prasetyo      85     45:00  ✅ Lulus        │   │
│  │ Fani Rahma        92     40:00  ✅ Lulus        │   │
│  │ Gita Permata      55     58:00  ❌ Tidak Lulus  │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

#### **Siswa - Take CBT UI**

```
┌─────────────────────────────────────────────────────────┐
│  Ujian: Matematika - Ujian Harian                     │
├─────────────────────────────────────────────────────────┤
│  Siswa: Ahmad Fauzi  |  Kelas: X-A                     │
│                                                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │ Timer: ⏱️ 45:30 / 120:00                        │   │
│  │ Progress: 12/20 soal (60%)                       │   │
│  │ Integrity: ✅ 100%                               │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │ Soal No. 13 / 20                                │   │
│  │                                                  │   │
│  │ Tentukan suku ke-7 dari barisan: 3, 7, 11, 15... │   │
│  │                                                  │   │
│  │ [Gambar/Video placeholder jika ada]              │   │
│  │                                                  │   │
│  │ Opsi Jawaban:                                    │   │
│  │ ⚪ A. 25                                         │   │
│  │ ⚪ B. 27                                         │   │
│  │ ⚪ C. 29                                         │   │
│  │ ⚪ D. 31                                         │   │
│  │ ⚪ E. 33                                         │   │
│  │                                                  │   │
│  │ [?] Ragu-ragu  [⏭ Lewati]  [➡️ Berikutnya]     │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
│  Navigator:                                            │
│  [⬅️ Sebelumnya]  Soal: [13 ▼]  [➡️ Berikutnya]      │
│                                                         │
│  [📋 Ragu-ragu: 3]  [✅ Kumpulkan Jawaban]             │
└─────────────────────────────────────────────────────────┘
```

#### **Siswa - Result UI**

```
┌─────────────────────────────────────────────────────────┐
│  Hasil Ujian: Matematika - Ujian Harian                │
├─────────────────────────────────────────────────────────┤
│  Siswa: Ahmad Fauzi  |  Kelas: X-A                     │
│  Tanggal: 15 Oktober 2026  |  Durasi: 45 menit         │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │ Skor Akhir                                       │   │
│  │                                                  │   │
│  │         ████████████████████░░░ 85/100          │   │
│  │                                                  │   │
│  │  Nilai: 85  |  Benar: 17  |  Salah: 3          │   │
│  │  Status: ✅ LULUS (Passing Grade: 60)           │   │
│  │  Peringkat: 3 dari 30 siswa                     │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │ Detail Jawaban                                   │   │
│  ├─────────────────────────────────────────────────┤   │
│  │ No.  Jawaban  Kunci  Status  Poin              │   │
│  ├─────────────────────────────────────────────────┤   │
│  │ 1    B        B      ✅      2.0               │   │
│  │ 2    C        C      ✅      2.0               │   │
│  │ 3    A        B      ❌      0.0               │   │
│  │ 4    -        A      ⏭️      0.0               │   │
│  │ ...                                           │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
│  [📄 Download PDF]  [📊 Lihat Grafik]  [🏠 Dashboard] │
└─────────────────────────────────────────────────────────┘
```

---

### 4.2 TRYOUT UI/UX FLOW

#### **Tryout Package Management UI**

```
┌─────────────────────────────────────────────────────────┐
│  Paket Tryout                                          │
├─────────────────────────────────────────────────────────┤
│  [Buat Paket Baru]  [Import Standar]                   │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Filter: [Jenjang: Semua ▼]  [Kategori: Semua ▼]       │
│                                                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │ Daftar Paket Tryout                            │   │
│  ├─────────────────────────────────────────────────┤   │
│  │ Paket           Jenjang  Mapel  Status  Aksi    │   │
│  ├─────────────────────────────────────────────────┤   │
│  │ UTBK 2026      SMA      8      Aktif   [Detail]│   │
│  │ SIMAK UI 2026  SMA      6      Aktif   [Detail]│   │
│  │ SNBP 2026      SMA      5      Draft   [Detail]│   │
│  └─────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
```

#### **Tryout Package Detail UI**

```
┌─────────────────────────────────────────────────────────┐
│  Paket: UTBK 2026                                      │
├─────────────────────────────────────────────────────────┤
│  [Edit]  [Tambah Sesi]  [Unduh Template]               │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Info:                                                 │
│  Kategori: Masuk PTN  |  Jenjang: SMA                   │
│  Tahun: 2026  |  Passing Grade: 70.0                  │
│  Durasi Total: 240 menit  |  Mapel: 8                  │
│                                                         │
│  Mata Pelajaran:                                       │
│  ┌─────────────────────────────────────────────────┐   │
│  │ No.  Mapel              Bobot  Soal  Durasi      │   │
│  ├─────────────────────────────────────────────────┤   │
│  │ 1    Matematika        1.0    20    45          │   │
│  │ 2    Bahasa Indonesia  1.0    20    45          │   │
│  │ 3    Bahasa Inggris    1.0    20    45          │   │
│  │ 4    Fisika            0.8    15    30          │   │
│  │ 5    Kimia             0.8    15    30          │   │
│  │ 6    Biologi           0.8    15    30          │   │
│  │ 7    Sejarah           0.5    10    10          │   │
│  │ 8    Geografi          0.5    10    10          │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
│  Standar Nilai:                                        │
│  Matematika: 75+  |  Bahasa Indonesia: 70+             │
│  Bahasa Inggris: 80+  |  IPA: 70+                      │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

#### **Siswa - Tryout Dashboard UI**

```
┌─────────────────────────────────────────────────────────┐
│  Tryout - Siswa Dashboard                               │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Tryout Tersedia:                                      │
│  ┌─────────────────────────────────────────────────┐   │
│  │ UTBK 2026 - Gelombang 1                          │   │
│  │ Durasi: 240 menit  |  Mapel: 8                    │   │
│  │ Deadline: 20 Oktober 2026                        │   │
│  │ [🚀 Mulai Tryout]                                 │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
│  Riwayat Tryout:                                        │
│  ┌─────────────────────────────────────────────────┐   │
│  │ Paket           Nilai  Status  Peringkat  Aksi   │   │
│  ├─────────────────────────────────────────────────┤   │
│  │ SIMAK UI 2025  78     Lulus   12/100   [Detail]│   │
│  │ SNBP 2025      65     Tidak   45/100   [Detail]│   │
│  │ UTBK Pra-2025  82     Lulus   8/100    [Detail]│   │
│  └─────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
```

#### **Siswa - Tryout Result UI**

```
┌─────────────────────────────────────────────────────────┐
│  Hasil Tryout: UTBK 2026                                │
├─────────────────────────────────────────────────────────┤
│  Siswa: Ahmad Fauzi  |  Kelas: XII-A                    │
│  Tanggal: 15 Oktober 2026  |  Durasi: 235 menit         │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │ Skor Total                                      │   │
│  │                                                  │   │
│  │         ████████████████████░░░ 78/100          │   │
│  │                                                  │   │
│  │  Nilai: 78  |  Status: ✅ LULUS                │   │
│  │  Peringkat: 12 dari 100 peserta                 │   │
│  │  Percentile: 88% (di atas 88% peserta)         │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │ Detail per Mata Pelajaran                       │   │
│  ├─────────────────────────────────────────────────┤   │
│  │ Mapel              Nilai  Max  %   Benar  Status│   │
│  ├─────────────────────────────────────────────────┤   │
│  │ Matematika         72     90   80%  16/20  ✅   │   │
│  │ Bahasa Indonesia  68     80   85%  17/20  ✅   │   │
│  │ Bahasa Inggris    80     80   100% 20/20  ✅   │   │
│  │ Fisika            60     75   80%  12/15  ⚠️   │   │
│  │ Kimia             65     75   87%  13/15  ✅   │   │
│  │ Biologi           70     75   93%  14/15  ✅   │   │
│  │ Sejarah           55     50   110% 10/10  ✅   │   │
│  │ Geografi          60     50   120% 10/10  ✅   │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │ Analisis Kelemahan                              │   │
│  │                                                  │   │
│  │ ⚠️ Fisika - Perlu perhatian khusus              │   │
│  │    Topik yang lemah: Dinamika, Listrik          │   │
│  │    Rekomendasi: Latihan soal lebih banyak       │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │ Prediksi Peminatan                             │   │
│  │                                                  │   │
│  │ 🎯 Teknik Informatika (90% match)               │   │
│  │ 🎯 Sistem Informasi (85% match)                 │   │
│  │ 🎯 Teknik Elektro (75% match)                   │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
│  [📄 Download Lengkap]  [📊 Banding dengan Standar]    │
└─────────────────────────────────────────────────────────┘
```

---

### 4.3 TKA ENHANCED UI/UX FLOW

#### **Guru - TKA Assessment UI**

```
┌─────────────────────────────────────────────────────────┐
│  Penilaian TKA - Kelas X-A                              │
├─────────────────────────────────────────────────────────┤
│  Siswa: Ahmad Fauzi  |  Semester: Ganjil 2026/2027    │
│  Tanggal: 15 Oktober 2026                              │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │ 1. Religius                                      │   │
│  │ Threshold: 3.0  |  Skor Saat Ini: 3.2           │   │
│  │                                                  │   │
│  │ Indikator:                                       │   │
│  │ ☑ Melaksanakan ibadah secara rutin (4/4)        │   │
│  │ ☑ Menghargai perbedaan agama (3/4)              │   │
│  │ ☑ Berbuat baik kepada sesama (4/4)              │   │
│  │ ☑ Menjaga kebersihan lingkungan (3/4)          │   │
│  │                                                  │   │
│  │ Total: 14/16 = 3.5                              │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │ 2. Nasionalis                                    │   │
│  │ Threshold: 3.0  |  Skor Saat Ini: 2.8           │   │
│  │                                                  │   │
│  │ Indikator:                                       │   │
│  │ ☑ Menghargai budaya Indonesia (3/4)             │   │
│  │ ☑ Menjaga persatuan bangsa (3/4)                │   │
│  │ ☑ Bangga menjadi WNI (4/4)                      │   │
│  │ ☑ Mengenal simbol negara (2/4)                  │   │
│  │                                                  │   │
│  │ Total: 12/16 = 3.0                              │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
│  [Simpan Draft]  [Simpan & Generate Report]            │
└─────────────────────────────────────────────────────────┘
```

#### **Guru - TKA Report UI**

```
┌─────────────────────────────────────────────────────────┐
│  Laporan TKA: Ahmad Fauzi                               │
├─────────────────────────────────────────────────────────┤
│  Kelas: X-A  |  Semester: Ganjil 2026/2027            │
│  Tanggal Penilaian: 15 Oktober 2026                    │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │ Radar Chart (Spider Web)                          │   │
│  │                                                  │   │
│  │              Religius                             │   │
│  │                 3.5                               │   │
│  │                  /\                               │   │
│  │                 /  \                              │   │
│  │                /    \                             │   │
│  │   Nasionalis 2.8      Mandiri 3.2                │   │
│  │      \          /                                  │   │
│  │       \        /                                   │   │
│  │        \      /                                    │   │
│  │    Gotong-royong 3.0    Kreatif 2.5               │   │
│  │          \    /                                    │   │
│  │           \  /                                     │   │
│  │            \/                                      │   │
│  │       Integritas 3.5  Berdaya Saing 2.8           │   │
│  │                                                  │   │
│  │ Threshold: 3.0 (garis merah)                     │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
│  Status Kesiapan: ✅ SIAP (Total: 3.1/3.0)            │
│                                                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │ Detail per Dimensi                              │   │
│  ├─────────────────────────────────────────────────┤   │
│  │ Dimensi        Skor  Threshold  Status          │   │
│  ├─────────────────────────────────────────────────┤   │
│  │ Religius       3.5   3.0         ✅ Siap         │   │
│  │ Nasionalis     2.8   3.0         ⚠️ Perlu Bimbingan│   │
│  │ Mandiri        3.2   3.0         ✅ Siap         │   │
│  │ Gotong-royong  3.0   3.0         ✅ Siap         │   │
│  │ Integritas     3.5   3.0         ✅ Siap         │   │
│  │ Kreatif        2.5   3.0         ⚠️ Perlu Bimbingan│   │
│  │ Berdaya Saing  2.8   3.0         ⚠️ Perlu Bimbingan│   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │ Peringatan Dini (Early Warning)                  │   │
│  │                                                  │   │
│  │ ⚠️ Kreatif - Tingkat: Sedang                    │   │
│  │    Alasan: Skor 2.5 di bawah threshold 3.0      │   │
│  │    Rekomendasi: Berikan tugas proyek kreatif    │   │
│  │    Status: [Pending ▼]  [Ditangani oleh: -]     │   │
│  │                                                  │   │
│  │ ⚠️ Berdaya Saing - Tingkat: Sedang               │   │
│  │    Alasan: Skor 2.8 di bawah threshold 3.0      │   │
│  │    Rekomendasi: Motivasi untuk kompetisi         │   │
│  │    Status: [Pending ▼]  [Ditangani oleh: -]     │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │ Tracking Perkembangan (Historical)               │   │
│  │                                                  │   │
│  │ Religius:                                       │   │
│  │ Semester 1: 3.5 ↗️ (+0.3 dari sebelumnya)       │   │
│  │ Semester 2: [Belum dinilai]                     │   │
│  │                                                  │   │
│  │ Nasionalis:                                     │   │
│  │ Semester 1: 2.8 ↘️ (-0.2 dari sebelumnya)       │   │
│  │ Semester 2: [Belum dinilai]                     │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
│  [📄 Download PDF]  [📧 Kirim ke Orang Tua]             │
└─────────────────────────────────────────────────────────┘
```

#### **Orang Tua - TKA Report UI**

```
┌─────────────────────────────────────────────────────────┐
│  Laporan TKA - Orang Tua                               │
├─────────────────────────────────────────────────────────┤
│  Anak: Ahmad Fauzi  |  Kelas: X-A                      │
│  Semester: Ganjil 2026/2027                            │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │ Ringkasan                                       │   │
│  │                                                  │   │
│  │ Status Kesiapan: ✅ SIAP                          │   │
│  │ Total Skor: 3.1 / 3.0                           │   │
│  │                                                  │   │
│  │ 💬 Rekomendasi Sekolah:                          │   │
│  │ "Anak menunjukkan kematangan yang sangat baik.  │   │
│  │ Terus dukung dengan aktivitas positif di rumah." │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │ Aktivitas yang Disarankan di Rumah              │   │
│  │                                                  │   │
│  │ 📖 Religius:                                    │   │
│  │   • Ajarkan anak untuk sholat berjamaah         │   │
│  │   • Baca cerita tentang nabi dan rasul          │   │
│  │                                                  │   │
│  │ 🎨 Kreatif (Perlu Perhatian):                  │   │
│  │   • Berikan tugas membuat karya seni            │   │
│  │   • Dorong anak untuk berpikir out-of-the-box    │   │
│  │                                                  │   │
│  │ 🏆 Berdaya Saing (Perlu Perhatian):            │   │
│  │   • Ikutkan anak dalam kompetisi olahraga        │   │
│  │   • Berikan tantangan untuk mencapai target     │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │ Detail Skor per Dimensi                         │   │
│  ├─────────────────────────────────────────────────┤   │
│  │ Dimensi        Skor  Status  Catatan Guru       │   │
│  ├─────────────────────────────────────────────────┤   │
│  │ Religius       3.5   ✅     Sangat baik         │   │
│  │ Nasionalis     2.8   ⚠️     Perlu ditingkatkan  │   │
│  │ Mandiri        3.2   ✅     Baik                │   │
│  │ Gotong-royong  3.0   ✅     Cukup               │   │
│  │ Integritas     3.5   ✅     Sangat baik         │   │
│  │ Kreatif        2.5   ⚠️     Perlu bimbingan     │   │
│  │ Berdaya Saing  2.8   ⚠️     Perlu latihan       │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
│  [✅ Tandai Sudah Dibaca]  [📄 Download PDF]            │
└─────────────────────────────────────────────────────────┘
```

---

### 4.4 FRONTEND COMPONENT STRUCTURE

#### **File Structure (Blade Templates)**

```
resources/views/
├── cbt/
│   ├── layouts/
│   │   └── cbt-app.blade.php          # CBT layout
│   ├── question-banks/
│   │   ├── index.blade.php            # List bank soal
│   │   ├── create.blade.php           # Create bank
│   │   ├── show.blade.php             # Detail bank
│   │   └── edit.blade.php             # Edit bank
│   ├── questions/
│   │   ├── create.blade.php           # Add question form
│   │   └── edit.blade.php             # Edit question
│   ├── sessions/
│   │   ├── index.blade.php            # List sesi
│   │   ├── create.blade.php           # Create sesi
│   │   ├── show.blade.php             # Detail sesi
│   │   └── monitoring.blade.php        # Live monitoring
│   └── student/
│       ├── index.blade.php            # Student dashboard
│       ├── take.blade.php             # Take exam interface
│       └── result.blade.php           # Result view
│
├── tryout/
│   ├── layouts/
│   │   └── tryout-app.blade.php       # Tryout layout
│   ├── packages/
│   │   ├── index.blade.php            # List packages
│   │   ├── create.blade.php           # Create package
│   │   └── show.blade.php             # Package detail
│   ├── sessions/
│   │   ├── index.blade.php            # List sessions
│   │   ├── create.blade.php           # Create session
│   │   └── show.blade.php             # Session detail
│   └── student/
│       ├── index.blade.php            # Student dashboard
│       ├── take.blade.php             # Take tryout
│       └── result.blade.php           # Result view
│
└── tka/
    ├── layouts/
    │   └── tka-app.blade.php          # TKA layout
    ├── dimensions/
    │   ├── index.blade.php            # List dimensions
    │   ├── create.blade.php           # Create dimension
    │   └── show.blade.php             # Dimension detail
    ├── assessments/
    │   ├── index.blade.php            # List assessments
    │   ├── create.blade.php           # Create assessment
    │   └── show.blade.php             # Assessment report
    └── parent/
        ├── index.blade.php            # Parent dashboard
        └── show.blade.php             # Parent report view
```

#### **JavaScript Structure**

```
resources/js/
├── cbt/
│   ├── question-bank.js               # Bank soal management
│   ├── question-editor.js             # Question form logic
│   ├── session-manager.js            # Session configuration
│   ├── monitoring.js                 # Live monitoring
│   └── student-exam.js               # Student exam interface
│       ├── timer.js                  # Countdown timer
│       ├── question-navigator.js     # Next/prev navigation
│       ├── answer-saver.js           # Auto-save answers
│       └── integrity-checker.js      # Anti-cheat detection
│
├── tryout/
│   ├── package-manager.js            # Package management
│   ├── session-manager.js            # Session management
│   └── student-tryout.js             # Student tryout interface
│       ├── subject-timer.js          # Per-subject timer
│       ├── progress-tracker.js       # Track progress
│       └── result-analyzer.js        # Result display
│
└── tka/
    ├── dimension-manager.js          # Dimension management
    ├── assessment-form.js            # Assessment input
    ├── radar-chart.js                # Radar chart visualization
    ├── historical-tracker.js         # Historical data display
    └── parent-viewer.js              # Parent report viewer
```

#### **CSS Structure**

```
resources/css/
├── cbt/
│   ├── question-bank.css             # Bank soal styles
│   ├── question-editor.css           # Question form styles
│   ├── monitoring.css                # Monitoring dashboard
│   └── student-exam.css              # Student exam interface
│
├── tryout/
│   ├── package.css                   # Package styles
│   └── student-tryout.css            # Student tryout styles
│
└── tka/
    ├── assessment.css                # Assessment form styles
    ├── radar-chart.css               # Chart styles
    └── parent-report.css             # Parent report styles
```

---

## 5. SECURITY & ANTI-CHEAT MEASURES

### 5.1 FRONTEND ANTI-CHEAT IMPLEMENTATION

#### **JavaScript Integrity Checker**

```javascript
// resources/js/cbt/student-exam/integrity-checker.js

class IntegrityChecker {
    constructor(testResultId) {
        this.testResultId = testResultId;
        this.violations = [];
        this.tabSwitchCount = 0;
        this.maxTabSwitches = 3;
        this.isLocked = false;
        this.init();
    }

    init() {
        // Detect tab switching
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.logViolation('tab_switch', 'Siswa meninggalkan tab ujian');
            }
        });

        // Detect window focus loss
        window.addEventListener('blur', () => {
            this.logViolation('focus_lost', 'Jendela browser kehilangan fokus');
        });

        // Prevent copy-paste
        document.addEventListener('copy', (e) => {
            e.preventDefault();
            this.logViolation('copy_paste', 'Percobaan copy teks soal');
        });

        document.addEventListener('paste', (e) => {
            e.preventDefault();
            this.logViolation('copy_paste', 'Percobaan paste jawaban');
        });

        // Detect multiple tabs (using localStorage)
        this.checkMultipleTabs();

        // Prevent print screen (partially)
        document.addEventListener('keydown', (e) => {
            if (e.key === 'PrintScreen') {
                this.logViolation('screen_capture', 'Percobaan screenshot');
                alert('Screenshot tidak diperbolehkan selama ujian!');
            }
        });

        // Prevent right-click
        document.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            this.logViolation('suspicious_activity', 'Percobaan klik kanan');
        });

        // Periodic integrity check
        setInterval(() => this.checkIntegrity(), 30000); // Every 30 seconds
    }

    checkMultipleTabs() {
        const tabId = localStorage.getItem('cbt_tab_id');
        if (tabId && tabId !== this.testResultId) {
            this.logViolation('multiple_tabs', 'Terdeteksi multiple tab terbuka');
            this.lockExam();
        } else {
            localStorage.setItem('cbt_tab_id', this.testResultId);
        }

        window.addEventListener('beforeunload', () => {
            localStorage.removeItem('cbt_tab_id');
        });
    }

    logViolation(type, description) {
        if (this.isLocked) return;

        this.violations.push({
            type,
            description,
            timestamp: new Date().toISOString()
        });

        this.tabSwitchCount++;

        // Send to server
        this.sendViolationToServer(type, description);

        // Lock if too many violations
        if (this.tabSwitchCount >= this.maxTabSwitches) {
            this.lockExam();
        }
    }

    async sendViolationToServer(type, description) {
        try {
            await fetch(`/cbt/integrity/${this.testResultId}/log`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    jenis_pelanggaran: type,
                    deskripsi: description
                })
            });
        } catch (error) {
            console.error('Failed to log violation:', error);
        }
    }

    checkIntegrity() {
        // Check if still on exam page
        if (!document.querySelector('.exam-container')) {
            this.logViolation('suspicious_activity', 'Konten ujian tidak terdeteksi');
        }

        // Check if browser dev tools are open (basic detection)
        const threshold = 160;
        if (window.outerWidth - window.innerWidth > threshold ||
            window.outerHeight - window.innerHeight > threshold) {
            this.logViolation('suspicious_activity', 'Terdeteksi DevTools terbuka');
        }
    }

    lockExam() {
        this.isLocked = true;
        alert('Ujian dikunci karena terlalu banyak pelanggaran kejujuran. Hubungi guru untuk info lebih lanjut.');

        // Disable all inputs
        document.querySelectorAll('input, textarea, button').forEach(el => {
            el.disabled = true;
        });

        // Auto-submit
        const submitBtn = document.querySelector('[data-action="submit"]');
        if (submitBtn) {
            submitBtn.click();
        }
    }

    getViolationCount() {
        return this.violations.length;
    }

    getIntegrityScore() {
        return Math.max(0, 100 - (this.violations.length * 10));
    }
}

// Initialize on exam page
document.addEventListener('DOMContentLoaded', () => {
    const testResultId = document.querySelector('[data-test-result-id]')?.dataset.testResultId;
    if (testResultId) {
        window.integrityChecker = new IntegrityChecker(testResultId);
    }
});
```

#### **Timer with Auto-Submit**

```javascript
// resources/js/cbt/student-exam/timer.js

class ExamTimer {
    constructor(durationMinutes, onExpire) {
        this.duration = durationMinutes * 60; // Convert to seconds
        this.remaining = this.duration;
        this.onExpire = onExpire;
        this.interval = null;
        this.isPaused = false;
    }

    start() {
        this.interval = setInterval(() => {
            if (!this.isPaused) {
                this.remaining--;
                this.updateDisplay();

                if (this.remaining <= 0) {
                    this.stop();
                    this.onExpire();
                }
            }
        }, 1000);
    }

    stop() {
        if (this.interval) {
            clearInterval(this.interval);
            this.interval = null;
        }
    }

    pause() {
        this.isPaused = true;
    }

    resume() {
        this.isPaused = false;
    }

    updateDisplay() {
        const minutes = Math.floor(this.remaining / 60);
        const seconds = this.remaining % 60;
        const display = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

        const timerElement = document.querySelector('[data-timer]');
        if (timerElement) {
            timerElement.textContent = display;

            // Change color when low
            if (this.remaining <= 300) { // 5 minutes
                timerElement.classList.add('text-danger');
            } else if (this.remaining <= 600) { // 10 minutes
                timerElement.classList.add('text-warning');
            }
        }

        // Warn at 5 minutes
        if (this.remaining === 300) {
            alert('⚠️ Waktu tersisa 5 menit!');
        }

        // Warn at 1 minute
        if (this.remaining === 60) {
            alert('⚠️ Waktu tersisa 1 menit!');
        }
    }

    getRemaining() {
        return this.remaining;
    }
}
```

#### **Auto-Save Answers**

```javascript
// resources/js/cbt/student-exam/answer-saver.js

class AnswerSaver {
    constructor(testResultId) {
        this.testResultId = testResultId;
        this.answers = {};
        this.saveInterval = null;
        this.init();
    }

    init() {
        // Load saved answers from localStorage
        const saved = localStorage.getItem(`cbt_answers_${this.testResultId}`);
        if (saved) {
            this.answers = JSON.parse(saved);
            this.restoreAnswers();
        }

        // Auto-save every 30 seconds
        this.saveInterval = setInterval(() => {
            this.saveToLocalStorage();
        }, 30000);

        // Save on page unload
        window.addEventListener('beforeunload', () => {
            this.saveToLocalStorage();
        });
    }

    saveAnswer(questionId, answer, isRaguRagu = false) {
        this.answers[questionId] = {
            answer,
            isRaguRagu,
            timestamp: new Date().toISOString()
        };

        // Save to server immediately
        this.sendToServer(questionId, answer, isRaguRagu);

        // Also save to localStorage
        this.saveToLocalStorage();
    }

    async sendToServer(questionId, answer, isRaguRagu) {
        try {
            const response = await fetch(`/cbt/student/${this.testResultId}/answer`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    question_id: questionId,
                    jawaban: answer,
                    ragu_ragu: isRaguRagu
                })
            });

            if (!response.ok) {
                throw new Error('Failed to save answer');
            }
        } catch (error) {
            console.error('Auto-save failed:', error);
            // Keep in localStorage for retry
        }
    }

    saveToLocalStorage() {
        localStorage.setItem(
            `cbt_answers_${this.testResultId}`,
            JSON.stringify(this.answers)
        );
    }

    restoreAnswers() {
        Object.keys(this.answers).forEach(questionId => {
            const { answer, isRaguRagu } = this.answers[questionId];
            const radioInput = document.querySelector(`input[name="q_${questionId}"][value="${answer}"]`);
            if (radioInput) {
                radioInput.checked = true;
            }

            const textInput = document.querySelector(`textarea[name="q_${questionId}"]`);
            if (textInput) {
                textInput.value = answer;
            }

            if (isRaguRagu) {
                const raguCheckbox = document.querySelector(`input[name="ragu_${questionId}"]`);
                if (raguCheckbox) {
                    raguCheckbox.checked = true;
                }
            }
        });
    }

    clear() {
        this.answers = {};
        localStorage.removeItem(`cbt_answers_${this.testResultId}`);
        if (this.saveInterval) {
            clearInterval(this.saveInterval);
        }
    }
}
```

---

### 5.2 BACKEND SECURITY MEASURES

#### **Middleware for CBT Security**

```php
// app/Http/Middleware/CbtSecurityMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Cbt\TestResult;

class CbtSecurityMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $testResultId = $request->route('result');

        if ($testResultId) {
            $result = TestResult::find($testResultId);

            // Check if exam is still valid
            if (!$result || $result->waktu_selesai) {
                return response()->json(['error' => 'Ujian sudah selesai'], 403);
            }

            // Check if time has expired
            $session = $result->testSession;
            $timeElapsed = now()->diffInSeconds($result->waktu_mulai);
            $maxTime = $session->durasi_menit * 60;

            if ($timeElapsed > $maxTime) {
                // Auto-submit if time expired
                return $this->autoSubmit($result);
            }

            // Check integrity score
            if ($result->integrity_score < 50) {
                return response()->json(['error' => 'Ujian dikunci karena pelanggaran'], 403);
            }
        }

        return $next($request);
    }

    protected function autoSubmit(TestResult $result)
    {
        // Calculate current score and submit
        $controller = new \App\Http\Controllers\Cbt\CbtStudentController();
        return $controller->submit($result);
    }
}
```

#### **Rate Limiting for API Endpoints**

```php
// routes/web.php (add rate limiting)

Route::middleware(['auth', 'role:siswa', 'throttle:60,1'])
    ->prefix('cbt/student')
    ->name('cbt.student.')
    ->group(function () {
        // Student CBT routes with rate limiting
        Route::post('/{result}/answer', [CbtStudentController::class, 'submitAnswer'])
            ->name('answer');
        Route::post('/{result}/submit', [CbtStudentController::class, 'submit'])
            ->name('submit');
    });
```

#### **IP-Based Session Locking**

```php
// app/Http/Middleware/CbtSessionLock.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Cbt\TestResult;

class CbtSessionLock
{
    public function handle(Request $request, Closure $next)
    {
        $testResultId = $request->route('result');

        if ($testResultId) {
            $result = TestResult::find($testResultId);

            if ($result && !$result->waktu_selesai) {
                // Check if IP matches
                if ($result->ip_address !== $request->ip()) {
                    return response()->json([
                        'error' => 'Ujian hanya dapat diakses dari IP yang sama'
                    ], 403);
                }

                // Check if user agent matches (basic session security)
                if ($result->user_agent !== $request->userAgent()) {
                    return response()->json([
                        'error' => 'Session tidak valid - browser berbeda'
                    ], 403);
                }
            }
        }

        return $next($request);
    }
}
```

---

### 5.3 DATA VALIDATION & SANITIZATION

#### **Question Content Sanitization**

```php
// app/Services/CbtQuestionSanitizer.php

namespace App\Services;

use Illuminate\Support\Facades\Purifier;

class CbtQuestionSanitizer
{
    protected $purifier;

    public function __construct()
    {
        $this->purifier = new \HTMLPurifier();
        $config = \HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'p,b,i,u,em,strong,a[href],img[src],ul,ol,li,br,span');
        $config->set('HTML.AllowedAttributes', 'a.href,img.src,span.style');
        $config->set('AutoFormat.RemoveEmpty', true);
        $this->purifier->config = $config;
    }

    public function sanitize($content)
    {
        return $this->purifier->purify($content);
    }

    public function sanitizeQuestion($questionData)
    {
        $questionData['pertanyaan'] = $this->sanitize($questionData['pertanyaan']);

        if (isset($questionData['opsi'])) {
            foreach ($questionData['opsi'] as &$opsi) {
                $opsi['isi'] = $this->sanitize($opsi['isi']);
            }
        }

        if (isset($questionData['pembahasan'])) {
            $questionData['pembahasan'] = $this->sanitize($questionData['pembahasan']);
        }

        return $questionData;
    }
}
```

#### **Answer Validation**

```php
// app/Http/Requests/CbtSubmitAnswerRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CbtSubmitAnswerRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'question_id' => 'required|exists:cbt_questions,id',
            'jawaban' => 'required|string|max:1000',
            'ragu_ragu' => 'boolean',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $questionId = $this->input('question_id');
            $answer = $this->input('jawaban');

            // Validate answer format based on question type
            $question = \App\Models\Cbt\Question::find($questionId);

            if ($question) {
                if ($question->tipe_soal === 'pilihan_ganda') {
                    if (!in_array($answer, ['A', 'B', 'C', 'D', 'E'])) {
                        $validator->errors()->add('jawaban', 'Jawaban harus berupa huruf A-E');
                    }
                } elseif ($question->tipe_soal === 'benar_salah') {
                    if (!in_array(strtolower($answer), ['benar', 'salah'])) {
                        $validator->errors()->add('jawaban', 'Jawaban harus "benar" atau "salah"');
                    }
                }
            }
        });
    }
}
```

---

### 5.4 PREVENTING COMMON ATTACKS

#### **SQL Injection Prevention**

All queries use Eloquent ORM which automatically parameterizes queries. Additional protection:

```php
// Use parameter binding for raw queries if needed
$questions = DB::select(
    'SELECT * FROM cbt_questions WHERE question_bank_id = ? AND aktif = ?',
    [$bankId, true]
);
```

#### **XSS Prevention**

- All Blade templates automatically escape output (`{{ }}`)
- Use `{!! !!}` only for trusted content
- Sanitize user input before storage (see CbtQuestionSanitizer above)

#### **CSRF Protection**

- All forms include CSRF token automatically
- API calls include X-CSRF-TOKEN header
- Validate CSRF token on all POST/PUT/DELETE requests

#### **Session Security**

```php
// config/session.php
return [
    'driver' => env('SESSION_DRIVER', 'file'),
    'lifetime' => env('SESSION_LIFETIME', 120),
    'expire_on_close' => false,
    'encrypt' => true,
    'files' => storage_path('framework/sessions'),
    'connection' => env('SESSION_CONNECTION'),
    'table' => 'sessions',
    'store' => null,
    'lottery' => [2, 100],
    'cookie' => env('SESSION_COOKIE_NAME', 'laravel_session'),
    'path' => '/',
    'domain' => env('SESSION_DOMAIN'),
    'secure' => env('SESSION_SECURE_COOKIE'),
    'http_only' => true,
    'same_site' => 'lax',
];
```

---

### 5.5 AUDIT LOGGING

#### **Enhanced Activity Logging**

```php
// app/Services/CbtAuditService.php

namespace App\Services;

use App\Models\Aktivitas;
use Illuminate\Support\Facades\Auth;

class CbtAuditService
{
    public static function log($action, $description, $data = [])
    {
        Aktivitas::create([
            'user_id' => Auth::id(),
            'jenis' => 'CBT',
            'keterangan' => $action,
            'detail' => $description,
            'data_lama' => null,
            'data_baru' => json_encode($data),
            'created_at' => now(),
        ]);
    }

    public static function logExamStart($testResultId, $siswaId)
    {
        self::log(
            'EXAM_START',
            "Siswa memulai ujian CBT",
            [
                'test_result_id' => $testResultId,
                'siswa_id' => $siswaId,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]
        );
    }

    public static function logExamSubmit($testResultId, $nilai, $lulus)
    {
        self::log(
            'EXAM_SUBMIT',
            "Siswa menyelesaikan ujian CBT",
            [
                'test_result_id' => $testResultId,
                'nilai' => $nilai,
                'lulus' => $lulus,
            ]
        );
    }

    public static function logIntegrityViolation($testResultId, $jenisPelanggaran)
    {
        self::log(
            'INTEGRITY_VIOLATION',
            "Pelanggaran kejujuran terdeteksi",
            [
                'test_result_id' => $testResultId,
                'jenis_pelanggaran' => $jenisPelanggaran,
            ]
        );
    }
}
```

---

### 5.6 BACKUP & RECOVERY

#### **Exam Data Backup**

```php
// app/Console/Commands/BackupCbtData.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cbt\TestSession;
use App\Models\Cbt\TestResult;
use Illuminate\Support\Facades\Storage;

class BackupCbtData extends Command
{
    protected $signature = 'cbt:backup {session_id}';
    protected $description = 'Backup CBT session data';

    public function handle()
    {
        $sessionId = $this->argument('session_id');
        $session = TestSession::with(['testResults.studentAnswers', 'testResults.siswa'])
                            ->findOrFail($sessionId);

        $backupData = [
            'session' => $session->toArray(),
            'results' => $session->testResults->map(function ($result) {
                return [
                    'result' => $result->toArray(),
                    'answers' => $result->studentAnswers->toArray(),
                ];
            }),
            'backup_date' => now()->toISOString(),
        ];

        $filename = "cbt_backup_{$sessionId}_" . now()->format('Y-m-d_His') . '.json';
        Storage::disk('backups')->put($filename, json_encode($backupData, JSON_PRETTY_PRINT));

        $this->info("Backup completed: {$filename}");
    }
}
```

---

## 6. IMPLEMENTATION ROADMAP

### 6.1 PHASE 1: CBT MODULE (Week 1-4)

#### **Week 1: Database & Models**
- [ ] Create all CBT migration files
- [ ] Run migrations
- [ ] Create CBT models (QuestionBank, Question, QuestionOption, TestSession, StudentAnswer, TestResult, IntegrityLog)
- [ ] Define model relationships
- [ ] Write model unit tests

#### **Week 2: Backend Controllers**
- [ ] Create CbtQuestionBankController
- [ ] Create CbtQuestionController
- [ ] Create CbtTestSessionController
- [ ] Create CbtStudentController
- [ ] Create CbtIntegrityController
- [ ] Implement middleware (CbtSecurityMiddleware, CbtSessionLock)
- [ ] Write controller tests

#### **Week 3: Frontend - Guru**
- [ ] Create CBT layout
- [ ] Build question bank management UI
- [ ] Build question editor UI
- [ ] Build test session configuration UI
- [ ] Build live monitoring dashboard
- [ ] Implement JavaScript for real-time updates

#### **Week 4: Frontend - Siswa & Security**
- [ ] Build student CBT dashboard
- [ ] Build exam interface with timer
- [ ] Implement integrity checker (anti-cheat)
- [ ] Implement auto-save functionality
- [ ] Build result display UI
- [ ] Security testing & bug fixes

---

### 6.2 PHASE 2: TRYOUT MODULE (Week 5-7)

#### **Week 5: Database & Models**
- [ ] Create Tryout migration files
- [ ] Run migrations
- [ ] Create Tryout models (Category, Package, Subject, TryoutQuestion, TryoutQuestionOption, Session, StudentAnswer, Result, NormReference)
- [ ] Define model relationships
- [ ] Write model unit tests

#### **Week 6: Backend Controllers**
- [ ] Create TryoutPackageController
- [ ] Create TryoutSessionController
- [ ] Create TryoutStudentController
- [ ] Implement norm reference logic
- [ ] Implement scoring engine with normalization
- [ ] Write controller tests

#### **Week 7: Frontend**
- [ ] Create Tryout layout
- [ ] Build package management UI
- [ ] Build session management UI
- [ ] Build student tryout dashboard
- [ ] Build tryout taking interface
- [ ] Build result display with analytics
- [ ] Implement comparison with norm references

---

### 6.3 PHASE 3: TKA ENHANCEMENT (Week 8-9)

#### **Week 8: Database & Models**
- [ ] Create TKA enhancement migration files
- [ ] Run migrations
- [ ] Create TKA models (DimensionEnhanced, AssessmentItem, StudentAssessment, AssessmentResponse, ParentReport, HistoricalTracking, EarlyWarning)
- [ ] Define model relationships
- [ ] Write model unit tests

#### **Week 9: Backend & Frontend**
- [ ] Create TkaDimensionController
- [ ] Create TkaAssessmentController
- [ ] Create TkaParentController
- [ ] Implement rubric scoring logic
- [ ] Implement early warning system
- [ ] Build TKA assessment UI
- [ ] Build radar chart visualization
- [ ] Build parent report UI
- [ ] Build historical tracking display

---

### 6.4 PHASE 4: INTEGRATION & TESTING (Week 10-11)

#### **Week 10: Integration**
- [ ] Integrate CBT with existing e-Rapor
- [ ] Integrate Tryout with existing grading system
- [ ] Integrate TKA with existing Dimensi model
- [ ] Update navigation menus
- [ ] Add role permissions
- [ ] Update documentation

#### **Week 11: Testing**
- [ ] Unit testing (all modules)
- [ ] Integration testing
- [ ] Security testing (penetration testing)
- [ ] Performance testing (load testing)
- [ ] User acceptance testing (UAT)
- [ ] Bug fixes

---

### 6.5 PHASE 5: DEPLOYMENT & DOCUMENTATION (Week 12)

#### **Week 12: Finalization**
- [ ] Create deployment checklist
- [ ] Prepare production database
- [ ] Deploy to staging environment
- [ ] Final testing on staging
- [ ] Deploy to production
- [ ] Create user guides (Admin, Guru, Siswa, Ortu)
- [ ] Create API documentation
- [ ] Create troubleshooting guide
- [ ] Training for users

---

### 6.6 ESTIMATED EFFORT

| Phase | Duration | Team Size | Effort (Person-Days) |
|-------|-----------|-----------|---------------------|
| Phase 1: CBT | 4 weeks | 2 devs | 40 |
| Phase 2: Tryout | 3 weeks | 2 devs | 30 |
| Phase 3: TKA | 2 weeks | 2 devs | 20 |
| Phase 4: Integration | 2 weeks | 2 devs | 20 |
| Phase 5: Deployment | 1 week | 2 devs | 10 |
| **Total** | **12 weeks** | **2 devs** | **120 person-days** |

---

### 6.7 RISK MITIGATION

| Risk | Probability | Impact | Mitigation Strategy |
|------|-------------|--------|---------------------|
| Technical complexity | Medium | High | Start with CBT (most familiar), reuse existing patterns |
| Anti-cheat bypass | Medium | High | Multi-layer security, server-side validation, regular updates |
| Performance issues | Low | Medium | Load testing, database indexing, caching |
| User adoption | Low | Medium | Training, documentation, gradual rollout |
| Integration issues | Medium | Medium | Thorough testing, rollback plan, staging environment |

---

### 6.8 SUCCESS CRITERIA

#### **CBT Module**
- [ ] Teachers can create question banks with 100+ questions
- [ ] Students can take exams with timer and auto-submit
- [ ] Anti-cheat detects 90%+ of violations
- [ ] Live monitoring shows real-time student progress
- [ ] Results are calculated and displayed within 5 seconds

#### **Tryout Module**
- [ ] Packages support 8+ subjects
- [ ] Scoring engine normalizes scores correctly
- [ ] Results show percentile ranking against norms
- [ ] Analytics identify weak subjects accurately
- [ ] PDF reports generate correctly

#### **TKA Enhancement**
- [ ] Radar chart displays all 7 dimensions
- [ ] Early warning system triggers for scores < 60%
- [ ] Parents can view reports for their children
- [ ] Historical tracking shows trends across semesters
- [ ] Assessment takes < 10 minutes per student

---

## 7. TESTING STRATEGY

### 7.1 UNIT TESTING

```php
// tests/Unit/Cbt/QuestionBankTest.php

namespace Tests\Unit\Cbt;

use App\Models\Cbt\QuestionBank;
use App\Models\Cbt\Question;
use Tests\TestCase;

class QuestionBankTest extends TestCase
{
    public function test_question_bank_can_be_created()
    {
        $bank = QuestionBank::create([
            'uuid' => Str::uuid(),
            'mata_pelajaran_id' => 1,
            'semester_id' => 1,
            'guru_id' => 1,
            'kode_bank' => 'TEST-001',
            'nama_bank' => 'Test Bank',
            'tingkat_kesulitan' => 'sedang',
            'aktif' => true,
        ]);

        $this->assertDatabaseHas('cbt_question_banks', [
            'kode_bank' => 'TEST-001',
        ]);
    }

    public function test_question_bank_has_many_questions()
    {
        $bank = QuestionBank::factory()->create();
        $questions = Question::factory()->count(5)->create([
            'question_bank_id' => $bank->id,
        ]);

        $this->assertCount(5, $bank->questions);
    }

    public function test_can_student_take_session()
    {
        $session = TestSession::factory()->create([
            'status' => 'ongoing',
            'tanggal_mulai' => now()->subHour(),
            'tanggal_selesai' => now()->addHour(),
            'boleh_ulang' => false,
        ]);

        $this->assertTrue($session->canStudentTake(1));
    }
}
```

### 7.2 INTEGRATION TESTING

```php
// tests/Feature/Cbt/StudentExamFlowTest.php

namespace Tests\Feature\Cbt;

use App\Models\Cbt\TestSession;
use App\Models\Cbt\TestResult;
use App\Models\Siswa;
use App\Models\User;
use Tests\TestCase;

class StudentExamFlowTest extends TestCase
{
    public function test_student_can_start_exam()
    {
        $user = User::factory()->create(['peran' => 'siswa']);
        $siswa = Siswa::factory()->create(['user_id' => $user->id]);
        $session = TestSession::factory()->create([
            'status' => 'ongoing',
            'tanggal_mulai' => now()->subHour(),
            'tanggal_selesai' => now()->addHour(),
        ]);

        $response = $this->actingAs($user)
                        ->get(route('cbt.student.start', $session));

        $response->assertStatus(200);
        $response->assertViewIs('cbt.student.take');
    }

    public function test_student_can_submit_answer()
    {
        $user = User::factory()->create(['peran' => 'siswa']);
        $siswa = Siswa::factory()->create(['user_id' => $user->id]);
        $result = TestResult::factory()->create([
            'siswa_id' => $siswa->id,
            'waktu_selesai' => null,
        ]);

        $response = $this->actingAs($user)
                        ->post(route('cbt.student.answer', $result), [
                            'question_id' => 1,
                            'jawaban' => 'A',
                            'ragu_ragu' => false,
                        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('cbt_student_answers', [
            'test_result_id' => $result->id,
            'jawaban' => 'A',
        ]);
    }

    public function test_exam_auto_submits_on_timeout()
    {
        // Test auto-submit when time expires
        $session = TestSession::factory()->create([
            'durasi_menit' => 1,
        ]);
        $result = TestResult::factory()->create([
            'test_session_id' => $session->id,
            'waktu_mulai' => now()->subMinutes(2),
            'waktu_selesai' => null,
        ]);

        $this->travelTo(now()->addMinutes(2));

        // Try to access exam
        $response = $this->get(route('cbt.student.result', $result));

        $response->assertStatus(200);
        $this->assertNotNull($result->fresh()->waktu_selesai);
    }
}
```

### 7.3 SECURITY TESTING

```php
// tests/Feature/Cbt/SecurityTest.php

namespace Tests\Feature\Cbt;

use App\Models\Cbt\TestResult;
use App\Models\User;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    public function test_student_cannot_access_another_students_exam()
    {
        $user1 = User::factory()->create(['peran' => 'siswa']);
        $user2 = User::factory()->create(['peran' => 'siswa']);
        $result = TestResult::factory()->create(['siswa_id' => 1]);

        $response = $this->actingAs($user2)
                        ->get(route('cbt.student.start', $result->testSession));

        $response->assertStatus(403);
    }

    public function test_integrity_violation_reduces_score()
    {
        $result = TestResult::factory()->create([
            'integrity_score' => 100.0,
        ]);

        // Simulate violation
        $this->post(route('cbt.integrity.log', $result), [
            'jenis_pelanggaran' => 'tab_switch',
            'deskripsi' => 'Test violation',
        ]);

        $result->refresh();
        $this->assertLessThan(100, $result->integrity_score);
    }

    public function test_rate_limiting_on_answer_submission()
    {
        $user = User::factory()->create(['peran' => 'siswa']);
        $result = TestResult::factory()->create();

        // Submit 61 answers in 1 minute (exceeds rate limit of 60)
        for ($i = 0; $i < 61; $i++) {
            $this->actingAs($user)
                ->post(route('cbt.student.answer', $result), [
                    'question_id' => 1,
                    'jawaban' => 'A',
                ]);
        }

        // Last request should be rate limited
        $this->actingAs($user)
            ->post(route('cbt.student.answer', $result), [
                'question_id' => 1,
                'jawaban' => 'A',
            ])
            ->assertStatus(429);
    }
}
```

### 7.4 PERFORMANCE TESTING

```php
// tests/Performance/CbtLoadTest.php

namespace Tests\Performance;

use App\Models\Cbt\TestSession;
use App\Models\Cbt\TestResult;
use Tests\TestCase;

class CbtLoadTest extends TestCase
{
    public function test_can_handle_100_concurrent_students()
    {
        $session = TestSession::factory()->create([
            'status' => 'ongoing',
        ]);

        // Simulate 100 students starting exam simultaneously
        $startTime = microtime(true);

        for ($i = 0; $i < 100; $i++) {
            $result = TestResult::factory()->create([
                'test_session_id' => $session->id,
                'waktu_mulai' => now(),
            ]);
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // Should complete within 5 seconds
        $this->assertLessThan(5, $duration);
    }

    public function test_monitoring_api_is_fast()
    {
        $session = TestSession::factory()->create();
        TestResult::factory()->count(50)->create([
            'test_session_id' => $session->id,
            'waktu_selesai' => null,
        ]);

        $startTime = microtime(true);
        $response = $this->get(route('cbt.sessions.api.monitoring', $session));
        $endTime = microtime(true);

        $duration = $endTime - $startTime;

        $response->assertStatus(200);
        $this->assertLessThan(1, $duration); // Should be < 1 second
    }
}
```

---

## 8. PERFORMANCE OPTIMIZATION

### 8.1 DATABASE INDEXING

All tables already have appropriate indexes defined in migration files. Additional indexes for performance:

```sql
-- Add composite indexes for common queries
CREATE INDEX idx_session_siswa_status ON cbt_test_results(test_session_id, siswa_id, waktu_selesai);
CREATE INDEX idx_siswa_semester_tanggal ON tka_student_assessments(siswa_id, semester_id, tanggal_penilaian);
CREATE INDEX idx_package_jenjang_tahun ON tryout_packages(jenjang, tahun_paket, aktif);
```

### 8.2 CACHING STRATEGY

```php
// app/Services/CbtCacheService.php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CbtCacheService
{
    public static function cacheQuestionBank($bankId, $data)
    {
        Cache::put("cbt_bank_{$bankId}", $data, now()->addHours(24));
    }

    public static function getQuestionBank($bankId)
    {
        return Cache::remember("cbt_bank_{$bankId}", now()->addHours(24), function () use ($bankId) {
            return \App\Models\Cbt\QuestionBank::with('questions.options')->find($bankId);
        });
    }

    public static function cacheMonitoringData($sessionId, $data)
    {
        Cache::put("cbt_monitoring_{$sessionId}", $data, now()->addMinutes(5));
    }

    public static function getMonitoringData($sessionId)
    {
        return Cache::get("cbt_monitoring_{$sessionId}");
    }
}
```

### 8.3 QUEUEING FOR HEAVY OPERATIONS

```php
// app/Jobs/GenerateCbtReport.php

namespace App\Jobs;

use App\Models\Cbt\TestResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateCbtReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $testResult;

    public function __construct(TestResult $testResult)
    {
        $this->testResult = $testResult;
    }

    public function handle()
    {
        // Generate PDF report
        $pdf = app('pdf')->loadView('cbt.reports.result', [
            'result' => $this->testResult->load(['testSession', 'siswa', 'studentAnswers']),
        ]);

        $filename = "cbt_result_{$this->testResult->uuid}.pdf";
        $pdf->save(storage_path("app/reports/{$filename}"));

        // Notify user
        // ...
    }
}
```

### 8.4 ASSET OPTIMIZATION

```javascript
// webpack.mix.js - Optimize JavaScript

mix.js('resources/js/cbt/student-exam/integrity-checker.js', 'public/js/cbt')
   .js('resources/js/cbt/student-exam/timer.js', 'public/js/cbt')
   .js('resources/js/cbt/student-exam/answer-saver.js', 'public/js/cbt')
   .minify('public/js/cbt/*.js');
```

---

## 9. DEPLOYMENT CHECKLIST

### 9.1 PRE-DEPLOYMENT

- [ ] All tests passing (unit, integration, security, performance)
- [ ] Code review completed
- [ ] Documentation updated
- [ ] Database migrations tested on staging
- [ ] Backup strategy in place
- [ ] Rollback plan documented
- [ ] Monitoring tools configured

### 9.2 DEPLOYMENT STEPS

1. **Database Migration**
   ```bash
   php artisan migrate --force
   ```

2. **Clear Cache**
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   ```

3. **Optimize**
   ```bash
   php artisan optimize
   composer install --optimize-autoloader --no-dev
   npm run build
   ```

4. **Queue Workers**
   ```bash
   php artisan queue:work --daemon
   ```

### 9.3 POST-DEPLOYMENT

- [ ] Verify all routes accessible
- [ ] Test CBT functionality
- [ ] Test Tryout functionality
- [ ] Test TKA functionality
- [ ] Monitor logs for errors
- [ ] Check performance metrics
- [ ] Verify backups running

---

## 10. SUPPORT & MAINTENANCE

### 10.1 TROUBLESHOOTING GUIDE

#### **Common Issues**

**Issue: Students cannot access exam**
- Check session status (must be 'ongoing')
- Check time window (start/end dates)
- Check student eligibility (class/rombel)
- Check integrity score (must be > 50)

**Issue: Integrity score dropping unexpectedly**
- Check if students are on unstable internet
- Verify browser compatibility
- Check if antivirus is interfering
- Review integrity logs for false positives

**Issue: Tryout scores not normalizing correctly**
- Verify norm reference data loaded
- Check weight configuration
- Review scoring algorithm
- Test with known data

### 10.2 MAINTENANCE TASKS

**Weekly**
- Review integrity logs
- Check backup completion
- Monitor performance metrics
- Review error logs

**Monthly**
- Update question banks
- Review and update norm references
- Audit user access
- Security patch updates

**Quarterly**
- Performance audit
- Security audit
- User feedback review
- Feature enhancement planning

---

## CONCLUSION

This comprehensive design document provides a complete roadmap for implementing CBT, Tryout, and enhanced TKA modules for the SIMANTAP platform. The design emphasizes:

1. **Security**: Multi-layer anti-cheat measures, session locking, and audit logging
2. **Performance**: Database indexing, caching, and queueing for heavy operations
3. **User Experience**: Intuitive interfaces, real-time monitoring, and comprehensive reporting
4. **Scalability**: Modular architecture that can handle growth
5. **Maintainability**: Clean code structure, comprehensive testing, and documentation

With a 12-week implementation timeline and a team of 2 developers, these modules can be successfully integrated into the existing SIMANTAP platform, transforming it into a comprehensive educational assessment system.

---

**Document Version**: 1.0
**Last Updated**: October 4, 2026
**Author**: Devin AI Assistant
**Status**: Ready for Implementation
