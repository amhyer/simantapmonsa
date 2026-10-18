# RENCANA PERBAIKAN: E-Rapor & Pembelajaran Alignment

## STATUS EKSEKUSI (diperbarui per fase selesai)

- [x] **Fase 1 — Analisis & Persiapan** (2026-10-04): audit 0/0 baris (DB dev kosong), backup `storage/app/backups/baseline-pra-alignment-20261004.dump` terverifikasi restore, desain `docs/10-ALIGNMENT-DESIGN.md`. Aturan matching rencana awal terbukti salah → wajib pakai §3 desain.
- [x] **Fase 2 — Database Changes** (2026-10-04): `2026_10_04_202238_add_jadwal_pelajaran_id...` (kolom nullable + FK `SET NULL` + indeks — terverifikasi di pgsql) dan `2026_10_04_202251_populate_...` (aturan koreksi: via `semesters`, lower/trim, ambiguitas tercatat, idempoten). Terbukti via test sementara (cocok persis, cocok case-insensitive, unmatched tetap null, down() nullify tanpa hapus data). Suite 94/94 hijau.
- [x] **Fase 3 — Model & Relationship** (2026-10-04): `jadwal_pelajaran_id` masuk `$fillable` + `NilaiErapot::jadwalPelajaran()` belongsTo (null-safe) + `JadwalPelajaran::nilaiErapot()` hasMany. Terbukti via test sementara (dua arah + null). Suite 94/94 hijau.
- [x] **Fase 4 — Controller Updates** (2026-10-04): service baru `JadwalPelajaranResolver` (`resolve()` + `alignmentStats()` aturan koreksi); `NilaiErapotController@simpan` resolve 1× per request + `@autoSave` resolve per baris ke kolom FK; `DataReferensiController@alignmentCheck()` + route `admin.referensi.alignment` (render view di Fase 5). Terbukti via test sementara (resolve persis/case/no-match, simpan+autosave isi FK, stats 1/2 + kategori). Suite 94/94 hijau.
- [x] **Fase 5 — Views & Frontend** (2026-10-04): halaman `admin/referensi/alignment-check.blade.php` (4 kartu ringkasan + tabel sebab + catatan ambiguitas); kolom "Nilai" (badge terpetakan/0) di tabel pembelajaran via `withCount`; entri menu "Cek Alignment" di sidebar admin. Terbukti via test sementara (render + badge). Suite 94/94 hijau.
- [x] **Fase 6 — Testing** (2026-10-04): permanen — `tests/Unit/Models/NilaiErapotTest.php` (5 test: fillable, 2 relasi, rumus, predikat) + `tests/Feature/AlignmentTest.php` (6 test: resolve, ambiguitas, simpan/autosave FK, halaman+badge, nullOnDelete perilaku, rollback-maju migration). Suite **105/105 hijau**.
- [x] **Fase 7 — Dokumentasi & Deployment** (2026-10-04): `docs/11-ALIGNMENT-GUIDE.md` (struktur, aturan matching, auto-resolve, operasional, troubleshooting) + `docs/12-ALIGNMENT-DEPLOYMENT-CHECKLIST.md` (pre/deploy/post/rollback/monitoring). Penomoran disesuaikan (06 sudah terpakai; checklist masuk docs agar tidak mengotori root).
- [ ] **Fase 3 — Model & Relationship**
- [ ] **Fase 4 — Controller Updates**
- [ ] **Fase 5 — Views & Frontend**
- [ ] **Fase 6 — Testing**
- [ ] **Fase 7 — Dokumentasi & Deployment

> **Status:** 📋 RENCANA — belum dieksekusi. Disimpan sebagai acuan eksekusi selanjutnya.
> **Disimpan:** 2026-10-04 (dari pesan pengguna, dipindahkan verbatim ke repo).
> **Aturan main:** jangan mulai Fase 2 (migration) sebelum Fase 1 selesai dan backup terverifikasi.

