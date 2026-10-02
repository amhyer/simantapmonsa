<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope::night();

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            return $isLocal ||
                   $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     * Tabel users tidak punya kolom email, jadi allowlist memakai nama_pengguna
     * (daftar via TELESCOPE_ALLOWED_USERS, default: admin).
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function (User $user) {
            if ($user->peran !== 'admin' || ! $user->aktif) {
                return false;
            }

            $allowed = array_filter(array_map(
                'trim',
                explode(',', (string) env('TELESCOPE_ALLOWED_USERS', 'admin'))
            ));

            return in_array($user->nama_pengguna, $allowed, true);
        });
    }
}
