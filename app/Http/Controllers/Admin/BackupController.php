<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Siswa;
use App\Models\Materi;
use App\Models\Kuis;
use App\Models\Nilai;
use App\Models\Kehadiran;
use App\Models\HasilKuis;
use App\Models\Catatan;
use App\Models\Dimensi;
use App\Models\Kebiasaan;
use App\Models\PengaturanGuru;
use App\Models\RingkasanGuru;
use App\Models\SekolahSettings;
use App\Models\Aktivitas;
use App\Jobs\ExportBackupJob;
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
        $backups = Storage::disk('local')->files('backups');
        $backupList = collect($backups)->map(function ($file) {
            return [
                'name' => basename($file),
                'size' => Storage::disk('local')->size($file),
                'date' => Storage::disk('local')->lastModified($file),
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

        // Validasi struktur dasar backup
        $validTables = ['users', 'siswa', 'materi', 'kuis', 'nilai', 'kehadiran', 'hasil_kuis', 'catatan', 'dimensi', 'kebiasaan', 'pengaturan_guru', 'ringkasan_guru', 'sekolah_settings', 'aktivitas'];
        foreach (array_keys($data) as $table) {
            if (!in_array($table, $validTables)) {
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

        $fillableMap = [
            'users' => ['id', 'uuid', 'nama_lengkap', 'nama_pengguna', 'kata_sandi', 'peran', 'terhubung_dengan', 'kelas_mata_pelajaran', 'aktif', 'terakhir_masuk', 'created_at', 'updated_at'],
            'siswa' => ['id', 'uuid', 'dapodik_id', 'guru_id', 'nama_guru', 'semester_id', 'nis', 'nisn', 'nama_peserta_didik', 'kelas', 'jenis_kelamin', 'nama_orang_tua', 'aktif', 'status_siswa', 'rekaman', 'nik', 'no_kk', 'agama', 'tempat_lahir', 'tanggal_lahir', 'alamat', 'telepon', 'penerima_kip', 'no_kip', 'created_at', 'updated_at'],
            'materi' => ['id', 'uuid', 'guru_id', 'nama_guru', 'tanggal', 'judul', 'mata_pelajaran', 'kelas', 'tujuan_pembelajaran', 'materi_pokok', 'tautan_sumber', 'pm', 'dimensi', 'pengalaman', 'sematkan', 'rekaman', 'created_at', 'updated_at'],
            'kuis' => ['id', 'uuid', 'guru_id', 'nama_guru', 'tanggal', 'judul', 'mata_pelajaran', 'kelas', 'mode', 'sumber', 'tautan_soal', 'materi_id', 'kkm', 'batas_waktu', 'aktif', 'jumlah_soal', 'stimulus', 'jenis', 'petunjuk', 'cara_nilai', 'soal', 'rekaman', 'created_at', 'updated_at'],
            'nilai' => ['id', 'uuid', 'guru_id', 'siswa_id', 'nama_guru', 'nama_siswa', 'tanggal', 'jenis', 'judul_penilaian', 'mata_pelajaran', 'nilai', 'kuis_id', 'rekaman', 'created_at', 'updated_at'],
            'kehadiran' => ['id', 'uuid', 'guru_id', 'siswa_id', 'tanggal', 'status', 'keterangan', 'rekaman', 'created_at', 'updated_at'],
            'hasil_kuis' => ['id', 'kuis_id', 'siswa_id', 'jawaban', 'skor', 'created_at', 'updated_at'],
            'catatan' => ['id', 'uuid', 'guru_id', 'siswa_id', 'nama_guru', 'nama_siswa', 'tanggal', 'jenis', 'catatan', 'rekaman', 'created_at', 'updated_at'],
            'dimensi' => ['id', 'uuid', 'guru_id', 'siswa_id', 'nama_guru', 'nama_siswa', 'no_dimensi', 'dimensi', 'skor', 'predikat', 'catatan', 'rekaman', 'created_at', 'updated_at'],
            'kebiasaan' => ['id', 'uuid', 'siswa_id', 'tanggal', 'bangun_pagi', 'beribadah', 'berolahraga', 'makan_sehat', 'gemar_belajar', 'bermasyarakat', 'tidur_cepat', 'catatan_orang_tua', 'diisi_oleh', 'waktu_simpan', 'rekaman', 'created_at', 'updated_at'],
            'pengaturan_guru' => ['id', 'guru_id', 'nama_guru', 'kelas', 'kkm', 'semester', 'tahun_pelajaran', 'pembaruan', 'created_at', 'updated_at'],
            'ringkasan_guru' => ['id', 'guru_id', 'nama_guru', 'mata_pelajaran', 'kelas', 'kkm', 'semester', 'tahun_pelajaran', 'jumlah_siswa', 'rata_rata', 'tuntas', 'belum_tuntas', 'ketuntasan_persen', 'kehadiran_persen', 'predikat_a', 'predikat_b', 'predikat_c', 'predikat_d', 'pembaruan', 'created_at', 'updated_at'],
            'sekolah_settings' => ['id', 'guru_id', 'npsn', 'nama_sekolah', 'alamat', 'telepon', 'email', 'status', 'pengaturan', 'akreditasi', 'kecamatan', 'created_at', 'updated_at'],
            'aktivitas' => ['id', 'uuid', 'guru_id', 'nama_guru', 'jenis', 'judul', 'deskripsi', 'tabel_terkait', 'record_id', 'data_lama', 'data_baru', 'created_at', 'updated_at'],
        ];

        try {
            DB::transaction(function () use ($data, $fillableMap) {
                foreach ($data as $table => $records) {
                    $this->restoreTable($table, $records, $fillableMap);
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
     * Restore satu tabel backup. Dipanggil di dalam DB::transaction agar
     * gagal di tengah tidak menyisakan data parcial (inkonsisten).
     */
    protected function restoreTable(string $table, mixed $records, array $fillableMap): void
    {
        if (!is_array($records) || empty($records)) {
            return;
        }

        if (!isset($fillableMap[$table])) {
            return;
        }

        $fillable = $fillableMap[$table];
        $modelClass = match($table) {
            'users' => User::class,
            'siswa' => Siswa::class,
            'materi' => Materi::class,
            'kuis' => Kuis::class,
            'nilai' => Nilai::class,
            'kehadiran' => Kehadiran::class,
            'hasil_kuis' => HasilKuis::class,
            'catatan' => Catatan::class,
            'dimensi' => Dimensi::class,
            'kebiasaan' => Kebiasaan::class,
            'pengaturan_guru' => PengaturanGuru::class,
            'ringkasan_guru' => RingkasanGuru::class,
            'sekolah_settings' => SekolahSettings::class,
            'aktivitas' => Aktivitas::class,
            default => null,
        };

        if (!$modelClass) {
            return;
        }

        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            $filtered = array_intersect_key($record, array_flip($fillable));
            if (isset($filtered['id']) && $filtered['id'] !== null) {
                $modelClass::updateOrCreate(['id' => $filtered['id']], $filtered);
            } else {
                $modelClass::create($filtered);
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
