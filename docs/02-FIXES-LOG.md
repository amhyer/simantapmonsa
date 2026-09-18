# SIMANTAP — Log Perbaikan

**Tanggal:** 13 September 2026
**Total Fixes:** 22 issues (9 CRITICAL, 9 HIGH, 4 MEDIUM)

---

## Fix #1: Demo Route Exposed Password (S1 — CRITICAL)
- **File:** `app/Http/Controllers/LandingController.php:46`
- **Before:** Demo route publicly shows `admin/admin123`
- **After:** Only shows in non-production environments
- **Code:**
```php
public function demo()
{
    if (app()->environment('production')) {
        return redirect()->route('login');
    }
    return redirect()->route('login')->with('info', '...');
}
```

## Fix #2: Backup Export Leaks Password Hashes (S3 — CRITICAL)
- **File:** `app/Http/Controllers/Admin/BackupController.php:39`
- **Before:** `User::all()` includes `kata_sandi`
- **After:** Excludes `kata_sandi` and `remember_token`
- **Code:**
```php
$usersData = User::all()->map(function ($user) {
    return collect($user->toArray())->except(['kata_sandi', 'remember_token'])->toArray();
});
```

## Fix #3: Backup Import Allows Password Injection (S4 — CRITICAL)
- **File:** `app/Http/Controllers/Admin/BackupController.php:82`
- **Before:** `'kata_sandi'` in users fillable
- **After:** Removed from fillable array

## Fix #4: Siswa Dashboard Wrong Attributes (V1-V3 — CRITICAL)
- **File:** `resources/views/siswa/dashboard.blade.php:56-58`
- **Before:** `$siswa->no_hp`, `$siswa->nama_ortu`, `$siswa->no_hp_ortu`
- **After:** `$siswa->telepon`, `$siswa->nama_orang_tua`, removed line

## Fix #5: Kepsek Aktivitas Variable Mismatch (V4 — CRITICAL)
- **File:** `app/Http/Controllers/Kepsek/AktivitasController.php`
- **Before:** Passed 3 variables, view expected 12+
- **After:** Rewrote controller to pass all expected variables including chart data

## Fix #6: Ortu Dashboard Wrong Attributes (V5 — CRITICAL)
- **File:** `resources/views/ortu/dashboard.blade.php:39,52`
- **Before:** `$anak->no_hp_ortu`, `$anak->no_hp`
- **After:** `$anak->telepon`

## Fix #7: Nilai Model Missing kuis_id (D3 — CRITICAL)
- **File:** `app/Models/Nilai.php:10`
- **Before:** `$fillable` missing `kuis_id`
- **After:** Added `'kuis_id'` to fillable array

## Fix #8: Password Policy Too Weak (S6 — HIGH)
- **Files:** `UserController.php:33,78`, `LandingController.php:27`
- **Before:** `min:4` or `min:6`
- **After:** All changed to `min:8`

## Fix #9: No Rate Limiting on Registration (S7 — HIGH)
- **File:** `routes/web.php:54`
- **Before:** `Route::post('/register', ...)`
- **After:** `Route::post('/register', ...)->middleware('throttle:5,1')`

## Fix #10: Resource Routes 405 Errors (C1 — HIGH)
- **File:** `routes/web.php:200,206,209`
- **Before:** `->except(['show'])` on dimensi, kehadiran, catatan
- **After:** `->only(['index', 'store'])` / `->only(['index', 'store', 'destroy'])`

## Fix #11: InputNilaiController Non-Existent Columns (C2 — HIGH)
- **File:** `app/Http/Controllers/Guru/InputNilaiController.php:84`
- **Before:** Updates `kelas`, `semester`, `tahun_ajaran` (don't exist on `nilai` table)
- **After:** Only updates `nilai` and `mata_pelajaran`

## Fix #12: IntegrasiController Null Pointer (C3 — HIGH)
- **File:** `app/Http/Controllers/Guru/IntegrasiController.php:14`
- **Before:** `$pengaturan->sistem ?? []`
- **After:** `optional($pengaturan)->sistem ?? []`

## Fix #13: NilaiErapotController Null Pointer (C4 — HIGH)
- **File:** `app/Http/Controllers/Guru/NilaiErapotController.php:18`
- **Before:** `$pengaturan->kelas ?? 'V A'`
- **After:** `optional($pengaturan)->kelas ?? 'V A'`

## Fix #14: Siswa Dashboard Kehadiran Logic (V6 — HIGH)
- **File:** `app/Http/Controllers/DashboardController.php:64`
- **Before:** `$kehadiran = Kehadiran::where(...)->count()` (raw count)
- **After:** Calculates actual percentage: `round($totalHadir / $totalKehadiran * 100, 1)`

## Fix #15: Raw header()+exit (M1 — MEDIUM)
- **Files:** `ImportErapotController.php:52,173`, `ErapotGeneratorController.php:138`
- **Before:** `header(...); header(...); $writer->save('php://output'); exit;`
- **After:** `return response()->stream(function() use ($writer) { $writer->save('php://output'); }, 200, [...]);`

## Fix #16: KehadiranController UUID Regeneration (M6 — MEDIUM)
- **File:** `app/Http/Controllers/Guru/KehadiranController.php:31`
- **Before:** `updateOrCreate` with new UUID every time
- **After:** Split into separate create/update; UUID only set on create

## Fix #17: Backup Import Null ID (M5 — MEDIUM)
- **File:** `app/Http/Controllers/Admin/BackupController.php:130`
- **Before:** `updateOrCreate(['id' => $filtered['id'] ?? null], $filtered)`
- **After:** Check if ID exists: create if null, update if present

## Fix #18: Seeder Old Column Names (M7 — MEDIUM)
- **File:** `database/seeders/ApiKeySeeder.php`
- **Before:** `name`, `email`, `password` (old schema)
- **After:** `nama_lengkap`, `nama_pengguna`, `kata_sandi` (current schema)

## Fix #19: Hardcoded API Key in Seeder (M8 — MEDIUM)
- **File:** `database/seeders/ApiKeySeeder.php:31`
- **Before:** Hardcoded `'11jtIdsSzunH3UDSAoNyw...'`
- **After:** `Str::random(64)`

## Fix #20: Admin Dashboard Semester Query Crash
- **File:** `app/Http\Controllers/DashboardController.php:30`
- **Before:** `Semester::where('aktif', true)` — column doesn't exist
- **After:** `Semester::orderByDesc('semester_id')->first()`

## Fix #21: Admin Dashboard Complete Redesign
- **File:** `app/Http/controllers/DashboardController.php` + `resources/views/admin/dashboard.blade.php`
- **Before:** 4 stat cards + empty welcome message
- **After:** 5 sections: Stats, Info Sekolah, Dapodik Sync, Konten Akademik, Aksi Cepat

## Fix #22: Various View Fixes (Earlier Session)
- `kepsek/pantau`: Added `total_siswa` to controller
- `ortu/rekap`: Fixed `translatedFormat()` on model → Carbon parse
- `admin/peta-kelas`: Fixed `this` in arrow functions
- `admin/dapodik`: Fixed `hasZero` → `allZero` variable name
- `guru/erapor`: Moved script inside `@push('scripts')`
- `layouts/app`: Added `tag-secondary`, `tag-info` CSS classes
- `kepsek/dashboard`: Fixed `info`/`warn` → `gold`/`primary` stat-card classes
- `admin/modul`: Fixed null deref on `SekolahSettings`
