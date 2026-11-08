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
        Telescope::hideRequestParameters([
            '_token',
            'kata_sandi',
            'kata_sandi_confirmation',
            'password',
            'password_confirmation',
            'current_password',
            'admin_password',
            'token',
        ]);

        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
            'x-api-key',
            'authorization',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     * Di lingkungan multi-sekolah, hanya `super_admin` atau `admin` tingkat
     * platform (`tenant_id === null`) yang masuk daftar allowlist yang diizinkan.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function (User $user) {
            if (! $user->aktif) {
                return false;
            }

            if ($user->peran === 'super_admin') {
                return true;
            }

            if ($user->peran !== 'admin' || $user->tenant_id !== null) {
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
