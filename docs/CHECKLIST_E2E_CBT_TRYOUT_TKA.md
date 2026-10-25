# Checklist E2E CBT, Tryout, dan TKA

**Lingkup:** otomasi browser dan pemeriksaan route untuk tiga alur asesmen.
**Master audit/work plan tetap:** [`RENCANA_PERBAIKAN_VERIFIED.md`](RENCANA_PERBAIKAN_VERIFIED.md). Dokumen ini hanya checklist pengujian terskop, bukan pengganti atau ringkasan kesiapan produksi.

> **Status implementasi:** test, fixture, route registry, dan CI wiring telah ditambahkan. **Status verifikasi akhir: menunggu hasil CI**; runtime PHP/Composer lokal tidak tersedia di sandbox saat pekerjaan ini dibuat. Jangan menafsirkan checklist implementasi sebagai klaim bahwa pipeline sudah lulus atau aplikasi siap produksi.

## 1. Cakupan dan artefak

- [x] Fixture ID deterministik di `database/seeders/E2eFixtureSeeder.php`.
- [x] Daftar core route dan status yang diharapkan di `tests/Feature/Support/RouteExpectationRegistry.php`.
- [x] Route/status, route sweep GET 5xx, dan penolakan tanpa mutasi di `tests/Feature/RouteSweepTest.php`.
- [x] Playwright journey di `tests/e2e/cbt.spec.js`, `tests/e2e/tryout.spec.js`, dan `tests/e2e/tka.spec.js`.
- [x] Login browser menggunakan password `E2E_*_PASSWORD` dari environment di `tests/e2e/global-setup.js`; password tidak ditanam di source.
- [x] CI job E2E memakai SQLite persisten tersendiri (`database/e2e.sqlite`), terpisah dari smoke job SQLite `:memory:`.
- [x] Trace, screenshot, video, serta log Playwright diunggah sebagai artefak jika job E2E gagal.
- [ ] Pipeline CI pada commit implementasi ini hijau (isi setelah run selesai; jangan mengganti dengan asumsi).

## 2. Peta fixture

ID di bawah stabil untuk URL dan assertion. User login menggunakan `nama_pengguna`, bukan email; nama tersebut sengaja disamakan dengan `siswa.nis` supaya pencarian siswa CBT/Tryout menggunakan relasi yang benar.

| Label fixture | Tabel/model dan ID | Catatan |
|---|---|---|
| `admin` | `users.id = 9000` | Akun admin untuk CSV/session admin. |
| `guru_a`, `guru_b` | `users.id = 9001`, `9002` | Memiliki siswa dan assessment terpisah. |
| `siswa_a1`, `siswa_a2`, `siswa_b1` (akun) | `users.id = 9003`, `9004`, `9005` | Username sama dengan NIS siswa. |
| `siswa_a1`, `siswa_a2`, `siswa_b1` (data siswa) | `siswa.id = 9101`, `9102`, `9103` | A1/A2 milik Guru A; B1 milik Guru B. |
| `ortu_a1`, `ortu_b1` | `users.id = 9201`, `9202` | Masing-masing tertaut ke siswa 9101 dan 9103. Nilai relasi ortu disimpan sebagai string ID untuk meliputi normalisasi JSON. |
| Semester TKA | `semesters.id = 9541`, `9542` | Semester ganjil/genap 2026/2027. |
| Nilai/kehadiran laporan guru | `nilai.id = 9600`, `9601`; `kehadiran.id = 9610` | Fixture bantu untuk report. Tidak ada row `pengaturan_guru`: report/export tetap diuji tanpa setelan guru. |
| CBT bank | `cbt_question_banks.id = 9301` | Lima soal pilihan ganda di `cbt_questions.id = 9302–9306`. |
| CBT session | `cbt_test_sessions.id = 9311` | Status `running`; snapshot soal dan peserta `[9101, 9102]` konsisten. Jadwal buka/tutup relatif terhadap waktu seed. |
| CBT result | `cbt_results.id = 9321` untuk route registry | Tidak di-seed sebagai attempt aktif agar browser memulai dari petunjuk. Route-sweep test membuat result fixture 9321; browser journey membuat satu result baru dengan ID autoincrement lalu membuktikan tidak ada duplikasi melalui satu row CSV. |
| Tryout category/package | `tryout_categories.id = 9400`; `tryout_packages.id = 9401` | `tryout_1` adalah paket 9401. |
| Tryout subject/mapel/question | `tryout_subjects.id = 9402`; `mata_pelajaran.id = 9403`; `tryout_questions.id = 9404` | Satu soal bernilai satu poin; pilihan benar A. Opsi `tryout_question_options.id = 9405, 9406`. |
| Tryout session/attempt | `tryout_sessions.id = 9407`; `tryout_results.id = 9411` | Session berlangsung; result 9411 sengaja belum selesai. Bridge engine boleh memperbarui row natural-key yang sama. |
| TKA assessments | `tka_student_assessments.id = 9501`, `9502` | Assessment A1 milik Guru A; assessment B1 milik Guru B. |
| TKA parent report | `tka_parent_reports.id = 9561` | Report untuk assessment 9501 / siswa 9101; akses sukses menandainya dibaca. |
| TKA dimensions/items | dimensi 9551 dengan item 9552 (bobot 1) dan 9553 (bobot 3); dimensi lain 9554 dengan item 9555 | Item 9555 dipakai untuk menguji URL nested yang menunjuk dimensi yang salah. |

