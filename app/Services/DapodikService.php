<?php

namespace App\Services;

use App\Models\Siswa;
use App\Models\User;
use App\Models\OrangTuaSiswa;
use Illuminate\Support\Facades\DB;

class DapodikService
{
    public function syncGTK(array $gtkData): array
    {
        $berhasil = 0;
        $gagal = 0;
        $errors = [];

        foreach ($gtkData as $index => $gtk) {
            try {
                $user = null;
                if (!empty($gtk['nip'])) {
                    $user = User::where('nama_pengguna', $gtk['nip'])->first();
                }
                if (!$user && !empty($gtk['nuptk'])) {
                    $user = User::where('nama_pengguna', $gtk['nuptk'])->first();
                }

                if (!$user) {
                    $user = User::create([
                        'uuid' => \Illuminate\Support\Str::uuid(),
                        'nama_lengkap' => $gtk['nama'],
                        'nama_pengguna' => $gtk['nip'] ?? $gtk['nuptk'] ?? 'gtk' . rand(1000, 9999),
                        'kata_sandi' => \Illuminate\Support\Facades\Hash::make('guru123'),
                        'peran' => 'guru',
                        'aktif' => true,
                        'terhubung_dengan' => [],
                        'kelas_mata_pelajaran' => $gtk['mata_pelajaran'] ?? null,
                    ]);
                } else {
                    $user->update([
                        'nama_lengkap' => $gtk['nama'],
                        'kelas_mata_pelajaran' => $gtk['mata_pelajaran'] ?? $user->kelas_mata_pelajaran,
                    ]);
                }

                $user->update([
                    'rekaman' => array_merge($user->rekaman ?? [], [
                        'gtk_id' => $gtk['gtk_id'] ?? null,
                        'nip' => $gtk['nip'] ?? null,
                        'nuptk' => $gtk['nuptk'] ?? null,
                        'nik' => $gtk['nik'] ?? null,
                        'jenis_ptk' => $gtk['jenis_ptk'] ?? null,
                        'status_kepegawaian' => $gtk['status_kepegawaian'] ?? null,
                        'pendidikan_terakhir' => $gtk['pendidikan_terakhir'] ?? null,
                        'bidang_studi' => $gtk['bidang_studi'] ?? null,
                        'sertifikasi' => $gtk['sertifikasi'] ?? null,
                        'tmt' => $gtk['tmt'] ?? null,
                        'jam_mengajar' => $gtk['jam_mengajar'] ?? null,
                    ]),
                ]);

                $berhasil++;
            } catch (\Exception $e) {
                $gagal++;
                $errors[] = "GTK " . ($gtk['nama'] ?? "baris " . ($index + 1)) . ": " . $e->getMessage();
            }
        }

        return ['berhasil' => $berhasil, 'gagal' => $gagal, 'errors' => $errors];
    }

    public function autoAssignKelas(string $guruId, ?string $kelas = null): int
    {
        $siswa = Siswa::whereNull('guru_id')->orWhere('guru_id', 0)->get();
        $count = 0;

        foreach ($siswa as $s) {
            if ($kelas && $s->kelas !== $kelas) continue;
            $guru = User::find($guruId);
            if (!$guru) continue;
            $s->update(['guru_id' => $guru->id, 'nama_guru' => $guru->nama_lengkap]);
            $count++;
        }

        return $count;
    }

    public function getStatistik(): array
    {
        return [
            'total_siswa' => Siswa::count(),
            'siswa_dapodik' => Siswa::where('nis', 'like', 'DAPODIK-%')->count(),
            'siswa_tanpa_kelas' => Siswa::whereNull('guru_id')->orWhere('guru_id', 0)->count(),
            'siswa_dengan_orang_tua' => Siswa::whereHas('orangTua')->count(),
            'siswa_penerima_kip' => Siswa::where('penerima_kip', true)->count(),
            'siswa_dengan_foto' => Siswa::whereNotNull('foto')->count(),
        ];
    }
}
