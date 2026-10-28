<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeder khusus pengujian peramban (Playwright) di CI.
 *
 * Dipisah dari `DatabaseSeeder` karena dua alasan:
 *   1. `DatabaseSeeder` membuat puluhan akun contoh — terlalu berat dan lambat
 *      untuk smoke test, dan datanya tidak dibutuhkan.
 *   2. Test peramban butuh akun yang bisa LANGSUNG masuk. `DatabaseSeeder`
 *      menandai akunnya dengan `force_password_change` (benar untuk produksi),
 *      yang akan melempar test ke halaman ganti sandi, bukan ke dasbor.
 *
 * Kredensial di sini hanya untuk CI dan tidak pernah dipakai di server mana pun.
 * `SeedGuard` tetap dipanggil agar seeder ini tidak bisa disalahgunakan di
 * production.
 */
class E2eSeeder extends Seeder
{
    public function run(): void
    {
        if (! Support\SeedGuard::bolehJalan()) {
            return;
        }

        User::updateOrCreate(
            ['nama_pengguna' => 'e2e_admin'],
            [
                'uuid' => Str::uuid(),
                'nama_lengkap' => 'Admin Uji Peramban',
                'kata_sandi' => Hash::make('e2e-sandi-aman'),
                'peran' => 'admin',
                'terhubung_dengan' => [],
                'kelas_mata_pelajaran' => null,
                'aktif' => true,
                // Sengaja false: test perlu masuk ke dasbor, bukan ke halaman
                // ganti sandi wajib.
                'force_password_change' => false,
            ]
        );

        // Satu siswa, agar halaman pemetaan QR punya baris untuk diuji.
        User::updateOrCreate(
            ['nama_pengguna' => 'e2e_siswa'],
            [
                'uuid' => Str::uuid(),
                'nama_lengkap' => 'Siswa Uji Peramban',
                'kata_sandi' => Hash::make('e2e-sandi-aman'),
                'peran' => 'siswa',
                'terhubung_dengan' => [],
                'kelas_mata_pelajaran' => null,
                'aktif' => true,
                'force_password_change' => false,
            ]
        );
    }
}
