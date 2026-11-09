<?php

namespace App\Providers;

use App\Models\CbtQuestion;
use App\Models\CbtQuestionBank;
use App\Models\CbtResult;
use App\Models\CbtTestSession;
use App\Models\NilaiErapot;
use App\Models\Siswa;
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

        // Jejak audit nilai e-Rapor: observer memastikan input guru, import,
        // dan sinkronisasi memakai rantai hash yang sama.
        NilaiErapot::observe(NilaiErapotObserver::class);

        // Share activeTab with all views for admin routes
        View::share('activeTab', activeAdminTab());

        // Sediakan data user layout sekali per request (dengan cache 5 menit)
        // agar layouts/app.blade.php tidak menjalankan query Siswa di tiap halaman.
        View::composer('layouts.app', function ($view) {
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

            $view->with('layoutUser', $user)->with('layoutUserFoto', $foto);
        });

        // Notifikasi topbar untuk admin: hanya skalar (jangan cache model).
        View::composer('layouts.app', function ($view) {
            $notifs = [];
            $user = Auth::user();

            if ($user && $user->peran === 'admin') {
                $last = SidebarSyncStatus::latest();
                if ($last) {
                    $notifs[] = [
                        'icon' => 'fas fa-sync',
                        'teks' => "Sinkron Dapodik: {$last['berhasil']} berhasil, {$last['gagal']} gagal",
                        'waktu' => $last['created_at'] ? Carbon::parse($last['created_at'])->diffForHumans() : '-',
                    ];
                }
                if (! TenantContext::check() && ! config('simantap.multi_tenant_enabled', false)
                    && ! Tenant::query()->exists() && Schema::hasTable('failed_jobs')) {
                    $gagal = DB::table('failed_jobs')->count();
                    if ($gagal > 0) {
                        $notifs[] = [
                            'icon' => 'fas fa-exclamation-triangle',
                            'teks' => "{$gagal} job antrean gagal perlu perhatian",
                            'waktu' => 'sekarang',
                        ];
                    }
                }
            }

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
