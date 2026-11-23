<?php

namespace App\Services;

use App\Models\Siswa;
use App\Models\User;
use App\Support\InitialPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
                if (! empty($gtk['nip'])) {
                    $user = User::where('nama_pengguna', $gtk['nip'])->first();
                }
                if (! $user && ! empty($gtk['nuptk'])) {
                    $user = User::where('nama_pengguna', $gtk['nuptk'])->first();
                }

                if (! $user) {
                    $username = $gtk['nip'] ?? $gtk['nuptk'] ?? ('gtk_'.Str::lower(Str::random(8)));
                    $baseUsername = $username;
                    $attempts = 0;
                    while (User::forSchool()->withTrashed()->where('nama_pengguna', $username)->exists()) {
                        $attempts++;
                        $username = $attempts <= 10
                            ? $baseUsername.'.'.$attempts
                            : 'gtk_'.Str::lower(Str::random(10));
                        if ($attempts > 25) {
                            throw new \RuntimeException('Gagal menghasilkan nama pengguna GTK yang unik.');
                        }
                    }

                    $user = User::create([
                        'uuid' => Str::uuid(),
                        'nama_lengkap' => $gtk['nama'],
                        'nama_pengguna' => $username,
                        'kata_sandi' => Hash::make(InitialPassword::generate()),
                        'peran' => 'guru',
                        'aktif' => true,
                        'force_password_change' => true,
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
                $errors[] = 'GTK '.($gtk['nama'] ?? 'baris '.($index + 1)).': '.$e->getMessage();
            }
        }

        return ['berhasil' => $berhasil, 'gagal' => $gagal, 'errors' => $errors];
    }

    public function autoAssignKelas(string $guruId, ?string $kelas = null): int
    {
        $siswa = Siswa::where(fn ($q) => $q->whereNull('guru_id')->orWhere('guru_id', 0))->get();
        $count = 0;

        foreach ($siswa as $s) {
            if ($kelas && $s->kelas !== $kelas) {
                continue;
            }
            $guru = User::forSchool()->find($guruId);
            if (! $guru) {
                continue;
            }
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
            'siswa_tanpa_kelas' => Siswa::where(fn ($q) => $q->whereNull('guru_id')->orWhere('guru_id', 0))->count(),
            'siswa_dengan_orang_tua' => Siswa::whereHas('orangTua')->count(),
            'siswa_penerima_kip' => Siswa::where('penerima_kip', true)->count(),
            'siswa_dengan_foto' => Siswa::whereNotNull('foto')->count(),
        ];
    }
}
