#!/usr/bin/env python3
"""Kontrak statis CBT, bukan pengganti PHPUnit/integrasi database."""
import argparse
import pathlib
import re
import sys

p = argparse.ArgumentParser()
p.add_argument('--root', default=str(pathlib.Path(__file__).resolve().parents[1]))
a = p.parse_args()
r = pathlib.Path(a.root)
def read(path):
    f = r / path
    return f.read_text() if f.exists() else ''
checks = []
def check(name, ok):
    checks.append(bool(ok))
    print(f'[{"PASS" if ok else "FAIL"}] {name}')
controllers = ['CbtQuestionBankController', 'CbtQuestionController', 'CbtTestSessionController', 'CbtStudentController', 'CbtIntegrityController']
check('Lima controller CBT tersedia', all((r / f'app/Http/Controllers/Cbt/{c}.php').exists() for c in controllers))
models = ['CbtQuestionBank', 'CbtQuestion', 'CbtTestSession', 'CbtResult', 'CbtViolation']
check('Lima model CBT tersedia', all((r / f'app/Models/{m}.php').exists() for m in models))
migration = read('database/migrations/2026_10_18_000001_create_cbt_tables.php')
check('Skema CBT lengkap dan satu percobaan per siswa', all(f"Schema::create('{t}'" in migration for t in ['cbt_question_banks','cbt_questions','cbt_test_sessions','cbt_results','cbt_violations']) and "unique(['session_id', 'siswa_id'])" in migration)
service = read('app/Services/CbtExamService.php')
check('Simpan/submit memakai transaksi dan row lock', 'DB::transaction' in service and service.count('lockForUpdate') >= 3)
check('Deadline diperiksa server', 'deadline_at' in service and 'greaterThanOrEqualTo' in service)
check('Snapshot soal dan peserta saat publikasi', 'question_snapshot' in service and 'participant_ids' in service and 'publish' in service)
score = read('app/Services/CbtScoring.php')
check('Kunci tidak ikut payload soal siswa', "unset($question['correct_answer'])" in score)
check('Penilaian otomatis ada dan jawaban kosong salah', 'function grade' in score and '!== null' in score)
student = read('app/Http/Controllers/Cbt/CbtStudentController.php')
check('Akses hasil dibatasi ke siswa dan akun pemilik', 'siswa_id' in student and 'user_id' in student and 'abort_unless' in student)
check('Mulai ujian via POST, GET tidak membuat percobaan', "->name('begin')" in read('routes/web.php') and 'function begin' in student)
check('Halaman ujian memakai autosave dan timer', all(x in read('resources/views/cbt/student/exam.blade.php') for x in ['fetch(', 'deadline', 'setInterval', 'csrf_token']))
check('Navigasi CBT mengikuti feature flag', all("config('simantap.cbt_enabled'" in read(f'resources/views/{role}/partials/sidebar.blade.php') for role in ['guru','siswa']))
check('Semua tabel CBT tercakup backup',
      all(f"'{t}' =>" in read('app/Support/BackupTables.php') for t in ['cbt_question_banks','cbt_questions','cbt_test_sessions','cbt_results','cbt_violations'])
      and 'makeVisible($row->getHidden())' in read('app/Jobs/ExportBackupJob.php')
      and 'makeVisible($row->getHidden())' in read('app/Console/Commands/BackupData.php')
      and "'cbt_violations', 'cbt_results', 'cbt_test_sessions'" in read('app/Http/Controllers/Admin/BackupController.php'))
check('Scheduler finalisasi ujian kedaluwarsa', 'cbt:finalize-expired' in read('routes/console.php'))
check('Feature test siklus dan isolasi tersedia', (r/'tests/Feature/CbtExamTest.php').exists() and (r/'tests/Unit/CbtScoringTest.php').exists())
check('Flag CBT tetap nonaktif secara default', "env('SIMANTAP_CBT_ENABLED', false)" in read('config/simantap.php'))
print(f'\nRingkasan: {sum(checks)} lulus, {len(checks)-sum(checks)} gagal (statis)')
sys.exit(0 if all(checks) else 1)
