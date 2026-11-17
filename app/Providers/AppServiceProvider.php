<?php

namespace App\Providers;

use App\Models\CbtQuestion;
use App\Models\CbtQuestionBank;
use App\Models\CbtResult;
use App\Models\CbtTestSession;
use App\Models\NilaiErapot;
use App\Models\SekolahSettings;
use App\Models\Siswa;
use App\Models\Tka\Kesiapan\TindakLanjut;
use App\Models\Tka\StudentAssessment;
use App\Models\Tryout\Result;
use App\Models\Tryout\Session;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use App\Observers\NilaiErapotObserver;
use App\Policies\CbtQuestionBankPolicy;
use App\Policies\CbtQuestionPolicy;
use App\Policies\CbtResultPolicy;
use App\Policies\CbtTestSessionPolicy;
use App\Policies\TkaAssessmentPolicy;
use App\Policies\TkaKesiapanPolicy;
use App\Policies\TryoutResultPolicy;
use App\Policies\TryoutSessionPolicy;
use App\Services\Dapodik\SidebarSyncStatus;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(Schedule $schedule): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
        // Policies modul asesmen (Fase 2). Didaftarkan eksplisit agar model
        // bernamespace (Tryout\*) tidak bergantung pada auto-discovery.
        Gate::policy(CbtQuestionBank::class, CbtQuestionBankPolicy::class);
        Gate::policy(CbtQuestion::class, CbtQuestionPolicy::class);
        Gate::policy(CbtTestSession::class, CbtTestSessionPolicy::class);
        Gate::policy(CbtResult::class, CbtResultPolicy::class);
        Gate::policy(Session::class, TryoutSessionPolicy::class);
        Gate::policy(Result::class, TryoutResultPolicy::class);
        Gate::policy(StudentAssessment::class, TkaAssessmentPolicy::class);
        Gate::policy(TindakLanjut::class, TkaKesiapanPolicy::class);

        // Jejak audit nilai e-Rapor: observer memastikan input guru, import,
        // dan sinkronisasi memakai rantai hash yang sama.
        NilaiErapot::observe(NilaiErapotObserver::class);

        // Share activeTab with all views for admin routes
        View::share('activeTab', activeAdminTab());

        // Sediakan data user layout dan identitas sekolah tenant (dengan cache 5 menit)
        // agar layout tidak menjalankan query berulang di tiap halaman.
        View::composer(['layouts.app', 'layouts.admin-with-tabs'], function ($view) {
            $user = Auth::user();
            $foto = null;

            if ($user) {
                $foto = Cache::remember(
                    'layout-foto-'.$user->id,
                    300,
                    function () use ($user) {
                        $siswa = Siswa::query()
                            ->where('nis', $user->nama_pengguna)
                            ->first(['foto']);

                        $terhubung = $user->terhubung_dengan;
                        if (! $siswa && is_array($terhubung) && $terhubung !== []) {
                            $siswa = Siswa::query()
                                ->whereIn('id', $terhubung)
                                ->first(['foto']);
                        }

                        return $siswa->foto ?? null;
                    }
                );
            }

            // Nama sekolah dinamis sesuai tenant sekolah yang sedang aktif/login
            $namaSekolah = 'UPTD SDN Unggulan Mongisidi 1';
            if ($user) {
                if ($user->peran === 'super_admin') {
                    $namaSekolah = 'Administrator Sistem';
                } elseif (! empty($user->tenant_id)) {
                    $tenantId = (int) $user->tenant_id;
                    $namaSekolah = Cache::remember(
                        'tenant-nama-'.$tenantId,
                        300,
                        function () use ($tenantId) {
                            $tenant = Tenant::query()->find($tenantId);
                            if ($tenant && ! empty($tenant->nama_sekolah)) {
                                return $tenant->nama_sekolah;
                            }
                            $settings = SekolahSettings::query()->where('tenant_id', $tenantId)->first(['nama_sekolah']);

                            return $settings->nama_sekolah ?? 'UPTD SDN Unggulan Mongisidi 1';
                        }
                    );
                } else {
                    $namaSekolah = Cache::remember(
                        'root-sekolah-nama',
                        300,
                        function () {
                            $settings = SekolahSettings::query()->first(['nama_sekolah']);

                            return $settings->nama_sekolah ?? 'UPTD SDN Unggulan Mongisidi 1';
                        }
                    );
                }
            } elseif (TenantContext::check()) {
                $tenantId = TenantContext::id();
                $namaSekolah = Cache::remember(
                    'tenant-nama-'.$tenantId,
                    300,
                    function () use ($tenantId) {
                        $tenant = Tenant::query()->find($tenantId);

                        return $tenant->nama_sekolah ?? 'UPTD SDN Unggulan Mongisidi 1';
                    }
                );
            }

            $view->with('layoutUser', $user)
                ->with('layoutUserFoto', $foto)
                ->with('layoutNamaSekolah', $namaSekolah);
        });

        // Nama sekolah dinamis di halaman login (terutama saat diakses via subdomain sekolah)
        View::composer('auth.login', function ($view) {
            $namaSekolah = 'UPTD SD Negeri Unggulan Mongisidi 1';
            if (TenantContext::check()) {
                $tenantId = TenantContext::id();
                $namaSekolah = Cache::remember(
                    'tenant-nama-'.$tenantId,
                    300,
                    function () use ($tenantId) {
                        $tenant = Tenant::query()->find($tenantId);

                        return $tenant->nama_sekolah ?? 'UPTD SD Negeri Unggulan Mongisidi 1';
                    }
                );
            } else {
                $namaSekolah = Cache::remember(
                    'root-sekolah-nama',
                    300,
                    function () {
                        $settings = SekolahSettings::query()->first(['nama_sekolah']);

                        return $settings->nama_sekolah ?? 'UPTD SD Negeri Unggulan Mongisidi 1';
                    }
                );
            }

            $view->with('layoutNamaSekolah', $namaSekolah);
        });

        // Notifikasi & ringkasan tugas lintas peran: hanya skalar/array (jangan cache model Eloquent).
        View::composer(['layouts.app', 'layouts.admin-with-tabs'], function ($view) {
            $user = Auth::user();
            $notifs = app(\App\Services\Navigation\RoleTaskSummaryService::class)->notificationsFor($user);

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
