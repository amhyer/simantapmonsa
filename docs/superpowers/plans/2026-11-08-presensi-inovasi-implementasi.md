# Inovasi Presensi ke Depan untuk SIMANTAP — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambahkan lima inovasi pada fitur presensi ZKTeco (Attendance Management) agar lebih kompleks dan mudah digunakan sekolah: dashboard interaktif, auto-sync scheduler, notifikasi perubahan kehadiran, jam kerja per jenjang, dan live monitor TCP.

**Architecture:** Semua inovasi memperluas alur yang sudah ada (DB reader → service → controller → view) tanpa mengubah perilaku inti proteksi data (izin/sakit/cuti, koreksi manual).

**Tech Stack:** Laravel 12 (PHP 8.4), Livewire/Tailwind view (Blade), PHPUnit feature tests, PostgreSQL.

**Spec:** `docs/superpowers/specs/2026-11-08-presensi-inovasi-design.md`

## Global Constraints

- Data presensi disimpan di tabel `presensi_pegawai`; `metode` tidak boleh di-overwrite menjadi selain `mesin`/`admin`.
- Status `izin` / `sakit` / `cuti` dan koreksi manual tidak boleh ditimpa oleh import mesin.
- Batas jam masuk default `07:15` (`PRESENSI_MESIN_JAM_MASUK`).
- Tes harus lolos untuk area presensi; kegagalan non-presensi (favicon dll) tidak mengubah scope plan ini.

## Review Focus

- **Input: memilih engine via website** (sudah ada) → dashboard/tabs menampilkan engine aktif yang benar setelah perubahan DB settings.
- **Input: data scan tanpa NIK cocok** (19 NIK) → apakah inovasi harus menangani auto-link/manual.

---

## Task 1: Dashboard Presensi Interaktif

**Files:**
- Modify: `app/Http/Controllers/Admin/PresensiController.php` (atau controller mesin)
- Modify: `resources/views/admin/presensi/mesin-import.blade.php` (atau view dashboard baru)
- Test: `tests/Feature/PresensiMesinTarikTest.php`

**Interfaces:**
- Consumes: `PresensiPegawai` (sudah ada), `User`.
- Produces: ringkasan harian hadir/terlambat/pulang.

- [x] **Step 1: Write the failing test**
```php
public function test_dashboard_presensi_menampilkan_ringkasan(): void
{
    $admin = User::factory()->admin()->create();
    // buat presensi mesin + user
    $this->actingAs($admin)->get(route('admin.presensi.dashboard'))->assertOk();
}
```

- [x] **Step 2: Run test to verify it fails**
- [x] **Step 3: Implement minimal code** — method ringkasan + view.
- [x] **Step 4: Run tests to pass.**
- [x] **Step 5: Commit.**

## Task 2: Auto-Sync Scheduler

**Files:**
- Create: `app/Console/Commands/PresensiMesinSyncCommand.php`
- Modify: `app/Providers/AppServiceProvider.php` (schedule)
- Test: `tests/Feature/PresensiMesinSyncTest.php`

**Interfaces:**
- Consumes: `AttendanceDatabaseReader`, `PresensiMesinService`.
- Produces: command `presensi:mesin-sync`.

- [x] **Step 1: Write the failing test** — command signature/exit code.
- [x] **Step 2: Run, verify fail.**
- [x] **Step 3: Implement command + schedule.**
- [x] **Step 4: Run, pass.**
- [x] **Step 5: Commit.**

## Task 3: Notifikasi Perubahan Kehadiran

**Files:**
- Modify: `app/Services/MesinPresensi/PresensiMesinService.php`
- Modify: controller import (setelah hasil import).
- Test: `tests/Feature/PresensiMesinTarikTest.php`.

**Interfaces:**
- Consumes: hasil import (dibuat/diperbarui/tetap).
- Produces: notifikasi perubahan status terlambat.

- [x] **Step 1..5:** tulis test notifikasi → implement → pass → commit.

## Task 4: Jam Kerja per Jenjang

**Files:** 
- Modify: `config/presensi.php`, `PresensiMesinService` (normalisasi jam).
- Test: `tests/Feature/PresensiMesin*`.

**Interfaces:**
- Consumes: `mesin_jam_masuk` + data jenjang.
- Produces: jam masuk per jenjang.

- [x] **Step 1..5:** tulis test → implement → pass → commit.

## Task 5: Live Monitor via TCP

**Files:** 
- Modify: `AttendanceDatabaseReader` (method read live), controller tes/baca, view mesin.

**Interfaces:** 
- Consumes: protokol TCP (sudah ada).
- Produces: endpoint baca live terkini.

- [x] **Step 1..5:** tulis test → implement → pass → commit.

---

## Execution Handoff

**"Plan complete and saved to `docs/superpowers/plans/2026-11-08-presensi-inovasi-implementasi.md`. Please review the plan. Which execution approach would you prefer?"**

- **Subagent-driven** — fresh subagent per task + reviewer; most thorough.
- **Native** — saya implement semua task sendiri di sesi ini; tercepat; reviewer tunggal di akhir.

**For this plan I recommend subagent-driven** — ada 5 task independen bertumpuk (satu per inovasi) dan beberapa saling bergantung ringan (service → controller → view).
