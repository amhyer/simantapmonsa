# Deployment Checklist: E-Rapor & Pembelajaran Alignment

> Rencana: `docs/09-ERAPOR-ALIGNMENT-PLAN.md` · Panduan: `docs/11-ALIGNMENT-GUIDE.md`.

## Pre-Deployment

- [ ] Backup database production (custom format + verifikasi baca TOC):
  ```powershell
  $env:PGPASSWORD='<password>'
  & pg_dump -h 127.0.0.1 -p 5432 -U postgres -F c `
    -f storage/app/backups/pra-alignment-<yyyyMMdd>.dump simantap_db
  & pg_restore --list storage/app/backups/pra-alignment-<yyyyMMdd>.dump
  ```
- [ ] Ulangi audit Fase 1 di database target (skrip §1.1 / halaman alignment-check staging): catat total, orphan estimate, kategori.
- [ ] Test migrations di staging terlebih dahulu.
- [ ] Run all tests: `php artisan test` (target: hijau; saat ini 105/105).
- [ ] Pastikan `semesters` terisi (nama + tahun ajaran benar) — populate bergantung padanya.

## Deploy (berurutan)

- [ ] `php artisan down` (bila deploy jam sibuk; opsional bila migrasi cepat).
- [ ] `php artisan migrate --force` — pastikan kedua migration alignment DONE:
  - `2026_10_04_202238_add_jadwal_pelajaran_id_to_nilai_erapor_table`
  - `2026_10_04_202251_populate_jadwal_pelajaran_id_on_nilai_erapor`
- [ ] Verifikasi kolom + FK + indeks:
  ```sql
  SELECT column_name, is_nullable FROM information_schema.columns
   WHERE table_name='nilai_erapor' AND column_name='jadwal_pelajaran_id';
  SELECT pg_get_constraintdef(oid) FROM pg_constraint
   WHERE conrelid='nilai_erapor'::regclass AND conname LIKE '%jadwal%';
  -- Harus: bigint YES + REFERENCES jadwal_pelajaran(id) ON DELETE SET NULL
  ```
- [ ] Cek log populate: `storage/logs/laravel.log` → `Populate jadwal_pelajaran_id selesai: N matched, {...}`.
- [ ] Validasi via halaman **Cek Alignment** (admin): catat persen + kategori unmatched.
- [ ] Uji simpan nilai e-Rapor oleh 1 guru → FK terisi (cek 1 baris).
- [ ] `php artisan up` (bila di-down-kan).

## Post-Deployment

- [ ] Pantau `storage/logs/laravel.log` untuk error (30 menit pertama).
- [ ] Uji input nilai e-Rapor + autosave di production (1 guru sampel).
- [ ] Buka halaman alignment-check: pastikan termuat < 3 detik.
- [ ] Tindak lanjuti kategori unmatched terbesar (perbaiki master mapel/kelas/semester).

## Rollback Plan (bila dibutuhkan)

| Level | Perintah |
|---|---|
| Batalkan populate saja | `php artisan migrate:rollback --step=1` **hanya bila** migration populate adalah batch terakhir; jika bukan, jalankan `down()`-nya manual via tinker atau update null — JANGAN rollback buta |
| Batalkan kolom FK | `php artisan migrate:rollback --step=2` (sesuaikan step aktual; cek `php artisan migrate:status`) |
| Darurat / korupsi data | `pg_restore -h ... -U postgres -d simantap_db <file-backup-pre>.dump` (drop-recreate DB bila perlu) |

> Selalu cek `php artisan migrate:status` sebelum rollback agar step tepat.

## Monitoring

- Error log: `Get-Content storage/logs/laravel.log -Tail 50 -Wait` (atau `tail -f` di Linux).
- Query lambat: halaman alignment memakai chunk 500 — waspadai bila `nilai_erapor` > 100rb baris (pertimbangkan job antrean).
- Response time halaman `admin/referensi/alignment` dan `admin/referensi/pembelajaran` (tambahan `withCount`).
