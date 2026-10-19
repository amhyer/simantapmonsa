# Audit Mendalam SIMANTAPMONSA

**Repository yang diperiksa:** https://github.com/amhyer/simantapmonsa  
**Branch:** `main`  
**Snapshot yang terlihat:** commit `56f7922fde7a08ca108d06ed3297083c7ac7e2a5` (15 Oktober 2026)  
**Jenis audit:** tinjauan statis kode, routes, migrasi, model, controller, integrasi, tes, dokumentasi, dan konfigurasi publik.  
**Batasan:** saya tidak mengakses database produksi, tidak melakukan serangan terhadap layanan aktif, dan tidak menjalankan aplikasi di lingkungan produksi. Temuan mengacu pada source code yang tersedia. Hasil integrasi tetap perlu diuji pada salinan lingkungan target.

---

## 1. Ringkasan Eksekutif

SIMANTAP adalah aplikasi manajemen sekolah berbasis Laravel dengan role admin, guru, siswa, orang tua, dan kepala sekolah. Fitur yang tampak dari kode mencakup data siswa dan guru, rombongan belajar, penilaian, e-Rapor, kehadiran, catatan perkembangan, kebiasaan siswa, materi dan kuis, CBT, tryout, TKA/readiness, integrasi Dapodik, sinkronisasi Google Sheets, backup/restore, PDF dan spreadsheet.

### Kesimpulan utama

1. **Jangan menganggap modul CBT dan tryout siap dipakai untuk ujian resmi sebelum perbaikan dan pengujian end-to-end.** Ada ketidaksesuaian migrasi dengan insert serta penyimpanan jawaban, mismatch payload pilihan ganda, algoritma benar/salah yang keliru, dan celah otorisasi objek.
2. **Sinkronisasi Google Sheets berisiko menghapus data di tab yang dipakai bersama.** Kode membatasi data ke guru yang login, tetapi mengosongkan tab spreadsheet yang diambil dari pengaturan sekolah.
3. **Backup terjadwal dan backup dari panel admin menggunakan cakupan berbeda.** Perintah terjadwal hanya mengumpulkan lima kelompok data, sementara registry backup panel lebih luas. Registry dan proses wipe juga belum meliputi keseluruhan modul CBT, tryout, dan TKA yang lebih baru.
4. **Dokumentasi dan implementasi tidak sinkron.** README utama masih boilerplate Laravel; laporan audit 14 Oktober menyatakan controller CBT belum ada/nonaktif, tetapi commit 15 Oktober menunjukkan controller, view, serta rute CBT tersedia dan flag CBT default aktif. Dokumen progress CBT juga masih menyatakan frontend pending.
5. **Verifikasi belum otomatis dijalankan oleh CI repository.** Commit terakhir menyebut PHPUnit 127/127 hijau, dan dokumen audit memuat pemeriksaan statis. Namun, tidak ditemukan workflow GitHub Actions di tree repository atau run actions terbaru; tidak ada feature test spesifik untuk isolasi pemilik CBT/tryout.

### Prioritas ringkas

| Prioritas | Fokus | Sasaran |
|---|---|---|
| P0 | CBT/tryout: schema, penilaian, otorisasi, perjalanan ujian | Cegah kegagalan mulai ujian, jawaban hilang, skor salah, akses lintas akun |
| P1 | Google Sheets dan backup/restore/wipe | Cegah kehilangan data yang tidak disengaja dan pastikan restore benar-benar lengkap |
| P1 | Pengamanan kredensial dan data anak | Rotasi kunci lama bila pernah terpapar; audit screenshot/dokumen publik |
| P1 | Tes otomatis dan deployment check | Pastikan temuan tidak kembali setelah perubahan |
| P2 | Dokumentasi, observabilitas, UX, performa, dan fitur baru | Kurangi biaya pemeliharaan dan tingkatkan manfaat bagi sekolah |

---

## 2. Identitas dan Arsitektur Proyek

### Stack yang terlihat

- PHP `^8.3`, Laravel `^13.17` pada `composer.json`.
- PostgreSQL direkomendasikan di `.env.example`; `phpunit.xml` memakai SQLite in-memory untuk test suite.
- Blade templates, Vite, TailwindCSS, Alpine.js, Chart.js dan JavaScript.
- Integrasi: `google/apiclient`, DomPDF, PhpSpreadsheet, Dapodik bridge Python.
- Autentikasi kustom memakai `nama_pengguna` dan `kata_sandi`; role yang digunakan antara lain `admin`, `guru`, `siswa`, `ortu`, `kepsek`.

### Domain utama

1. **Administrasi:** akun, data siswa/PTK, rombel, mapel, semester, profil sekolah, setting dan referensi.
2. **Pembelajaran:** materi, kuis, nilai, kehadiran, catatan siswa, kebiasaan dan dimensi perkembangan.
3. **e-Rapor:** nilai dan deskripsi capaian, penyesuaian referensi/jadwal, PDF/Excel, transkrip.
4. **Asesmen:** CBT, tryout dan TKA/readiness.
5. **Integrasi:** Dapodik bridge/API, Google Sheets, pencarian sekolah.
6. **Operasional:** backup, restore, job queue, scheduled commands, activity log dan laporan.

