<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Siswa;
use App\Models\NilaiErapot;
use App\Models\Kebiasaan;
use App\Models\Kehadiran;
use App\Models\PengaturanGuru;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $guru = User::firstOrCreate(
            ['nama_pengguna' => 'guru'],
            [
                'uuid' => Str::uuid(),
                'nama_lengkap' => 'Dra. Sartika Ningsih, M.Pd.',
                'kata_sandi' => Hash::make('guru123'),
                'peran' => 'guru',
                'terhubung_dengan' => [],
                'kelas_mata_pelajaran' => 'V A · Matematika',
                'aktif' => true,
            ]
        );

        PengaturanGuru::updateOrCreate(
            ['guru_id' => $guru->id],
            [
                'nama_guru' => $guru->nama_lengkap,
                'mata_pelajaran' => 'Matematika',
                'kelas' => 'V A',
                'kkm' => 70,
                'semester' => 'Ganjil',
                'tahun_pelajaran' => '2025/2026',
                'pengaturan' => [
                    'sekolah' => 'SD Negeri 1 Harapan Bangsa',
                    'npsn' => '40312345',
                    'alamat' => 'Jl. Pendidikan No. 17, Makassar',
                    'kota' => 'Makassar',
                    'kepsek' => 'Dr. Hj. Siti Rahmah, M.Pd.',
                    'nipKepsek' => '19700304 199403 1 008',
                ],
            ]
        );

        $namaSiswa = [
            ['Andi Nurul Fadhilah', 'P'],
            ['Muh. Rizky Ramadhan', 'L'],
            ['Siti Aisyah Putri', 'P'],
            ['Ahmad Fauzan Akbar', 'L'],
            ['Nabila Syakira', 'P'],
            ['Muh. Ilham Saputra', 'L'],
            ['Aulia Rahmadani', 'P'],
            ['Fajar Ardiansyah', 'L'],
            ['Khaerunnisa Amelia', 'P'],
            ['Yusuf Maulana', 'L'],
        ];

        foreach ($namaSiswa as $i => [$nama, $jk]) {
            $siswa = Siswa::firstOrCreate(
                ['nis' => '2401' . str_pad($i + 1, 3, '0', STR_PAD_LEFT)],
                [
                    'uuid' => Str::uuid(),
                    'guru_id' => $guru->id,
                    'nama_guru' => $guru->nama_lengkap,
                    'nisn' => '012345' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                    'nama_peserta_didik' => $nama,
                    'kelas' => 'V A',
                    'jenis_kelamin' => $jk,
                    'nama_orang_tua' => 'ORT' . str_pad(1000 + $i * 7, 4, '0', STR_PAD_LEFT),
                    'aktif' => true,
                ]
            );

            $nilaiDasar = [85, 64, 79, 72, 91, 58, 84, 68, 86, 74][$i];

            NilaiErapot::updateOrCreate(
                [
                    'siswa_id' => $siswa->id,
                    'mata_pelajaran' => 'Matematika',
                    'semester' => 'Ganjil',
                    'tahun_ajaran' => '2025/2026',
                ],
                [
                    'uuid' => Str::uuid(),
                    'guru_id' => $guru->id,
                    'kelas' => 'V A',
                    'nilai_formatif' => $nilaiDasar + rand(-5, 5),
                    'nilai_sumatif' => $nilaiDasar + rand(-5, 5),
                    'nilai_sumatif_akhir' => $nilaiDasar + rand(-5, 5),
                    'nilai_akhir' => $nilaiDasar,
                    'predikat' => $nilaiDasar >= 90 ? 'A' : ($nilaiDasar >= 80 ? 'B' : ($nilaiDasar >= 70 ? 'C' : 'D')),
                ]
            );

            for ($d = 11; $d >= 0; $d--) {
                $tgl = now()->subDays($d)->format('Y-m-d');
                if (now()->subDays($d)->isSunday()) continue;

                $r = rand(1, 100);
                $status = $r > 94 ? 'S' : ($r > 91 ? 'I' : ($r > 90 ? 'A' : 'H'));

                Kehadiran::updateOrCreate(
                    ['siswa_id' => $siswa->id, 'tanggal' => $tgl],
                    ['uuid' => Str::uuid(), 'guru_id' => $guru->id, 'status' => $status]
                );
            }

            for ($d = 6; $d >= 0; $d--) {
                $tgl = now()->subDays($d)->format('Y-m-d');
                if (rand(0, 100) < 20) continue;

                Kebiasaan::updateOrCreate(
                    ['siswa_id' => $siswa->id, 'tanggal' => $tgl],
                    [
                        'uuid' => Str::uuid(),
                        'bangun_pagi' => rand(2, 4),
                        'beribadah' => rand(2, 4),
                        'berolahraga' => rand(2, 4),
                        'makan_sehat' => rand(2, 4),
                        'gemar_belajar' => rand(2, 4),
                        'bermasyarakat' => rand(2, 4),
                        'tidur_cepat' => rand(2, 4),
                        'diisi_oleh' => 'Orang Tua Wali',
                    ]
                );
            }
        }

        $this->command->info('✅ Demo data berhasil dibuat!');
        $this->command->info('📌 Guru: guru / guru123');
    }
}