Nama password environment yang harus disediakan untuk browser global setup:

`E2E_ADMIN_PASSWORD`, `E2E_GURU_A_PASSWORD`, `E2E_GURU_B_PASSWORD`, `E2E_SISWA_A1_PASSWORD`, `E2E_SISWA_A2_PASSWORD`, `E2E_SISWA_B1_PASSWORD`, `E2E_ORTU_A1_PASSWORD`, `E2E_ORTU_B1_PASSWORD`.

CI menghasilkan nilai acak baru per run. Untuk uji lokal, gunakan password acak/rahasia dari environment; jangan memasukkan password literal ke source, fixture, atau dokumentasi.

## 3. Browser journeys

Kotak `[x]` di bagian ini menandakan assertion telah ditulis dalam test source. Itu **bukan** pernyataan bahwa browser/CI sudah menjalankannya dengan sukses; hasil eksekusi tetap menunggu CI dan harus dicatat terpisah.

### CBT (`tests/e2e/cbt.spec.js`)

- [x] **E2E-CBT-01** — Siswa A1 membuka route aktual `cbt.student.start` untuk session 9311, melihat petunjuk, lalu mulai; attempt `cbt_results` dibuat server-side.
- [x] **E2E-CBT-02** — Pilihan jawaban dikirim melalui autosave dan test menunggu respons HTTP sukses.
- [x] **E2E-CBT-03** — Reload memulihkan jawaban yang tampak terpilih.
- [x] **E2E-CBT-04** — Event tab tersembunyi mengirim telemetry integritas.
- [x] **E2E-CBT-05** — Submit dinilai server-side dan result menampilkan nilai.
- [x] **E2E-CBT-06** — Jawaban ditolak setelah submit (HTTP 409).
- [x] **E2E-CBT-07** — Mulai ganda kembali ke result yang sama; ekspor membuktikan hanya satu row result untuk siswa tersebut. Test feature `CbtExamTest` juga mencakup duplicate begin dan submit idempoten.
- [x] **E2E-CBT-08** — Siswa A2 ditolak pada GET result, autosave, submit, dan integrity.
- [x] **E2E-CBT-09** — Admin membuka detail session dan tautan **Ekspor CSV**, memeriksa content type, header, dan satu row hasil.
- [x] Pemeriksaan feature test mengunci assertion bahwa request negatif tidak mengubah result, menambah violation, atau membuat `exam_attempt`.

### Tryout (`tests/e2e/tryout.spec.js`)

- [x] **E2E-TRY-01** — Siswa A1 memulai session 9407 dan mengirim jawaban ke server.
- [x] **E2E-TRY-02** — Setelah reload, test memastikan radio jawaban terlihat terpilih dan progress `100.0%`; bukan hanya memeriksa DB. Controller mengirim jawaban tersimpan keyed by Tryout question ID, Blade memulihkan kontrol dan state JS, lalu progress diperbarui pada page load.
- [x] **E2E-TRY-03** — Submit menampilkan hasil server-side non-zero dan status lulus; jawaban setelah completion dikunci.
- [x] **E2E-TRY-04** — Siswa lain dan parent yang tidak terkait ditolak untuk result; post answer/complete juga ditolak.
- [x] Pemeriksaan feature test memastikan penolakan tidak mengubah `tryout_results`, `tryout_student_answers`, atau membuat `exam_attempt`.

### TKA (`tests/e2e/tka.spec.js`)

- [x] **E2E-TKA-01** — Index guru A hanya menampilkan assessment siswa yang dikelolanya.
- [x] **E2E-TKA-02** — Form mengirim skor item 1/4/4 dengan bobot 1/3/1; hasil terhitung 85.0% dan kesiapan `Siap`.
- [x] **E2E-TKA-03** — Guru A mendapat 403 saat membuka assessment guru B.
- [x] **E2E-TKA-04** — Parent A1 dapat membaca report 9561; parent B1 dan guru ditolak.
- [x] **E2E-TKA-05** — DELETE item 9555 melalui URL nested dimensi 9551 mendapat 404, dan item tetap ada di dimensi 9554.
- [x] **E2E-TKA-06** — Pemeriksaan feature test membuktikan akses parent/guru yang ditolak tidak menandai report sudah dibaca atau menghapus item.