> **Catatan pra-eksekusi (temuan saat penyimpanan, bukan bagian rencana asli):**
> 1. `dropForeignKeyIfExists()` **bukan method Laravel** — saat eksekusi pakai `$table->dropForeign(['jadwal_pelajaran_id'])` (cek nama FK aktual) atau `Schema::disableForeignKeyConstraints()`.
> 2. Skrip populate mencocokkan `semester_id` vs kolom `semester` — **verifikasi dulu nama kolom aktual** di `jadwal_pelajaran` dan `nilai_erapor` (lihat `docs/06-DATABASE-COLUMNS.md`) sebelum menjalankan Fase 2.2.
> 3. `onDelete('restrict')` — uji di SQLite (testing) karena perilaku FK SQLite berbeda dari Postgres/MySQL; pastikan migration tidak merusak `php artisan test`.
> 4. Rencana asli menyebut file `docs/06-ALIGNMENT-GUIDE.md`, tetapi slot `06` sudah dipakai (`06-DATABASE-COLUMNS.md`) — pakai nomor bebas berikutnya saat Fase 7.

---

## FASE 1: ANALISIS & PERSIAPAN (3-5 hari)

### 1.1 Audit Data Integrity

- Query orphaned nilai_erapor records (tanpa matching jadwal_pelajaran)
- Identifikasi inconsistencies antara mata_pelajaran text vs referensi
- Cek nilai_erapor dengan guru_id tidak ada di jadwal_pelajaran untuk kelas/semester yang sama
- Backup database sebelum migration

### 1.2 Dokumentasi Perubahan

- Buat design document untuk foreign key relationship
- Document backward compatibility requirements
- Plan rollback strategy

## FASE 2: DATABASE CHANGES (1 minggu)

### 2.1 Tambah Foreign Key ke nilai_erapor

Migration file: `add_jadwal_pelajaran_id_to_nilai_erapor_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nilai_erapor', function (Blueprint $table) {
            // Tambah kolom baru
            $table->foreignId('jadwal_pelajaran_id')
                  ->nullable()
                  ->after('guru_id')
                  ->constrained('jadwal_pelajaran')
                  ->onDelete('restrict');  // Prevent delete jika masih ada nilai

            // Index untuk query cepat
            $table->index(['jadwal_pelajaran_id', 'semester', 'tahun_ajaran']);
        });
    }

    public function down(): void
    {
        Schema::table('nilai_erapor', function (Blueprint $table) {
            $table->dropForeignKeyIfExists(['jadwal_pelajaran_id']);
            $table->dropColumn('jadwal_pelajaran_id');
        });
    }
};
```

Langkah eksekusi:

```bash
php artisan make:migration add_jadwal_pelajaran_id_to_nilai_erapor_table
# Copy file di atas ke migration yang dibuat
php artisan migrate
```

### 2.2 Populating Data (Migration Helper)

File: `database/migrations/2026_10_04_populate_jadwal_pelajaran_ids.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\NilaiErapot;
use App\Models\JadwalPelajaran;

return new class extends Migration
{
    public function up(): void
    {
        // Match existing nilai_erapor dengan jadwal_pelajaran
        $nilaiWithoutJadwal = NilaiErapot::whereNull('jadwal_pelajaran_id')->get();

        $matched = 0;
        $unmatched = 0;

        foreach ($nilaiWithoutJadwal as $nilai) {
            $jadwal = JadwalPelajaran::where('guru_id', $nilai->guru_id)
                ->where('mata_pelajaran', $nilai->mata_pelajaran)
                ->where('kelas', $nilai->kelas)
                ->where('semester_id', $nilai->semester)  // Adjust field name
                ->first();

            if ($jadwal) {
                $nilai->update(['jadwal_pelajaran_id' => $jadwal->id]);
                $matched++;
            } else {
                $unmatched++;
                \Log::warning("Unmatched nilai_erapor", [
                    'nilai_id' => $nilai->id,
                    'guru_id' => $nilai->guru_id,
                    'mata_pelajaran' => $nilai->mata_pelajaran,
                    'kelas' => $nilai->kelas,
                ]);
            }
        }

        \Log::info("Population complete: {$matched} matched, {$unmatched} unmatched");
    }

    public function down(): void
    {
        // Reset to null untuk rollback
        \DB::table('nilai_erapor')->update(['jadwal_pelajaran_id' => null]);
    }
};
```

## FASE 3: MODEL & RELATIONSHIP (2-3 hari)

