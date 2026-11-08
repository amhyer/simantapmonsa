# Audit statis menu dan navigasi — 8 November 2026

## Ringkasan eksekutif

**Rekomendasi: audit dan rapikan menu yang sudah ada terlebih dahulu; belum perlu menambah menu baru.** Inventaris sidebar saat audit berjumlah **113 entri** dari tujuh peran. Admin sendiri mempunyai **57 entri** (54 rute unik), sedangkan fitur bersekolah-banyak memiliki beberapa aksi lama yang masih terlihat tetapi sengaja ditolak middleware dengan HTTP 503. Ini berisiko terasa sebagai navigasi rusak, walaupun perlindungan datanya memang benar.

Audit ini bersifat **statis**: ia memeriksa entri navigasi, keberadaan named route, target controller, middleware route, dan sebagian flag modul. Audit tidak menyimpulkan bahwa halaman “tidak dipakai” hanya karena tak ada tautan, dan tidak menyimpulkan menu “tak berguna” karena tabelnya sedang kosong. Untuk keputusan berdasarkan pemakaian riil diperlukan telemetry kunjungan per peran dan periode.

## Cara menjalankan dan membaca matriks

Jalankan di root proyek setelah dependency tersedia:

```bash
php artisan test --filter=MenuInventoryTest
```

Tes ini memvalidasi bahwa setiap rute menu terdaftar serta tidak ada nama rute ganda dalam peran dan tab yang sama. Tes juga membuat **matriks lengkap** berisi peran, tab/grup, label, named route, URI, `Controller@method`, middleware efektif, dan flag modul di:

```text
storage/logs/menu-inventory.md
```

Di GitHub Actions, berkas tersebut diunggah sebagai artifact bernama **`menu-inventory`** pada workflow `Tests`, termasuk jika job gagal setelah tes berjalan. Artifact merupakan snapshot otomatis terkini; tabel rute/controller/middleware tidak disalin manual di dokumen ini agar tidak basi ketika route berubah.

## Inventaris sidebar saat audit

| Peran | Entri | Rute unik | Catatan |
|---|---:|---:|---|
| Admin | 57 | 54 | Empat tab: e-Rapor 18, CBT 9, TKA 4, Setup 26 |
| Guru | 25 | 25 | Ada grup asesmen yang dipengaruhi flag modul |
| Siswa | 9 | 9 | Ada item asesmen yang dipengaruhi flag modul |
| Orang tua | 8 | 8 | Ada item asesmen yang dipengaruhi flag modul |
| Kepala sekolah | 8 | 8 | Grup pemetaan punya kondisi tenant yang perlu diselaraskan |
| Superadmin | 3 | 3 | — |
| Pegawai | 3 | 3 | Tautan ditulis langsung sebagai komponen Blade |
| **Total** | **113** | **110 jika menjumlahkan unik per peran** | Angka ini bukan deduplikasi global; beberapa rute dipakai lintas peran |

> Angka dihitung dari sumber sidebar pada tanggal audit. Jalankan tes untuk memperbarui angka dan matriks setelah perubahan.

## Temuan dan tindakan

### P1 — Tautan masih tampil, tetapi aksi backend diblokir pada mode multi-sekolah

Middleware `data.single-school` / `dapodik.single-school` mengembalikan 503 ketika konteks tenant aktif, `simantap.multi_tenant_enabled` aktif, atau sudah ada tenant. Dampaknya:

- **Admin → Setup → Tarik Data Dapodik** masih tampil. Halaman integrasi memanggil endpoint `admin.dapodik.api.config`, `admin.dapodik.api.test-connection`, `admin.dapodik.api.preview`, dan `admin.dapodik.api.sync`; endpoint aksi lama ini dibatasi middleware single-school. Halaman dapat terbuka tetapi tombol-tombolnya gagal.
- **Admin → Presensi → Import Presensi Mesin** masih tampil. Aksi tarik/tes koneksi mesin (`admin.presensi.mesin.tarik*`, `admin.presensi.mesin.tes`) dibatasi `data.single-school`; tombol import berkas tidak otomatis sama statusnya, jadi jangan sembunyikan seluruh halaman tanpa menilai alur per aksinya.
- Di sidebar admin saat ini hanya `admin.dapodik.push.index`, `admin.backup.index`, dan `admin.log.index` yang disaring ketika ada tenant. Itu belum mencakup UI Dapodik tarik maupun tombol tarik mesin.
- Sidebar kepala sekolah menyembunyikan grup **PEMETAAN** hanya dengan `TenantContext::check()`, sedangkan middleware memakai tiga kondisi di atas. Saat flag multi-tenant aktif/tenant sudah ada tanpa context yang sedang terpasang, visibilitas dan akses tidak seragam.

