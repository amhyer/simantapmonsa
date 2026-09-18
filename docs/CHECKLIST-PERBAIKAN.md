# 📋 CHECKLIST PERBAIKAN SIMANTAP - PER FASE & PER INDIKATOR

Berdasarkan hasil audit lengkap, berikut checklist perbaikan yang terarah, terurut, dan bebas error (setiap langkah diverifikasi).

**Tanggal Audit:** 10 September 2026
**Skor Saat Ini:** 58%
**Target:** 80% dalam 1 bulan

---

## 🎯 PRINSIP PENGERJAAN

```
1. Setiap fase HARUS selesai 100% sebelum lanjut ke fase berikutnya
2. Setiap item HARUS diverifikasi dengan test command
3. Jangan lanjut jika ada error di fase sebelumnya
4. Commit setiap selesai 1 fase (jika pakai Git)
5. Backup database sebelum fase yang mengubah schema
```

---

## 🔴 FASE 0: PERSIAPAN & BACKUP (30 menit)

**Tujuan:** Amankan project sebelum perubahan besar

### Indikator 0.1: Backup Database

```powershell
# Windows PowerShell
cd D:\Project\simantap

# Backup PostgreSQL
pg_dump -U postgres -d simantap_db -f backup_pre_perbaikan.sql

# Cek file terbuat
dir backup_pre_perbaikan.sql
```

- [ ] ✅ Verifikasi: File backup_pre_perbaikan.sql ada, ukuran > 0 KB
- [ ] ❌ Jika gagal: Install PostgreSQL client tools, cek password DB

### Indikator 0.2: Backup Project Files

```powershell
# Copy seluruh folder project (kecuali node_modules, vendor)
robocopy D:\Project\simantap D:\Backup\simantap-2026-09-10 /E /XD node_modules vendor .git
```

- [ ] ✅ Verifikasi: Folder D:\Backup\simantap-2026-09-10 ada
- [ ] ❌ Jika gagal: Cek permission folder

### Indikator 0.3: Catat Status Awal

```powershell
# Catat versi
php -v
node -v
composer --version

# Catat migration status
php artisan migrate:status > status_migration_awal.txt

# Catat route list
php artisan route:list > status_route_awal.txt
```

- [ ] ✅ Verifikasi: 3 file .txt terbuat
- [ ] ❌ Jika gagal: Cek php artisan bisa jalan

---

## 🔴 FASE 1: SECURITY CRITICAL (1-2 jam)

**Tujuan:** Tutup 9 vulnerability kritis SEGERA
**⚠️ JANGAN LANJUT ke Fase 2 sebelum Fase 1 selesai 100%**

### Indikator 1.1: Hapus Role Admin dari Registration

```php
// app/Http/Controllers/LandingController.php
// CARI: validasi register
// GANTI: 'peran' => 'required|in:guru,siswa,ortu,kepsek,admin'
// MENJADI:
'peran' => 'required|in:guru,siswa,ortu,kepsek',
```

- [ ] ✅ Buka /register di browser, coba pilih "Admin" → tidak boleh ada opsi admin
- [ ] ❌ Jika gagal: Cek tidak ada admin di resources/views/auth/register.blade.php

### Indikator 1.2: Rate Limiting Login

```php
// routes/web.php
// TAMBAHKAN middleware throttle
Route::post('/login', [LoginController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('login.post');
```

- [ ] ✅ Coba login salah 6x → percobaan ke-6 harus dapat "Too Many Attempts"
- [ ] ❌ Jika gagal: Jalankan php artisan route:clear

### Indikator 1.3: Rate Limiting API

```php
// routes/api.php
Route::middleware(['apikey:dapodik:import', 'throttle:60,1'])
    ->prefix('dapodik')
    ->group(function () {
        Route::post('/ping', [DapodikController::class, 'ping']);
        Route::post('/import-siswa', [DapodikController::class, 'importSiswa']);
        Route::get('/status', [DapodikController::class, 'status']);
    });
```

- [ ] ✅ `php artisan route:list | findstr "api/dapodik"` → harus muncul throttle di middleware
- [ ] ❌ Jika gagal: Cek bootstrap/app.php — pastikan api middleware group ada

### Indikator 1.4: Password Minimum 8 Karakter

```php
// app/Http/Controllers/Admin/UserController.php
// CARI: validasi store() dan update()
// GANTI:
'kata_sandi' => 'required|string|min:4',
// MENJADI:
'kata_sandi' => 'required|string|min:8',
```

- [ ] ✅ Buka /admin/users/create, isi password "123" → harus error "minimal 8 karakter"
- [ ] ❌ Jika gagal: Cek app/Http/Controllers/Auth/RegisteredUserController.php (jika ada)

### Indikator 1.5: Hapus Default Password 123456

```php
// app/Http/Controllers/Admin/UserController.php
// CARI:
'kata_sandi' => Hash::make($validated['kata_sandi'] ?? '123456'),
// GANTI:
if (empty($validated['kata_sandi'])) {
    return back()->withErrors(['kata_sandi' => 'Kata sandi wajib diisi.']);
}
'kata_sandi' => Hash::make($validated['kata_sandi']),
```

- [ ] ✅ Buat user baru tanpa password → harus error
- [ ] ❌ Jika gagal: Cek tidak ada ?? '123456' di code

### Indikator 1.6: Password Akun Massal Bukan NIS

