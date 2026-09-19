<?php

namespace App\Services\Dapodik;

use App\Models\DapodikConfig;
use App\Models\DapodikSyncLog;
use App\Models\JadwalPelajaran;
use App\Models\Kehadiran;
use App\Models\Nilai;
use App\Models\NilaiErapot;
use App\Models\SekolahSettings;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DapodikPushService
{
    private DapodikClient $client;
    private DapodikConfig $config;

    public function __construct()
    {
        $this->config = DapodikConfig::getInstance();
        $this->client = new DapodikClient(
            npsn: $this->config->npsn ?? '',
            token: $this->config->token ?? '',
            host: $this->config->host ?? 'localhost',
            port: (int) ($this->config->port ?? 5774),
            protocol: $this->config->protocol ?? 'http',
            cfAccess: $this->config->cf_access ?? [],
        );
    }

    // ─── PUSH SEKOLAH ───────────────────────────────────────────────

    public function pushSekolah(): array
    {
        $result = ['berhasil' => 0, 'gagal' => 0, 'errors' => []];

        try {
            $sekolah = SekolahSettings::first();
            if (!$sekolah) {
                return ['berhasil' => 0, 'gagal' => 1, 'errors' => ['Data sekolah tidak ditemukan']];
            }

            $data = [
                'npsn' => $sekolah->npsn,
                'nama' => $sekolah->nama_sekolah,
                'alamat' => $sekolah->alamat,
                'telepon' => $sekolah->telepon,
                'email' => $sekolah->email,
                'kepala_sekolah' => $sekolah->kepala_sekolah,
                'alamat_jalan' => $sekolah->alamat,
                'kecamatan' => $sekolah->kecamatan,
                'kabupaten' => $sekolah->kota,
                'provinsi' => $sekolah->provinsi,
                'kode_pos' => $sekolah->kode_pos,
                'telepon' => $sekolah->telepon,
                'email' => $sekolah->email,
                'website' => $sekolah->website,
                'kepala_sekolah' => $sekolah->kepala_sekolah,
                'npsn' => $sekolah->npsn,
                'nama' => $sekolah->nama_sekolah,
                'alamat_jalan' => $sekolah->alamat,
                'rt' => '',
                'rw' => '',
                'dusun' => '',
                'desa' => '',
                'kecamatan' => $sekolah->kecamatan,
                'kabupaten' => $sekolah->kota,
                'provinsi' => $sekolah->provinsi,
                'kode_pos' => $sekolah->kode_pos,
                'telepon' => $sekolah->telepon,
                'handphone' => $sekolah->telepon,
                'email' => $sekolah->email,
                'nama_kepala_sekolah' => $sekolah->kepala_sekolah,
                'nip_kepala_sekolah' => '',
                'status_sekolah' => $sekolah->status,
                'jenis_sekolah' => $sekolah->jenis_sekolah,
                'jenjang' => $sekolah->jenjang,
                'akreditasi' => $sekolah->akreditasi,
                'status_sekolah' => $sekolah->status,
                'kecamatan' => $sekolah->kecamatan,
                'kode_pos' => $sekolah->kode_pos,
                'telepon' => $sekolah->telepon,
                'handphone' => $sekolah->telepon,
                'email' => $sekolah->email,
            ];

            $result = $this->client->pushSekolah($data);

            if (isset($result['success']) && $result['success']) {
                return ['berhasil' => 1, 'gagal' => 0, 'errors' => []];
            } else {
                return ['berhasil' => 0, 'gagal' => 1, 'errors' => ['Gagal push sekolah: ' . ($result['message'] ?? 'Unknown error')]];
            }
        } catch (\Exception $e) {
            Log::error('Push sekolah gagal: ' . $e->getMessage());
            return ['berhasil' => 0, 'gagal' => 1, 'errors' => [$e->getMessage()]];
        }
    }

    // ─── PUSH PESERTA DIDIK ───────────────────────────────────────────────

    public function pushPesertaDidik(string $semesterId): array
    {
        $berhasil = 0;
        $gagal = 0;
        $errors = [];

        $semester = \App\Models\Semester::where('semester_id', $semesterId)->first();
        $semesterKey = $semester?->id ?? $semesterId;

        $siswaList = Siswa::where('semester_id', $semesterKey)
            ->where('aktif', true)
            ->whereNull('archived_at')
            ->with('guru', 'rombel')
            ->get();

        if ($siswaList->isEmpty()) {
            return ['berhasil' => 0, 'gagal' => 0, 'errors' => ['Tidak ada siswa untuk di-push']];
        }

        foreach ($siswaList as $siswa) {
            try {
                $data = $this->mapSiswaToDapodik($siswa);
                $response = $this->client->pushPesertaDidik($data);

                if (isset($response['success']) && $response['success']) {
                    $berhasil++;
                } else {
                    $gagal++;
                    $errors[] = "Siswa {$siswa->nama_peserta_didik}: " . ($response['message'] ?? 'Unknown error');
                }
            } catch (\Exception $e) {
                $gagal++;
                $errors[] = "Siswa {$siswa->nama_peserta_didik}: " . $e->getMessage();
                Log::error("Push siswa gagal: " . $e->getMessage());
            }
        }

        return ['berhasil' => $berhasil, 'gagal' => $gagal, 'errors' => $errors];
    }

    private function mapSiswaToDapodik($siswa): array
    {
        return [
            'peserta_didik_id' => $siswa->dapodik_id,
            'nisn' => $siswa->nisn,
            'nis' => $siswa->nis,
            'nik' => $siswa->nik,
            'no_kk' => $siswa->no_kk,
            'nama' => $siswa->nama_peserta_didik,
            'jenis_kelamin' => $siswa->jenis_kelamin,
            'tempat_lahir' => $siswa->tempat_lahir,
            'tanggal_lahir' => $siswa->tanggal_lahir?->format('Y-m-d'),
            'agama' => $siswa->agama,
            'alamat' => $siswa->alamat,
            'rt' => $siswa->rt,
            'rw' => $siswa->rw,
            'dusun' => $siswa->dusun,
            'desa' => $siswa->desa,
            'kecamatan' => $siswa->kecamatan,
            'kabupaten' => $siswa->kabupaten,
            'provinsi' => $siswa->provinsi,
            'kode_pos' => $siswa->kode_pos,
            'telepon' => $siswa->telepon,
            'hp' => $siswa->telepon,
            'email' => $siswa->email,
            'anak_ke' => $siswa->anak_ke,
            'jumlah_saudara' => $siswa->jumlah_saudara,
            'tinggi_badan' => $siswa->tinggi_badan,
            'berat_badan' => $siswa->berat_badan,
            'golongan_darah' => $siswa->golongan_darah,
            'penerima_kip' => $siswa->penerima_kip,
            'no_kip' => $siswa->no_kip,
            'penerima_kps' => false,
            'no_kps' => null,
            'penerima_kip' => $siswa->penerima_kip,
            'no_kip' => $siswa->no_kip,
            'kps_pkh' => false,
            'no_kps' => null,
            'kks' => false,
            'no_kk' => $siswa->no_kk,
            'nik' => $siswa->nik,
            'anak_ke' => $siswa->anak_ke,
            'jumlah_saudara' => $siswa->jumlah_saudara,
            'tinggi_badan' => $siswa->tinggi_badan,
            'berat_badan' => $siswa->berat_badan,
            'golongan_darah' => $siswa->golongan_darah,
            'penerima_kip' => $siswa->penerima_kip,
            'no_kip' => $siswa->no_kip,
            'akta_lahir' => $siswa->no_akta_lahir,
            'kelas' => $siswa->kelas,
            'semester_id' => $siswa->semester_id,
            'rombel_id' => $siswa->rombel_id,
            'guru_id' => $siswa->guru_id,
            'nama_ayah' => $siswa->nama_ayah,
            'nik_ayah' => $siswa->nik_ayah,
            'tahun_lahir_ayah' => null,
            'pendidikan_ayah' => null,
            'pekerjaan_ayah' => null,
            'penghasilan_ayah' => null,
            'nik_ayah' => $siswa->nik_ayah,
            'no_telepon_ayah' => $siswa->telepon,
            'email_ayah' => null,
            'nama_ibu' => $siswa->nama_ibu,
            'nik_ibu' => $siswa->nik_ibu,
            'tahun_lahir_ibu' => null,
            'pendidikan_ibu' => null,
            'pekerjaan_ibu' => null,
            'penghasilan_ibu' => null,
            'no_telepon_ibu' => $siswa->telepon,
            'email_ibu' => null,
            'nama_wali' => null,
            'nik_wali' => null,
            'tahun_lahir_wali' => null,
            'pendidikan_wali' => null,
            'pekerjaan_wali' => null,
            'penghasilan_wali' => null,
            'no_telepon_wali' => null,
            'email_wali' => null,
            'status' => $siswa->aktif ? 'aktif' : 'nonaktif',
        ];
    }

    // ─── PUSH GTK (GURU/TENDIK) ──────────────────────────────────────────

    public function pushGtk(): array
    {
        $berhasil = 0;
        $gagal = 0;
        $errors = [];

        $gtkList = User::where('peran', 'guru')
            ->where('aktif', true)
            ->whereNotNull('dapodik_id')
            ->get();

        if ($gtkList->isEmpty()) {
            return ['berhasil' => 0, 'gagal' => 0, 'errors' => ['Tidak ada GTK untuk di-push']];
        }

        foreach ($gtkList as $gtk) {
            try {
                $data = [
                    'ptk_id' => $gtk->dapodik_id,
                    'nip' => $gtk->nama_pengguna,
                    'nuptk' => $gtk->nuptk,
                    'nama' => $gtk->nama_lengkap,
                    'jenis_ptk' => $gtk->peran === 'admin' ? 'tendik' : 'guru',
                    'jenis_ptk_id' => $gtk->peran === 'admin' ? '3' : '1',
                    'jabatan' => $gtk->kelas_mata_pelajaran,
                    'bidang_studi' => $gtk->kelas_mata_pelajaran,
                    'nip' => $gtk->nama_pengguna,
                    'nuptk' => $gtk->nuptk,
                    'nik' => $gtk->nik ?? null,
                    'tempat_lahir' => '',
                    'tanggal_lahir' => null,
                    'jenis_kelamin' => 'L',
                    'agama' => 'Islam',
                    'alamat' => '',
                    'rt' => '',
                    'rw' => '',
                    'dusun' => '',
                    'desa' => '',
                    'kecamatan' => '',
                    'kabupaten' => '',
                    'provinsi' => '',
                    'kode_pos' => '',
                    'telepon' => '',
                    'hp' => '',
                    'email' => $gtk->email ?? '',
                    'status_aktif' => 'aktif',
                    'status_kepegawaian' => 'PNS',
                    'pendidikan_terakhir' => '',
                    'bidang_studi' => $gtk->kelas_mata_pelajaran ?? '',
                    'pekerjaan' => 'Guru',
                    'penghasilan' => '',
                    'tmt' => null,
                    'sertifikasi' => '',
                    'pendidikan_terakhir' => '',
                    'bidang_studi' => $gtk->kelas_mata_pelajaran ?? '',
                    'pekerjaan' => 'Guru',
                    'penghasilan' => '',
                    'no_telepon' => '',
                    'email' => $gtk->email ?? '',
                    'nama_ibu' => '',
                    'nik_ibu' => null,
                    'tahun_lahir_ibu' => null,
                    'pendidikan_ibu' => '',
                    'pekerjaan_ibu' => '',
                    'penghasilan_ibu' => '',
                    'no_telepon_ibu' => '',
                    'email_ibu' => '',
                    'status_hidup' => 'hidup',
                ];

                $response = $this->client->pushGtk($data);

                if (isset($response['success']) && $response['success']) {
                    $berhasil++;
                } else {
                    $gagal++;
                    $errors[] = "Guru {$gtk->nama_lengkap}: " . ($response['message'] ?? 'Unknown error');
                }
            } catch (\Exception $e) {
                $gagal++;
                $errors[] = "Guru {$gtk->nama_lengkap}: " . $e->getMessage();
                Log::error("Push GTK gagal: " . $e->getMessage());
            }
        }

        return ['berhasil' => $berhasil, 'gagal' => $gagal, 'errors' => $errors];
    }

    // ─── PUSH ROMBEL ────────────────────────────────────────────────

    public function pushRombel(): array
    {
        $berhasil = 0;
        $gagal = 0;
        $errors = [];

        $rombels = Rombel::with('guru')
            ->whereNotNull('dapodik_id')
            ->get();

        if ($rombels->isEmpty()) {
            return ['berhasil' => 0, 'gagal' => 0, 'errors' => ['Tidak ada rombel untuk di-push']];
        }

        foreach ($rombels as $rombel) {
            try {
                $data = [
                    'rombongan_belajar_id' => $rombel->dapodik_id,
                    'nama' => $rombel->nama_rombel,
                    'tingkat' => $rombel->tingkat,
                    'kelas' => $rombel->nama_rombel,
                    'semester_id' => $rombel->semester_id,
                    'guru_id' => $rombel->guru?->dapodik_id,
                    'wali_kelas' => $rombel->guru?->dapodik_id ?? '',
                    'tahun_ajaran' => $rombel->semester?->tahun_ajaran,
                    'semester' => $rombel->semester?->semester,
                    'wali_kelas' => $rombel->guru?->dapodik_id ?? '',
                ];

                $response = $this->client->pushRombonganBelajar($data);

                if (isset($response['success']) && $response['success']) {
                    $berhasil++;
                } else {
                    $gagal++;
                    $errors[] = "Rombel {$rombel->nama_rombel}: " . ($response['message'] ?? 'Unknown error');
                }
            } catch (\Exception $e) {
                $gagal++;
                $errors[] = "Rombel {$rombel->nama_rombel}: " . $e->getMessage();
            }
        }

        return ['berhasil' => $berhasil, 'gagal' => $gagal, 'errors' => $errors];
    }

    // ─── PUSH JADWAL ────────────────────────────────────────────────

    public function pushJadwal(): array
    {
        $berhasil = 0;
        $gagal = 0;
        $errors = [];

        $jadwals = JadwalPelajaran::with('guru', 'rombonganBelajar')
            ->whereNotNull('dapodik_id')
            ->get();

        if ($jadwals->isEmpty()) {
            return ['berhasil' => 0, 'gagal' => 0, 'errors' => ['Tidak ada jadwal untuk di-push']];
        }

        foreach ($jadwals as $jadwal) {
            try {
                $data = [
                    'jadwal_id' => $jadwal->dapodik_id,
                    'rombongan_belajar_id' => $jadwal->rombonganBelajar?->dapodik_id,
                    'guru_id' => $jadwal->guru?->dapodik_id,
                    'mata_pelajaran' => $jadwal->mata_pelajaran,
                    'hari' => $jadwal->hari,
                    'jam_mulai' => $jadwal->jam_mulai,
                    'jam_selesai' => $jadwal->jam_selesai,
                    'ruangan' => $jadwal->ruangan,
                    'kelas' => $jadwal->kelas,
                    'mata_pelajaran' => $jadwal->mata_pelajaran,
                    'hari' => $jadwal->hari,
                    'jam_mulai' => $jadwal->jam_mulai,
                    'jam_selesai' => $jadwal->jam_selesai,
                    'ruangan' => $jadwal->ruangan,
                ];

                $response = $this->client->pushJadwal($data);

                if (isset($response['success']) && $response['success']) {
                    $berhasil++;
                } else {
                    $gagal++;
                    $errors[] = "Jadwal {$jadwal->mata_pelajaran}: " . ($response['message'] ?? 'Unknown error');
                }
            } catch (\Exception $e) {
                $gagal++;
                $errors[] = "Jadwal {$jadwal->mata_pelajaran}: " . $e->getMessage();
            }
        }

        return ['berhasil' => $berhasil, 'gagal' => $gagal, 'errors' => $errors];
    }

    // ─── PUSH NILAI RAPOR ───────────────────────────────────────────

    public function pushNilaiRapor(string $semesterId): array
    {
        $berhasil = 0;
        $gagal = 0;
        $errors = [];

        $semester = \App\Models\Semester::where('semester_id', $semesterId)->first();
        $siswaIds = $semester
            ? Siswa::where('semester_id', $semester->id)->pluck('id')
            : collect();

        $nilais = NilaiErapot::with('siswa')
            ->when($siswaIds->isNotEmpty(), fn ($q) => $q->whereIn('siswa_id', $siswaIds))
            ->when($semester, fn ($q) => $q->where('tahun_ajaran', $semester->tahun_ajaran))
            ->get();

        if ($nilais->isEmpty()) {
            return ['berhasil' => 0, 'gagal' => 0, 'errors' => ['Tidak ada nilai rapor untuk di-push']];
        }

        foreach ($nilais as $nilai) {
            try {
                $data = [
                    'peserta_didik_id' => $nilai->siswa?->dapodik_id,
                    'semester_id' => $semesterId,
                    'mata_pelajaran' => $nilai->mata_pelajaran,
                    'kelas' => $nilai->kelas,
                    'nilai_akhir' => $nilai->nilai_akhir,
                    'predikat' => $nilai->predikat,
                    'deskripsi' => $nilai->deskripsi_capaian,
                ];

                $response = $this->client->pushNilaiRapor($data);

                if (isset($response['success']) && $response['success']) {
                    $berhasil++;
                } else {
                    $gagal++;
                    $errors[] = "Nilai {$nilai->mata_pelajaran} (siswa #{$nilai->siswa_id}): " . ($response['message'] ?? 'Unknown error');
                }
            } catch (\Exception $e) {
                $gagal++;
                $errors[] = "Nilai {$nilai->mata_pelajaran} (siswa #{$nilai->siswa_id}): " . $e->getMessage();
                Log::error("Push nilai rapor gagal: " . $e->getMessage());
            }
        }

        return ['berhasil' => $berhasil, 'gagal' => $gagal, 'errors' => $errors];
    }

    // ─── PUSH KEHADIRAN ─────────────────────────────────────────────

    public function pushKehadiran(string $semesterId): array
    {
        $berhasil = 0;
        $gagal = 0;
        $errors = [];

        $semester = \App\Models\Semester::where('semester_id', $semesterId)->first();
        $siswaIds = $semester
            ? Siswa::where('semester_id', $semester->id)->pluck('id')
            : collect();

        $kehadiranList = Kehadiran::with('siswa')
            ->when($siswaIds->isNotEmpty(), fn ($q) => $q->whereIn('siswa_id', $siswaIds))
            ->get();

        if ($kehadiranList->isEmpty()) {
            return ['berhasil' => 0, 'gagal' => 0, 'errors' => ['Tidak ada data kehadiran untuk di-push']];
        }

        foreach ($kehadiranList as $hadir) {
            try {
                $data = [
                    'peserta_didik_id' => $hadir->siswa?->dapodik_id,
                    'semester_id' => $semesterId,
                    'tanggal' => $hadir->tanggal?->format('Y-m-d'),
                    'status' => $hadir->status,
                    'keterangan' => $hadir->keterangan,
                ];

                $response = $this->client->pushKehadiran($data);

                if (isset($response['success']) && $response['success']) {
                    $berhasil++;
                } else {
                    $gagal++;
                    $errors[] = "Kehadiran #{$hadir->id}: " . ($response['message'] ?? 'Unknown error');
                }
            } catch (\Exception $e) {
                $gagal++;
                $errors[] = "Kehadiran #{$hadir->id}: " . $e->getMessage();
                Log::error("Push kehadiran gagal: " . $e->getMessage());
            }
        }

        return ['berhasil' => $berhasil, 'gagal' => $gagal, 'errors' => $errors];
    }
}