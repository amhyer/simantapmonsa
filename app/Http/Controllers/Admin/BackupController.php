<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aktivitas;
use App\Services\Backup\BackupJsonStreamReader;
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
    /** @var array<string, list<string>> */
    private array $restoreColumns = [];

    /** @var array<string, class-string<\Illuminate\Database\Eloquent\Model>|null> */
    private array $restoreModels = [];

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
            'backup_file' => 'required|file|mimes:json|max:102400',
        ]);

        $stream = fopen($request->file('backup_file')->getRealPath(), 'rb');
        if ($stream === false) {
            return redirect()->route('admin.backup.index')
                ->with('error', 'File backup tidak dapat dibaca.');
        }

        $validTables = BackupTables::tabel();
        $totalRecords = 0;
        $spools = [];

        try {
            // Validate and spool to disk before opening a DB transaction. Rows
            // from arbitrary JSON key order are replayed later in FK registry order.
            (new BackupJsonStreamReader($stream))->eachRecord(
                function (string $table) use ($validTables): void {
                    if (! in_array($table, $validTables, true)) {
                        throw new \UnexpectedValueException("Tabel '{$table}' tidak valid dalam backup.");
                    }
                },
                function (string $table, array $record) use (&$totalRecords, &$spools): void {
                    $totalRecords++;
                    if ($totalRecords > 100000) {
                        throw new \LengthException('File backup terlalu besar (maks 100.000 baris).');
                    }

                    if (! isset($spools[$table])) {
                        $spools[$table] = tmpfile();
                        if ($spools[$table] === false) {
                            unset($spools[$table]);
                            throw new \RuntimeException('Gagal membuat temporary file untuk restore.');
                        }
                    }

                    $json = json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                    $this->writeStreamAll($spools[$table], $json."\n");
                }
            );
        } catch (\LengthException $e) {
            fclose($stream);
            $this->closeSpools($spools);

            return redirect()->route('admin.backup.index')->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            fclose($stream);
            $this->closeSpools($spools);

            return redirect()->route('admin.backup.index')
                ->with('error', 'File backup tidak valid: '.$e->getMessage());
        }

        fclose($stream);

        try {
            DB::transaction(function () use ($spools): void {
                foreach (BackupTables::tabel() as $table) {
                    if (! isset($spools[$table])) {
                        continue;
                    }

                    rewind($spools[$table]);
                    while (($line = fgets($spools[$table])) !== false) {
                        $record = json_decode(rtrim($line, "\r\n"), true, 512, JSON_THROW_ON_ERROR);
                        $this->restoreRecord($table, $record);
                    }
                    $this->resetSequence($table);
                }
            });
        } catch (\Throwable $e) {
            Log::error('Backup import gagal: '.$e->getMessage());

            return redirect()->route('admin.backup.index')
                ->with('error', 'Restore gagal, tidak ada data yang diubah. Periksa format file backup.');
        } finally {
            $this->closeSpools($spools);
        }

        return redirect()->route('admin.backup.index')
            ->with('success', 'Data berhasil dipulihkan dari backup.');
    }

    /**
     * Restore one streamed row inside the import transaction. Filtering against
     * the live schema keeps backups from other releases backward-compatible.
     */
    protected function restoreRecord(string $table, mixed $record): void
    {
        if (! is_array($record) || ! in_array($table, BackupTables::tabel(), true)) {
            return;
        }

        if (! array_key_exists($table, $this->restoreColumns)) {
            if (! Schema::hasTable($table)) {
                $this->restoreColumns[$table] = [];
                $this->restoreModels[$table] = null;
            } else {
                $this->restoreColumns[$table] = Schema::getColumnListing($table);
                $this->restoreModels[$table] = BackupTables::model($table);
            }
        }

        $kolom = $this->restoreColumns[$table];
        if ($kolom === []) {
            return;
        }
        $modelClass = $this->restoreModels[$table];
        $row = array_intersect_key($record, array_flip($kolom));
        if ($row === []) {
            return;
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
            \Illuminate\Database\Eloquent\Model::unguarded(function () use ($modelClass, $id, $row): void {
                $query = method_exists($modelClass, 'withTrashed') ? $modelClass::withTrashed() : $modelClass::query();
                if ($id !== null) {
                    $query->updateOrCreate(['id' => $id], $row);
                } else {
                    $modelClass::create($row);
                }
            });

            return;
        }

        // Tabel tanpa model Eloquent (pivot).
        if ($id !== null) {
            DB::table($table)->updateOrInsert(['id' => $id], $row);
        } else {
            DB::table($table)->insert($row);
        }
    }

    /** @param resource $stream */
    private function writeStreamAll($stream, string $value): void
    {
        $offset = 0;
        $length = strlen($value);

        while ($offset < $length) {
            $written = fwrite($stream, substr($value, $offset));
            if ($written === false || $written === 0) {
                throw new \RuntimeException('Gagal menulis temporary row stream untuk restore.');
            }
            $offset += $written;
        }
    }

    /** @param array<string, resource> $spools */
    private function closeSpools(array $spools): void
    {
        foreach ($spools as $spool) {
            if (is_resource($spool)) {
                fclose($spool);
            }
        }
    }

    /** Ensure PostgreSQL sequences remain above restored explicit IDs. */
    private function resetSequence(string $table): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable($table)) {
            return;
        }

        if (! in_array('id', Schema::getColumnListing($table), true)) {
            return;
        }

        $sequence = DB::selectOne('SELECT pg_get_serial_sequence(?, ?) AS seq', [$table, 'id'])?->seq;
        if ($sequence) {
            $maxId = (int) DB::table($table)->max('id');
            DB::statement('SELECT setval(?, ?, ?)', [$sequence, max($maxId, 1), $maxId > 0]);
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

        // Wipe dan backup berbagi registry. Account, semester, setting sekolah,
        // API keys, dan tabel framework dipertahankan; log/cache dapat dibuat ulang.
        $tabelData = BackupTables::tabelUntukWipe();

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