```php
// app/Http/Controllers/Admin/UserController.php
// CARI: prosesAkunMassal()
// GANTI: Hash::make($siswa->nis)
// MENJADI:
Hash::make('Simantap@' . $siswa->nis . rand(100, 999)),
```

- [ ] ✅ Buat akun massal, cek password di DB bukan hanya NIS
- [ ] ❌ Jika gagal: Cek method prosesAkunMassal di controller

### Indikator 1.7: Fix Backup Import Whitelist

```php
// app/Http/Controllers/Admin/BackupController.php
// CARI: import()
// TAMBAHKAN sebelum updateOrCreate:
$allowedFields = [
    'nama_peserta_didik', 'nis', 'nisn', 'nik', 'kelas',
    'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'agama',
    'nama_orang_tua', 'aktif'
];
$filtered = array_intersect_key($record, array_flip($allowedFields));
$modelClass::updateOrCreate(['id' => $record['id'] ?? null], $filtered);
```

- [ ] ✅ Test import backup — tidak boleh ada error mass assignment
- [ ] ❌ Jika gagal: Cek semua field di model $fillable

### Indikator 1.8: Session Encryption

```env
# .env
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
```

- [ ] ✅ `php artisan config:clear && php artisan config:show session` → SESSION_ENCRYPT harus true
- [ ] ❌ Jika gagal: Cek file .env tidak ter-cache

### Indikator 1.9: API Key Hash Comparison

```php
// app/Http/Middleware/ApiKeyMiddleware.php
// CARI:
$key = ApiKey::where('key', $apiKey)->first();
// GANTI:
$key = ApiKey::where('key', $apiKey)->first();
if (!$key || !hash_equals($key->key, $apiKey)) {
    return response()->json(['success' => false, 'message' => 'API Key tidak valid.'], 401);
}
```

- [ ] ✅ Test API dengan key salah → harus 401; key benar → harus 200
- [ ] ❌ Jika gagal: Cek tidak ada logic di atas hash_equals

### ✅ VERIFIKASI FASE 1

```powershell
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan route:list
php artisan config:show
```

- [ ] 9 indikator di atas ✅
- [ ] Tidak ada error di php artisan route:list
- [ ] Aplikasi masih bisa login
- [ ] Backup database terbuat

**❌ JANGAN lanjut ke Fase 2 jika ada error di sini**

---

## 🔴 FASE 2: BERSIHKAN DEAD CODE (1 jam)

**Tujuan:** Hapus ~35 file tidak terpakai

### Indikator 2.1: Hapus Breeze Auth Controllers

```powershell
# Backup dulu
mkdir D:\Backup\simantap-dead-code -ErrorAction SilentlyContinue
Copy-Item app\Http\Controllers\Auth D:\Backup\simantap-dead-code\Auth -Recurse

# Hapus file Breeze (JANGAN hapus LoginController.php — itu custom kita)
Remove-Item app\Http\Controllers\Auth\AuthenticatedSessionController.php -Force
Remove-Item app\Http\Controllers\Auth\ConfirmablePasswordController.php -Force
Remove-Item app\Http\Controllers\Auth\EmailVerificationNotificationController.php -Force
Remove-Item app\Http\Controllers\Auth\EmailVerificationPromptController.php -Force
Remove-Item app\Http\Controllers\Auth\NewPasswordController.php -Force
Remove-Item app\Http\Controllers\Auth\PasswordController.php -Force
Remove-Item app\Http\Controllers\Auth\PasswordResetLinkController.php -Force
Remove-Item app\Http\Controllers\Auth\RegisteredUserController.php -Force
Remove-Item app\Http\Controllers\Auth\VerifyEmailController.php -Force
```

- [ ] ✅ `dir app\Http\Controllers\Auth` → HANYA ada LoginController.php

### Indikator 2.2: Hapus Orphan Controllers

```powershell
Remove-Item app\Http\Controllers\ProfileController.php -Force
Remove-Item app\Http\Controllers\Admin\AdminDashboardController.php -Force
Remove-Item app\Http\Requests\Auth\LoginRequest.php -Force
```

- [ ] ✅ `php artisan route:list` → tidak ada error import

### Indikator 2.3: Hapus Dead Views

```powershell
Remove-Item resources\views\welcome.blade.php -Force
Remove-Item resources\views\dashboard.blade.php -Force
Remove-Item resources\views\layouts\navigation.blade.php -Force
Remove-Item resources\views\layouts\guest.blade.php -Force
Remove-Item resources\views\auth\confirm-password.blade.php -Force
Remove-Item resources\views\auth\verify-email.blade.php -Force
Remove-Item resources\views\auth\forgot-password.blade.php -Force
Remove-Item resources\views\auth\reset-password.blade.php -Force
Remove-Item resources\views\profile -Recurse -Force
Remove-Item resources\views\components -Recurse -Force
```

- [ ] ✅ Buka semua halaman utama aplikasi — tidak boleh ada error
- [ ] ✅ `php artisan view:clear` → tidak ada error

### Indikator 2.4: Hapus routes/auth.php

```powershell
Remove-Item routes\auth.php -Force
Remove-Item database\database.sqlite -Force
```

- [ ] ✅ Cek bootstrap/app.php → pastikan TIDAK import auth.php
- [ ] ✅ `php artisan route:list` → tidak ada route dari auth.php

### Indikator 2.5: Uninstall Package Tidak Terpakai

```powershell
composer remove laravel/breeze --no-interaction
composer remove laravel/pao --no-interaction
npm uninstall concurrently
```

- [ ] ✅ `php artisan route:list` → tidak ada error
- [ ] ✅ `npm run build` → tidak ada error

