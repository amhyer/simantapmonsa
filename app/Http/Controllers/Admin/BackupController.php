<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ExportBackupJob;
use App\Models\Aktivitas;
use App\Models\SekolahSettings;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use App\Services\Backup\BackupDirectory;
use App\Services\Backup\BackupJsonStreamReader;
use App\Services\Backup\EraporXspBackupService;
use App\Services\BackupRestoreService;
use App\Services\Navigation\SchoolDataAvailabilityService;
use App\Services\NilaiErapotAuditService;
use App\Support\BackupTables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BackupController extends Controller
{
    /** @var array<string, list<string>> */
    private array $restoreColumns = [];

    /** @var array<string, class-string<Model>|null> */
    private array $restoreModels = [];

    /**
     * Tentukan konteks isolasi sekolah aktif.
     *
     * @return array{0: ?int, 1: bool} [tenantId, isMultiTenant]
     */
    private function resolveTenantScope(): array
    {
        $tenantId = auth()->user()?->tenant_id ?? TenantContext::id();
        $hasTenants = Schema::hasTable('tenants') && Tenant::hasAnyTenant();
        $isMultiTenant = config('simantap.multi_tenant_enabled', false) || $tenantId !== null || $hasTenants;

        if ($isMultiTenant && $tenantId === null) {
            abort(403, 'Akun admin belum terhubung ke sekolah.');
        }

        return [$tenantId !== null ? (int) $tenantId : null, $isMultiTenant];
    }

    public function index()
    {
        [$tenantId, $isMultiTenant] = $this->resolveTenantScope();

        // Disk & folder mengikuti config/backup.php dan diisolasi per tenant
        // (`backups/tenant-{id}`) agar admin sekolah hanya melihat backup miliknya.
        $disk = BackupDirectory::disk();
        $direktori = BackupDirectory::forTenant($tenantId);

        $backups = $disk->files($direktori);
        $backupList = collect($backups)->map(function ($file) use ($disk) {
            return [
                'name' => basename($file),
                'size' => $disk->size($file),
                'date' => $disk->lastModified($file),
            ];
        })->sortByDesc('date')->values();

        $namaSekolah = null;
        if ($tenantId !== null && Schema::hasTable('tenants')) {
            $namaSekolah = Tenant::where('id', $tenantId)->value('nama_sekolah');
        }
        if (! $namaSekolah && Schema::hasTable('sekolah_settings')) {
            $namaSekolah = SekolahSettings::first()?->nama_sekolah;
        }

        return view('admin.backup.index', compact('backupList', 'isMultiTenant', 'namaSekolah'));
    }

    public function export()
    {
        [$tenantId] = $this->resolveTenantScope();

        if (request()->expectsJson()) {
            // Job query + tulis backup sendiri secara streaming (hemat memori).
            // Hanya membawa scalar tenantId (tanpa model Eloquent).
            dispatch(new ExportBackupJob($tenantId));

            return response()->json([
                'success' => true,
                'message' => 'Export backup dimulai secara asynchronous. File akan siap di folder backups dalam waktu singkat.',
            ]);
        }

        // Untuk pengiriman form langsung dari halaman Admin, eksekusi secara
        // sinkron agar berkas .json langsung muncul di Riwayat Backup meskipun
        // queue worker tidak sedang berjalan.
        dispatch_sync(new ExportBackupJob($tenantId));

        return redirect()->route('admin.backup.index')
            ->with('success', 'Backup data sekolah SIMANTAP (.json) berhasil dibuat dan tersimpan di Riwayat Backup di bawah.');
    }

    public function exportEraporXsp(EraporXspBackupService $xspService)
    {
        [$tenantId] = $this->resolveTenantScope();

        try {
            $disk = BackupDirectory::disk();
            $direktori = BackupDirectory::forTenant($tenantId);
            $disk->makeDirectory($direktori);

            $tempXsp = $xspService->exportToXsp($tenantId);
            $fileName = basename($tempXsp);
            $targetPath = $direktori.'/'.$fileName;
            // Salin sebagai stream: arsip .xsp bisa puluhan MB dan tidak perlu masuk memori.
            $handle = fopen($tempXsp, 'rb');
            if ($handle === false) {
                throw new \RuntimeException('File .xsp hasil ekspor tidak dapat dibaca.');
            }
            try {
                $disk->put($targetPath, $handle);
            } finally {
                fclose($handle);
            }

            return response()->download($tempXsp, $fileName)->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            Log::error('Export backup .xsp gagal: '.$e->getMessage());

            return redirect()->route('admin.backup.index')
                ->with('error', 'Gagal membuat backup e-Rapor (.xsp): '.$e->getMessage());
        }
    }

    public function download(string $filename)
    {
        [$tenantId] = $this->resolveTenantScope();

        $safeName = basename($filename);
        if ($safeName === '' || $safeName !== $filename || ! preg_match('/^[a-zA-Z0-9._-]+$/', $safeName)) {
            abort(404);
        }

        $disk = BackupDirectory::disk();
        $direktori = BackupDirectory::forTenant($tenantId);
        $relPath = $direktori.'/'.$safeName;

        if (! $disk->exists($relPath)) {
            abort(404, 'File backup tidak ditemukan.');
        }

        return response()->download($disk->path($relPath), $safeName);
    }

    public function import(Request $request)
    {
        [$tenantId] = $this->resolveTenantScope();

        $request->validate([
            'backup_file' => 'required|file|max:102400',
        ]);

        $uploadedFile = $request->file('backup_file');
        $clientExt = strtolower((string) $uploadedFile->getClientOriginalExtension());
        $realPath = $uploadedFile->getRealPath();

        // Deteksi apakah file adalah arsip .xsp e-Rapor (ekstensi .xsp atau header ZIP PK\x03\x04)
        $magic = is_string($realPath) && is_file($realPath) ? (string) file_get_contents($realPath, false, null, 0, 4) : '';
        if ($clientExt === 'xsp' || str_starts_with($magic, "PK\x03\x04")) {
            try {
                $stats = app(EraporXspBackupService::class)->restoreFromXsp((string) $realPath, $tenantId);
                $ringkasan = sprintf(
                    'Database e-Rapor (.xsp) "%s" (NPSN %s) berhasil dipulihkan: %d semester, %d PTK/Guru, %d rombel, %d mapel, %d siswa, %d pembelajaran, %d TP, %d nilai rapor, %d transkrip, %d catatan walas, dan %d penilaian P5.',
                    $stats['nama_sekolah'],
                    $stats['npsn'],
                    $stats['semesters'],
                    $stats['ptk'],
                    $stats['rombel'],
                    $stats['mata_pelajaran'],
                    $stats['siswa'],
                    $stats['jadwal_pelajaran'],
                    $stats['capaian_pembelajaran'],
                    $stats['nilai_erapor'],
                    $stats['transkrip_nilai'],
                    $stats['catatan'],
                    $stats['p5_penilaian']
                );

                return redirect()->route('admin.backup.index')->with('success', $ringkasan);
            } catch (\Throwable $e) {
                Log::error('Restore backup e-Rapor (.xsp) gagal: '.$e->getMessage());

                return redirect()->route('admin.backup.index')
                    ->with('error', 'Restore file e-Rapor (.xsp) gagal: '.$e->getMessage());
            }
        }

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

        $restoreService = app(BackupRestoreService::class);

        try {
            NilaiErapotAuditService::tanpaAudit(function () use ($spools, $restoreService, $tenantId): void {
                DB::transaction(function () use ($spools, $restoreService, $tenantId): void {
                    foreach (BackupTables::tabel() as $table) {
                        if (! isset($spools[$table])) {
                            continue;
                        }

                        rewind($spools[$table]);
                        while (($line = fgets($spools[$table])) !== false) {
                            $record = json_decode(rtrim($line, "\r\n"), true, 512, JSON_THROW_ON_ERROR);
                            $restoreService->restoreRecord($table, $record, $tenantId);
                        }
                        $restoreService->sinkronkanSequence($table);
                    }
                });
            });
        } catch (\UnexpectedValueException $e) {
            Log::warning('Backup import ditolak karena isolasi tenant: '.$e->getMessage(), ['tenant_id' => $tenantId]);

            return redirect()->route('admin.backup.index')
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Backup import gagal: '.$e->getMessage());

            return redirect()->route('admin.backup.index')
                ->with('error', 'Restore gagal, tidak ada data yang diubah. Periksa format file backup.');
        } finally {
            $this->closeSpools($spools);
        }

        SchoolDataAvailabilityService::forget($tenantId);

        return redirect()->route('admin.backup.index')
            ->with('success', 'Data sekolah berhasil dipulihkan dari backup.');
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

        Log::info('Wipe data diminta oleh: '.(auth()->user()?->nama_pengguna ?? '?').' IP: '.request()->ip());

        // Daftar wipe diturunkan dari registry backup, dengan pengecualian
        // eksplisit untuk tabel identitas yang harus tetap ada.
        // Urutan dibalik (anak -> induk) agar penghapusan aman tanpa
        // mematikan FK constraint pada PostgreSQL non-superuser.
        $tabelData = array_reverse(BackupTables::dikosongkan());

        $dihapus = [];
        $tenantId = auth()->user()?->tenant_id ?? TenantContext::id();
        $hasTenants = Schema::hasTable('tenants') && Tenant::hasAnyTenant();
        $isMultiTenant = config('simantap.multi_tenant_enabled', false) || $tenantId !== null || $hasTenants;

        if ($isMultiTenant && $tenantId === null) {
            abort(403, 'Akun admin belum terhubung ke sekolah.');
        }

        // Seluruh wipe berjalan dalam SATU transaksi: gagal di tabel mana pun
        // membatalkan semuanya (tidak ada DB setengah terhapus). Snapshot ID diambil
        // di dalam transaksi dengan lockForUpdate() agar baris baru tidak lolos di tengah wipe.
        $driver = DB::getDriverName();
        try {
            DB::transaction(function () use ($tabelData, $isMultiTenant, $tenantId, $driver, &$dihapus): void {
                $siswaIds = ($isMultiTenant && Schema::hasTable('siswa') && Schema::hasColumn('siswa', 'tenant_id'))
                    ? DB::table('siswa')->where('tenant_id', $tenantId)->lockForUpdate()->pluck('id')
                    : collect();
                $userIds = ($isMultiTenant && Schema::hasTable('users') && Schema::hasColumn('users', 'tenant_id'))
                    ? DB::table('users')->where('tenant_id', $tenantId)->lockForUpdate()->pluck('id')
                    : collect();

                if ($driver !== 'pgsql') {
                    Schema::disableForeignKeyConstraints();
                }
                try {
                    foreach ($tabelData as $tabel) {
                        if (! Schema::hasTable($tabel)) {
                            continue;
                        }
                        if ($isMultiTenant) {
                            // DILARANG mengeksekusi TRUNCATE global saat multi-sekolah aktif.
                            if (Schema::hasColumn($tabel, 'tenant_id')) {
                                DB::table($tabel)->where('tenant_id', $tenantId)->delete();
                            } elseif (Schema::hasColumn($tabel, 'siswa_id')) {
                                DB::table($tabel)->whereIn('siswa_id', $siswaIds)->delete();
                            } elseif (Schema::hasColumn($tabel, 'guru_id')) {
                                DB::table($tabel)->whereIn('guru_id', $userIds)->delete();
                            } elseif (Schema::hasColumn($tabel, 'user_id')) {
                                DB::table($tabel)->whereIn('user_id', $userIds)->delete();
                            } else {
                                // Tabel referensi/anak tanpa kolom kepemilikan langsung dilewati
                                // agar data sekolah lain tetap 100% utuh.
                                continue;
                            }
                        } else {
                            DB::table($tabel)->delete();
                        }
                        $dihapus[] = $tabel;
                    }
                } finally {
                    if ($driver !== 'pgsql') {
                        Schema::enableForeignKeyConstraints();
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::error('Wipe data gagal, transaksi dibatalkan: '.$e->getMessage());

            return redirect()->route('admin.backup.index')->with(
                'error',
                'Wipe dibatalkan — tidak ada data yang diubah. Periksa log server untuk detail.'
            );
        }

        if (count($dihapus) === 0) {
            return redirect()->route('admin.backup.index')->with(
                'error',
                'Tidak ada data yang dihapus. Tabel tidak ditemukan atau koneksi basis data bermasalah.'
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
                'deskripsi' => 'Mengosongkan '.count($dihapus).' tabel: '.implode(', ', $dihapus),
                'tabel_terkait' => null,
            ]);
        } catch (\Exception $e) {
            Log::warning('Gagal catat aktivitas hapus data: '.$e->getMessage());
        }

        SchoolDataAvailabilityService::forget($tenantId !== null ? (int) $tenantId : null);

        $pesan = 'Berhasil mengosongkan '.count($dihapus).' tabel data. Akun pengguna, semester, identitas sekolah, dan API key dipertahankan.';

        return redirect()->route('admin.backup.index')->with('success', $pesan);
    }
}
