# RENCANA PERBAIKAN & RILIS SIMANTAP

> Status dasar (terverifikasi): 75/75 test, 0 route mati, 0 view hilang, log bersih.
> Dokumen ini satu-satunya sumber kebenaran untuk sisa pekerjaan.
> Update 2026-10-02: B1 (Perkembangan Nilai), B2 (Cetak admin), B3 (Transkrip Ijazah) **selesai** — 94/94 test hijau.

## BAGIAN A — Verifikasi & Rilis (syarat "siap digunakan")

| # | Item | Status | Aksi |
|---|---|---|---|
| A1 | Commit fitur wipe (3 file) | ⏳ Belum | `commit` + pesan, lalu opsional `push` |
| A2 | Uji browser 9 halaman baru | ⏳ Manual | Tanggal Rapor, Kelompok, Mapping, Ekskul, Logo/TTD, Foto (massal+per-baris), Statistik (filter), Daftar+Detail Rombel, Zona wipe (JANGAN eksekusi wipe sungguhan — cukup tampil + validasi ditolak saat konfirmasi salah) |
| A3 | Uji browser regresi | ⏳ Manual | Login 5 role + 1 alur inti tiap role (guru: input nilai; siswa: kerjakan kuis; ortu: isi kebiasaan; kepsek: rekap; admin: backup export) |
| A4 | Deploy staging + smoke test | ⏳ Server | `migrate --force`, `config:cache`, `route:cache`, `view:cache`, `.env` staging |
| A5 | Checklist produksi | ⏳ Deploy | `APP_ENV=production`, `APP_DEBUG=false`, HTTPS + `SESSION_SECURE_COOKIE=true`, `TELESCOPE_ENABLED=false`, queue worker (supervisor), cron scheduler, ganti semua password default, backup terjadwal |

## BAGIAN B — Modul Fase 2 (11 badge "Segera")

Urutan disarankan berdasarkan ketergantungan (transkrip butuh mapping + tanggal).

### B1. Perkembangan Nilai (S) — 2 halaman, tanpa tabel baru
- Grafik Nilai Rapor per kelas (dropdown kelas sudah ada polanya di Statistik) + tren multi-semester.
- Data: agregasi `nilai_erapor` yang sudah ada.
- **Status: ✅ selesai** — rilis di commit "halaman Perkembangan Nilai dan Grafik Nilai Rapor".

### B2. Cetak admin: Leger + Pelengkap + Nilai Rapor (M)
- Gunakan ulang engine PDF guru (`ErapotGeneratorController`) dengan guard peran admin + filter kelas.
- Tanpa tabel baru; tambah 3 route + 1–2 view ringkas + link sidebar (ganti badge Segera).
- **Status: ✅ selesai (2026-10-02)** — `CetakController`: leger (matriks nilai per kelas), pelengkap (rekap hadir + catatan), nilai rapor (+ detail per siswa). 6 test (`CetakTest`).
- **Deviasi dari rencana:** cetak via print browser (`window.print()` + `@media print`), bukan engine dompdf — menghindari koupling ke view PDF guru; link sidebar aktif (badge Segera dilepas).

### B3. Transkrip Ijazah (L) — modul penuh
- Tabel baru: `transkrip_setting` (1 baris: desimal, kop, TTD, nama/NIP kepsek), `nomor_ijazah` (siswa_id, nomor), `nilai_transkrip` (siswa_id, mapel, nilai).
- Manfaatkan kolom `mapel.masuk_transkrip` (sudah live) untuk Mapping Mapel.
- Halaman: Setting, Import Nomor (CSV), Mapping (checkbox masuk_transkrip), Input Nilai, Import Nilai (CSV + tolak formula, contoh e-Rapor), Cetak (PDF, pakai nomor + setting).
- Estimasi terbesar; kerjakan per sub-halaman dengan verifikasi tiap langkah (pola R1–R5 yang terbukti).
- **Status: ✅ selesai (2026-10-02)** — `TranskripController` (13 route), 12 test (`TranskripTest`), 6 link sidebar aktif.
- **Deviasi dari rencana:** tanpa tabel baru untuk setting & nomor — setting menumpuk di JSON `SekolahSettings->pengaturan['transkrip']`, nomor ijazah jadi kolom `siswa.nomor_ijazah` (unique); Mapping memakai halaman Mapping Rapor yang sudah punya kolom `masuk_transkrip`; cetak via print browser. Import nilai menolak nilai non-numeric/formula (is_numeric + rentang 0–100).

### B4. Kirim Nilai ke Dapodik (M, setelah B2)
- Halaman UI sudah ada (`push.index`); yang kurang: verifikasi end-to-end melawan bridge sungguhan + status per modul yang jujur (sukses/gagal per baris).
- Butuh akses server Dapodik aktif saat testing.

## BAGIAN C — Higiene (kapan saja, tidak memblokir rilis)

- Pint cleanup (18 isu kosmetik): `./vendor/bin/pint`, satu file per commit.
- Supervisi queue worker produksi + retry/failed-jobs monitoring.
- Jadwal backup otomatis (cron `schedule:run` + `backup:clean` bila ada).
- Rotasi service-account Google setelah go-live.

## Definisi Selesai per Item
1. `php -l` bersih · 2. route terdaftar · 3. Blade compile · 4. PHPUnit hijau · 5. uji browser lolos · 6. commit (+push bila diminta). Tanpa loncat langkah — pola yang menyelamatkan kita dari 3 parse error.