### ✅ VERIFIKASI FASE 2

```powershell
php artisan optimize:clear
php artisan route:list
php artisan view:clear
```

- [ ] 5 indikator di atas ✅
- [ ] Semua menu bisa dibuka tanpa error
- [ ] Tidak ada Class not found

---

## 🔴 FASE 3: FIX N+1 QUERIES (4-6 jam)

**Tujuan:** Optimasi 14 N+1 problem
**⚠️ FASE INI PALING PENTING UNTUK PERFORMANCE**

### Indikator 3.1: Fix N+1 Guru Dashboard

```php
// app/Http/Controllers/Guru/DashboardController.php
// GANTI method index()

public function index()
{
    $guruId = auth()->id();
    $siswa = Siswa::where('guru_id', $guruId)->aktif()->get();

    // BATCH QUERY (1x, bukan N kali)
    $siswaIds = $siswa->pluck('id');

    $allNilai = Nilai::whereIn('siswa_id', $siswaIds)->get()->groupBy('siswa_id');
    $allKehadiran = Kehadiran::whereIn('siswa_id', $siswaIds)->get()->groupBy('siswa_id');
    $allHasilKuis = HasilKuis::whereIn('siswa_id', $siswaIds)
        ->with('kuis')->get()->groupBy('siswa_id');
    $allKuis = Kuis::where('guru_id', $guruId)->get();

    // Hitung dari collection (tidak query lagi)
    $perluPendampingan = [];
    $menonjol = [];

    foreach ($siswa as $s) {
        $nilai = $allNilai->get($s->id, collect());
        $hasilKuis = $allHasilKuis->get($s->id, collect());
        $kehadiran = $allKehadiran->get($s->id, collect());

        $nilaiAkhir = $this->hitungNilaiFromCollection($nilai, $hasilKuis, $allKuis);
        $persenHadir = $kehadiran->count() > 0
            ? ($kehadiran->where('status', 'H')->count() / $kehadiran->count() * 100)
            : 0;

        if ($nilaiAkhir > 0 && $nilaiAkhir < 70) {
            $perluPendampingan[] = ['siswa' => $s, 'nilai' => $nilaiAkhir];
        }
        if ($nilaiAkhir >= 85) {
            $menonjol[] = ['siswa' => $s, 'nilai' => $nilaiAkhir];
        }
    }

    $ringkasan = [
        'jumlah' => $siswa->count(),
        'rata_rata' => $this->hitungRataRata($allNilai, $allHasilKuis, $allKuis),
        'tuntas' => count(array_filter($perluPendampingan, fn($x) => $x['nilai'] >= 70)),
        'belum' => count($perluPendampingan),
        'kehadiran' => $this->hitungRataHadir($allKehadiran),
    ];

    return view('guru.dashboard', compact('ringkasan', 'perluPendampingan', 'menonjol', 'allKuis'));
}

// TAMBAHKAN method private
private function hitungNilaiFromCollection($nilai, $hasilKuis, $allKuis): float
{
    $bobot = ['Tugas' => 0.2, 'Ulangan Harian' => 0.25, 'Praktik' => 0.15, 'PTS' => 0.2, 'PAS' => 0.2];
    $perKomponen = [];

    foreach ($nilai->groupBy('jenis') as $jenis => $items) {
        $perKomponen[$jenis] = $items->avg('nilai');
    }

    foreach ($hasilKuis as $hk) {
        $kuis = $allKuis->firstWhere('id', $hk->kuis_id);
        if ($kuis && isset($bobot[$kuis->jenis])) {
            $perKomponen[$kuis->jenis] = ($perKomponen[$kuis->jenis] ?? 0) + $hk->skor;
        }
    }

    $total = 0; $totalBobot = 0;
    foreach ($perKomponen as $jenis => $nilaiItem) {
        $total += $nilaiItem * ($bobot[$jenis] ?? 0);
        $totalBobot += ($bobot[$jenis] ?? 0);
    }

    return $totalBobot > 0 ? round($total / $totalBobot, 2) : 0;
}
```

- [ ] ✅ Install debugbar: `composer require barryvdh/laravel-debugbar --dev`
- [ ] ✅ Buka /guru/dashboard → query count harus < 20 (bukan 420+)
- [ ] ❌ Jika gagal: Cek debugbar menunjukkan query mana yang masih N+1

### Indikator 3.2: Fix N+1 Kepsek Dashboard

```php
// app/Http/Controllers/Kepsek/KepsekDashboardController.php
// Fokus pada method index() — batch semua query per kelas

$kelasList = Siswa::where('aktif', true)->distinct('kelas')->pluck('kelas');
$siswaByKelas = Siswa::where('aktif', true)->get()->groupBy('kelas');

$perKelas = [];
foreach ($kelasList as $kelas) {
    $siswaIds = $siswaByKelas[$kelas]->pluck('id');

    // Batch query 1x per kelas
    $nilai = NilaiErapot::whereIn('siswa_id', $siswaIds)->get();
    $kehadiran = Kehadiran::whereIn('siswa_id', $siswaIds)->get();
    $kebiasaan = Kebiasaan::whereIn('siswa_id', $siswaIds)
        ->distinct('siswa_id')->count('siswa_id');

    $perKelas[] = [
        'kelas' => $kelas,
        'jumlah' => count($siswaIds),
        'rata_nilai' => round($nilai->avg('nilai_akhir') ?? 0, 1),
        'persen_hadir' => $kehadiran->count() > 0
            ? round($kehadiran->where('status', 'H')->count() / $kehadiran->count() * 100, 1)
            : 0,
        'partisipasi_ortu' => count($siswaIds) > 0
            ? round($kebiasaan / count($siswaIds) * 100, 1) : 0,
    ];
}
```