Skala repository cukup besar—tree publik berisi sekitar 799 path—dan memiliki banyak controller, migrasi, view, dan beberapa domain asesmen. Pada skala ini, pemeriksaan “file ada”, “route ada”, dan “syntax lolos” tidak cukup. Validasi utama harus berpusat pada alur lintas lapisan: role → authorization → validation → model/database → job/API → output dan audit trail.

---

## 3. Temuan P0 — CBT dan Tryout

### P0-01. `waktu_selesai` wajib di database, tetapi diisi `null` saat mulai

- CBT: `database/migrations/2026_10_04_210653_create_cbt_test_results_table.php` mendefinisikan `waktu_selesai` dengan `dateTime()` tanpa `nullable()`.
- `app/Http/Controllers/Cbt/CbtStudentController.php::start()` membuat hasil baru dengan `waktu_selesai => null`.
- Tryout: `database/migrations/2026_10_04_222414_create_tryout_results_table.php` juga mendefinisikan `waktu_selesai` non-null; `TryoutStudentController::start()` mengirim `null`.

Pada database baru yang mengikuti migrasi, insert hasil ketika mulai ujian dapat ditolak oleh constraint `NOT NULL`.

**Perbaikan:** buat migrasi baru yang menjadikan kolom `waktu_selesai` nullable. Migrasi harus aman untuk database existing; jangan mengedit migrasi lama yang sudah digunakan di deployment. Tambahkan test start CBT dan tryout pada PostgreSQL.

### P0-02. Kontrak penyimpanan jawaban CBT tidak konsisten

- Migrasi `cbt_student_answers` memiliki `test_session_id`, `siswa_id`, `question_id`, `attempt_number` serta unique key komposit. Tidak terlihat kolom `test_result_id`.
- `CbtStudentController::submitAnswer()` mencari `StudentAnswer` melalui `test_result_id` dan membuat record dengan field itu.
- `StudentAnswer::$fillable` pun menggunakan `test_session_id`, bukan `test_result_id`.
- `TestResult::studentAnswers()` mencari dengan session ID + siswa ID dan tidak membatasi `attempt_number`.

Konsekuensi: query autosave dapat gagal karena kolom tidak ada; insert tidak memasok kolom session yang wajib; dan jawaban dari pengulangan ujian bisa tercampur.

**Perbaikan:** pilih satu model relasi per percobaan. Rekomendasi: jawaban menyimpan FK ke `test_result_id` atau entitas `attempt` yang unik, dengan FK, indeks, dan aturan unik yang tepat. Gunakan `test_result_id` sebagai satu-satunya sumber kebenaran; migrasikan data lama secara eksplisit bila perlu.

### P0-03. Pemeriksaan kepemilikan objek CBT tidak konsisten

- `CbtQuestionBankController::index()` melakukan filter `guru_id = auth()->id()`, tetapi `show`, `edit`, `update`, `destroy`, dan `apiGetQuestions` tidak melakukan verifikasi ownership.
- `CbtQuestionController` menyimpan/mengubah/menghapus pertanyaan berdasarkan model binding tanpa memastikan bank pemiliknya berada dalam otorisasi guru.
- `CbtTestSessionController::index()` dibatasi berdasarkan guru, tetapi operasi detail/publish/start/complete/monitoring tidak memeriksa bahwa sesi milik guru login.
- Saat sesi baru dibuat, `question_bank_id` divalidasi sebagai ID yang ada, bukan ID bank milik guru login.
- Pada sisi siswa, operasi submit answer/submit/result menerima `TestResult` tetapi tidak memeriksa `result.siswa_id` terhadap siswa yang sedang login.
- `TestSession::canStudentTake()` memeriksa status, waktu, dan batas percobaan, tetapi tidak memeriksa kelas/rombel. `start()` karena itu berpotensi bisa dipanggil langsung terhadap sesi kelas lain meskipun sesi tidak ditampilkan di index.

Ini adalah pola **insecure direct object reference (IDOR)**: role middleware saja tidak memastikan hak akses terhadap objek dengan ID tertentu.

**Perbaikan:** gunakan Laravel Policies atau `authorize()`; scope objek pada query; pastikan `student_id` milik attempt cocok dengan login; verifikasi kelas/rombel saat memulai; verifikasi bahwa soal berasal dari sesi/bank yang benar. Tambahkan tes dua guru dan dua siswa yang mencoba akses silang pada semua metode baca dan mutasi.

### P0-04. Frontend pilihan ganda dan grader memakai format berbeda

Di `resources/views/cbt/student/take.blade.php`:

- Radio input mengirim `option.id`.
- Teks opsi dibaca sebagai `option.teks`.

