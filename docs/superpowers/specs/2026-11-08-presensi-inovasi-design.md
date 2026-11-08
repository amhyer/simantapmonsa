# Design: Inovasi Presensi ke Depan untuk SIMANTAP

- **Tanggal:** 2026-11-08
- **Status:** Disetujui (user setuju ke kelima ide)
- **Konteks:** Fitur presensi ZKTeco (Attendance Management / `att2000.mdb`) di SIMANTAP sudah terbawa cukup jauh — DB reader (TCP / PowerShell / ODBC), fallback, import scan, tes koneksi, pengaturan engine via website.

## Tujuan Inovasi

Membuat fitur presensi lebih **kompleks (kompleet)** dan **mudah digunakan** oleh sekolah, tanpa mengubah perilaku inti yang sudah tervalidasi.

## Ide 1: Dashboard Presensi Interaktif

- **Akar masalah:** Data presensi tersebar (DB mesin → import → tabel presensi_pegawai). Untuk pengawas/admin, membaca tabel mentah tidak praktis.
- **Solusi:** Dashboard ringkasan harian per guru/kelas: jumlah hadir, terlambat, pulang, izin/sakit. Visual grafik/bars.
- **File:** `PresensiController` (method baru atau di area presensi), view baru/extend, route.
- **Data:** `presensi_pegawai` + `users`.

## Ide 2: Auto-Sync Scheduler

- **Akar masalah:** Saat ini penarikan manual via tombol (1 klik).
- **Solusi:** Artisan command `presensi:mesin-sync` → baca DB mesin → import; dijadwalkan (mis. harian) via `Schedule`.
- **File:** `app/Console/Commands/PresensiMesinSyncCommand.php`, `AppServiceProvider@boot` (schedule), route tidak perlu.
- **Data:** tidak baru.

## Ide 3: Notifikasi Real-time / Perubahan Kehadiran

- **Akar masalah:** Perubahan kehadiran (terlambat/pulang) hanya tersimpan, tidak memberi tahu.
- **Solusi:** Notifikasi in-app (atau log aktivitas + notifikasi) saat hasil import/tarik menghasilkan status terlambat / perubahan.
- **File:** `PresensiMesinService`, notification (jika ada), activity log.
- **Catatan:** perlu evaluasi channel notifikasi yang tersedia di aplikasi.

## Ide 4: Konfigurasi Jam Kerja per Jenjang

- **Akar masalah:** Satu `PRESENSI_MESIN_JAM_MASUK` untuk semuanya.
- **Solusi:** Jam masuk per jenjang (PAUD/SD/MI/SMA/SMK/SMA) via pengaturan (DB JSON / config).
- **File:** `config/presensi.php`, reader/service, form pengaturan.
- **Catatan:** perlu relasi ke data jenjang di SIMANTAP (sekolah/siswa).

## Ide 5: Live Monitor via TCP

- **Akar masalah:** Tes koneksi membaca sekali; tidak ada monitoring realtime.
- **Solusi:** Mode live: membaca scan terbaru dari mesin via TCP (pakai packet helpers yang sudah ada), ditampilkan di view (polling atau trigger tombol "Baca Sekarang").
- **File:** controller method, view, reader (method read live).
- **Catatan:** paket protokol TCP sudah jadi, tinggal integrasi.

## Rencana Implementasi (ringkas, akan dibuat plan detail)

Urutan prioritas berdasarkan nilai & kompleksitas:
1. Dashboard presensi interaktif (paling berdampak, relatif cepat).
2. Auto-sync scheduler (ringan, berbasis command).
3. Notifikasi perubahan kehadiran.
4. Jam kerja per jenjang.
5. Live monitor TCP.

## Risiko / Catatan

- Fitur baru harus mengikuti pola existing (service/controller/view/test).
- Jangan mengubah perilaku proteksi data (izin/sakit/cuti, koreksi manual).
- Tes harus tetap hijau.

## Verifikasi yang diusulkan

- Tes per fitur baru (dashboard, scheduler command, notifikasi, jam kerja, live).
- Full suite tetap harus lolos (kecuali kegagulan pre-existing yang tidak berkaitan).
