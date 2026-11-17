# Arsip Dokumentasi SIMANTAP

Folder ini sebelumnya menampung dokumen audit, ringkasan, dan perencanaan tahap awal
pengembangan (September–Oktober 2026) yang dipindahkan dari root repositori pada
25 Oktober 2026.

Pada **17 November 2026**, dokumen historis yang sudah kedaluwarsa (`AUDIT_*.md`,
`LAPORAN_*_2026-10-14.md`, `COMPLETE_APPLICATION_OVERVIEW.md`, `MENU_AND_ROLES_*`,
`DATABASE_MODELS_AND_RELATIONSHIPS.md`, `00_START_HERE.txt`, `QUICK_REFERENCE_CARD.txt`,
`IMPROVEMENT-PLAN-2026.md`, dan turunannya) telah dibersihkan dari working tree aktif
agar tidak menimbulkan angka atau klaim yang bertentangan dengan kode terkini. Seluruh
rekaman lama tersebut tetap utuh di riwayat Git (`git log -- docs/arsip/`).

## Acuan dokumentasi aktif

| Keperluan | Berkas |
|---|---|
| Indeks dokumentasi aktif | [`../README.md`](../README.md) |
| Deploy ke produksi | [`../../DEPLOYMENT.md`](../../DEPLOYMENT.md) |
| Kesiapan launching & hardening | [`../../LAPORAN_KESIAPAN_LAUNCH_2026-10-25.md`](../../LAPORAN_KESIAPAN_LAUNCH_2026-10-25.md) |
| Keamanan | [`../04-SECURITY-GUIDE.md`](../04-SECURITY-GUIDE.md) |
| Arsitektur & peta proyek | [`../05-ARCHITECTURE.md`](../05-ARCHITECTURE.md) · [`../13-PETA-PROYEK.md`](../13-PETA-PROYEK.md) |
| Rencana induk & peta tugas | [`../RENCANA-INDUK-PERBAIKAN-DAN-PENGEMBANGAN.md`](../RENCANA-INDUK-PERBAIKAN-DAN-PENGEMBANGAN.md) · [`../PETA-TUGAS-AI-GEMINI.md`](../PETA-TUGAS-AI-GEMINI.md) |
| Rencana perbaikan terverifikasi | [`../RENCANA_PERBAIKAN_VERIFIED.md`](../RENCANA_PERBAIKAN_VERIFIED.md) |
| Modul CBT | [`../CBT.md`](../CBT.md) |

## Aturan dokumentasi

- Dokumen operasional & arsitektur aktif → **root** (sesuai whitelist verifier) atau `docs/`.
- Jangan menyimpan berkas audit duplikat atau scratchpad investigasi sementara di working tree; perbarui dokumen acuan yang relevan di `docs/`.