Sedangkan model `QuestionOption` menggunakan `isi_opsi` dan `huruf`; grader membandingkan jawaban dengan `correctOption->huruf`. Soal juga tidak selalu dimuat bersama relasi `options` sebelum `json_encode()`—relasi hanya dimuat secara eksplisit jika pengacakan opsi diaktifkan.

Dampak: pilihan bisa tidak muncul, teks opsi kosong, dan jawaban yang dikirim tidak cocok dengan kunci yang dibandingkan.

**Perbaikan:** eager-load `questions.options`; samakan properti JSON dengan model; tetapkan kontrak payload tunggal (misalnya `option_id` lalu lookup server-side, atau huruf jawaban tervalidasi). Jangan percaya jawaban atau skor yang dikirim client.

### P0-05. Penilaian benar/salah dan jenis soal lain tidak benar

Di `CbtStudentController::submit()` dan `CbtSubmitService::submit()`:

- Jawaban `benar` dinyatakan benar tanpa memeriksa kunci spesifik pertanyaan. Akibatnya, semua soal benar/salah bisa dinilai dengan aturan yang salah.
- Tipe `isian_singkat`, `menjodohkan`, dan `essay` tidak ditangani di alur auto-grading yang terlihat; jawaban akan jatuh ke nilai salah, meskipun esai seharusnya dapat menunggu penilaian guru.
- `nilai_maksimal` dihitung dari semua pertanyaan di bank soal, bukan hanya soal yang diberikan kepada peserta.
- Randomisasi dilakukan setelah `limit(jumlah_soal_tampil)`, sehingga yang dipilih tetap soal teratas lalu urutannya diacak.
- Logika grading diduplikasi antara controller dan service sehingga mudah menyimpang setelah pemeliharaan.

**Perbaikan:** buat satu service penilaian yang diuji secara unit, gunakan kunci benar/salah yang eksplisit, dukung status belum dinilai untuk esai, tetapkan normalisasi jawaban, dan simpan snapshot daftar soal yang benar-benar diterima tiap attempt. Nilai maksimum harus dihitung berdasarkan snapshot itu.

### P0-06. Rute pelaporan anti-cheat berada di dalam grup guru

`resources/views/cbt/student/take.blade.php` membangun endpoint pelaporan pelanggaran dengan `route("cbt.integrity.log", $result)`. Rute `cbt.integrity.log` berada di grup `routes/web.php` dengan middleware `role:guru`, sedangkan pemanggilnya adalah siswa. Pemanggilan dari antarmuka siswa karena itu berpotensi ditolak oleh middleware. Anti-cheat berbasis IP/UA bukan pengganti kepemilikan attempt.

**Perbaikan:** tempatkan endpoint pelaporan pelanggaran dalam grup siswa khusus; otorisasi `result` supaya siswa hanya bisa melaporkan pelanggaran pada attempt miliknya; validasi tipe payload dan lakukan rate limit.

### P0-07. Middleware auto-submit tidak mengembalikan response HTTP

`CbtSecurityMiddleware` memanggil `autoSubmit($result)` saat timer habis. Implementasi `autoSubmit()` di `CbtSubmitService` mengembalikan model `TestResult`, bukan object `Response`. Middleware harus mengembalikan response HTTP yang sah—misalnya redirect atau JSON—setelah proses submit selesai, dan proses submit harus aman jika dipanggil dua kali.

**Perbaikan:** tetapkan kontrak response eksplisit, lakukan finalize attempt secara idempotent dan transaksional, serta uji submit normal, auto-submit, submit ganda, dan request terlambat.

### P0-08. Tryout memberi tahu jawaban benar saat peserta masih mengerjakan

`TryoutStudentController::submitAnswer()` mengembalikan `is_correct` dan `poin` langsung kepada client. Jika tryout adalah ujian/asesmen resmi, endpoint ini membuka informasi yang bisa dimanfaatkan untuk memperbaiki jawaban selama tes.

**Perbaikan:** untuk mode ujian, endpoint hanya memberi status bahwa jawaban tersimpan; kunci dan nilai dibuka berdasarkan konfigurasi sesi setelah ujian dikumpulkan/ditutup. Umpan balik langsung harus menjadi mode latihan terpisah yang diaktifkan secara eksplisit.

### P0-09. Isolasi data TKA terhadap guru perlu ditegakkan

`TkaAssessmentController::create()` mengambil seluruh siswa aktif, dan `store()` menerima `siswa_id` yang ada serta ID butir asesmen yang ada, lalu menyimpan `guru_id` berdasarkan akun login. Validasi `exists` saja tidak memastikan siswa tersebut berada di cakupan pengajaran guru atau butir berasal dari dimensi/versi yang ditetapkan.

`TkaParentController::generateReport()` menerima `StudentAssessment` melalui route guru tanpa pemeriksaan ownership eksplisit terhadap asesmen. Ini perlu ditutup dengan Policy/scope.

**Perbaikan:** filter daftar siswa sesuai kelas/rombel yang boleh ditangani guru, validasi setiap butir terhadap set dimensi aktif yang ditetapkan, dan pastikan asesmen yang diubah/dilaporkan memang dikelola guru tersebut.

