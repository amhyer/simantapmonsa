# Arsip Dokumentasi SIMANTAP

Kumpulan dokumen audit, perencanaan, dan ringkasan yang **disusun pada tahap
pengembangan sebelumnya**. Dipindahkan dari root repositori pada 25 Oktober 2026 agar
root hanya berisi dokumen yang masih dipakai sehari-hari.

> ⚠️ **Isi folder ini adalah rekaman historis, bukan acuan.** Beberapa klaim di dalamnya
> sudah tidak berlaku — misalnya `03-REMAINING-ISSUES.md` di `docs/` sendiri sudah
> ditandai kedaluwarsa. Untuk kondisi terkini, lihat dokumen di bawah.

## Acuan yang masih berlaku

| Keperluan | Berkas |
|---|---|
| Deploy ke produksi | [`../../DEPLOYMENT.md`](../../DEPLOYMENT.md) |
| Kesiapan launching & temuan terkini | [`../../LAPORAN_KESIAPAN_LAUNCH_2026-10-25.md`](../../LAPORAN_KESIAPAN_LAUNCH_2026-10-25.md) |
| Keamanan | [`../04-SECURITY-GUIDE.md`](../04-SECURITY-GUIDE.md) |
| Arsitektur | [`../05-ARCHITECTURE.md`](../05-ARCHITECTURE.md) |
| Rencana perbaikan terverifikasi | [`../RENCANA_PERBAIKAN_VERIFIED.md`](../RENCANA_PERBAIKAN_VERIFIED.md) |
| Modul CBT | [`../CBT.md`](../CBT.md) |

## Isi arsip

| Berkas | Isi |
|---|---|
| `00_START_HERE.txt` | Ringkasan paket dokumentasi (masih menyebut path `D:\Project\simantap\`) |
| `DOCUMENTATION_INDEX.md` | Indeks navigasi paket dokumentasi |
| `DOCUMENTATION_GENERATION_REPORT.md` | Laporan pembuatan paket dokumentasi |
| `COMPLETE_APPLICATION_OVERVIEW.md` | Gambaran aplikasi & 5 peran |
| `MENU_AND_ROLES_DOCUMENTATION.md` | Struktur menu per peran |
| `MENU_AND_ROLES_SUMMARY.txt` | Versi ASCII dari dokumen di atas |
| `QUICK_REFERENCE_CARD.txt` | Kartu referensi cepat |
| `DATABASE_MODELS_AND_RELATIONSHIPS.md` | Model Eloquent & relasinya |
| `AUDIT_SIMANTAP.md` · `AUDIT_MENDALAM_SIMANTAP.md` | Audit tahap awal |
| `LAPORAN_AUDIT_BUG_2026-10-14.md` · `LAPORAN_PERBAIKAN_2026-10-14.md` | Audit & perbaikan 14 Okt 2026 |
| `DAPODIK_FIX_SUMMARY.md` | Perbaikan sinkronisasi Dapodik |
| `IMPLEMENTASI_TRYOUT_TKA.md` | Catatan implementasi Tryout/TKA |
| `IMPROVEMENT-PLAN-2026.md` | Rencana peningkatan (sebagian sudah dikerjakan) |

## Menambah dokumen baru

- Dokumen operasional & keputusan terkini → **root** atau `docs/`.
- Catatan audit/rapat yang bersifat rekaman → folder ini, lalu daftarkan di tabel di atas.
