# Reset Sandi Mandiri

Sebelum perubahan ini, satu-satunya cara memulihkan akun adalah meminta admin
mengganti sandi. Sekarang pengguna dapat meminta tautan reset melalui email.
Login tetap memakai `nama_pengguna`; email hanya dipakai untuk verifikasi dan
pengiriman tautan, sehingga tidak ada perubahan cara masuk bagi pengguna lama.

## 1. Yang ditambahkan

| Bagian | Berkas |
|---|---|
| Kolom `users.email` (nullable, unik) + tabel `password_reset_tokens` | `database/migrations/2026_10_26_000001_add_email_to_users_and_password_reset_tokens.php` |
| Permintaan tautan (`/lupa-sandi`) | `app/Http/Controllers/Auth/ForgotPasswordController.php` |
| Penyelesaian reset (`/reset-sandi/{token}`) | `app/Http/Controllers/Auth/ResetPasswordController.php` |
| Tampilan | `resources/views/auth/forgot-password.blade.php`, `resources/views/auth/reset-password.blade.php` |
| Tautan di halaman login | `resources/views/auth/login.blade.php` |
| Form email akun untuk admin | `resources/views/admin/users/create.blade.php`, `edit.blade.php` |
| Bukti otomatis | `tests/Feature/PasswordResetTest.php` |

Token memakai broker bawaan Laravel (`config/auth.php` → `passwords.users`),
berlaku default 60 menit, sekali pakai, dan disimpan sebagai hash di
`password_reset_tokens`.

## 2. Deploy

```bash
php artisan migrate --force
php artisan config:clear
```

Tidak ada seed ulang dan tidak ada perubahan pada sandi pengguna yang sudah ada.
Semua pengguna lama otomatis memiliki `email = NULL`.

## 3. Konfigurasi email (wajib untuk reset mandiri)

Reset tidak akan sampai tanpa pengiriman email yang benar. Isi variabel
produksi berikut (contoh SMTP sekolah):

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.sekolah.sch.id
MAIL_PORT=587
MAIL_USERNAME=no-reply@sekolah.sch.id
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@sekolah.sch.id
MAIL_FROM_NAME="SIMANTAP"
APP_URL=https://simantap.sekolah.sch.id
```

`APP_URL` menentukan domain tautan di email. Setelah konfigurasi berubah:

```bash
php artisan config:cache
php artisan queue:restart   # bila memakai queue worker
```

Checklist staging:

- [ ] Kirim satu permintaan reset ke email nyata dan pastikan tautan membuka
      `/reset-sandi/{token}` di domain yang benar.
- [ ] Periksa email masuk ke inbox (bukan hanya log) dan tidak masuk spam.
- [ ] Selesaikan reset, login dengan sandi baru, lalu pastikan sandi lama gagal.
- [ ] Pastikan `password_reset_tokens` kosong setelah reset berhasil.
- [ ] Pastikan akun tanpa email tetap mendapat pesan untuk menghubungi admin.

## 4. Alur pengguna

1. Pengguna membuka **Lupa kata sandi?** di halaman login.
2. Pengguna memasukkan nama pengguna **atau** email.
3. Jika akun aktif dan punya email, sistem mengirim tautan. Pesan yang tampil
   selalu generic agar tidak membocorkan akun mana yang terdaftar.
4. Pengguna membuka tautan, mengisi sandi baru minimal 8 karakter dua kali.
5. Sistem menghapus token, mengosongkan `force_password_change`, dan meminta
   pengguna login dengan sandi baru.

Akun tanpa email: admin membuka **Admin › Akun**, menambahkan email, lalu
pengguna dapat memakai alur mandiri. Admin juga tetap dapat mereset sandi
langsung; reset oleh admin memicu `force_password_change`.

## 5. Keamanan dan batas

- Permintaan dibatasi 5 percobaan/menit per IP; percobaan berlebihan ditolak.
- Hanya akun `aktif = true` yang diproses.
- Token disimpan ter-hash, kedaluwarsa, dan dihapus setelah dipakai.
- Sandi di-hash oleh cast `hashed` pada model `User`; controller tidak menyimpan
  sandi mentah.
- Respons permintaan tidak membedakan "akun tidak ada", "tidak punya email", dan
  "email terkirim" (kecuali pesan throttle).
- Email **belum diverifikasi** saat admin mengisinya. Pastikan admin memasukkan
  alamat yang benar; reset akan dikirim ke alamat itu.
- Pengguna yang tidak punya akses email tetap bergantung pada admin.
- Pengiriman email produksi tetap memerlukan SPF/DKIM domain sekolah; ini di luar
  aplikasi dan harus diuji di staging.