**Tindakan yang disarankan:** tentukan batas per fitur. Untuk aksi yang belum tenant-safe, sembunyikan/nonaktifkan tombol dengan pesan alasan yang jelas memakai predicate tenant yang sama; jangan sekadar membuang proteksi backend. Prioritas berikutnya adalah desain isolasi tenant jika integrasi itu harus tersedia untuk semua sekolah.

### P2 — Tiga tautan duplikat antartab Admin

Setiap duplikat berada pada tab berbeda sehingga tidak melanggar validasi duplikat per tab, tetapi berpotensi membingungkan bila pengguna mengira setiap tab punya fungsi terpisah:

| Named route | Muncul sebagai |
|---|---|
| `admin.dashboard` | “Dasbor Sistem” (e-Rapor) dan “Dasbor Utama (E-Rapor)” (CBT) |
| `admin.modul.index` | “Modul & Tampilan (Setup)” (CBT) dan “Modul & Tampilan” (Setup) |
| `admin.log.index` | “Log Aktivitas (Setup)” (CBT) dan “Log Aktivitas” (Setup) |

**Tindakan:** pertahankan hanya jika tab adalah pintasan kontekstual yang disengaja; jika tidak, pilih tab utama tunggal. Hindari dua label untuk tujuan identik.

### P2 — Rute tanpa menu tidak otomatis yatim atau mati

Matriks CI menyertakan daftar kandidat rute GET tanpa parameter yang tidak ditemukan sebagai rujukan di sidebar, tautan Blade, command palette, atau navigasi bawah. Kandidat itu harus ditinjau manual: halaman cetak/unduh, callback, halaman detail, dan endpoint alur internal memang sering tidak memiliki menu sendiri. Rute yang punya parameter dikecualikan dari daftar kandidat otomatis.

Contoh yang **bukan** temuan yatim setelah verifikasi: `kepsek.hasil-belajar.index` sekarang ada di sidebar kepala sekolah; `admin.users.siswa` (`UserController::siswaIndex`) dipakai oleh sidebar, command palette, dan navigasi bawah. Temuan dokumentasi roadmap lama mengenai dua hal tersebut sudah usang.

### P3 — Menu tanpa aktivitas/data belum dapat diputuskan dari scan kode

Keberadaan named route membuktikan target navigasi terdaftar, bukan bahwa role benar-benar menggunakannya. Sebaliknya, halaman yang menampilkan empty state belum tentu tak terpakai. `MenuAuditTest` menguji sebagian navigasi dengan data contoh; tes itu juga bukan bukti pemakaian produksi. Untuk menemukan menu yang benar-benar jarang/tidak dipakai, tambahkan pencatatan kunjungan yang menjaga privasi: waktu, route, role, tenant (ID internal), tanpa payload sensitif; lalu evaluasi 30–60 hari mencakup siklus akademik yang relevan.

## Batasan dan langkah berikut

- Matriks route/controller/middleware dari artifact adalah hasil **konfigurasi test/CI**. Flag CBT/Tryout diaktifkan oleh test agar rute opsional ikut muncul; flag per-item `asesmen` dicatat dari deklarasi sidebar. Tampilkan kondisi deployment sebenar-benarnya secara eksplisit sebelum memutuskan menu mana yang dipublish.
- Resolver statis tidak dapat membuktikan otorisasi efektif untuk tiap kombinasi role/tenant, apakah route benar-benar dapat dibuka dengan data nyata, atau apakah bisnis memakai fitur. Itu memerlukan feature tests dan telemetry.
- `MenuAuditTest` dan `MenuNavigationRegressionTest` memang sudah ada; `MenuInventoryTest` menambahkan inventarisasi menu/rute, pemeriksaan rute dan duplikasi dalam CI, serta laporan matriks otomatis.
- Urutan kerja yang disarankan: (1) bereskan visibilitas aksi yang return 503, (2) sepakati duplikat antar-tab, (3) review daftar rute kandidat tanpa menu pada artifact, (4) ukur penggunaan nyata, baru (5) putuskan penambahan/penghapusan menu.
