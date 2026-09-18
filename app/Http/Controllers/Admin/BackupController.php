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
use Illuminate\Support\Facades\Storage;

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

        if (!$data) {
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

        foreach ($data as $table => $records) {
            if (!is_array($records) || empty($records)) {
                continue;
            }

            if (!isset($fillableMap[$table])) {
                continue;
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
                continue;
            }

            foreach ($records as $record) {
                $filtered = array_intersect_key($record, array_flip($fillable));
                if (isset($filtered['id']) && $filtered['id'] !== null) {
                    $modelClass::updateOrCreate(
                        ['id' => $filtered['id']],
                        $filtered
                    );
                } else {
                    $modelClass::create($filtered);
                }
            }
        }

        return redirect()->route('admin.backup.index')
            ->with('success', 'Data berhasil dipulihkan dari backup.');
    }
}
