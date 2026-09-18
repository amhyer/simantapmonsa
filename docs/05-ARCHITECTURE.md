# SIMANTAP — Arsitektur Sistem

**Versi:** 4.2
**Tanggal:** 13 September 2026

---

## Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                    SIMANTAP v4.2 Architecture                   │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌──────────────┐     ┌──────────────┐     ┌──────────────┐    │
│  │   Dapodik    │────▶│   Bridge     │────▶│   SIMANTAP   │    │
│  │  Desktop v6  │     │  v4.0 EXE    │     │  Laravel 11  │    │
│  └──────────────┘     └──────────────┘     └──────────────┘    │
│         │                    │                    │             │
│         ▼                    ▼                    ▼             │
│  ┌──────────────┐     ┌──────────────┐     ┌──────────────┐    │
│  │  WebService  │     │    POST      │     │  PostgreSQL  │    │
│  │ :5774        │     │  /api/       │     │  Database    │    │
│  └──────────────┘     │  dapodik/    │     └──────────────┘    │
│                       │  sync/{modul}│                         │
│                       └──────────────┘                         │
└─────────────────────────────────────────────────────────────────┘
```

---

## Tech Stack

| Layer | Technology | Version |
|-------|------------|---------|
| Backend | Laravel | 11.x |
| PHP | PHP | 8.5 |
| Database | PostgreSQL | 16 |
| Frontend | Blade + Tailwind | - |
| Chart | Chart.js | 4.x |
| PDF Export | DomPDF | 2.x |
| Excel Export | PhpSpreadsheet | 5.9 |
| Bridge | Python + Custom EXE | 4.0 |

---

## Database Tables (Verified)

### Core Tables
| Table | Description | Key Columns |
|-------|-------------|-------------|
| `users` | All user accounts | id, uuid, nama_lengkap, nama_pengguna, kata_sandi, peran, is_active |
| `siswa` | Student data | id, uuid, nis, nisn, nama_peserta_didik, kelas, telepon |
| `guru` | Teacher data | id, uuid, nip, nama_guru, mata_pelajaran |
| `semester` | Academic terms | id, semester_id, tahun_ajaran, nama_semester |
| `mata_pelajaran` | Subjects | id, uuid, nama_mapel, guru_id |
| `mapel` | Alternative subjects table | id, uuid, nama_mapel, kelas, guru_id |

### Academic Tables
| Table | Description | Key Columns |
|-------|-------------|-------------|
| `pengaturan_guru` | Teacher settings per subject | id, guru_id, mata_pelajaran, kelas, kkm, sistem |
| `nilai` | Grades | id, uuid, guru_id, siswa_id, nilai, kuis_id |
| `dimensi` | Competency dimensions | id, uuid, guru_id, siswa_id, dimensi, skor |
| `kehadiran` | Attendance | id, uuid, guru_id, siswa_id, status, tanggal |
| `kuis` | Quizzes | id, uuid, guru_id, judul, opsi_a-d, jawaban_benar |
| `materi` | Teaching materials | id, uuid, guru_id, judul, kelas, mata_pelajaran |

### Support Tables
| Table | Description |
|-------|-------------|
| `api_keys` | API authentication for Bridge |
| `dapodik_sync_logs` | Sync operation logs |
| `dapodik_config` | Dapodik connection settings |
| `backups` | Backup file metadata |
| `setting_sekolah` | School profile settings |

---

## 5 User Roles

### Admin (`peran = 'admin'`)
- Full CRUD: Users, Siswa, Guru, Mata Pelajaran
- School profile settings
- API key management
- Dapodik sync trigger
- Backup/restore
- Grade weight configuration (Bobot Penilaian)
- Dashboard: statistics, charts, school info

### Guru (`peran = 'guru'`)
- Input grades (manual + Dapodik import)
- Manage quiz (create, activate/deactivate)
- Upload materials
- Attendance (kehadiran)
- View own dashboard with student statistics
- e-Rapor export (PDF/Excel)

### Siswa (`peran = 'siswa'`)
- View own grades and attendance
- Take quizzes (online)
- View materials from teacher
- View own dashboard

### Ortu (`peran = 'ortu'`)
- View child's grades
- View child's attendance
- View child's quiz results
- Reports with filtering

### Kepsek (`peran = 'kepsek'`)
- Monitor all teachers (pantau guru)
- View school-wide reports
- Activity logs (aktivitas)
- Print reports
- Dashboard with school overview

---

## Authentication Flow

### Custom Auth Implementation
```php
// app/helpers.php
function check($user, $password): bool
{
    return Hash::check($password, $user->kata_sandi);
}