---

## 4. Temuan P1 — Integrasi dan Operasional

### P1-01. Google Sheets bisa kehilangan baris milik guru lain

Pada `app/Http/Controllers/Guru/IntegrasiController.php::fullSync()`:

1. Spreadsheet ID dibaca dari `SekolahSettings::first()->pengaturan['google_sheet']`—pengaturan sekolah/global.
2. Query data difilter berdasarkan `auth()->id()` sehingga hanya mengambil data guru yang sedang login.
3. Untuk setiap jenis data, kode menjalankan `clearSheet()` sebelum mengirim baris milik guru itu.

Jika spreadsheet/tab dipakai bersama untuk data sekolah, sinkronisasi salah satu guru akan mengosongkan tab lalu mengisi hanya data guru itu. Jika sinkronisasi gagal di tengah proses, isi tab juga dapat tertinggal dalam keadaan kosong atau parsial. Komentar yang menyebut spreadsheet pribadi tidak sesuai dengan sumber ID spreadsheet yang digunakan.

**Perbaikan:** pilih salah satu model dengan tegas:

- **Spreadsheet pribadi guru:** ambil spreadsheet ID dari `PengaturanGuru.sistem` milik guru itu dan validasi kepemilikannya; atau
- **Spreadsheet pusat sekolah:** sinkronkan dataset lengkap untuk semua guru atau tulis ke tab per guru. Jangan `clearSheet()` pada tab gabungan lalu mengisinya dengan subset satu guru.

Tambahkan mekanisme staging/retry dan tes kegagalan di tengah proses. Perlu juga memeriksa bahwa guru tidak dapat mengirim data ke URL spreadsheet milik pihak yang tidak berhak.

### P1-02. Backup terjadwal hanya mencakup lima kelompok data

`routes/console.php` menjadwalkan `simantap:backup` setiap hari pukul 02.00. Namun `app/Console/Commands/BackupData.php` hanya mengumpulkan `users`, `siswa`, `nilai_erapor`, `kehadiran`, dan `kebiasaan`. Sementara itu, tombol ekspor dari panel admin menjalankan `ExportBackupJob` yang menggunakan `BackupTables::semua()` dan mencakup kumpulan tabel jauh lebih luas.

Nama file kedua jalur serupa (`backup-simantap-<timestamp>.json`), sehingga operator bisa mengira backup terjadwal merupakan backup seluruh aplikasi. Restore dari backup terjadwal tidak dapat mengembalikan tabel yang tidak pernah diekspor.

**Perbaikan:** gunakan satu registry dan satu layanan backup untuk backup manual dan terjadwal. Tambahkan metadata versi format, daftar tabel, waktu, checksum, ukuran, status job dan jumlah record. Gunakan nama file yang membedakan backup penuh dan backup parsial bila keduanya memang diperlukan.

### P1-03. Backup, restore, dan wipe belum mencakup seluruh domain baru

`BackupTables::semua()` berisi model/tabel utama seperti siswa, nilai, e-Rapor, kehadiran, kuis, dimensi, kokurikuler dan aktivitas, tetapi tidak memasukkan tabel/model modern CBT, tryout, dan TKA enhanced yang tampak di migrasi terbaru. Daftar di `BackupController::wipeAll()` juga mengosongkan sekumpulan tabel lama dan tidak menyebut tabel CBT/tryout/TKA.

Konsekuensinya, ada dua risiko operasional:

- Backup/restore dianggap selesai, padahal data asesmen baru tidak ikut dicadangkan.
- Fitur “Hapus Semua Data” dapat meninggalkan hasil ujian/asesmen baru, sehingga reset tidak benar-benar membersihkan semua domain.

**Perbaikan:** buat registry terpusat yang eksplisit membedakan tabel wajib dicadangkan, dapat dibuat ulang, dan dikecualikan dengan alasan. Selaraskan export, restore, dan reset; untuk setiap tabel yang dikecualikan, wajib ada alasan dan test. Ubah label tombol reset bila sengaja hanya membersihkan domain tertentu.

### P1-04. Wipe dapat berakhir parsial

`BackupController::wipeAll()` memvalidasi teks konfirmasi dan password—ini langkah pengaman yang baik—tetapi menghapus tabel satu per satu, menangkap exception per tabel, lalu melanjutkan proses. Tidak ada transaksi tunggal yang menjadikan operasi reset sebagai satu unit. Akibatnya, operasi bisa berhasil sebagian dan pengguna menerima pesan campuran tabel berhasil/gagal. PostgreSQL `TRUNCATE ... CASCADE` juga berdampak pada tabel lain yang terhubung melalui foreign key.

**Perbaikan:** sebelum reset, tampilkan dry-run daftar tabel dan hitungan baris yang akan terhapus, minta konfirmasi ulang dengan identitas operasi, buat backup penuh yang sudah diverifikasi, tulis audit log di luar tabel yang dihapus, dan gunakan transaksi/strategi reset yang sesuai driver. Jangan izinkan reset jika backup preflight gagal. Uji pada salinan database, bukan produksi.

