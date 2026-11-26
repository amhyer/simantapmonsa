<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\SessionSecurity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MfaResetCommand extends Command
{
    protected $signature = 'mfa:reset
                            {username : Nama pengguna atau email akun yang akan direset MFA-nya}
                            {--tenant= : Subdomain sekolah (wajib jika nama_pengguna dipakai di lebih dari satu sekolah)}';

    protected $description = 'Reset darurat Autentikasi Dua Faktor (MFA) untuk akun yang kehilangan perangkat authenticator';

    public function handle(): int
    {
        $identifier = trim((string) $this->argument('username'));
        $subdomain = trim((string) ($this->option('tenant') ?? ''));

        $query = User::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where(function ($q) use ($identifier) {
                $q->where('nama_pengguna', $identifier)
                    ->orWhere('email', $identifier);
            });

        if ($subdomain !== '') {
            $query->whereHas('tenant', fn ($t) => $t->where('subdomain', strtolower($subdomain)));
        }

        $matches = $query->get();

        if ($matches->isEmpty()) {
            $this->error("Akun '{$identifier}' tidak ditemukan.");

            return self::FAILURE;
        }

        if ($matches->count() > 1) {
            $this->error("Ditemukan lebih dari satu akun dengan nama pengguna '{$identifier}'. Sertakan --tenant=<subdomain> atau gunakan email.");

            return self::FAILURE;
        }

        /** @var User $user */
        $user = $matches->first();

        $user->forceFill([
            'mfa_secret' => null,
            'mfa_recovery_codes' => null,
            'mfa_confirmed_at' => null,
            'mfa_last_used_window' => null,
            'remember_token' => Str::random(60),
        ])->save();

        $deletedSessions = SessionSecurity::invalidateUserSessions((int) $user->id);

        Log::warning('MFA direset lewat CLI artisan mfa:reset', [
            'user_id' => $user->id,
            'nama_pengguna' => $user->nama_pengguna,
            'peran' => $user->peran,
            'deleted_sessions' => $deletedSessions,
        ]);

        $this->info("MFA untuk akun '{$user->nama_pengguna}' ({$user->peran}) berhasil direset. {$deletedSessions} sesi aktif dihapus.");

        return self::SUCCESS;
    }
}
