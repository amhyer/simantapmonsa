# Rencana Refactor Struktur Kode (Item #17)

Dokumen ini adalah **keputusan** atas temuan review #17: beberapa *god file*
(> 800 baris) dan `routes/web.php` yang gemuk. Refactor besar dilakukan
bertahap, bukan sekaligus, karena setiap perpindahan kelas menyentuh jalur
kritis (sinkronisasi Dapodik, presensi mesin, e-Rapor, CBT) yang tidak dapat
diverifikasi hanya dengan membaca diff.

## 1. Kondisi terukur (baseline 2026-11-22)

| Berkas | Baris | Peran |
| --- | --- | --- |
| `app/Services/MesinPresensi/AttendanceDatabaseReader.php` | 1.231 | Baca DB mesin presensi via TCP/PowerShell/ODBC |
| `app/Services/Dapodik/DapodikSyncService.php` | 1.226 | Orkestrasi tarik/simpan referensi Dapodik |
| `app/Services/Tka/TkaCognitiveAnalyticsService.php` | 1.072 | Analitik kognitif TKA |
| `app/Http/Controllers/Admin/PresensiLaporanController.php` | 1.006 | Laporan presensi (query + ekspor + cetak) |
| `app/Http/Controllers/Api/DapodikSyncController.php` | 929 | Endpoint sinkronisasi bridge |
| `routes/web.php` | 1.172 | Seluruh rute web (700 `Route::`) |

Ambang yang dipakai: **800 baris** per kelas/berkas. Guard otomatis ada di
`tests/Unit/StrukturKodeTest.php`, sehingga berkas baru tidak boleh melewati
ambang dan berkas di daftar baseline tidak boleh bertambah panjang.

## 2. Seam ekstraksi per berkas

Setiap butir di bawah adalah unit kerja terpisah (satu PR kecil), berurutan
dari risiko terendah.

### `AttendanceDatabaseReader` (1.231)
1. `MesinPresensi\TcpAttendanceReader` — seluruh jalur protokol TCP
   (koneksi, handshake, pembacaan record). Sudah ada
   `tests/Unit/MesinPresensiTcpProtocolTest.php` sebagai jaring pengaman.
2. `MesinPresensi\PowerShellAttendanceReader` — pembungkus `Process`
   + resolusi biner PowerShell/script.
3. `MesinPresensi\OdbcAttendanceReader` — jalur ODBC.
4. Sisakan `AttendanceDatabaseReader` sebagai *façade* pemilih engine
   (`auto|tcp|powershell|odbc`) + normalisasi hasil.

### `DapodikSyncService` (1.226)
1. `Dapodik\Sync\ReferensiSync` — tabel referensi (mapel, rombel, guru).
2. `Dapodik\Sync\PesertaDidikSync` — siswa + rekaman.
3. `Dapodik\Sync\NilaiSync` — nilai/e-Rapor.
4. `Dapodik\Sync\SyncReporter` — ringkasan hasil, `Log`, dan
   `AktivitasService`, sehingga service hanya berisi alur.

### `TkaCognitiveAnalyticsService` (1.072)
1. `Tka\Analytics\DimensiAgregator` — agregasi per dimensi.
2. `Tka\Analytics\PredikatMapper` — pemetaan skor → predikat (sumber
   tunggal, dirujuk juga oleh `PredikatKonsistensiTest`).
3. `Tka\Analytics\RekomendasiBuilder` — narasi rekomendasi.

### `PresensiLaporanController` (1.006)
1. `Presensi\Laporan\PresensiRekapQuery` — seluruh query rekap.
2. `Presensi\Laporan\PresensiLaporanExporter` — CSV/Excel/PDF.
3. Controller hanya validasi + delegasi + `return view(...)`.

### `Api\DapodikSyncController` (929)
1. `Dapodik\Bridge\BridgeRequestValidator` — validasi payload per aksi.
2. `Dapodik\Bridge\BridgeResponder` — bentuk respons/peringatan.
3. Controller hanya memilih aksi dan mendelegasikan.

## 3. Rute

`routes/web.php` dipecah berdasarkan grup peran **tanpa mengubah urutan
registrasi** (urutan penting: rute statis sebelum rute berparameter):

```
routes/web.php            -> hanya include + grup lintas peran
routes/web/guest.php      -> login, landing, health
routes/web/superadmin.php -> grup prefix superadmin
routes/web/admin.php      -> grup prefix admin (termasuk admin/cbt, admin/tka)
routes/web/guru.php       -> grup prefix guru
routes/web/siswa.php      -> grup prefix siswa + ortu
routes/web/lainnya.php    -> kepsek, pengawas, tendik, cetak, api internal
```

Aturan: satu berkas = satu grup middleware yang sudah ada; blok dipindah
utuh (potong-tempel) agar urutan tetap; `php artisan route:list` dan
`RouteSweep`/`RouteExpectationRegistry` wajib identik sebelum vs sesudah.

## 4. Kriteria selesai

- Tidak ada berkas > 800 baris di `app/` dan `routes/web.php` < 400 baris.
- `tests/Feature/Support/RouteExpectationRegistry.php` tidak berubah isinya
  saat rute dipecah (bukti tidak ada rute yang hilang/berubah nama).
- Setiap ekstraksi disertai test yang menyebut kelas baru.
- Tidak ada perubahan perilaku: seluruh test Feature/Unit hijau di CI.
