<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aktivitas;
use App\Jobs\ExportBackupJob;
use App\Support\BackupTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BackupController extends Controller
{
    public function index()
    {
        // Disk & folder mengikuti config/backup.php supaya daftar ini selalu
        // menunjukkan tempat `simantap:backup` dan ExportBackupJob benar-benar menulis.
        $disk = Storage::disk((string) config('backup.disk', 'local'));
        $direktori = trim((string) config('backup.direktori', 'backups'), '/');

        $backups = $disk->files($direktori);
        $backupList = collect($backups)->map(function ($file) use ($disk) {
            return [
                'name' => basename($file),
                'size' => $disk->size($file),
                'date' => $disk->lastModified($file),
            ];
        })->sortByDesc('date')->values();

        return view('admin.backup.index', compact('backupList'));
    }

    public function export()
    {
        // Job query + tulis backup sendiri secara streaming (hemat memori).
        // Tanpa payload agar antrean tetap ringan.
        dispatch(new ExportBackupJob());

        return response()->json([
            'success' => true,
            'message' => 'Export backup dimulai secara asynchronous. File akan siap di folder backups dalam waktu singkat.',
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'backup_file' => 'required|file|mimes:json|max:10240',
        ]);

        $file = $request->file('backup_file');
        $content = file_get_contents($file->getRealPath());
        $data = json_decode($content, true, 512);

        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            return redirect()->route('admin.backup.index')
                ->with('error', 'File backup tidak valid.');
        }

        // Hanya tabel yang dikenal (App\Support\BackupTables) yang boleh dipulihkan.
        $validTables = BackupTables::tabel();
        foreach (array_keys($data) as $table) {
            if ($table === 'metadata') {
                continue; // blok metadata dari `simantap:backup` tidak diimpor
            }
            if (!in_array($table, $validTables, true)) {
                return redirect()->route('admin.backup.index')
                    ->with('error', "Tabel '$table' tidak valid dalam backup.");
            }
        }

        // Batas pengaman agar file raksasa tidak menghabiskan memori/waktu.
        $totalRecords = 0;
        foreach ($data as $records) {
            if (is_array($records)) {
                $totalRecords += count($records);
            }
        }
        if ($totalRecords > 100000) {
            return redirect()->route('admin.backup.index')
                ->with('error', 'File backup terlalu besar (maks 100.000 baris). Bagi menjadi beberapa file.');
        }

        try {
            DB::transaction(function () use ($data) {
                // Ikuti urutan registry (induk → anak) agar foreign key terpenuhi.
                foreach (BackupTables::tabel() as $table) {
                    if (array_key_exists($table, $data)) {
                        $this->restoreTable($table, $data[$table]);
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::error('Backup import gagal: ' . $e->getMessage());

            return redirect()->route('admin.backup.index')
                ->with('error', 'Restore gagal, tidak ada data yang diubah. Periksa format file backup.');
        }

        return redirect()->route('admin.backup.index')
            ->with('success', 'Data berhasil dipulihkan dari backup.');
    }

    /**
     * Restore satu tabel backup. Dipanggil di dalam DB::transaction agar gagal
     * di tengah tidak menyisakan data parsial (inkonsisten).
     *
     * Kolom difilter terhadap kolom yang benar-benar ada di tabel (schema),
     * sehingga backup dari versi lama/patch tidak menyebabkan SQL error.
     */
    protected function restoreTable(string $table, mixed $records): void
    {
        if (!is_array($records) || empty($records)) {
            return;
        }

        if (!in_array($table, BackupTables::tabel(), true) || !Schema::hasTable($table)) {
            return;
        }

        $kolom = Schema::getColumnListing($table);
        $modelClass = BackupTables::model($table);

        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            $row = array_intersect_key($record, array_flip($kolom));
            if ($row === []) {
                continue;
            }

            $id = $row['id'] ?? null;

            // Export sengaja tidak menyertakan `kata_sandi`. Karena kolomnya
            // NOT NULL, baris users yang dipulihkan tanpa sandi diberi sandi acak
            // + wajib ganti saat login pertama (tidak ada akun tanpa sandi).
            if ($table === 'users' && empty($row['kata_sandi'])) {
                $row['kata_sandi'] = Hash::make(bin2hex(random_bytes(8)));
                $row['force_password_change'] = true;
            }

            if ($modelClass) {
                \Illuminate\Database\Eloquent\Model::unguarded(function () use ($modelClass, $id, $row) {
                    $query = method_exists($modelClass, 'withTrashed') ? $modelClass::withTrashed() : $modelClass::query();
                    if ($id !== null) {
                        $query->updateOrCreate(['id' => $id], $row);
                    } else {
                        $modelClass::create($row);
                    }
                });
                continue;
            }

            // Tabel tanpa model Eloquent (pivot).
            if ($id !== null) {
                DB::table($table)->updateOrInsert(['id' => $id], $row);
            } else {
                DB::table($table)->insert($row);
            }
        }

        if (DB::getDriverName() === 'pgsql' && in_array('id', $kolom, true)) {
            $seq = DB::selectOne('SELECT pg_get_serial_sequence(?, ?) AS seq', [$table, 'id'])?->seq;
            if ($seq) {
                $maxId = (int) DB::table($table)->max('id');
                DB::statement('SELECT setval(?, ?, ?)', [$seq, max($maxId, 1), $maxId > 0]);
            }
        }
    }

    public function wipeAll(Request $request)
    {
        $request->validate([
            'konfirmasi' => 'required|in:HAPUS SEMUA DATA',
            'password' => 'required|current_password',
        ], [
            'konfirmasi.required' => 'Ketik persis HAPUS SEMUA DATA untuk konfirmasi.',
            'konfirmasi.in' => 'Teks konfirmasi salah. Ketik persis: HAPUS SEMUA DATA.',
            'password.required' => 'Kata sandi wajib diisi untuk konfirmasi identitas.',
            'password.current_password' => 'Kata sandi salah. Gunakan kata sandi akun Anda yang sedang login.',
        ]);

        Log::info('Wipe data diminta oleh: ' . (auth()->user()?->nama_pengguna ?? '?') . ' IP: ' . request()->ip());

        // Daftar tabel data yang dikosongkan. SENGAJA dikecualikan:
        // users (agar admin tetap bisa login), semesters, sekolah_settings,
        // api_keys (akses bridge), dan seluruh tabel framework
        // (migrations, jobs, cache, sessions, telescope, dsb).
        $tabelData = [
            'cbt_violations', 'cbt_results', 'cbt_test_sessions', 'cbt_questions', 'cbt_question_banks',
            // Engine ujian terpadu (Fase 3) kini sumber kebenaran; tabel cbt_*/
            // tryout_* hanya cermin, jadi keduanya harus ikut dikosongkan agar
            // tidak tersisa keadaan setengah (engine kosong, cermin terisi).
            'exam_violations', 'exam_results', 'exam_answers', 'exam_attempts', 'exam_sessions',
            'exam_question_options', 'exam_questions', 'exam_sections', 'exam_banks', 'exam_engines',
            'siswa', 'orang_tua_siswa', 'nilai', 'nilai_erapor', 'nilai_mapel',
            'nilai_cp', 'capaian_pembelajaran', 'kehadiran', 'hasil_kuis',
            'kuis', 'materi', 'catatan', 'dimensi', 'kebiasaan', 'ptk',
            'rombel', 'jadwal_pelajaran', 'mata_pelajaran', 'pengaturan_guru',
            'ringkasan_guru', 'dapodik_sync_logs', 'dapodik_import_logs',
            'dapodik_data_cache', 'aktivitas', 'tanggal_rapor',
            'ekstrakurikuler', 'siswa_ekstrakurikuler',
            'tema_kokurikuler', 'kegiatan_kokurikuler', 'kelompok_kokurikuler',
            'anggota_kelompok',
        ];

        $dihapus = [];
        $gagal = [];
        $driver = DB::getDriverName();
        Schema::disableForeignKeyConstraints();
        try {
            foreach ($tabelData as $tabel) {
                if (!Schema::hasTable($tabel)) {
                    continue;
                }
                try {
                    if ($driver === 'pgsql') {
                        // CASCADE agar constraint FK antar tabel data ikut teratasi;
                        // RESTART IDENTITY agar ID mulai lagi dari 1.
                        DB::statement('TRUNCATE TABLE "' . $tabel . '" RESTART IDENTITY CASCADE');
                    } else {
                        // MySQL / SQLite: truncate tanpa sintaks Postgres.
                        DB::table($tabel)->truncate();
                    }
                    $dihapus[] = $tabel;
                } catch (\Exception $e) {
                    Log::warning('Gagal truncate tabel ' . $tabel . ': ' . $e->getMessage());
                    $gagal[] = $tabel;
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        if (count($dihapus) === 0) {
            return redirect()->route('admin.backup.index')->with(
                'error',
                'Tidak ada data yang dihapus. Tabel tidak ditemukan atau koneksi basis data bermasalah.' .
                (count($gagal) ? ' Gagal pada: ' . implode(', ', $gagal) . '.' : '')
            );
        }

        $guru = auth()->user();
        try {
            Aktivitas::create([
                'uuid' => (string) Str::uuid(),
                'guru_id' => $guru?->id,
                'nama_guru' => $guru?->nama_lengkap ?? 'Admin',
                'jenis' => 'pengaturan',
                'judul' => 'Hapus Semua Data',
                'deskripsi' => 'Mengosongkan ' . count($dihapus) . ' tabel: ' . implode(', ', $dihapus),
                'tabel_terkait' => null,
            ]);
        } catch (\Exception $e) {
            Log::warning('Gagal catat aktivitas hapus data: ' . $e->getMessage());
        }

        $pesan = 'Berhasil mengosongkan ' . count($dihapus) . ' tabel data. Akun pengguna, semester, identitas sekolah, dan API key dipertahankan.';
        if (count($gagal) > 0) {
            $pesan .= ' Sebagian gagal: ' . implode(', ', $gagal) . '.';
        }

        return redirect()->route('admin.backup.index')->with('success', $pesan);
    }
}
