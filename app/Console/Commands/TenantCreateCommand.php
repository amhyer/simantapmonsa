<?php

namespace App\Console\Commands;

use App\Models\SekolahSettings;
use App\Models\User;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantCreateCommand extends Command
{
    protected $signature = 'tenant:create 
                            {--npsn= : NPSN 8 digit sekolah}
                            {--nama= : Nama resmi sekolah}
                            {--subdomain= : Subdomain akses unik}
                            {--jenjang=SD : Jenjang sekolah (SD/SMP/SMA/SMK)}
                            {--admin-user= : Username login admin}
                            {--admin-nama= : Nama lengkap admin}
                            {--admin-email= : Email admin}
                            {--admin-pass= : Kata sandi admin}';

    protected $description = 'Mendaftarkan sekolah (tenant) baru beserta akun Administrator sekolah';

    public function handle(): int
    {
        $npsn = $this->option('npsn') ?: $this->ask('Masukkan NPSN (8 digit)');
        $nama = $this->option('nama') ?: $this->ask('Masukkan Nama Resmi Sekolah');
        $subdomain = strtolower($this->option('subdomain') ?: $this->ask('Masukkan Subdomain (misal: smpn1mks)'));
        $jenjang = strtoupper($this->option('jenjang') ?: $this->choice('Pilih Jenjang', ['SD', 'SMP', 'SMA', 'SMK'], 0));

        if (Tenant::where('npsn', $npsn)->exists()) {
            $this->error("Gagal: NPSN {$npsn} sudah terdaftar!");
            return self::FAILURE;
        }

        if (Tenant::where('subdomain', $subdomain)->exists()) {
            $this->error("Gagal: Subdomain {$subdomain} sudah digunakan!");
            return self::FAILURE;
        }

        $adminNama = $this->option('admin-nama') ?: $this->ask('Nama Lengkap Admin', "Admin {$nama}");
        $adminUser = $this->option('admin-user') ?: $this->ask('Username Admin', "admin_{$subdomain}");
        $adminEmail = $this->option('admin-email') ?: $this->ask('Email Admin', "admin@{$subdomain}.sch.id");
        $adminPass = $this->option('admin-pass') ?: $this->secret('Password Admin (min 8 karakter)') ?: 'Admin123!';

        if (User::where('nama_pengguna', $adminUser)->exists()) {
            $this->error("Gagal: Username {$adminUser} sudah digunakan!");
            return self::FAILURE;
        }

        DB::transaction(function () use ($npsn, $nama, $subdomain, $jenjang, $adminNama, $adminUser, $adminEmail, $adminPass) {
            $tenant = Tenant::create([
                'uuid' => (string) Str::uuid(),
                'npsn' => $npsn,
                'nama_sekolah' => $nama,
                'subdomain' => $subdomain,
                'aktif' => true,
                'meta' => [
                    'jenjang' => $jenjang,
                    'created_by' => 'artisan:tenant:create',
                ],
            ]);

            $admin = User::create([
                'uuid' => (string) Str::uuid(),
                'nama_lengkap' => $adminNama,
                'nama_pengguna' => $adminUser,
                'email' => $adminEmail,
                'kata_sandi' => $adminPass,
                'peran' => 'admin',
                'tenant_id' => $tenant->id,
                'aktif' => true,
                'force_password_change' => false,
            ]);

            SekolahSettings::create([
                'tenant_id' => $tenant->id,
                'guru_id' => $admin->id,
                'nama_sekolah' => $nama,
                'npsn' => $npsn,
                'jenjang' => $jenjang,
                'email' => $adminEmail,
                'status' => 'aktif',
            ]);

            $this->info("✓ Sekolah {$nama} berhasil didaftarkan sebagai Tenant #{$tenant->id}");
            $this->info("✓ Akun Administrator #{$admin->id} ({$adminUser}) siap digunakan.");
        });

        return self::SUCCESS;
    }
}
