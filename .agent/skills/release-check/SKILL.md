---
name: release-check
description: Checklist kesiapan rilis produksi SIMANTAP MONSA. Gunakan sebelum deploy atau saat user bertanya "sudah siap online belum".
---

# Release Check

Periksa dan laporkan LULUS / GAGAL / PERLU KONFIRMASI untuk tiap butir:
1. composer test lulus (DB testing saja).
2. npm run build berhasil tanpa error.
3. Pint lulus untuk file yang diubah.
4. composer audit dan npm audit: tidak ada kerentanan high/critical.
5. .env.example lengkap; APP_DEBUG=false dan APP_ENV=production tercatat di panduan deploy.
6. php artisan route:cache, config:cache, view:cache, event:cache berhasil (tidak ada closure/env() bermasalah).
7. Tidak ada file besar (>5 MB) di public/ atau tracked git.
8. Tidak ada placeholder/dummy/lorem ipsum yang tampil ke pengguna.
9. Header keamanan aktif (CSP minimal mode report-only).
10. Fitur terkunci flag (CBT, multi-tenant) benar-benar tertutup saat flag false.
11. Storage link, queue worker, scheduler (cron), dan backup terdokumentasi.
Hasil ke reports/RELEASE-CHECK.md.