- [ ] ✅ Query count < 30 (bukan 100+)

### Indikator 3.3: Fix N+1 Analisis Controller

```php
// app/Http/Controllers/Guru/AnalisisController.php
// GANTI nested loop dengan batch query

$kuisIds = Kuis::where('guru_id', auth()->id())->pluck('id');
$allNilai = Nilai::where('guru_id', auth()->id())->get();
$allHasilKuis = HasilKuis::whereIn('kuis_id', $kuisIds)->get()->groupBy('kuis_id');

$butirSulit = [];
foreach ($allHasilKuis as $kuisId => $hasilList) {
    $kuis = Kuis::find($kuisId);
    if (!$kuis || empty($kuis->soal)) continue;

    foreach ($kuis->soal as $i => $so) {
        $benar = $hasilList->filter(function ($h) use ($i, $so) {
            return $so['tipe'] === 'pg'
                ? ($h->jawaban[$i] ?? null) == $so['kunci']
                : strtolower(trim($h->jawaban[$i] ?? '')) === strtolower(trim($so['kunci']));
        })->count();

        $persen = $hasilList->count() > 0 ? $benar / $hasilList->count() * 100 : 0;
        if ($persen < 60) {
            $butirSulit[] = compact('kuis', 'i', 'so', 'persen');
        }
    }
}
```

- [ ] ✅ Query count < 15 (bukan 300+)

### Indikator 3.4: Fix N+1 ErapotGenerator

```php
// app/Http/Controllers/Guru/ErapotGeneratorController.php
// Method prepareData() — batch query

protected function prepareDataBulk(array $siswaIds): array
{
    // 1x query untuk semua siswa
    $siswa = Siswa::with(['orangTua'])->whereIn('id', $siswaIds)->get();
    $nilaiMapel = NilaiErapot::whereIn('siswa_id', $siswaIds)->get()->groupBy('siswa_id');
    $kehadiran = Kehadiran::whereIn('siswa_id', $siswaIds)->get()->groupBy('siswa_id');
    $dimensi = Dimensi::whereIn('siswa_id', $siswaIds)->get()->groupBy('siswa_id');
    $catatan = Catatan::whereIn('siswa_id', $siswaIds)
        ->orderBy('tanggal', 'desc')->get()->groupBy('siswa_id');

    $result = [];
    foreach ($siswa as $s) {
        $result[] = [
            'siswa' => $s,
            'nilaiMapel' => $nilaiMapel->get($s->id, collect()),
            'kehadiran' => $kehadiran->get($s->id, collect()),
            'dimensi' => $dimensi->get($s->id, collect()),
            'catatan' => $catatan->get($s->id, collect())->take(5),
        ];
    }
    return $result;
}
```

- [ ] ✅ Export Excel 30 siswa < 5 detik

### Indikator 3.5: Fix N+1 NilaiService

```php
// app/Services/NilaiService.php
// Method hitungNilaiAkhir() — jangan Kuis::find di loop

public function hitungNilaiAkhirBatch(array $siswaIds, $bobot = null): array
{
    $bobot = $bobot ?? self::BOBOT_DEFAULT;

    // 1x query untuk semua siswa
    $allNilai = Nilai::whereIn('siswa_id', $siswaIds)->get()->groupBy('siswa_id');
    $allHasilKuis = HasilKuis::whereIn('siswa_id', $siswaIds)
        ->with('kuis')->get()->groupBy('siswa_id');

    $result = [];
    foreach ($siswaIds as $siswaId) {
        $nilai = $allNilai->get($siswaId, collect());
        $hasilKuis = $allHasilKuis->get($siswaId, collect());
        $result[$siswaId] = $this->hitungNilaiFromCollection($nilai, $hasilKuis, $bobot);
    }
    return $result;
}
```

- [ ] ✅ Batch 30 siswa < 100ms

### Indikator 3.6: Fix N+1 TKAController

```php
// app/Http/Controllers/Guru/TKAController.php
// Batch query untuk index()

$siswaIds = $siswa->pluck('id');
$allDimensi = Dimensi::whereIn('siswa_id', $siswaIds)->get()->groupBy('siswa_id');
$allKebiasaan = Kebiasaan::whereIn('siswa_id', $siswaIds)->get()->groupBy('siswa_id');

$dataTKA = [];
foreach ($siswa as $s) {
    $dataTKA[] = [
        'siswa' => $s,
        'dimensi' => $allDimensi->get($s->id, collect()),
        'kebiasaan' => $allKebiasaan->get($s->id, collect()),
    ];
}
```

### Indikator 3.7: Fix N+1 LaporanController

```php
// app/Http/Controllers/Guru/LaporanController.php
// Batch query untuk index()

$siswa = Siswa::where('guru_id', auth()->id())->aktif()->get();
$siswaIds = $siswa->pluck('id');

$allNilai = Nilai::whereIn('siswa_id', $siswaIds)->get()->groupBy('siswa_id');
$allKehadiran = Kehadiran::whereIn('siswa_id', $siswaIds)->get()->groupBy('siswa_id');
$allHasilKuis = HasilKuis::whereIn('siswa_id', $siswaIds)->get()->groupBy('siswa_id');

$laporan = [];
foreach ($siswa as $s) {
    $laporan[] = [
        'siswa' => $s,
        'nilai' => $this->nilaiService->hitungNilaiFromCollection(
            $allNilai->get($s->id, collect()),
            $allHasilKuis->get($s->id, collect())
        ),
        'kehadiran' => $this->hitungKehadiran($allKehadiran->get($s->id, collect())),
    ];
}
```

