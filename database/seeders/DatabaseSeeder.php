<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Siswa;
use App\Models\Materi;
use App\Models\Kuis;
use App\Models\Nilai;
use App\Models\Kehadiran;
use App\Models\HasilKuis;
use App\Models\Catatan;
use App\Models\Dimensi;
use App\Models\PengaturanGuru;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $guru = User::create([
            'uuid' => Str::uuid(),
            'nama_lengkap' => 'Guru Kelas 7A',
            'nama_pengguna' => 'guru',
            'kata_sandi' => Hash::make('guru123'),
            'peran' => 'guru',
            'terhubung_dengan' => [],
            'kelas_mata_pelajaran' => 'Kelas 7A - Matematika',
            'aktif' => true,
        ]);

        $admin = User::where('nama_pengguna', 'admin')->first();
        if (!$admin) {
            $admin = User::create([
                'uuid' => Str::uuid(),
                'nama_lengkap' => 'Administrator',
                'nama_pengguna' => 'admin',
                'kata_sandi' => Hash::make('admin123'),
                'peran' => 'admin',
                'terhubung_dengan' => [],
                'kelas_mata_pelajaran' => null,
                'aktif' => true,
            ]);
        }

        $kepsek = User::create([
            'uuid' => Str::uuid(),
            'nama_lengkap' => 'Kepala Sekolah',
            'nama_pengguna' => 'kepsek',
            'kata_sandi' => Hash::make('kepsek123'),
            'peran' => 'kepsek',
            'terhubung_dengan' => [],
            'kelas_mata_pelajaran' => null,
            'aktif' => true,
        ]);

        PengaturanGuru::create([
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'kelas' => 'Kelas 7A',
            'kkm' => 70,
            'semester' => 'Ganjil',
            'tahun_pelajaran' => date('Y') . '/' . (date('Y') + 1),
            'pembaruan' => now(),
        ]);

        $daftarNama = [
            'Ahmad Rizki Pratama', 'Siti Nurhaliza', 'Muhammad Fadil',
            'Aisyah Putri Ramadhani', 'Budi Santoso', 'Dewi Kartika Sari',
            'Eko Prasetyo', 'Fani Oktaviani', 'Gilang Ramadhan',
            'Hana Permata Sari', 'Irfan Hakim', 'Jingga Melati',
            'Kurniawan Adi', 'Lestari Budianti', 'Maya Anggraeni',
            'Nanda Pratama', 'Omar Daniel', 'Putri Amelia',
            'Rizky Amelia', 'Satria Bhaskara',
        ];

        $siswaIds = [];
        foreach ($daftarNama as $i => $nama) {
            $nis = str_pad(2026001 + $i, 7, '0', STR_PAD_LEFT);
            $siswa = Siswa::create([
                'uuid' => Str::uuid(),
                'guru_id' => $guru->id,
                'nama_guru' => $guru->nama_lengkap,
                'nis' => $nis,
                'nama_peserta_didik' => $nama,
                'jenis_kelamin' => $i % 2 == 0 ? 'L' : 'P',
                'kelas' => 'Kelas 7A',
                'nama_orang_tua' => 'Orang Tua ' . $nama,
                'aktif' => true,
            ]);
            $siswaIds[] = $siswa->id;

            User::create([
                'uuid' => Str::uuid(),
                'nama_lengkap' => $nama,
                'nama_pengguna' => 'siswa' . $nis,
                'kata_sandi' => Hash::make($nis),
                'peran' => 'siswa',
                'terhubung_dengan' => [$siswa->id],
                'kelas_mata_pelajaran' => 'Kelas 7A',
                'aktif' => true,
            ]);

            User::create([
                'uuid' => Str::uuid(),
                'nama_lengkap' => 'Orang Tua ' . $nama,
                'nama_pengguna' => 'ortu' . $nis,
                'kata_sandi' => Hash::make($nis),
                'peran' => 'ortu',
                'terhubung_dengan' => [$siswa->id],
                'kelas_mata_pelajaran' => 'Kelas 7A',
                'aktif' => true,
            ]);
        }

        $materiList = [
            ['judul' => 'Pengenalan Bilangan Bulat', 'mata_pelajaran' => 'Matematika', 'kelas' => 'Kelas 7A'],
            ['judul' => 'Operasi Hitung Campuran', 'mata_pelajaran' => 'Matematika', 'kelas' => 'Kelas 7A'],
            ['judul' => 'Pecahan dan Desimal', 'mata_pelajaran' => 'Matematika', 'kelas' => 'Kelas 7A'],
        ];

        $materiIds = [];
        foreach ($materiList as $m) {
            $materi = Materi::create([
                'uuid' => Str::uuid(),
                'guru_id' => $guru->id,
                'nama_guru' => $guru->nama_lengkap,
                'tanggal' => now()->subDays(rand(1, 30)),
                'judul' => $m['judul'],
                'mata_pelajaran' => $m['mata_pelajaran'],
                'kelas' => $m['kelas'],
                'tujuan_pembelajaran' => 'Memahami konsep ' . strtolower($m['judul']),
                'materi_pokok' => 'Materi pembelajaran tentang ' . strtolower($m['judul']),
                'kegiatan' => 'Diskusi dan latihan soal',
                'sematkan' => false,
            ]);
            $materiIds[] = $materi->id;
        }

        $kuisList = [
            ['judul' => 'Kuis Bilangan Bulat', 'bobot' => 20, 'materi_idx' => 0],
            ['judul' => 'Kuis Operasi Hitung', 'bobot' => 25, 'materi_idx' => 1],
            ['judul' => 'Kuis Pecahan', 'bobot' => 30, 'materi_idx' => 2],
        ];

        foreach ($kuisList as $k) {
            Kuis::create([
                'uuid' => Str::uuid(),
                'guru_id' => $guru->id,
                'nama_guru' => $guru->nama_lengkap,
                'tanggal' => now()->subDays(rand(1, 15)),
                'judul' => $k['judul'],
                'mata_pelajaran' => 'Matematika',
                'kelas' => 'Kelas 7A',
                'mode' => 'harian',
                'sumber' => 'lokal',
                'materi_id' => $materiIds[$k['materi_idx']],
                'kkm' => 70,
                'batas_waktu' => 60,
                'aktif' => true,
                'jumlah_soal' => 10,
                'jenis' => 'Ulangan Harian',
                'cara_nilai' => 'guru',
                'soal' => [],
            ]);
        }

        $statusList = ['H', 'H', 'H', 'H', 'S', 'I', 'A'];
        foreach ($siswaIds as $siswaId) {
            $siswa = Siswa::find($siswaId);

            for ($d = 1; $d <= 10; $d++) {
                Kehadiran::create([
                    'uuid' => Str::uuid(),
                    'siswa_id' => $siswaId,
                    'guru_id' => $guru->id,
                    'tanggal' => now()->subDays($d * 3),
                    'status' => $statusList[array_rand($statusList)],
                    'keterangan' => null,
                ]);
            }

            Catatan::create([
                'uuid' => Str::uuid(),
                'siswa_id' => $siswaId,
                'guru_id' => $guru->id,
                'nama_guru' => $guru->nama_lengkap,
                'nama_siswa' => $siswa->nama_peserta_didik,
                'tanggal' => now(),
                'jenis' => 'umum',
                'catatan' => 'Catatan perkembangan untuk siswa',
            ]);
        }

        $dimensiList = [
            ['no_dimensi' => 1, 'dimensi' => 'Akhlak kepada Allah'],
            ['no_dimensi' => 2, 'dimensi' => 'Akhlak kepada Manusia'],
            ['no_dimensi' => 3, 'dimensi' => 'Akhlak kepada Diri Sendiri'],
            ['no_dimensi' => 4, 'dimensi' => 'Akhlak kepada Alam'],
            ['no_dimensi' => 5, 'dimensi' => 'Akhlak kepada Lingkungan'],
        ];

        $siswaPertama = Siswa::first();
        foreach ($dimensiList as $d) {
            Dimensi::create([
                'uuid' => Str::uuid(),
                'guru_id' => $guru->id,
                'siswa_id' => $siswaPertama->id,
                'nama_guru' => $guru->nama_lengkap,
                'nama_siswa' => $siswaPertama->nama_peserta_didik,
                'no_dimensi' => $d['no_dimensi'],
                'dimensi' => $d['dimensi'],
                'skor' => rand(70, 100),
                'predikat' => 'Baik',
                'catatan' => 'Pencapaian baik pada dimensi ini',
                'rekaman' => [],
            ]);
        }

        echo "Seed berhasil! Login: admin/admin123, guru/guru123, kepsek/kepsek123\n";
    }
}
