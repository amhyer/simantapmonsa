---
description: Verifikasi runtime - tes, build, lint (aman, tanpa ubah data)
---
1. Pastikan berada di branch non-main (git branch --show-current).
2. Jalankan `composer test` (hanya DB testing). Catat hasil.
3. Jalankan `npm run build`. Catat hasil dan ukuran aset.
4. Jalankan `./vendor/bin/pint --test`. Catat file yang melanggar.
5. Tulis ringkasan di reports/VERIFY.md. Jangan perbaiki apa pun dulu.
