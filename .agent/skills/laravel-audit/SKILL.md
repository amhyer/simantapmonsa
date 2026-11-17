---
name: laravel-audit
description: Audit aplikasi Laravel SIMANTAP MONSA untuk performa, keamanan, dan kualitas kode. Gunakan saat user meminta audit, review, atau pengecekan kesehatan proyek.
---

# Laravel Audit

## Langkah (mode baca; jangan ubah kode)
1. Keamanan: cari env() di luar config/, route tanpa middleware auth/role, query DB::raw/whereRaw dengan input user, upload tanpa validasi mime/ukuran, mass assignment ($guarded = []), {!! !!} di Blade dengan data user, debug aktif, CORS terlalu terbuka, status SECURITY_CSP.
2. Performa: query di dalam loop (N+1), endpoint list tanpa paginate, ->get()/all() pada tabel besar, tidak ada cache pada query berat, kolom yang sering difilter tanpa index (cek migrasi), closure di routes/, asset besar di public/.
3. Kualitas: controller >500 baris, method >80 baris, kode duplikat, dependency tidak terpakai (composer.json/package.json), cakupan tes.
4. Multi-tenant: model yang berisi data sekolah tapi tidak memakai scope tenant.
5. Tulis reports/AUDIT.md: tabel Temuan | File:baris | Risiko (Tinggi/Sedang/Rendah) | Perbaikan | Usaha (kecil/sedang/besar).
6. Berhenti dan minta persetujuan sebelum menerapkan perbaikan apa pun.