### P1-05. Strategi ekspor “streaming” masih dapat memakai memori besar

`ExportBackupJob` menulis JSON ke `php://temp`, melakukan chunking tabel 200 baris, tetapi pada akhir proses memanggil `stream_get_contents($stream)` sebelum `Storage::put()`. Saat itu seluruh JSON harus dikumpulkan lagi menjadi string; ini dapat memicu lonjakan memori untuk sekolah dengan data besar.

**Perbaikan:** tulis ke file sementara di disk/object storage dan unggah secara streaming; gunakan ukuran chunk yang terukur, checksum, batas retensi dan observabilitas kegagalan. Pastikan job queue aktif di deployment.

### P1-06. Queue dan scheduler memerlukan deployment contract yang jelas

`.env.example` memakai `QUEUE_CONNECTION=database`, tetapi `BackupController::export()` melakukan `dispatch(new ExportBackupJob())`. Jika worker queue tidak berjalan, pengguna dapat melihat pesan “dimulai” sementara file belum tersedia. Scheduler juga hanya bekerja jika `schedule:run` dijalankan oleh cron/scheduler lingkungan produksi.

**Perbaikan:** dokumentasikan konfigurasi worker/scheduler dengan jelas, tampilkan status job `queued/running/succeeded/failed`, simpan `failed_jobs`, berikan tombol unduh hanya jika status selesai, dan tambahkan health check untuk worker, scheduler dan disk.

### P1-07. Kunci yang pernah ada di repository perlu ditangani sebagai insiden kredensial

Dokumen audit 14 Oktober melaporkan kredensial/API key Dapodik pernah tersimpan di repository. Pada snapshot saat ini `dapodik-bridge/config.json` tidak ditemukan dan `.gitignore` mengecualikannya; `setup_api_key.php` juga mengharuskan kunci berasal dari environment/argumen, bukan literal.

Namun, menghapus file dari HEAD tidak otomatis menghapus rahasia dari riwayat Git atau salinan/clone lama.

**Perbaikan:** jika kunci/token lama valid atau sempat dipakai, cabut dan rotasi segera, cari pemakaiannya di riwayat commit/log/artefak, pindahkan ke secret manager/environment, dan bersihkan riwayat Git hanya setelah menilai dampaknya bagi seluruh clone dan integrasi.

### P1-08. Privasi data siswa di aset dokumentasi publik

Repository berisi screenshot dan dokumentasi UI. Belum dilakukan tinjauan visual penuh atas setiap gambar, sehingga ini adalah langkah pemeriksaan yang wajib, bukan klaim bahwa data pribadi pasti terlihat.

**Perbaikan:** periksa seluruh gambar/log/contoh ekspor untuk nama siswa, NIS/NIK, nama orang tua, token, alamat, dan nilai riil. Gunakan data sintetis atau blur; pertimbangkan menghapus aset sensitif dari riwayat Git jika pernah terkomit.

---

## 5. Temuan P2 — Kualitas, Arsitektur, dan Pemeliharaan

### P2-01. Dokumentasi utama tidak mencerminkan produk

- `README.md` masih berisi boilerplate Laravel, bukan panduan SIMANTAP.
- `docs/CBT_IMPLEMENTATION_PROGRESS.md` menyatakan frontend CBT pending meskipun tree saat ini sudah memuat view dan JavaScript CBT.
- `LAPORAN_AUDIT_BUG_2026-10-14.md` menjelaskan CBT belum tersedia/nonaktif di snapshot yang lebih lama; commit 15 Oktober mengubah situasi itu dan `config/simantap.php` kini default CBT aktif.
- `docs/IMPROVEMENT_BACKLOG.md` masih bertanggal 13 September dan menyebut Laravel 11, sementara composer saat ini mensyaratkan Laravel 13.

**Perbaikan:** jadikan README sebagai sumber onboarding yang hidup: tujuan, fitur, role, versi runtime, instalasi, `.env`, migrasi, queue, scheduler, storage, test, deployment, recovery, dan status setiap modul. Tambahkan tanggal/snapshot pada laporan audit dan tandai dokumen kedaluwarsa.

### P2-02. Feature flag CBT bukan kill switch seluruh modul

`config/simantap.php` memakai `SIMANTAP_CBT_ENABLED` dengan default `true`. Grup rute CBT guru/siswa dibungkus flag, tetapi grup `admin/cbt` terlihat berada di luar blok tersebut. Jika tujuannya menonaktifkan CBT secara keseluruhan ketika ada insiden, flag tidak menutup rute admin CBT.

**Perbaikan:** tentukan semantik flag secara eksplisit dan bungkus seluruh route yang merupakan satu modul, atau gunakan middleware konfigurasi yang konsisten. Uji flag dalam kedua keadaan dan dokumentasikan cache konfigurasi saat deployment.

### P2-03. Tidak ditemukan CI workflow pada snapshot

