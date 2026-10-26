# Rotasi Kredensial — Dapodik, API Key, dan Google Sheets

**Status:** `BELUM TERBUKTI` — belum ada bukti rotasi yang ditandatangani pemilik sistem.
Dokumen ini mencatat temuan riwayat Git, dampak, dan langkah rotasi yang harus dieksekusi
sebelum go-live. Nilai rahasia **tidak** ditulis di sini; hanya sidik jari SHA-256 (12 karakter
pertama) agar bukti tetap dapat dicocokkan tanpa membocorkan kredensial.

## 1. Temuan riwayat Git

Audit dijalankan pada 26 Oktober 2026 terhadap seluruh ref (`git log --all`).

| Commit | Berkas | Jenis | Panjang | SHA-256 (12) | Status di HEAD |
|---|---|---|---|---|---|
| `898ce58` (initial) | `dapodik-bridge/config.json` | `api_key` | 64 | `17e52cacbfd9` | berkas sudah tidak dilacak |
| `898ce58` (initial) | `dapodik-bridge/config.json` | `dapodik_token` | 15 | `2c18fd4dfd0d` | berkas sudah tidak dilacak |
| `898ce58` | `dapodik-bridge/test_fixed_sync.py` | `API_KEY` | 64 | `17e52cacbfd9` | nilai sudah dibersihkan |
| `898ce58` | `dapodik-bridge/test_fixed_sync2.py` | `API_KEY` | 64 | `17e52cacbfd9` | nilai sudah dibersihkan |

- `.env` **tidak pernah** masuk riwayat Git (`git log --all -- .env` kosong).
- Commit `31328cd` ("Keamanan: bersihkan kredensial lama...") menghapus kredensial yang masih
  tertulis di 7 berkas. Namun **nilai lama tetap dapat dipulihkan dari riwayat**.
- Pemeriksaan HEAD dengan pola `DAPODIK_TOKEN`, `Bearer`, `BEGIN PRIVATE KEY`, dan `AIza...`
  tidak menemukan nilai rahasia di berkas terlacak.
- `dapodik-bridge/config.json` sekarang di-`.gitignore`; `tools/verifikasi-perbaikan.php`
  memeriksa berkas itu tidak berisi nilai dan tidak ter-track.

**Kesimpulan:** kredensial yang pernah muncul di riwayat harus dianggap **bocor** sampai pemilik
sistem membuktikan sudah dicabut dan diganti. Menghapus riwayat Git saja tidak cukup.

Reproduksi audit (tanpa mencetak nilai):

```bash
git log --all --oneline --full-history -- .env
git log --all --oneline -S'DAPODIK_TOKEN' --name-only
git log --all --oneline -S'GOOGLE_CREDENTIALS_JSON' --name-only
php tools/verifikasi-perbaikan.php   # bagian "Kredensial di repositori"
```

## 2. Urutan rotasi yang wajib

Rotasi dilakukan oleh **pemilik sistem** (bukan agen) karena membutuhkan akses konsol
Dapodik, Google Cloud, dan panel admin SIMANTAP.

### 2.1 Token Dapodik

1. Login ke aplikasi Dapodik sebagai admin sekolah.
2. Cabut/regenerasi token webservice yang pernah dipakai bridge lama.
3. Simpan token baru hanya di salah satu tempat:
   - panel **Admin › Integrasi › Dapodik** (kolom `dapodik_configs.token`, terenkripsi), atau
   - secret manager/CI variable untuk bridge Python.
4. Jangan menaruhnya kembali di `config.json` yang ter-track, skrip uji, atau dokumen.
5. Verifikasi koneksi read-only dari staging (lihat §4).

### 2.2 API key SIMANTAP (dipakai bridge)

1. Buka **Admin › API Key**; cabut key dengan sidik jari `17e52cacbfd9` bila masih ada.
2. Buat key baru; salin **hanya sekali** saat ditampilkan (disimpan sebagai hash SHA-256).
3. Perbarui konfigurasi bridge di mesin staging/produksi, lalu uji endpoint sync.
4. Catat tanggal, pembuat, dan sidik jari key baru di checklist §3.

### 2.3 Google Sheets / service account

1. Di Google Cloud Console, buat **key baru** untuk service account yang dipakai SIMANTAP.
2. Hapus key lama setelah key baru terbukti bekerja.
3. Batasi akses spreadsheet hanya ke service account tersebut; jangan bagikan ke akun pribadi.
4. Simpan kredensial sebagai `GOOGLE_CREDENTIALS_JSON` (secret manager) atau
   `storage/app/google-service-account.json` yang **tidak** di-track.
5. Uji baca metadata spreadsheet dari staging sebelum go-live.

### 2.4 `APP_KEY` dan sesi

- `APP_KEY` hanya perlu dirotasi bila pernah bocor. Rotasi membuat kolom terenkripsi
  (mis. `dapodik_configs.token`) tidak terbaca; siapkan input ulang token Dapodik.
- Setelah rotasi kredensial, hapus sesi lama (`php artisan session:clear` bila tersedia)
  dan paksa login ulang untuk akun admin.

### 2.5 Bridge Python (`dapodik-bridge/`)

- Hapus `dapodik-bridge/config.json` dari mesin yang tidak dipakai.
- Pakai `config.example.json` + environment variable; jangan pernah commit nilai asli.
- Audit ulang `dapodik-bridge/*.py` sebelum dijalankan di staging.

## 3. Checklist bukti rotasi

Isi dan tandatangani sebelum go-live. Status awal: semua `BELUM`.

| Item | Tanggal | Pelaksana | Bukti (hash/ID, tanpa nilai) | Status |
|---|---|---|---|---|
| Token Dapodik dicabut & diganti | | | | BELUM |
| API key lama `17e52cacbfd9` dicabut | | | | BELUM |
| API key baru dibuat & diuji | | | | BELUM |
| Service account Google key lama dihapus | | | | BELUM |
| Key Google baru diuji baca/tulis staging | | | | BELUM |
| `APP_KEY` ditinjau (rotasi bila perlu) | | | | BELUM |
| `dapodik-bridge/config.json` dihapus dari server | | | | BELUM |
| Audit riwayat dijalankan ulang & bersih | | | | BELUM |

## 4. Verifikasi setelah rotasi

Jalankan dari **staging** dengan kredensial baru:

```bash
php artisan integrasi:dapodik:cek --json     # konektivitas & sampel read-only
php artisan integrasi:sheets:cek --json      # metadata spreadsheet
php artisan test --filter Integrasi
```

Keberhasilan perintah di atas hanya membuktikan kredensial baru dapat terhubung. Uji tulis,
idempotensi, dan rekonsiliasi data tetap harus dilakukan di staging dengan data salinan
produksi.

## 5. Catatan tentang rewrite riwayat

`git filter-repo` dapat menghapus nilai dari riwayat, tetapi:

- tidak membatalkan kredensial yang sudah bocor;
- memaksa semua clone/fork melakukan re-clone;
- mengubah hash commit dan dapat memutus referensi PR/rilis.

Karena itu urutannya: **rotasi dulu, rewrite belakangan (opsional)**. Jika rewrite dilakukan,
catat tanggal dan koordinasikan dengan seluruh pemegang clone.
