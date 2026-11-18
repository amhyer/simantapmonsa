# Aturan Proyek SIMANTAP MONSA (Laravel 13, PostgreSQL, Blade + Alpine, Tailwind, Vite)

## Umum
- Penjelasan dalam bahasa Indonesia; nama kode (variabel, fungsi, kelas) dalam bahasa Inggris.
- Kerja hanya di branch non-main. Jangan push, merge, atau rebase tanpa izin.
- Satu tugas = satu commit kecil (feat:, fix:, perf:, refactor:, docs:, chore:).
- Sebelum mengubah lebih dari 3 file, tulis rencana dan tunggu persetujuan.

## Larangan keras
- Jangan menampilkan, menyalin, atau commit isi .env, kunci, atau token. Sebut nama variabelnya saja.
- Jangan jalankan migrate:fresh, migrate:rollback, db:wipe, atau seeder pada database non-test.
- Jangan hapus file/migrasi lama; migrasi baru harus berupa file baru.
- Jangan menulis ulang riwayat git (filter-branch, force push).
- Jangan menghapus fitur multi-tenant, CBT, atau flag SIMANTAP_*; cukup jaga lewat config.

## Standar Laravel
- Validasi pakai Form Request, bukan di dalam controller.
- Logika bisnis baru ditaruh di Action/Service, bukan menambah controller yang sudah >500 baris.
- Hindari N+1: pakai with()/load(). Daftar data besar wajib paginate/chunk/cursor.
- Jangan pakai closure di routes/ (merusak route:cache).
- Pakai config() di kode, bukan env() di luar folder config/.
- Setiap fitur yang menyentuh data per-sekolah harus menghormati scope tenant.
- Pengecekan peran lewat middleware/policy, bukan if di view.

## Frontend
- Satu sistem CSS utama (Tailwind). Jangan menambah pemakaian Bootstrap baru.
- Gambar: WebP/AVIF, loading="lazy", width/height eksplisit.
- Jangan tambah library JS besar tanpa alasan.

## Verifikasi
- Setelah perubahan PHP: ./vendor/bin/pint --test (hanya file yang diubah) dan composer test.
- Setelah perubahan frontend: npm run build.
- Laporkan hasil perintah apa adanya; jangan klaim "berhasil" tanpa menjalankannya.
- Saat melaporkan hasil tes, tempel baris ringkasan asli dari output (contoh: "Tests: 636, Assertions: 3632" atau "FAILURES!") beserta durasi dari perintah yang BARU dijalankan. Jangan merangkum dari ingatan atau dari proses sebelumnya. Jika output tidak jelas, jalankan ulang.
