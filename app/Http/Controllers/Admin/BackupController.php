<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aktivitas;
use App\Jobs\ExportBackupJob;
use App\Services\BackupRestoreService;
use App\Support\BackupTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        // Logika yang sama dipakai `simantap:backup:drill` agar drill benar-benar
        // menguji prosedur restore produksi, bukan tiruan terpisah.
        $service = app(BackupRestoreService::class);
        $validasi = $service->validasi($data);

        if ($validasi['tidak_dikenal'] !== []) {
            return redirect()->route('admin.backup.index')
                ->with('error', "Tabel '" . implode("', '", $validasi['tidak_dikenal']) . "' tidak valid dalam backup.");
        }

        // Batas pengaman agar file raksasa tidak menghabiskan memori/waktu.
        if ($validasi['total'] > 100000) {
            return redirect()->route('admin.backup.index')
                ->with('error', 'File backup terlalu besar (maks 100.000 baris). Bagi menjadi beberapa file.');
        }

        try {
            DB::transaction(fn () => $service->restore($data));
        } catch (\Throwable $e) {
            Log::error('Backup import gagal: ' . $e->getMessage());

            return redirect()->route('admin.backup.index')
                ->with('error', 'Restore gagal, tidak ada data yang diubah. Periksa format file backup.');
        }

        return redirect()->route('admin.backup.index')
            ->with('success', 'Data berhasil dipulihkan dari backup.');
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

        // Daftar tabel data yang dikosongkan. Diturunkan dari registry backup
        // (App\Support\BackupTables) supaya tabel yang dicadangkan selalu ikut
        // dikosongkan; hanya identitas yang benar-benar dipertahankan yang
        // dikecualikan (lihat BackupTables::tidakDikosongkan()).
        $tabelData = BackupTables::dikosongkan();

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
