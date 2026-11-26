# Playbook Go-Live & Aktivasi Bertahap Modul Ujian (TKA, CBT, Tryout)

Dokumen ini menjadi panduan operasional langkah-demi-langkah untuk menyalakan modul ujian SIMANTAP secara bertahap tanpa mengganggu layanan akademik inti (e-Rapor, presensi, Dapodik).

---

## Ringkasan 4 Fase Aktivasi

| Fase | Modul Aktif | Variabel `.env` Utama | Gerbang Wajib |
|---|---|---|---|
| **Fase 0 (Launch Awal)** | Akademik, e-Rapor, Presensi, TKA | `SIMANTAP_CBT_ENABLED=false`<br>`SIMANTAP_EXAM_ENGINE_READERS=false` | `php artisan exam:preflight --target=tka` |
| **Fase 1 (TKA Aktif)** | Statistik & Kesiapan TKA | `TKA_KESIAPAN_AKTIF=true` | Cron `tka:refresh` tiap 6 jam berjalan |
| **Fase 2 (CBT & Tryout Dual-Write)** | CBT & Tryout aktif (tulis ke legacy + `exam_*`) | `SIMANTAP_CBT_ENABLED=true`<br>`SIMANTAP_EXAM_ENGINE_CBT=true`<br>`SIMANTAP_EXAM_ENGINE_TRYOUT=true`<br>`SIMANTAP_EXAM_ENGINE_READERS=false` | `php artisan exam:preflight --target=cbt` + cron `cbt:finalize-expired` tiap menit |
| **Fase 3 (Cutover Engine Readers)** | Dasbor/laporan ujian membaca langsung dari `exam_*` | `SIMANTAP_EXAM_ENGINE_READERS=true` | `php artisan exam:backfill` + `php artisan exam:preflight --target=readers` |

---

## Prosedur Eksekusi Per Fase

### 1. Sebelum Naik ke Fase 2 (Aktivasi CBT & Tryout)
1. Pastikan cron scheduler berjalan setiap menit (`* * * * * php artisan schedule:run`) agar `cbt:finalize-expired` otomatis memfinalisasi ujian siswa yang terputus.
2. Pastikan `DB_QUEUE_RETRY_AFTER=600` di `.env` dan queue worker berjalan dengan `--timeout=300`.
3. Jalankan preflight read-only:
   ```bash
   php artisan exam:preflight --target=cbt
   ```
4. Hanya lanjutkan bila status **GO** (`exit code 0`). Ubah `.env` menjadi `SIMANTAP_CBT_ENABLED=true`, lalu jalankan:
   ```bash
   php artisan config:cache && php artisan queue:restart
   ```
5. Lakukan uji penerimaan 1 kelas percontohan (mulai ujian → autosave → reload browser → kumpulkan → cek nilai & analisis butir).

### 2. Sebelum Naik ke Fase 3 (Cutover `SIMANTAP_EXAM_ENGINE_READERS=true`)
1. Jalankan simulasi backfill terlebih dahulu di staging / produksi:
   ```bash
   php artisan exam:backfill --dry-run
   php artisan exam:backfill
   ```
2. Verifikasi kesamaan isi penuh + audit nilai + kesiapan flag:
   ```bash
   php artisan exam:preflight --target=readers
   ```
3. Bila status **GO**, aktifkan `SIMANTAP_EXAM_ENGINE_READERS=true`, jalankan `php artisan config:cache`, dan pantau log 24 jam.

### 3. Opsional Skala Tinggi (300+ Peserta Serentak)
- Hanya aktifkan `SIMANTAP_CBT_BUFFER_ASYNC=true` **setelah** server memiliki Redis aktif (`CACHE_STORE=redis`) dan lolos uji beban (`docs/LOAD-TEST.md`). Perintah `exam:preflight` otomatis menolak kombinasi `SIMANTAP_CBT_BUFFER_ASYNC=true` pada cache non-Redis.

---

## Rollback Instan (Tanpa Kehilangan Data)

- **Rollback Fase 3 → Fase 2**: Set `SIMANTAP_EXAM_ENGINE_READERS=false` → `php artisan config:cache`.
- **Rollback Fase 2 → Fase 0**: Set `SIMANTAP_CBT_ENABLED=false` → `php artisan config:cache`. Data ujian yang sudah masuk tetap utuh dan tidak dihapus.
