<?php

namespace App\Providers;

use App\Models\CbtQuestion;
use App\Models\CbtQuestionBank;
use App\Models\CbtResult;
use App\Models\CbtTestSession;
use App\Models\NilaiErapot;
use App\Models\PembelajaranMendalam\CapaianPembelajaran;
use App\Models\PembelajaranMendalam\HariLibur;
use App\Models\PembelajaranMendalam\KokurikulerPm;
use App\Models\PembelajaranMendalam\ProsemItem;
use App\Models\PembelajaranMendalam\ProtaItem;
use App\Models\PembelajaranMendalam\TujuanPembelajaranPm;
use App\Models\SekolahSettings;
use App\Models\Siswa;
use App\Models\Tka\Kesiapan\TindakLanjut;
use App\Models\Tka\StudentAssessment;
use App\Models\Tryout\Result;
use App\Models\Tryout\Session;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\TenantExistenceCache;
use App\Observers\NilaiErapotObserver;
use App\Policies\CbtQuestionBankPolicy;
use App\Policies\CbtQuestionPolicy;
use App\Policies\CbtResultPolicy;
use App\Policies\CbtTestSessionPolicy;
use App\Policies\PegawaiFotoPolicy;
use App\Policies\PembelajaranMendalamPolicy;
use App\Policies\SiswaFotoPolicy;
use App\Policies\TkaAssessmentPolicy;
use App\Policies\TkaKesiapanPolicy;
use App\Policies\TryoutResultPolicy;
use App\Policies\TryoutSessionPolicy;
use App\Services\Navigation\RoleTaskSummaryService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantExistenceCache::class);

        // Telescope berada di require-dev; daftar kondisional agar `composer install --no-dev` aman.
        if (class_exists(TelescopeApplicationServiceProvider::class)) {
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(Schedule $schedule): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        Password::defaults(function () {
            $rule = Password::min(8);

            return $this->app->isProduction()
                ? $rule->letters()->numbers()
                : $rule;
        });

        RateLimiter::for('admin-reset-massal', function (Request $request): Limit {
            return Limit::perMinute(5)->by('admin-reset-massal:'.($request->user()?->id ?: $request->ip()));
        });
        RateLimiter::for('admin-reset-massal-pratinjau', function (Request $request): Limit {
            return Limit::perMinute(30)->by('admin-reset-massal-pratinjau:'.($request->user()?->id ?: $request->ip()));
        });
        RateLimiter::for('admin-ekspor-sandi-awal', function (Request $request): Limit {
            return Limit::perMinute(10)->by('admin-ekspor-sandi-awal:'.($request->user()?->id ?: $request->ip()));
        });
        RateLimiter::for('sekolah-lookup', function (Request $request): Limit {
            return Limit::perMinute(10)->by('sekolah-lookup:'.$request->ip());
        });
        RateLimiter::for('csp-report', function (Request $request): Limit {
            return Limit::perMinute(30)->by('csp-report:'.$request->ip());
        });
        // Policies modul asesmen (Fase 2). Didaftarkan eksplisit agar model
        // bernamespace (Tryout\*) tidak bergantung pada auto-discovery.
        Gate::policy(CbtQuestionBank::class, CbtQuestionBankPolicy::class);
        Gate::policy(CbtQuestion::class, CbtQuestionPolicy::class);
        Gate::policy(CbtTestSession::class, CbtTestSessionPolicy::class);
        Gate::policy(Siswa::class, SiswaFotoPolicy::class);
        Gate::define('view-pegawai-foto', [PegawaiFotoPolicy::class, 'viewFoto']);
        Gate::policy(CbtResult::class, CbtResultPolicy::class);
        Gate::policy(Session::class, TryoutSessionPolicy::class);
        Gate::policy(Result::class, TryoutResultPolicy::class);
        Gate::policy(StudentAssessment::class, TkaAssessmentPolicy::class);
        Gate::policy(TindakLanjut::class, TkaKesiapanPolicy::class);

        // Policies modul Pembelajaran Mendalam
        Gate::policy(CapaianPembelajaran::class, PembelajaranMendalamPolicy::class);
        Gate::policy(TujuanPembelajaranPm::class, PembelajaranMendalamPolicy::class);
        Gate::policy(ProtaItem::class, PembelajaranMendalamPolicy::class);
        Gate::policy(ProsemItem::class, PembelajaranMendalamPolicy::class);
        Gate::policy(KokurikulerPm::class, PembelajaranMendalamPolicy::class);
        Gate::policy(HariLibur::class, PembelajaranMendalamPolicy::class);

        // Jejak audit nilai e-Rapor: observer memastikan input guru, import,
        // dan sinkronisasi memakai rantai hash yang sama.
        NilaiErapot::observe(NilaiErapotObserver::class);

        // Share activeTab fallback, dan evaluasi ulang di View::composer saat route sudah cocok
        View::share('activeTab', 'setup');

        // Sediakan data user layout dan identitas sekolah tenant (dengan cache 5 menit)
        // agar layout tidak menjalankan query berulang di tiap halaman.
        View::composer(['layouts.app', 'layouts.admin-with-tabs'], function ($view) {
            $user = Auth::user();
            $foto = null;

            if ($user) {
                $foto = Cache::remember(
                    'layout-foto-v2-'.$user->id,
                    300,
                    function () use ($user) {
                        $siswa = Siswa::query()
                            ->where('nis', $user->nama_pengguna)
                            ->first(['id', 'uuid', 'foto']);

                        $terhubung = $user->terhubung_dengan;
                        if (! $siswa && is_array($terhubung) && $terhubung !== []) {
                            $siswa = Siswa::query()
                                ->whereIn('id', $terhubung)
                                ->first(['id', 'uuid', 'foto']);
                        }

                        return $siswa?->fotoUrl();
                    }
                );
            }

            // Nama sekolah dinamis sesuai tenant sekolah yang sedang aktif/login
            $defaultNamaSekolah = config('app.name', 'SIMANTAP');
            $namaSekolah = $defaultNamaSekolah;
            $activeTenantId = TenantContext::check()
                ? (int) TenantContext::id()
                : (! empty($user?->tenant_id) ? (int) $user->tenant_id : null);

            if ($user && $user->peran === 'super_admin' && ! TenantContext::check()) {
                $namaSekolah = 'Administrator Sistem';
            } elseif ($activeTenantId !== null) {
                $namaSekolah = Cache::remember(
                    'tenant-nama-'.$activeTenantId,
                    300,
                    function () use ($activeTenantId, $defaultNamaSekolah) {
                        $settings = SekolahSettings::withoutGlobalScopes()
                            ->where('tenant_id', $activeTenantId)
                            ->first(['nama_sekolah']);
                        if ($settings && ! empty($settings->nama_sekolah) && ! in_array($settings->nama_sekolah, ['SIMANTAP', 'SIMANTAP_MONSA'], true)) {
                            return $settings->nama_sekolah;
                        }

                        $tenant = Tenant::query()->find($activeTenantId);
                        if ($tenant && ! empty($tenant->nama_sekolah)) {
                            return $tenant->nama_sekolah;
                        }

                        return $settings->nama_sekolah ?? $defaultNamaSekolah;
                    }
                );
            } elseif ($user) {
                $namaSekolah = Cache::remember(
                    'root-sekolah-nama',
                    300,
                    function () use ($defaultNamaSekolah) {
                        $settings = SekolahSettings::query()->first(['nama_sekolah']);

                        return $settings->nama_sekolah ?? $defaultNamaSekolah;
                    }
                );
            }

            $view->with('activeTab', activeAdminTab())
                ->with('layoutUser', $user)
                ->with('layoutUserFoto', $foto)
                ->with('layoutNamaSekolah', $namaSekolah);
        });

        // Nama sekolah dinamis di halaman login (terutama saat diakses via subdomain sekolah)
        View::composer('auth.login', function ($view) {
            $defaultNamaSekolah = config('app.name', 'SIMANTAP');
            $namaSekolah = $defaultNamaSekolah;
            if (TenantContext::check()) {
                $tenantId = TenantContext::id();
                $namaSekolah = Cache::remember(
                    'tenant-nama-'.$tenantId,
                    300,
                    function () use ($tenantId, $defaultNamaSekolah) {
                        $tenant = Tenant::query()->find($tenantId);

                        return $tenant->nama_sekolah ?? $defaultNamaSekolah;
                    }
                );
            } else {
                $namaSekolah = Cache::remember(
                    'root-sekolah-nama',
                    300,
                    function () use ($defaultNamaSekolah) {
                        $settings = SekolahSettings::query()->first(['nama_sekolah']);

                        return $settings->nama_sekolah ?? $defaultNamaSekolah;
                    }
                );
            }

            $view->with('layoutNamaSekolah', $namaSekolah);
        });

        // Notifikasi & ringkasan tugas lintas peran: hanya skalar/array (jangan cache model Eloquent).
        View::composer(['layouts.app', 'layouts.admin-with-tabs'], function ($view) {
            $user = Auth::user();
            $notifs = app(RoleTaskSummaryService::class)->notificationsFor($user);

            $view->with('layoutNotifs', $notifs);
        });

        // Auto-sync presensi dari database mesin fingerprint: pagi (scan masuk)
        // dan sore (scan pulang) agar data hari ini langsung terlihat di website.
        // Wajib memakai command() agar artisan dipanggil; call() hanya menerima closure.
        $schedule->command('presensi:mesin-sync')
            ->dailyAt('07:35')
            ->name('presensi-mesin-sync-pagi')
            ->withoutOverlapping();

        $schedule->command('presensi:mesin-sync')
            ->dailyAt('15:35')
            ->name('presensi-mesin-sync-sore')
            ->withoutOverlapping();
    }
}