Commit terbaru menyebut PHPUnit `127/127` hijau. Ini tanda baik, tetapi pemeriksaan API Actions menunjukkan tidak ada run terbaru dan tree tidak menampilkan `.github/workflows`. Dengan demikian, kualitas test lokal belum menjadi gerbang otomatis untuk setiap pull request/commit.

**Perbaikan:** tambahkan CI dengan langkah:

1. `composer validate --strict` dan instalasi dependency dari lockfile.
2. `php tools/verifikasi-perbaikan.php`.
3. Pint/lint dan `php artisan test` pada SQLite untuk tes cepat.
4. Integration test pada PostgreSQL untuk constraint, migrasi, JSON, dan transaksi yang bergantung driver.
5. `npm ci` dan `npm run build`.
6. Tes authorization negatif dan alur end-to-end asesmen.

Jangan menyamakan tes statis dengan bukti operasional. Jalankan test dengan database bersih dan skema yang sama dengan deployment.

### P2-04. Banyak sumber kebenaran untuk perilaku asesmen

Logika skor dan predikat muncul pada lebih dari satu layanan/controller serta beberapa modul kuis/CBT/tryout/e-Rapor. Semakin banyak implementasi, semakin besar kemungkinan nilai atau predikat yang sama menghasilkan output berbeda.

**Perbaikan:** konsolidasikan kalkulasi dalam domain services yang memiliki input/output terdefinisi; buat contract tests dengan kasus batas; gunakan konfigurasi sekolah/semester untuk KKM dan bobot; simpan versi aturan yang digunakan saat rapor diterbitkan.

### P2-05. Operasi integrasi perlu idempotency dan rekonsiliasi

Dapodik sync, push, import, dan Google Sheets dapat gagal saat memproses sebagian data, mengalami retry, atau menerima data yang berubah di sumber. Log status saja tidak cukup untuk memastikan sinkronisasi dapat diulang dengan aman.

**Perbaikan:** gunakan external ID/idempotency key, preview/dry-run, validasi mapping, laporan konflik, checkpoint/resume, retry dengan backoff, dan ringkasan record inserted/updated/skipped/failed per entitas. Untuk sync dua arah, tetapkan sistem mana yang menjadi sumber kebenaran tiap kolom.

---

## 6. Ide Pengembangan Baru Setelah P0/P1 Selesai

Prioritas fitur di bawah ini didasarkan pada modul yang sudah ada. **Jangan memulai semuanya sekaligus**; stabilkan alur yang berisiko terlebih dahulu.

### Ide A — Satu mesin asesmen bersama

**Manfaat:** mengurangi duplikasi logic pada kuis, CBT, dan tryout.

Komponen yang direkomendasikan:

- `QuestionBank`, `Question`, `QuestionOption`, `AssessmentSession`, `AssessmentAttempt`, `AttemptQuestion`, `StudentAnswer`, `GradingRubric`.
- Snapshot daftar soal dan bobot yang ditetapkan saat attempt dimulai.
- Grader per tipe soal: pilihan ganda, benar/salah dengan kunci eksplisit, jawaban singkat dengan normalisasi, menjodohkan, esai dengan penilaian guru.
- Autosave idempotent, resume attempt, batas waktu server-side, review/appeal, pembukaan nilai berdasarkan kebijakan.
- Dukungan randomisasi soal dan opsi tanpa mengubah kunci jawaban.

**Kriteria selesai:** satu attempt dapat dibuat, dijawab, dilanjutkan, dikumpulkan sekali, dinilai benar, direview, dan hanya terlihat oleh pihak berwenang; skor konsisten pada UI, laporan dan ekspor.

### Ide B — Early-warning akademik yang bisa ditindaklanjuti

Gabungkan tren nilai, absensi, perubahan performa TKA, dan catatan perkembangan untuk menampilkan indikator siswa yang membutuhkan pendampingan. Hindari label otomatis yang menghakimi; tampilkan alasan, data sumber, periode, dan tindakan yang bisa dilakukan guru.

**Tahap awal:** aturan transparan (mis. penurunan rata-rata pada dua periode atau pola absen berulang), bukan AI yang memberi keputusan tanpa penjelasan. Perlu mekanisme koreksi data, kontrol akses, dan audit siapa yang melihat laporan.

### Ide C — Portal orang tua yang berorientasi tindakan

Tambahkan ringkasan mingguan yang mudah dimengerti, absensi, pekerjaan yang perlu didampingi, perkembangan kebiasaan dan rekomendasi belajar di rumah. Setiap informasi harus berasal dari data yang disimpan dan memiliki timestamp; orang tua hanya boleh melihat anak yang terhubung resmi.

### Ide D — Dapodik sync center

Buat panel tunggal untuk status koneksi, terakhir sync, durasi, perubahan per entitas, duplikat, mapping yang perlu ditinjau, retry dan rollback jika memungkinkan. Semua perubahan perlu ditampilkan pada preview sebelum diterapkan.

### Ide E — Backup/restore yang dapat dipercaya