### ✅ VERIFIKASI FASE 3

```powershell
composer require barryvdh/laravel-debugbar --dev
```

Buka setiap halaman berikut, cek query count di debugbar:
- [ ] `/guru/dashboard` → harus < 20 queries
- [ ] `/kepsek/dashboard` → harus < 30 queries
- [ ] `/guru/analisis` → harus < 15 queries
- [ ] `/guru/laporan` → harus < 20 queries
- [ ] `/guru/erapor` → harus < 15 queries
- [ ] Semua halaman < 50 queries
- [ ] Load time < 1 detik

---

## 🔴 FASE 4: TAMBAH INDEX DATABASE (30 menit)

**Tujuan:** Percepat query dengan index

### Indikator 4.1: Buat Migration Index

```powershell
php artisan make:migration add_performance_indexes
```

```php
// database/migrations/xxxx_add_performance_indexes.php
public function up(): void
{
    Schema::table('nilai_erapor', function (Blueprint $table) {
        $table->index('guru_id');
        $table->index('kelas');
        $table->index('predikat');
    });

    Schema::table('kehadiran', function (Blueprint $table) {
        $table->index('status');
    });

    Schema::table('capaian_pembelajaran', function (Blueprint $table) {
        $table->index('kode_cp');
        $table->index(['mata_pelajaran', 'fase']);
    });

    Schema::table('dapodik_import_logs', function (Blueprint $table) {
        $table->index('user_id');
        $table->index('created_at');
    });

    Schema::table('jadwal_pelajaran', function (Blueprint $table) {
        $table->index(['guru_id', 'hari']);
        $table->index(['kelas', 'hari']);
    });

    Schema::table('p5_projek', function (Blueprint $table) {
        $table->index('kelas');
        $table->index('koordinator_id');
    });

    Schema::table('prestasi', function (Blueprint $table) {
        $table->index('siswa_id');
    });

    Schema::table('ekstrakurikuler', function (Blueprint $table) {
        $table->index('pembina_id');
        $table->index('aktif');
    });
}

public function down(): void
{
    Schema::table('nilai_erapor', function (Blueprint $table) {
        $table->dropIndex(['guru_id']);
        $table->dropIndex(['kelas']);
        $table->dropIndex(['predikat']);
    });

    Schema::table('kehadiran', function (Blueprint $table) {
        $table->dropIndex(['status']);
    });

    Schema::table('capaian_pembelajaran', function (Blueprint $table) {
        $table->dropIndex(['kode_cp']);
        $table->dropIndex(['mata_pelajaran', 'fase']);
    });

    Schema::table('dapodik_import_logs', function (Blueprint $table) {
        $table->dropIndex(['user_id']);
        $table->dropIndex(['created_at']);
    });

    Schema::table('jadwal_pelajaran', function (Blueprint $table) {
        $table->dropIndex(['guru_id', 'hari']);
        $table->dropIndex(['kelas', 'hari']);
    });

    Schema::table('p5_projek', function (Blueprint $table) {
        $table->dropIndex(['kelas']);
        $table->dropIndex(['koordinator_id']);
    });

    Schema::table('prestasi', function (Blueprint $table) {
        $table->dropIndex(['siswa_id']);
    });

    Schema::table('ekstrakurikuler', function (Blueprint $table) {
        $table->dropIndex(['pembina_id']);
        $table->dropIndex(['aktif']);
    });
}
```

```powershell
php artisan migrate
```

- [ ] ✅ Tidak ada error saat migrate
- [ ] ✅ Index terbuat di semua tabel

---

## 🔴 FASE 5: SEED DATA KRITIS (1 jam)

**Tujuan:** Isi data yang kosong untuk demo

### Indikator 5.1: Seeder Orang Tua Siswa

```powershell
php artisan make:seeder OrangTuaSiswaSeeder
```

```php
// database/seeders/OrangTuaSiswaSeeder.php
use App\Models\Siswa;
use App\Models\OrangTuaSiswa;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;

class OrangTuaSiswaSeeder extends Seeder
{
    public function run(): void
    {
        $siswa = Siswa::all();

        foreach ($siswa as $s) {
            // Ayah
            OrangTuaSiswa::updateOrCreate(
                ['siswa_id' => $s->id, 'tipe' => 'ayah'],
                [
                    'uuid' => Str::uuid(),
                    'nama' => 'Ayah ' . $s->nama_peserta_didik,
                    'pekerjaan' => collect(['Petani', 'Pedagang', 'PNS', 'Karyawan'])->random(),
                    'pendidikan' => collect(['SD', 'SMP', 'SMA/SMK', 'S1'])->random(),
                    'penghasilan' => collect(['< 1jt', '1-2jt', '2-5jt'])->random(),
                    'no_telepon' => '08' . rand(1000000000, 9999999999),
                ]
            );

            // Ibu
            OrangTuaSiswa::updateOrCreate(
                ['siswa_id' => $s->id, 'tipe' => 'ibu'],
                [
                    'uuid' => Str::uuid(),
                    'nama' => 'Ibu ' . $s->nama_peserta_didik,
                    'pekerjaan' => collect(['Ibu Rumah Tangga', 'Guru', 'Pedagang'])->random(),
                    'pendidikan' => collect(['SMP', 'SMA/SMK', 'S1'])->random(),
                    'penghasilan' => collect(['< 1jt', '1-2jt', '2-5jt'])->random(),
                    'no_telepon' => '08' . rand(1000000000, 9999999999),
                ]
            );
        }

        $this->command->info('✅ ' . (Siswa::count() * 2) . ' data orang tua dibuat');
    }
}
```