## 4. Route registry dan sweep

`RouteExpectationRegistry` mencatat route aktual dan status yang diharapkan untuk GET core journey dengan fixture valid. Nama route mengikuti aplikasi sekarang, antara lain `cbt.student.*`, `admin.cbt.sessions.export`, `tryout.student.*`, `tka.assessments.*`, dan `tka.parent.*`; jangan menyalin nama route dari contoh lama tanpa memeriksa `routes/web.php`.

`RouteSweepTest` melakukan tiga pemeriksaan terpisah:

1. Status route core dengan ID fixture yang benar (termasuk status 403 untuk akses lintas pemilik).
2. Sweep semua GET route terhadap delapan akun fixture, mengisi parameter yang dikenal dengan ID valid dan mengabaikan hanya auth/diagnostics yang tidak relevan. Targetnya lebih dari 1.000 kombinasi dan nol HTTP 5xx. Hasil dicatat di `storage/logs/route-health-report.txt`.
3. Assertion tidak ada mutasi pada request negatif CBT, Tryout, TKA parent, serta nested TKA item.

CBT session memakai `opens_at`, `closes_at`, dan `status`, bukan `started_at`. `CbtExamService::publish()` membekukan snapshot/peserta dan `open()` mengubah sesi ke `running`; fixture E2E menanam snapshot/peserta yang konsisten untuk browser start. Waktu attempt berada di `CbtResult.started_at` dan deadline. Jika lifecycle fixture berubah, pertahankan invariant ini.

## 5. Menjalankan E2E lokal

Perintah berikut membuat/menghapus ulang **hanya** file SQLite yang ditunjuk `DB_DATABASE`. Jangan arahkan perintah destructive ini ke database kerja, staging, atau produksi.

```bash
export APP_ENV=e2e
export APP_DEBUG=false
export DB_CONNECTION=sqlite
export DB_DATABASE="$PWD/database/e2e.sqlite"
export DB_FOREIGN_KEYS=true
export CACHE_STORE=file
export SESSION_DRIVER=file
export QUEUE_CONNECTION=sync
export SIMANTAP_CBT_ENABLED=true
export SIMANTAP_EXAM_ENGINE_CBT=true
export SIMANTAP_EXAM_ENGINE_TRYOUT=true

for account in ADMIN GURU_A GURU_B SISWA_A1 SISWA_A2 SISWA_B1 ORTU_A1 ORTU_B1; do
  export "E2E_${account}_PASSWORD=$(openssl rand -hex 32)"
done

mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions storage/logs bootstrap/cache
touch "$DB_DATABASE"
php artisan key:generate
php artisan migrate:fresh --force
php artisan db:seed --class='Database\Seeders\E2eFixtureSeeder' --force
npm ci
npm run build
npx playwright install chromium
npm run test:e2e
```

Seeder menolak environment selain `testing`/`e2e`; pada `APP_ENV=e2e` ia juga mensyaratkan SQLite database yang nama file-nya memuat `e2e`. File DB dan auth state Playwright diabaikan Git. Untuk hasil browser yang sama dengan CI, gunakan versi PHP/Node dan browser yang dipatok workflow.

## 6. CI dan artefak

- PHPUnit SQLite dan PostgreSQL tetap menjalankan feature/route tests.
- Smoke Chromium unauthenticated tetap terpisah dan memakai `playwright.smoke.config.js`; ia tidak membutuhkan E2E accounts atau database.
- Job `Authenticated E2E (CBT, Tryout, TKA)` menjalankan migrasi + seeder pada file `database/e2e.sqlite` khusus job, lalu menyalakan server Laravel terpisah untuk Playwright.
- Jika E2E gagal, artifact `playwright-e2e-failure-artifacts` menyimpan `test-results/` (trace/screenshot/video) dan log. Artifak bukan bukti lulus; evaluasi error dan run CI tetap diperlukan.

## 7. Batas lingkup

- Staging tetap memerlukan environment yang disetujui; dokumen ini tidak menambah target staging.
- Penghapusan tabel legacy CBT/Tryout tetap di luar lingkup; tetap perlu inventaris dependensi dan rencana rollback.
- Tidak ada `test.skip` tanpa issue reference. Jika suatu journey sementara tidak dapat dijalankan, catat blocker/issue dan jangan menyamarkan kegagalan sebagai skip.
