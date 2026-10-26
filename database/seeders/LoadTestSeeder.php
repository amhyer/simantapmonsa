<?php

namespace Database\Seeders;

use App\Models\PengaturanGuru;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Akun & data minimal untuk pengujian beban di staging.
 *
 * Seeder ini aditif dan idempoten: akun dengan nama `loadtest.*` dibuat bila
 * belum ada, data lain tidak dihapus. Jangan jalankan di produksi.
 */
class LoadTestSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'staging', 'testing'])) {
            throw new RuntimeException('LoadTestSeeder hanya boleh dijalankan di local/staging/testing.');
        }

        $password = (string) env('LOAD_TEST_PASSWORD', '');

        if ($password === '') {
            if (! app()->environment('local', 'testing')) {
                throw new RuntimeException('Set LOAD_TEST_PASSWORD sebelum menjalankan seeder di staging.');
            }

            $password = 'LoadTest#2026';
        }

        $jumlahGuru = max(1, min(100, (int) env('LOAD_TEST_GURU', 5)));
        $jumlahSiswa = max(1, min(200, (int) env('LOAD_TEST_SISWA', 10)));

        $guruPertama = null;

        for ($i = 1; $i <= $jumlahGuru; $i++) {
            $username = sprintf('loadtest.guru%02d', $i);

            $guru = User::firstOrCreate(
                ['nama_pengguna' => $username],
                [
                    'uuid' => Str::uuid(),
                    'nama_lengkap' => 'Load Test Guru ' . $i,
                    'kata_sandi' => Hash::make($password),
                    'peran' => 'guru',
                    'terhubung_dengan' => [],
                    'kelas_mata_pelajaran' => '6A',
                    'aktif' => true,
                    'force_password_change' => false,
                ]
            );

            PengaturanGuru::firstOrCreate(
                ['guru_id' => $guru->id],
                [
                    'nama_guru' => $guru->nama_lengkap,
                    'mata_pelajaran' => 'Matematika',
                    'kelas' => '6A',
                    'kkm' => 75,
                    'semester' => 'Ganjil',
                    'tahun_pelajaran' => date('Y') . '/' . (date('Y') + 1),
                    'pembaruan' => now(),
                ]
            );

            $guruPertama ??= $guru;
        }

        for ($i = 1; $i <= $jumlahSiswa; $i++) {
            $username = sprintf('loadtest.siswa%02d', $i);

            $siswaUser = User::firstOrCreate(
                ['nama_pengguna' => $username],
                [
                    'uuid' => Str::uuid(),
                    'nama_lengkap' => 'Load Test Siswa ' . $i,
                    'kata_sandi' => Hash::make($password),
                    'peran' => 'siswa',
                    'terhubung_dengan' => [],
                    'kelas_mata_pelajaran' => '6A',
                    'aktif' => true,
                    'force_password_change' => false,
                ]
            );

            Siswa::firstOrCreate(
                ['nis' => $username],
                [
                    'uuid' => Str::uuid(),
                    'guru_id' => $guruPertama?->id,
                    'nama_guru' => $guruPertama?->nama_lengkap,
                    'nama_peserta_didik' => $siswaUser->nama_lengkap,
                    'kelas' => '6A',
                    'jenis_kelamin' => $i % 2 === 0 ? 'P' : 'L',
                    'aktif' => true,
                ]
            );
        }

        $this->command?->info(sprintf(
            'Load test siap: %d guru, %d siswa. Akun: loadtest.guru01 / %s (password dari LOAD_TEST_PASSWORD).',
            $jumlahGuru,
            $jumlahSiswa,
            app()->environment('local', 'testing') && env('LOAD_TEST_PASSWORD', '') === '' ? $password : '••••••'
        ));
    }
}
