# Backlog Pengembangan SIMANTAP MONSA

Dokumen ini mencatat item pengembangan lanjutan yang ditunda dari rilis awal agar peluncuran tetap fokus dan aman.

## Fase 2 — Modul CBT (Sebelum `SIMANTAP_CBT_ENABLED=true`)
1. **Pengelolaan Butir Soal CBT oleh Admin (Opsi a)**
   - Ekstrak logika mutasi soal (transaksi `lockForUpdate`, batas 200 soal per bank, sinkronisasi `LegacyExamMapper`) dari `App\Http\Controllers\Cbt\CbtQuestionController` ke `App\Services\Cbt\CbtQuestionService`.
   - Tambahkan halaman/endpoint admin di `/admin/cbt/banks/{bank}/questions/*` menggunakan `CbtQuestionService`, `StoreCbtQuestionRequest` (atau varian admin), dan pencatatan audit via `AktivitasService` agar admin dapat membantu guru menyusun butir soal saat mode persiapan (`cbt_enabled=false`) tanpa membuka route `/cbt/*` milik guru.
2. **Impor Soal Massal (Word / Excel)**
   - Tambahkan fitur impor butir soal pilihan ganda dari format Word/Excel pada jalur Bank Soal CBT (belum tersedia baik di sisi guru maupun admin).
3. **Uji Beban & Redis CBT**
   - Aktifkan Redis untuk `SIMANTAP_CBT_BUFFER_ASYNC=true` dan lakukan uji beban (k6) sebelum menyalakan `SIMANTAP_CBT_ENABLED=true`.

## Backlog Teknis Lainnya
- Evaluasi `set_time_limit(120)` pada `GET /admin/dapodik/api/semesters` (`DapodikController.php:224`) dan pastikan HTTP timeout eksplisit bila memanggil API Dapodik.
- Ubah cetak kartu QR massal (`PresensiKartuController`, 180s) menjadi queue job + unduhan asinkron.
- Evaluasi `php artisan test --parallel` untuk mempercepat `composer test`.