// Middleware checks peran
if ($user && check($user, $password)) {
    // Set session
    session(['pengguna_login' => $user->toArray()]);
}
```

### Key Points
- Uses `nama_pengguna` field (not `email`)
- `getAuthPassword()` returns `kata_sandi` attribute
- Session stores full user array as `pengguna_login`
- `$user->pemeriksaan()` returns role-based

---

## Dapodik WebService Integration

### Protocol
- **Base URL:** `http://localhost:5774/WebService`
- **Auth:** `Authorization: Bearer <token>` + `?npsn=<NPSN>`
- **Method:** GET only
- **Response:** JSON `{results: N, start: N, limit: N, rows: [...]}`

### Endpoints
| Endpoint | Description |
|----------|-------------|
| `getSekolah` | School profile data |
| `getPesertaDidik` | Student data |
| `getGtk` | Teacher data |
| `getRombonganBelajar` | Class/subject assignments |

### Sync Flow
```
1. Admin clicks "Sync dari Dapodik" in SIMANTAP
2. SIMANTAP calls Bridge API: POST /api/dapodik/sync/{modul}
3. Bridge sends GET request to Dapodik WebService
4. Bridge parses response, extracts rows
5. Bridge POSTs rows to SIMANTAP API: POST /api/dapodik/sync/{modul}
6. SIMANTAP SyncService processes and stores data
7. Response sent back to Bridge
8. Bridge shows result to admin
```

### API Key Middleware
```php
// routes/api.php
Route::post('/api/dapodik/sync/{modul}', [DapodikSyncController::class, 'sync'])
    ->middleware('apikey');  // Requires valid API key

Route::get('/api/dapodik/ping', [DapodikSyncController::class, 'ping']);  // Open
```

---

## Routes Summary

### Public Routes
- `GET /` — Landing page
- `POST /login` — Login
- `POST /register` — Registration (throttled)
- `GET /demo` — Demo login (non-production only)

### Protected Routes (require auth + role middleware)

#### Admin (`role:admin`)
- `GET /admin/dashboard`
- `GET /admin/users` — CRUD
- `GET /admin/siswa` — CRUD
- `GET /admin/guru` — CRUD
- `GET /admin/mapel` — CRUD
- `GET /admin/setting-sekolah` — School profile
- `GET /admin/api-keys` — API key management
- `GET /admin/bobot` — Grade weights
- `GET /admin/dapodik` — Dapodik sync panel
- `GET /admin/backup` — Backup/restore
- `GET /admin/semester` — Semester management
- `GET /admin/peta-kelas` — Class map
- `POST /admin/dapodik/sync` — Trigger sync
- `GET /admin/laporan` — Reports

#### Guru (`role:guru`)
- `GET /guru/dashboard`
- `GET /guru/input-nilai` — Grade input
- `GET /guru/erapor` — e-Rapor
- `GET /guru/kuis` — Quiz management
- `GET /guru/materi` — Materials
- `GET /guru/kehadiran` — Attendance
- `GET /guru/kegiatan` — Activities
- `GET /guru/integrasi` — Dapodik import

#### Siswa (`role:siswa`)
- `GET /siswa/dashboard`
- `GET /siswa/nilai` — Grades
- `GET /siswa/kehadiran` — Attendance
- `GET /siswa/kuis` — Quiz
- `GET /siswa/materi` — Materials

#### Ortu (`role:ortu`)
- `GET /ortu/dashboard`
- `GET /ortu/rekap` — Child's grades
- `GET /ortu/kehadiran` — Child's attendance
- `GET /ortu/laporan` — Reports

#### Kepsek (`role:kepsek`)
- `GET /kepsek/dashboard`
- `GET /kepsek/pantau` — Monitor teachers
- `GET /kepsek/aktivitas` — Activity logs
- `GET /kepsek/laporan` — School reports

---

## Middleware Stack

```php
// bootstrap/app.php
$middleware->alias([
    'role' => \App\Http\Middleware\RoleMiddleware::class,
    'apikey' => \App\Http\Middleware\ApiKeyMiddleware::class,
]);
```

### RoleMiddleware
- Checks `session('pengguna_login.peran')` against required role
- Redirects to `/` if unauthorized

### ApiKeyMiddleware
- Validates `X-API-KEY` header against `api_keys` table
- Used for Bridge API authentication
- Open for `/api/dapodik/ping`, protected for `/api/dapodik/sync/{modul}`

---

## Key Services

### DapodikSyncService
- `app/Services/Dapodik/DapodikSyncService.php`
- Handles data sync from Dapodik WebService
- Maps Dapodik fields to SIMANTAP schema
- Manages upsert logic (create or update)
- Logs sync operations

### NilaiService
- `app/Services/NilaiService.php`
- Grade calculation and prediction
- Supports configurable weights (Formatif/Sumatif/Sumatif Akhir)
- Predikat calculation: A(≥90), B(≥80), C(≥70/KKM), D(<70)

### Setting
- `app/helpers.php` — file-based settings via `setting()`
- Settings stored in `storage/app/settings.json`
- Registered in `composer.json` autoload files