### 3.1 Update NilaiErapot Model

`app/Models/NilaiErapot.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
```

### 3.2 Update JadwalPelajaran Model

`app/Models/JadwalPelajaran.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
```

## FASE 4: CONTROLLER UPDATES (3-4 hari)

### 4.1 Update DataReferensiController

`app/Http/Controllers/Admin/DataReferensiController.php`

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ekstrakurikuler;
```

### 4.2 Update Guru NilaiErapotController

`app/Http/Controllers/Guru/NilaiErapotController.php`

```php
<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\JadwalPelajaran;
```

## FASE 5: VIEWS & FRONTEND (4-5 hari)

### 5.1 Create Alignment Check View

`resources/views/admin/referensi/alignment-check.blade.php`

```blade
@extends('layouts.app')

@section('content')
<div class="container">
    <div class="card">
        <div class="card-header bg-primary text-white">
```

### 5.2 Update Pembelajaran View

`resources/views/admin/referensi/pembelajaran.blade.php`

```blade
@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between mb-3">
        <h2>Referensi Pembelajaran</h2>
```

## FASE 6: TESTING (3-5 hari)

### 6.1 Unit Tests

`tests/Unit/Models/NilaiErapotTest.php`

```php
<?php

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use App\Models\NilaiErapot;
```

### 6.2 Feature Tests

`tests/Feature/AlignmentTest.php`

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
```

## FASE 7: DOKUMENTASI & DEPLOYMENT (3 hari)

### 7.1 Update Documentation

`docs/06-ALIGNMENT-GUIDE.md` (sesuaikan nomor — slot 06 sudah terpakai)

```md
# E-Rapor & Pembelajaran Alignment Guide

## Struktur Data

### Foreign Key Relationship
- `nilai_erapor.jadwal_pelajaran_id` → `jadwal_pelajaran.id`
```

### 7.2 Create Migration Checklist

`DEPLOYMENT_CHECKLIST.md`

```md
# Deployment Checklist: E-Rapor & Pembelajaran Alignment

## Pre-Deployment
- [ ] Backup database production
- [ ] Test migrations di staging environment
- [ ] Run all tests (unit + feature)
- [ ] Verify jadwal_pelajaran_id column exists
- [ ] Run data population:
```

```bash
php artisan migrate --step=1  # untuk migration populate
```

```bash
# Run validation check:
php artisan db:seed --class=AlignmentValidationSeeder
```

```bash
php artisan up # (service online)
```

```md
## Post-Deployment
- [ ] Check admin log untuk errors
- [ ] Test e-Rapor input di staging
- [ ] Verify alignment check page loads
- [ ] Monitor application logs

## Rollback Plan (if needed)
```

```bash
php artisan down
php artisan migrate:rollback --step=2
php artisan up
```

```md
## Monitoring
- Watch error logs: tail -f storage/logs/laravel.log
- Check database query performance
- Monitor sistem response time
```

---

## RINGKASAN TIMELINE & EFFORT

| Fase | Durasi | Effort | Lead |
|------|--------|--------|------|
| 1. Analisis | 3-5 hari | 5 SP | DBA + Dev |
| 2. Database | 5-7 hari | 8 SP | DBA + Dev |
| 3. Model | 2-3 hari | 5 SP | Backend Dev |
| 4. Controller | 3-4 hari | 8 SP | Backend Dev |
| 5. Views | 4-5 hari | 5 SP | Frontend Dev |
| 6. Testing | 3-5 hari | 10 SP | QA + Dev |
| 7. Docs & Deploy | 3 hari | 5 SP | Tech Lead |
| **TOTAL** | **3-4 minggu** | **46 SP** | - |

---

## PRIORITAS PERBAIKAN

🔴 **CRITICAL** (Segera)
- Foreign key implementation
- Data population & validation
- Delete protection on jadwal

🟠 **HIGH** (Minggu 1-2)
- Model & Controller updates
- Testing suite
- Documentation

🟡 **MEDIUM** (Minggu 2-3)
- View improvements
- Auto-fix features
- Performance optimization

🟢 **LOW** (Future)
- Advanced audit logging
- Analytics dashboards
- Batch operations
