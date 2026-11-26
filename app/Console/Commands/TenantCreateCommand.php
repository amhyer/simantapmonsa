<?php

namespace App\Console\Commands;

use App\Http\Controllers\Auth\RegisterSekolahController;
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
        $npsn = trim((string) ($this->option('npsn') ?: $this->ask('Masukkan NPSN (8 digit)')));
        $nama = trim((string) ($this->option('nama') ?: $this->ask('Masukkan Nama Resmi Sekolah')));
        $subdomain = strtolower(trim((string) ($this->option('subdomain') ?: $this->ask('Masukkan Subdomain (misal: smpn1mks)'))));
        $jenjang = strtoupper(trim((string) ($this->option('jenjang') ?: $this->choice('Pilih Jenjang', ['SD', 'SMP', 'SMA', 'SMK'], 0))));

        if (! preg_match('/^[0-9A-Za-z]{4,16}$/', $npsn)) {
            $this->error("Gagal: Format NPSN '{$npsn}' tidak valid!");

            return self::FAILURE;
        }

        if (Tenant::where('npsn', $npsn)->exists()) {
            $this->error("Gagal: NPSN {$npsn} sudah terdaftar!");

            return self::FAILURE;
        }

        if (! preg_match('/^[a-z0-9](?:[a-z0-9\-]{1,62}[a-z0-9])?$/', $subdomain)) {
            $this->error("Gagal: Subdomain '{$subdomain}' hanya boleh berisi huruf kecil, angka, dan tanda hubung (minimal 3 karakter)!");

            return self::FAILURE;
        }

        if (in_array($subdomain, RegisterSekolahController::RESERVED_SUBDOMAINS, true)) {
            $this->error("Gagal: Subdomain '{$subdomain}' dicadangkan oleh sistem!");

            return self::FAILURE;
        }

        if (Tenant::where('subdomain', $subdomain)->exists()) {
            $this->error("Gagal: Subdomain {$subdomain} sudah digunakan!");

            return self::FAILURE;
        }

        $adminNama = trim((string) ($this->option('admin-nama') ?: $this->ask('Nama Lengkap Admin', "Admin {$nama}")));
        $adminUser = trim((string) ($this->option('admin-user') ?: $this->ask('Username Admin', "admin_{$subdomain}")));
        $adminEmail = strtolower(trim((string) ($this->option('admin-email') ?: $this->ask('Email Admin', "admin@{$subdomain}.sch.id"))));
        $adminPass = (string) ($this->option('admin-pass') ?: $this->secret('Password Admin (min 8 karakter)') ?: 'Admin123!');

        if (User::withoutGlobalScopes()->withTrashed()->whereNull('tenant_id')->where('nama_pengguna', $adminUser)->exists()) {
            $this->error("Gagal: Username {$adminUser} sudah digunakan oleh akun pusat!");

            return self::FAILURE;
        }

        if ($adminEmail !== '' && User::withoutGlobalScopes()->withTrashed()->whereRaw('LOWER(email) = ?', [$adminEmail])->exists()) {
            $this->error("Gagal: Email {$adminEmail} sudah digunakan!");

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