- Backup penuh otomatis dan offsite, enkripsi di luar aplikasi, retensi, checksum, dan notifikasi kegagalan.
- Tombol **uji pemulihan** ke database staging, bukan produksi.
- Status job dan manifest isi backup (tabel, row counts, versi schema).
- Wipe hanya dapat dilaksanakan setelah backup baru diverifikasi.

### Ide F — Mode koneksi lambat/PWA

Untuk lingkungan sekolah dengan Wi-Fi atau internet yang tidak stabil, perbaiki ketahanan halaman absensi dan pengumpulan jawaban: auto-save dengan retry, indikator status sinkronisasi, idempotency key, dan deteksi konflik. Jangan membuat mode offline untuk nilai akhir sebelum aturan konflik dan keamanan data jelas.

### Ide G — Audit trail akademik

Catat siapa mengubah nilai, nilai lama/baru, alasan, waktu, semester, dan sumber import. Untuk e-Rapor, pertimbangkan status draft → diperiksa → disahkan → diterbitkan serta pembekuan snapshot ketika rapor sudah disahkan. Perubahan setelah penerbitan harus tercatat sebagai revisi.

### Ide H — Aksesibilitas dan pengalaman guru

Sediakan tampilan mobile, pencarian/filter konsisten, pagination untuk dataset besar, pesan error yang dapat ditindaklanjuti, label formulir jelas, empty states dan keyboard navigation. Hindari bergantung pada warna saja untuk status. Uji dengan data realistis dan ukuran layar kelas.

---

## 7. Rencana Perbaikan Bertahap

### Fase 0 — Lindungi data dan hentikan risiko

1. Buat backup DB dan file yang diverifikasi; catat commit awal.
2. Jika kredensial lama pernah terpapar, cabut/rotasi tanpa menunggu refactor.
3. Nonaktifkan CBT/tryout untuk penggunaan resmi sampai skema, penilaian dan authorization tests lulus. Feature flag harus menutup rute guru, siswa, dan admin jika modul dimatikan.
4. Jangan jalankan `wipeAll` pada database produksi selama cakupan backup belum terbukti lengkap.

### Fase 1 — Perbaiki skema dan authorization

1. Migrasi nullable `waktu_selesai` untuk CBT dan tryout.
2. Desain ulang relasi jawaban CBT per attempt; tambahkan FK, indeks dan uniqueness yang tepat.
3. Perbaiki Policy/scope guru dan siswa pada seluruh operasi bank soal, pertanyaan, sesi, hasil dan jawaban.
4. Terapkan ownership check untuk sesi tryout dan asesmen TKA.
5. Buat tests yang memastikan pengguna lain menerima 403/404 tanpa side effect.

### Fase 2 — Pastikan penilaian akurat

1. Samakan kontrak payload soal/opsi dan grader.
2. Implementasikan kunci benar/salah, grading objective, manual grading untuk esai, dan status hasil.
3. Snapshot soal per attempt; betulkan perhitungan skor maksimum dan randomisasi pilihan.
4. Pindahkan auto-submit ke service idempotent yang mengembalikan alur response HTTP yang benar.
5. Hapus umpan balik benar/salah saat tes resmi sedang berjalan.

### Fase 3 — Amankan integrasi dan backup

1. Perbaiki pemilihan spreadsheet dan jangan menghapus tab bersama saat hanya sync subset guru.
2. Gabungkan command backup terjadwal dan export panel ke registry yang sama.
3. Selaraskan backup/restore/wipe untuk semua tabel atau tampilkan batasan secara eksplisit.
4. Tulis backup melalui stream yang benar, tampilkan status queue, dan uji restore berkala.
5. Uji Dapodik sync dengan duplikat, data tidak lengkap, timeout, retry, dan perubahan sebagian.

### Fase 4 — CI, dokumentasi dan deployment

1. Tambah GitHub Actions untuk test/lint/build dan PostgreSQL integration test.
2. Update README dan hapus/arsipkan status report yang kedaluwarsa.
3. Tambahkan deployment checklist: `APP_DEBUG=false`, HTTPS, secret management, queue worker, cron scheduler, storage permissions, DB migration backup, health checks, log retention.
4. Buat staging yang mendekati produksi dan lakukan UAT per role.

### Fase 5 — Fitur baru

Mulai mesin asesmen bersama, early warning, portal orang tua, Dapodik sync center dan peningkatan UX hanya setelah fase 0–4 stabil. Rilis per modul dengan feature flag, migration plan, test rollback, dan acceptance criteria yang jelas.

---

## 8. Matriks Tes Minimum sebelum rilis

