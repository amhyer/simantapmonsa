# Dokumentasi Aktif SIMANTAP

Indeks resmi seluruh dokumen aktif pada repositori [SIMANTAP (`amhyer/simantapmonsa`)](https://github.com/amhyer/simantapmonsa/blob/main/README.md).
Dokumen historis, scratchpad investigasi agen, dan laporan audit Sept–Okt 2026 yang sudah kedaluwarsa telah dibersihkan dari tree kerja dan tetap tersedia melalui riwayat Git ([`arsip/README.md`](arsip/README.md)).

---

## 1. Dokumen Root & Operasional Utama

| Topik | Berkas |
|---|---|
| Gambaran proyek & instalasi | [`../README.md`](../README.md) ([GitHub](https://github.com/amhyer/simantapmonsa/blob/main/README.md)) |
| Panduan deployment produksi | [`../DEPLOYMENT.md`](../DEPLOYMENT.md) ([GitHub](https://github.com/amhyer/simantapmonsa/blob/main/DEPLOYMENT.md)) |
| Kesiapan launching & riwayat hardening | [`../LAPORAN_KESIAPAN_LAUNCH_2026-10-25.md`](../LAPORAN_KESIAPAN_LAUNCH_2026-10-25.md) |
| Koordinasi multi-agen & batas kepemilikan | [`../AGENTS.md`](../AGENTS.md) · [`../CLAUDE.md`](../CLAUDE.md) |
| Runbook go-live & rollback | [`RUNBOOK-GO-LIVE.md`](RUNBOOK-GO-LIVE.md) |
| Checklist staging & integrasi | [`CHECKLIST-STAGING-INTEGRASI.md`](CHECKLIST-STAGING-INTEGRASI.md) |
| Drill restore backup | [`DRILL-RESTORE-BACKUP.md`](DRILL-RESTORE-BACKUP.md) |
| Uji beban (k6) | [`LOAD-TEST.md`](LOAD-TEST.md) |

---

## 2. Arsitektur, Basis Data & Keamanan

| Topik | Berkas |
|---|---|
| Arsitektur sistem, route & middleware | [`05-ARCHITECTURE.md`](05-ARCHITECTURE.md) |
| Peta proyek menyeluruh | [`13-PETA-PROYEK.md`](13-PETA-PROYEK.md) |
| Inventaris antarmuka (UI) lintas peran | [`13-INVENTARIS-UI.md`](13-INVENTARIS-UI.md) |
| Referensi kolom database kritis | [`06-DATABASE-COLUMNS.md`](06-DATABASE-COLUMNS.md) |
| Panduan keamanan & production checklist | [`04-SECURITY-GUIDE.md`](04-SECURITY-GUIDE.md) |
| Rotasi kredensial & API key | [`ROTASI-KREDENSIAL.md`](ROTASI-KREDENSIAL.md) |
| Reset sandi mandiri | [`RESET-SANDI-MANDIRI.md`](RESET-SANDI-MANDIRI.md) |
| Audit nilai e-Rapor (hash chain) | [`AUDIT-NILAI.md`](AUDIT-NILAI.md) |
| Telemetri pemakaian menu | [`MENU-TELEMETRY.md`](MENU-TELEMETRY.md) |

---

## 3. Rencana Kerja, E-Learning, Perangkat Ajar & Navigasi

| Topik | Berkas |
|---|---|
| Rencana induk perbaikan & pengembangan | [`RENCANA-INDUK-PERBAIKAN-DAN-PENGEMBANGAN.md`](RENCANA-INDUK-PERBAIKAN-DAN-PENGEMBANGAN.md) |
| Peta tugas & gelombang kerja multi-agen | [`PETA-TUGAS-AI-GEMINI.md`](PETA-TUGAS-AI-GEMINI.md) |
| Rencana perbaikan terverifikasi | [`RENCANA_PERBAIKAN_VERIFIED.md`](RENCANA_PERBAIKAN_VERIFIED.md) |
| Desain E-Learning / Kelas Digital & wali kelas | [`DESAIN-ELEARNING-DAN-WALI-KELAS.md`](DESAIN-ELEARNING-DAN-WALI-KELAS.md) |
| Desain Perangkat Ajar & penataan menu | [`DESAIN-PERANGKAT-AJAR-DAN-MENU.md`](DESAIN-PERANGKAT-AJAR-DAN-MENU.md) |
| Prompt acuan E-Learning, perangkat & menu | [`PROMPT-ELEARNING-PERANGKAT-MENU.md`](PROMPT-ELEARNING-PERANGKAT-MENU.md) |
| Matriks & audit menu lintas 7 peran | [`AUDIT-MENU-MATRIKS-2026-11-08.md`](AUDIT-MENU-MATRIKS-2026-11-08.md) · [`AUDIT-MENU-PER-ROLE-2026-11-08.md`](AUDIT-MENU-PER-ROLE-2026-11-08.md) |
| Panduan lengkap seluruh peran & hak akses aktif | [`PANDUAN-PERAN-DAN-HAK-AKSES.md`](PANDUAN-PERAN-DAN-HAK-AKSES.md) |

---

## 4. Modul Ujian (CBT, Tryout, TKA), e-Rapor, Dapodik & Presensi

| Topik | Berkas |
|---|---|
| Mesin ujian terpadu | [`14-MESIN-UJIAN.md`](14-MESIN-UJIAN.md) |
| Desain & panduan CBT / Tryout / TKA | [`CBT.md`](CBT.md) · [`CBT_TRYOUT_TKA_DESIGN.md`](CBT_TRYOUT_TKA_DESIGN.md) · [`CBT-VERIFIKASI-2026-10-18.md`](CBT-VERIFIKASI-2026-10-18.md) |
| Analisis butir & antrean offline ujian | [`ANALISIS-BUTIR.md`](ANALISIS-BUTIR.md) · [`ANTREAN-OFFLINE-UJIAN.md`](ANTREAN-OFFLINE-UJIAN.md) |
| Rekonsiliasi CBT/Tryout & checklist E2E | [`REKONSILIASI-FASE-3.md`](REKONSILIASI-FASE-3.md) · [`CHECKLIST_E2E_CBT_TRYOUT_TKA.md`](CHECKLIST_E2E_CBT_TRYOUT_TKA.md) |
| Pemantauan Kesiapan TKA | [`TKA-KESIAPAN.md`](TKA-KESIAPAN.md) |
| Penyelarasan e-Rapor Kemdikbud (area Kiro) | [`09-ERAPOR-ALIGNMENT-PLAN.md`](09-ERAPOR-ALIGNMENT-PLAN.md) · [`10-ALIGNMENT-DESIGN.md`](10-ALIGNMENT-DESIGN.md) · [`11-ALIGNMENT-GUIDE.md`](11-ALIGNMENT-GUIDE.md) · [`12-ALIGNMENT-DEPLOYMENT-CHECKLIST.md`](12-ALIGNMENT-DEPLOYMENT-CHECKLIST.md) |
| Isolasi Dapodik & bridge desktop | [`PERBAIKAN-ISOLASI-DAPODIK-2026-11-08.md`](PERBAIKAN-ISOLASI-DAPODIK-2026-11-08.md) · [`../dapodik-bridge/README.md`](../dapodik-bridge/README.md) · [`../dapodik-bridge/QUICK_START.md`](../dapodik-bridge/QUICK_START.md) · [`../dapodik-bridge/OPSI_B_AUTO_PULL.md`](../dapodik-bridge/OPSI_B_AUTO_PULL.md) |
| Presensi, izin orang tua & bridge mesin | [`PRESENSI-PENGERASAN-DAN-IZIN-2026-11-08.md`](PRESENSI-PENGERASAN-DAN-IZIN-2026-11-08.md) · [`PRESENSI-BRIDGE-HOSTING.md`](PRESENSI-BRIDGE-HOSTING.md) · [`PRESENSI-BRIDGE-TCP.md`](PRESENSI-BRIDGE-TCP.md) · [`../presensi-bridge/README.md`](../presensi-bridge/README.md) |
| Panduan uji route & E2E Playwright | [`PANDUAN_UJI_ROUTE_PLAYWRIGHT.md`](PANDUAN_UJI_ROUTE_PLAYWRIGHT.md) |

---

## 5. Operasional, Backlog & Build Bridge

| Topik | Berkas |
|---|---|
| Panduan deploy produksi (alternatif DEPLOYMENT.md) | [`DEPLOY.md`](DEPLOY.md) |
| Panduan operasional produksi (cache, reset massal, login) | [`OPERASIONAL.md`](OPERASIONAL.md) |
| Backlog pengembangan & tugas lanjutan | [`BACKLOG.md`](BACKLOG.md) |
| Cutover foto siswa ke penyimpanan private | [`CUTOVER-FOTO.md`](CUTOVER-FOTO.md) |
| Build ulang SIMANTAP-Bridge.exe | [`BRIDGE-BUILD.md`](BRIDGE-BUILD.md) |
| Rencana pemecahan god file & rute | [`REFACTOR-RENCANA.md`](REFACTOR-RENCANA.md) |

> Catatan: [`../DEPLOYMENT.md`](../DEPLOYMENT.md) adalah dokumen deploy kanonis.
> [`DEPLOY.md`](DEPLOY.md) adalah panduan alternatif dengan isi yang tumpang tindih —
> bila keduanya berbeda, DEPLOYMENT.md yang menang.