```powershell
php artisan db:seed --class=OrangTuaSiswaSeeder
```

- [ ] ✅ `SELECT COUNT(*) FROM orang_tua_siswa;` → harus = 40

### Indikator 5.2: Seeder Dimensi Lengkap (8)

```powershell
php artisan make:seeder DimensiSeeder
```

```php
// database/seeders/DimensiSeeder.php
use App\Models\Dimensi;
use App\Models\Siswa;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;

class DimensiSeeder extends Seeder
{
    public function run(): void
    {
        $dimensiList = [
            'Keimanan dan Ketakwaan',
            'Kewargaan',
            'Penalaran Kritis',
            'Kreativitas',
            'Kolaborasi',
            'Kemandirian',
            'Kesehatan',
            'Komunikasi',
        ];

        $siswa = Siswa::all();

        foreach ($siswa as $s) {
            foreach ($dimensiList as $i => $nama) {
                Dimensi::updateOrCreate(
                    ['siswa_id' => $s->id, 'no_dimensi' => $i],
                    [
                        'uuid' => Str::uuid(),
                        'guru_id' => $s->guru_id,
                        'nama_guru' => $s->nama_guru,
                        'nama_siswa' => $s->nama_peserta_didik,
                        'dimensi' => $nama,
                        'skor' => rand(2, 4),
                        'predikat' => collect(['Mulai Berkembang', 'Berkembang', 'Sesuai Harapan', 'Sangat Berkembang'])->random(),
                    ]
                );
            }
        }

        $this->command->info('✅ ' . (Siswa::count() * 8) . ' data dimensi dibuat');
    }
}
```

```powershell
php artisan db:seed --class=DimensiSeeder
```

- [ ] ✅ Dimensi count = 160 (20 × 8)

### Indikator 5.3: Seeder Nilai E-Rapor

```powershell
php artisan make:seeder NilaiErapotSeeder
```

```php
// database/seeders/NilaiErapotSeeder.php
use App\Models\NilaiErapot;
use App\Models\Siswa;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;

class NilaiErapotSeeder extends Seeder
{
    public function run(): void
    {
        $siswa = Siswa::all();
        $mapel = ['Matematika', 'Bahasa Indonesia', 'IPA', 'IPS'];

        foreach ($siswa as $s) {
            foreach ($mapel as $m) {
                $formatif = rand(60, 95);
                $sumatif = rand(60, 95);
                $sumatifAkhir = rand(60, 95);
                $nilaiAkhir = round(($formatif * 0.3) + ($sumatif * 0.4) + ($sumatifAkhir * 0.3), 2);

                NilaiErapot::updateOrCreate(
                    [
                        'siswa_id' => $s->id,
                        'mata_pelajaran' => $m,
                        'semester' => 'Ganjil',
                        'tahun_ajaran' => '2025/2026',
                    ],
                    [
                        'uuid' => Str::uuid(),
                        'guru_id' => $s->guru_id,
                        'kelas' => $s->kelas,
                        'nilai_formatif' => $formatif,
                        'nilai_sumatif' => $sumatif,
                        'nilai_sumatif_akhir' => $sumatifAkhir,
                        'nilai_akhir' => $nilaiAkhir,
                        'predikat' => $nilaiAkhir >= 90 ? 'A' : ($nilaiAkhir >= 80 ? 'B' : ($nilaiAkhir >= 70 ? 'C' : 'D')),
                        'deskripsi_capaian' => "Menunjukkan penguasaan yang baik dalam materi {$m}.",
                    ]
                );
            }
        }

        $this->command->info('✅ ' . (Siswa::count() * 4) . ' data nilai e-Rapor dibuat');
    }
}
```

```powershell
php artisan db:seed --class=NilaiErapotSeeder
```

- [ ] ✅ Nilai erapor count = 80 (20 × 4)

### Indikator 5.4: Seeder API Key

```powershell
php artisan make:seeder ApiKeySeeder
```

```php
// database/seeders/ApiKeySeeder.php
use App\Models\User;
use App\Models\ApiKey;
use Illuminate\Database\Seeder;

class ApiKeySeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('peran', 'admin')->first();

        if ($admin) {
            $key = ApiKey::generate($admin->id, 'Dapodik Bridge Demo');
            $key->update(['expires_at' => now()->addYear()]);

            $this->command->info('✅ API Key dibuat: ' . $key->key);
        }
    }
}
```

```powershell
php artisan db:seed --class=ApiKeySeeder
```

- [ ] ✅ API Key terbuat dan ditampilkan

### Indikator 5.5: Seeder Capaian Pembelajaran

```powershell
php artisan make:seeder CapaianPembelajaranSeeder
```

