<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\InitialPassword;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class AuditDefaultPasswordsCommand extends Command
{
    protected $signature = 'akun:audit-password-bawaan
                            {--password= : Kata sandi bawaan yang diperiksa (kosongkan untuk prompt tersembunyi)}
                            {--tenant= : Batasi ke tenant_id tertentu}
                            {--apply : Terapkan reset kata sandi acak baru + force_password_change=true}
                            {--export= : Path file CSV ekspor sekali-pakai saat --apply (wajib di luar repo)}
                            {--force : Lewati konfirmasi interaktif saat --apply}';

    protected $description = 'Audit (dry-run default) dan reset akun yang masih memakai kata sandi bawaan lama';

    public function handle(): int
    {
        $targetPassword = (string) ($this->option('password') ?? '');
        if ($targetPassword === '') {
            $targetPassword = (string) $this->secret('Masukkan kata sandi bawaan yang ingin diperiksa');
        }

        if ($targetPassword === '') {
            $this->error('Kata sandi pemeriksaan tidak boleh kosong.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $exportPath = $this->option('export');
        if ($apply && (! is_string($exportPath) || trim($exportPath) === '')) {
            $this->error('Opsi --export=<path-di-luar-repo.csv> wajib diisi saat menggunakan --apply.');

            return self::FAILURE;
        }

        $query = User::withoutGlobalScopes()->whereNull('deleted_at')->orderBy('id');
        if ($this->option('tenant') !== null && $this->option('tenant') !== '') {
            $query->where('tenant_id', (int) $this->option('tenant'));
        }

        $matched = [];
        $query->chunkById(100, function ($users) use ($targetPassword, &$matched): void {
            foreach ($users as $user) {
                if (Hash::check($targetPassword, (string) $user->kata_sandi)) {
                    $matched[] = $user;
                }
            }
        });

        $this->info('Mode: '.($apply ? 'APPLY' : 'DRY-RUN (tidak mengubah data)'));
        $this->info('Jumlah akun cocok: '.count($matched));

        if ($matched === []) {
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Peran', 'Tenant ID'],
            array_map(fn (User $u): array => [$u->id, $u->peran, $u->tenant_id ?? '-'], $matched)
        );

        if (! $apply) {
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Reset '.count($matched).' akun dengan kata sandi acak baru dan force_password_change=true?')) {
            $this->warn('Dibatalkan.');

            return self::SUCCESS;
        }

        $handle = fopen((string) $exportPath, 'w');
        if ($handle === false) {
            $this->error("Tidak dapat menulis ke file ekspor: {$exportPath}");

            return self::FAILURE;
        }

        fputcsv($handle, ['Nama', 'Kelas', 'Username', 'Password Baru']);
        foreach ($matched as $user) {
            $newPlain = InitialPassword::generate();
            $user->forceFill([
                'kata_sandi' => Hash::make($newPlain),
                'force_password_change' => true,
                'sandi_awal_diterbitkan_at' => now(),
            ])->save();

            fputcsv($handle, [
                $user->nama_lengkap,
                $user->kelas_mata_pelajaran ?? '-',
                $user->nama_pengguna,
                $newPlain,
            ]);
        }
        fclose($handle);

        $this->info('Reset selesai. Daftar kata sandi baru ditulis sekali ke: '.$exportPath);

        return self::SUCCESS;
    }
}
