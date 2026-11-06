<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SuperAdminCreateCommand extends Command
{
    protected $signature = 'superadmin:create
                            {--user= : Username login superadmin}
                            {--pass= : Kata sandi superadmin}
                            {--name= : Nama lengkap superadmin}
                            {--email= : Email superadmin}';

    protected $description = 'Membuat akun Super Administrator / FKKG untuk mengelola multi-tenant SIMANTAP';

    public function handle(): int
    {
        $user = $this->option('user') ?: $this->ask('Username Superadmin', 'superadmin');
        $name = $this->option('name') ?: $this->ask('Nama Lengkap', 'Administrator Pusat FKKG');
        $email = $this->option('email') ?: $this->ask('Email (opsional)', 'pusat@simantap.id');
        $pass = $this->option('pass') ?: $this->secret('Password (min 8 karakter)') ?: 'SuperAdmin123!';

        if (User::where('nama_pengguna', $user)->exists()) {
            $this->error("Gagal: Username {$user} sudah digunakan!");
            return self::FAILURE;
        }

        $superAdmin = User::create([
            'uuid' => (string) Str::uuid(),
            'nama_lengkap' => $name,
            'nama_pengguna' => $user,
            'email' => $email,
            'kata_sandi' => Hash::make($pass),
            'peran' => 'super_admin',
            'tenant_id' => null,
            'aktif' => true,
            'force_password_change' => false,
        ]);

        $this->info("✅ Super Administrator berhasil dibuat!");
        $this->table(
            ['ID', 'Nama Lengkap', 'Username', 'Peran', 'Tenant ID'],
            [[$superAdmin->id, $superAdmin->nama_lengkap, $superAdmin->nama_pengguna, $superAdmin->peran, 'NULL (Pusat)']]
        );

        return self::SUCCESS;
    }
}