```php
// database/seeders/CapaianPembelajaranSeeder.php
use App\Models\CapaianPembelajaran;
use Illuminate\Database\Seeder;

class CapaianPembelajaranSeeder extends Seeder
{
    public function run(): void
    {
        $mapelCp = [
            'Matematika' => [
                'Fase B' => [
                    ['elemen' => 'Bilangan', 'deskripsi' => 'Memahami operasi hitung pecahan', 'kode' => 'MAT.B.1'],
                    ['elemen' => 'Geometri', 'deskripsi' => 'Menghitung volume bangun ruang', 'kode' => 'MAT.B.2'],
                    ['elemen' => 'Data', 'deskripsi' => 'Menyajikan data dalam diagram', 'kode' => 'MAT.B.3'],
                ],
            ],
            'Bahasa Indonesia' => [
                'Fase B' => [
                    ['elemen' => 'Membaca', 'deskripsi' => 'Memahami teks narasi', 'kode' => 'BI.B.1'],
                    ['elemen' => 'Menulis', 'deskripsi' => 'Menulis paragraf deskriptif', 'kode' => 'BI.B.2'],
                ],
            ],
        ];

        foreach ($mapelCp as $mapel => $faseList) {
            foreach ($faseList as $fase => $cpList) {
                foreach ($cpList as $cp) {
                    CapaianPembelajaran::updateOrCreate(
                        ['kode_cp' => $cp['kode']],
                        [
                            'mata_pelajaran' => $mapel,
                            'fase' => $fase,
                            'elemen' => $cp['elemen'],
                            'deskripsi' => $cp['deskripsi'],
                        ]
                    );
                }
            }
        }

        $this->command->info('✅ ' . CapaianPembelajaran::count() . ' CP dibuat');
    }
}
```

### Indikator 5.6: Update DatabaseSeeder

```php
// database/seeders/DatabaseSeeder.php
public function run(): void
{
    $this->call([
        // ... existing seeders
        OrangTuaSiswaSeeder::class,
        DimensiSeeder::class,
        NilaiErapotSeeder::class,
        ApiKeySeeder::class,
        CapaianPembelajaranSeeder::class,
    ]);
}
```

```powershell
php artisan db:seed
```

- [ ] ✅ Tidak ada error saat seed

### ✅ VERIFIKASI FASE 5

```sql
SELECT
  'orang_tua' as tabel, COUNT(*) FROM orang_tua_siswa
UNION ALL SELECT 'dimensi', COUNT(*) FROM dimensi
UNION ALL SELECT 'nilai_erapor', COUNT(*) FROM nilai_erapor
UNION ALL SELECT 'api_keys', COUNT(*) FROM api_keys
UNION ALL SELECT 'capaian_pembelajaran', COUNT(*) FROM capaian_pembelajaran;
```

- [ ] orang_tua_siswa: 40 records
- [ ] dimensi: 160 records (20 × 8)
- [ ] nilai_erapor: 80 records
- [ ] api_keys: 1+ record
- [ ] capaian_pembelajaran: 5+ records

---

## 🔴 FASE 6: TAMBAH FITUR P5, EKSTRA, PRESTASI (3-5 hari)

**Tujuan:** Lengkapi 3 fitur yang belum ada

### Indikator 6.1: P5/Kokurikuler - Controller + View + Route

```powershell
php artisan make:controller Guru/P5Controller --resource
```

**Controller:** `app/Http/Controllers/Guru/P5Controller.php`
**Views:** `resources/views/guru/p5/index.blade.php`, `create.blade.php`, `nilai.blade.php`
**Route:** `Route::resource('p5', P5Controller::class);`

- [ ] ✅ `/guru/p5` bisa diakses
- [ ] ✅ Bisa create projek
- [ ] ✅ Bisa input nilai per siswa

### Indikator 6.2: Ekstrakurikuler - Controller + View + Route

```powershell
php artisan make:controller Guru/EkstrakurikulerController --resource
```

**Controller:** `app/Http/Controllers/Guru/EkstrakurikulerController.php`
**Views:** `resources/views/guru/ekstrakurikuler/index.blade.php`, `create.blade.php`, `nilai.blade.php`
**Route:** `Route::resource('ekstrakurikuler', EkstrakurikulerController::class);`

- [ ] ✅ `/guru/ekstrakurikuler` bisa diakses
- [ ] ✅ Bisa create ekstrakurikuler
- [ ] ✅ Bisa input nilai per siswa

### Indikator 6.3: Prestasi - Controller + View + Route

```powershell
php artisan make:controller Guru/PrestasiController --resource
```

**Controller:** `app/Http/Controllers/Guru/PrestasiController.php`
**Views:** `resources/views/guru/prestasi/index.blade.php`, `create.blade.php`
**Route:** `Route::resource('prestasi', PrestasiController::class);`

- [ ] ✅ `/guru/prestasi` bisa diakses
- [ ] ✅ Bisa create prestasi
- [ ] ✅ Ownership check berfungsi

### Indikator 6.4: Routes Terdaftar

```powershell
php artisan route:list | findstr "guru/p5"
php artisan route:list | findstr "guru/ekstra"
php artisan route:list | findstr "guru/prestasi"
```

- [ ] ✅ Semua route terdaftar

### Indikator 6.5: Update PDF e-Rapor

Tambahkan section P5, Ekstrakurikuler, dan Prestasi di `resources/views/guru/erapor/pdf.blade.php`.

Update `ErapotGeneratorController::prepareData()` untuk load P5, ekstra, prestasi.

- [ ] ✅ PDF muncul section P5
- [ ] ✅ PDF muncul section Ekstrakurikuler
- [ ] ✅ PDF muncul section Prestasi

---

## 🟡 FASE 7: CONFIG & FIX MINOR (2 jam)

