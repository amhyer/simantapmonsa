<?php

namespace App\Support;

use App\Models\Aktivitas;
use App\Models\Catatan;
use App\Models\Dimensi;
use App\Models\Ekstrakurikuler;
use App\Models\HasilKuis;
use App\Models\JadwalPelajaran;
use App\Models\Kebiasaan;
use App\Models\KegiatanKokurikuler;
use App\Models\Kehadiran;
use App\Models\KelompokKokurikuler;
use App\Models\Kuis;
use App\Models\MataPelajaran;
use App\Models\Materi;
use App\Models\Nilai;
use App\Models\NilaiErapot;
use App\Models\OrangTuaSiswa;
use App\Models\PengaturanGuru;
use App\Models\Ptk;
use App\Models\RingkasanGuru;
use App\Models\Rombel;
use App\Models\SekolahSettings;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TanggalRapor;
use App\Models\TemaKokurikuler;
use App\Models\TranskripNilai;
use App\Models\User;

/**
 * Satu sumber kebenaran untuk cakupan backup (export) dan restore (import).
 *
 * PERBAIKAN BUG: sebelumnya export/import hanya menangani 14 tabel, sedangkan
 * tombol "Hapus Semua Data" (BackupController::wipeAll) mengosongkan 31 tabel
 * data — sehingga nilai e-Rapor, transkrip, PTK, rombel, jadwal, mapel,
 * ekstrakurikuler, dan kokurikuler hilang permanen setelah wipe + restore.
 *
 * Urutan entri = urutan restore; tabel induk WAJIB lebih dulu agar foreign key
 * terpenuhi saat import.
 *
 * 'class'  = kelas model Eloquent; null untuk tabel pivot tanpa model.
 * 'except' = kolom yang tidak dicadangkan (rahasia / berkas biner besar).
 */
class BackupTables
{
    /**
     * @return array<string, array{class: class-string<\Illuminate\Database\Eloquent\Model>|null, except: array<int, string>}>
     */
    public static function semua(): array
    {
        return [
            // ── Identitas & referensi induk ─────────────────────────────
            'users' => ['class' => User::class, 'except' => ['kata_sandi', 'remember_token', 'dapodik_id', 'rekaman']],
            'semesters' => ['class' => Semester::class, 'except' => []],
            'sekolah_settings' => ['class' => SekolahSettings::class, 'except' => []],
            'ptk' => ['class' => Ptk::class, 'except' => []],
            'rombel' => ['class' => Rombel::class, 'except' => []],
            'mata_pelajaran' => ['class' => MataPelajaran::class, 'except' => []],

            // ── Peserta didik & relasinya ───────────────────────────────
            'siswa' => ['class' => Siswa::class, 'except' => ['rekaman', 'foto', 'nik', 'no_kk']],
            'orang_tua_siswa' => ['class' => OrangTuaSiswa::class, 'except' => []],
            'ekstrakurikuler' => ['class' => Ekstrakurikuler::class, 'except' => []],

            // ── Pembelajaran & penilaian ────────────────────────────────
            'jadwal_pelajaran' => ['class' => JadwalPelajaran::class, 'except' => []],
            'pengaturan_guru' => ['class' => PengaturanGuru::class, 'except' => []],
            'ringkasan_guru' => ['class' => RingkasanGuru::class, 'except' => []],
            'materi' => ['class' => Materi::class, 'except' => []],
            'kuis' => ['class' => Kuis::class, 'except' => []],
            'nilai' => ['class' => Nilai::class, 'except' => []],
            'nilai_erapor' => ['class' => NilaiErapot::class, 'except' => []],
            'transkrip_nilai' => ['class' => TranskripNilai::class, 'except' => []],
            'kehadiran' => ['class' => Kehadiran::class, 'except' => []],
            'hasil_kuis' => ['class' => HasilKuis::class, 'except' => []],
            'catatan' => ['class' => Catatan::class, 'except' => []],
            'dimensi' => ['class' => Dimensi::class, 'except' => []],
            'kebiasaan' => ['class' => Kebiasaan::class, 'except' => []],
            // Tabel penilaian tanpa model Eloquent (class null → query builder).
            'capaian_pembelajaran' => ['class' => null, 'except' => []],
            'nilai_mapel' => ['class' => null, 'except' => []],
            'nilai_cp' => ['class' => null, 'except' => []],

            // ── Ekstrakurikuler & kokurikuler ───────────────────────────
            'siswa_ekstrakurikuler' => ['class' => null, 'except' => []],
            'tema_kokurikuler' => ['class' => TemaKokurikuler::class, 'except' => []],
            'kegiatan_kokurikuler' => ['class' => KegiatanKokurikuler::class, 'except' => []],
            'kelompok_kokurikuler' => ['class' => KelompokKokurikuler::class, 'except' => []],
            'anggota_kelompok' => ['class' => null, 'except' => []],

            // ── Lain-lain ───────────────────────────────────────────────
            'tanggal_rapor' => ['class' => TanggalRapor::class, 'except' => []],
            'aktivitas' => ['class' => Aktivitas::class, 'except' => []],
        ];
    }

    /**
     * Tabel yang SENGAJA tidak dicadangkan karena isinya dapat dibuat ulang
     * (log & cache sinkronisasi). Didaftarkan eksplisit agar selisih antara
     * tabel yang di-wipe dan yang dicadangkan selalu disengaja, bukan bug.
     *
     * Diperiksa oleh tools/verifikasi-perbaikan.php.
     *
     * @return array<int, string>
     */
    public static function tidakDicadangkan(): array
    {
        return [
            'dapodik_sync_logs',   // riwayat sinkronisasi (dapat dibuat ulang)
            'dapodik_import_logs', // riwayat import (dapat dibuat ulang)
            'dapodik_data_cache',  // cache data Dapodik (diisi ulang saat sync)
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function tabel(): array
    {
        return array_keys(self::semua());
    }

    public static function model(string $table): ?string
    {
        return self::semua()[$table]['class'] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public static function except(string $table): array
    {
        return self::semua()[$table]['except'] ?? [];
    }
}