| Modul/alur | Skenario sukses | Skenario negatif wajib |
|---|---|---|
| Login/role | Setiap role masuk dan diarahkan ke dashboardnya | Akun nonaktif, gagal login berulang, role salah |
| CBT | Mulai → autosave → resume → submit → nilai → review | ID result siswa lain, ID sesi/bank guru lain, waktu habis, submit ganda, soal tidak termasuk sesi |
| Tryout | Mulai → simpan semua jawaban → selesai → rekap | Result orang lain, soal dari paket lain, bocor nilai sebelum selesai, akun tanpa record siswa |
| TKA | Guru menilai siswa pada cakupannya; orang tua melihat laporan anak | Guru mengirim siswa/asesmen di luar cakupannya; orang tua mengubah ID anak |
| e-Rapor | Import/input → hitung → cetak → ekspor konsisten | Nilai dengan jenis tidak valid, jadwal tak terpetakan, duplikasi import |
| Google Sheets | Sync penuh sesuai model personal/pusat | Guru A tidak boleh menghapus data guru B; kegagalan API di tengah sync |
| Dapodik | Preview → sinkron → log hasil | Duplicate, referensi invalid, key tak valid, timeout/retry, partial failure |
| Backup/restore | Backup lengkap → restore staging → row counts dan relasi diverifikasi | Backup parsial, checksum salah, skema berbeda, restore gagal di tengah |
| Wipe | Dry-run dan konfirmasi kuat setelah backup valid | Pembatalan, password salah, tabel gagal, efek cascade tak diharapkan |
| Deployment | Asset build, worker dan scheduler sehat | Missing env, failed job, disk penuh, migrasi gagal |

Setiap tes negatif harus memeriksa bukan hanya status HTTP, tetapi juga bahwa database tidak berubah dan tidak ada informasi rahasia di respons/log.

---

## 9. Dokumentasi dan sumber kode yang menjadi acuan

- [README](https://github.com/amhyer/simantapmonsa/blob/main/README.md)
- [Laporan audit 14 Oktober](https://github.com/amhyer/simantapmonsa/blob/main/LAPORAN_AUDIT_BUG_2026-10-14.md)
- [Laporan perbaikan 14 Oktober](https://github.com/amhyer/simantapmonsa/blob/main/LAPORAN_PERBAIKAN_2026-10-14.md)
- [Routes web](https://github.com/amhyer/simantapmonsa/blob/main/routes/web.php)
- [CBT Student Controller](https://github.com/amhyer/simantapmonsa/blob/main/app/Http/Controllers/Cbt/CbtStudentController.php)
- [CBT Session Controller](https://github.com/amhyer/simantapmonsa/blob/main/app/Http/Controllers/Cbt/CbtTestSessionController.php)
- [CBT Question Bank Controller](https://github.com/amhyer/simantapmonsa/blob/main/app/Http/Controllers/Cbt/CbtQuestionBankController.php)
- [CBT result migration](https://github.com/amhyer/simantapmonsa/blob/main/database/migrations/2026_10_04_210653_create_cbt_test_results_table.php)
- [CBT answer migration](https://github.com/amhyer/simantapmonsa/blob/main/database/migrations/2026_10_04_210643_create_cbt_student_answers_table.php)
- [Tryout student controller](https://github.com/amhyer/simantapmonsa/blob/main/app/Http/Controllers/Tryout/TryoutStudentController.php)
- [Tryout result migration](https://github.com/amhyer/simantapmonsa/blob/main/database/migrations/2026_10_04_222414_create_tryout_results_table.php)
- [Google Sheets integration](https://github.com/amhyer/simantapmonsa/blob/main/app/Http/Controllers/Guru/IntegrasiController.php)
- [Backup controller](https://github.com/amhyer/simantapmonsa/blob/main/app/Http/Controllers/Admin/BackupController.php)
- [Scheduled backup command](https://github.com/amhyer/simantapmonsa/blob/main/app/Console/Commands/BackupData.php)
- [Backup registry](https://github.com/amhyer/simantapmonsa/blob/main/app/Support/BackupTables.php)
- [Test suite](https://github.com/amhyer/simantapmonsa/tree/main/tests)
- [CBT progress document](https://github.com/amhyer/simantapmonsa/blob/main/docs/CBT_IMPLEMENTATION_PROGRESS.md)

---

## 10. Putusan Akhir

SIMANTAP memiliki cakupan fungsi yang luas dan basis implementasi yang cukup besar. Pekerjaan audit sebelumnya sudah membantu, dan commit terbaru menyebut test suite hijau. Namun, pemeriksaan langsung atas beberapa jalur kritis menemukan ketidaksesuaian kontrak data dan kontrol akses yang tidak dapat dibuktikan aman hanya dengan pemeriksaan sintaks atau validasi statis.

**Rekomendasi utama:** tahan penggunaan CBT/tryout untuk penilaian resmi sampai P0 ditutup dan lolos pengujian PostgreSQL serta tes akses silang. Berikutnya amankan Google Sheets dan backup/restore, lalu jadikan test + build sebagai CI wajib. Setelah itu, fitur baru paling bernilai adalah mesin asesmen bersama, early-warning yang transparan, portal orang tua, dan pusat rekonsiliasi Dapodik.

Dokumen ini adalah audit statis atas snapshot publik yang disebutkan di awal, bukan sertifikasi keamanan atau bukti bahwa aplikasi produksi sedang dieksploitasi. Temuan perlu diverifikasi kembali setelah setiap perubahan kode dan migrasi.