### Indikator 7.1: Fix .env

```env
APP_NAME=SIMANTAP
APP_LOCALE=id
APP_FALLBACK_LOCALE=id
APP_FAKER_LOCALE=id_ID
APP_TIMEZONE=Asia/Makassar
```

```powershell
php artisan config:clear
php artisan config:show app
```

- [ ] ✅ APP_NAME harus "SIMANTAP"

### Indikator 7.2: Fix KKM Configurable

```php
// app/Models/NilaiErapot.php
public function generatePredikat(): string
{
    $pengaturan = PengaturanGuru::where('guru_id', $this->guru_id)->first();
    $kkm = $pengaturan?->kkm ?? 70;

    if ($this->nilai_akhir >= 90) return 'A';
    if ($this->nilai_akhir >= 80) return 'B';
    if ($this->nilai_akhir >= $kkm) return 'C';
    return 'D';
}
```

### Indikator 7.3: Fix NilaiService Formula

```php
// app/Services/NilaiService.php
// CARI: $totalNilai / $totalBobot * 100 / $totalBobot
// GANTI MENJADI:
$nilaiAkhir = $totalBobot > 0 ? round($total / $totalBobot, 2) : 0;
```

### ✅ VERIFIKASI FASE 7

```powershell
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear
```

---

## 🟢 FASE 8: UI/UX IMPROVEMENT (1 minggu)

### Indikator 8.1: Konsistenkan Sidebar Icons

Ganti semua emoji di sidebar admin, kepsek, ortu menjadi Font Awesome.

- [ ] ✅ Admin sidebar pakai FA icons
- [ ] ✅ Kepsek sidebar pakai FA icons
- [ ] ✅ Ortu sidebar pakai FA icons + data-driven

### Indikator 8.2: Tambah Loading States

Gunakan Alpine.js di semua form submit.

```html
<form x-data="{ loading: false }" @submit="loading = true">
    <button type="submit" :disabled="loading">
        <span x-show="!loading">Simpan</span>
        <span x-show="loading"><i class="fas fa-spinner fa-spin"></i> Menyimpan...</span>
    </button>
</form>
```

- [ ] ✅ Semua form ada loading indicator

### Indikator 8.3: Tambah @error di Semua Form

```html
<div class="form-group">
    <label>Nama</label>
    <input type="text" name="nama" value="{{ old('nama') }}" class="form-control">
    @error('nama')
        <small class="text-danger">{{ $message }}</small>
    @enderror
</div>
```

- [ ] ✅ Semua form input ada `@error`

### Indikator 8.4: Buat Auth Layout

Buat `resources/views/layouts/auth.blade.php` untuk login, register, dll.

- [ ] ✅ Auth views extend auth layout
- [ ] ✅ Tidak ada duplikasi CSS

### Indikator 8.5: Pindah CSS ke External

Pindah 460 baris inline CSS di `layouts/app.blade.php` ke `resources/css/app.css`.

```powershell
npm run build
```

- [ ] ✅ CSS external berhasil di-build
- [ ] ✅ Tampilan tidak berubah

---

## 📊 RINGKASAN CHECKLIST

| Fase | Item | Estimasi | Prioritas | Status |
|------|------|----------|-----------|--------|
| 0 | Persiapan & Backup (3) | 30 mnt | 🔴 | ⬜ |
| 1 | Security Critical (9) | 1-2 jam | 🔴 | ⬜ |
| 2 | Bersihkan Dead Code (5) | 1 jam | 🔴 | ⬜ |
| 3 | Fix N+1 Queries (7) | 4-6 jam | 🔴 | ⬜ |
| 4 | Tambah Index DB (1) | 30 mnt | 🔴 | ⬜ |
| 5 | Seed Data Kritis (6) | 1 jam | 🔴 | ⬜ |
| 6 | Fitur P5/Ekstra/Prestasi (5) | 3-5 hari | 🔴 | ⬜ |
| 7 | Config & Fix Minor (3) | 2 jam | 🟡 | ⬜ |
| 8 | UI/UX Improvement (5) | 1 minggu | 🟢 | ⬜ |
| | **Total** | **~3 minggu** | | |

---

## ⚠️ ATURAN PENGERJAAN

### JANGAN PERNAH:
- ❌ Loncat fase — kerjakan berurutan
- ❌ Lanjut fase jika masih ada error
- ❌ Ubah database tanpa backup
- ❌ Deploy ke production tanpa test lokal
- ❌ Skip verifikasi tiap indikator

### SELALU:
- ✅ Test setiap indikator sebelum lanjut
- ✅ Commit ke Git setiap fase selesai
- ✅ Backup DB sebelum fase yang ubah schema
- ✅ Cek `php artisan route:list` setelah ubah routes
- ✅ Cek debugbar setelah fix N+1

---

## 🎯 CARA PAKAI CHECKLIST INI

### Setiap Hari:
```powershell
# 1. Pilih 1-2 indikator
# 2. Kerjakan
# 3. Verifikasi dengan test command
# 4. Centang [x] jika berhasil
# 5. Commit ke Git

git add .
git commit -m "Fase X Indikator Y: [deskripsi]"
```

### Jika Ada Error:
```powershell
# 1. STOP — jangan lanjut
# 2. Baca error message
# 3. Fix error
# 4. Test ulang
# 5. Baru lanjut
```

---

**Simpan checklist ini. Centang satu per satu. Jangan skip. Hasilnya pasti project yang stabil dan cepat! 🚀**
