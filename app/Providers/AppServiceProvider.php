<?php

namespace App\Providers;

use App\Services\Dapodik\SidebarSyncStatus;
use App\Models\NilaiErapot;
use App\Models\Siswa;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use App\Observers\NilaiErapotObserver;
use App\Policies\CbtQuestionBankPolicy;
use App\Policies\CbtQuestionPolicy;
use App\Policies\CbtResultPolicy;
use App\Policies\CbtTestSessionPolicy;
use App\Policies\TryoutResultPolicy;
use App\Policies\TryoutSessionPolicy;
use App\Policies\TkaAssessmentPolicy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
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
    public function boot(): void
    {
        // Policies modul asesmen (Fase 2). Didaftarkan eksplisit agar model
        // bernamespace (Tryout\*) tidak bergantung pada auto-discovery.
        Gate::policy(\App\Models\CbtQuestionBank::class, CbtQuestionBankPolicy::class);
        Gate::policy(\App\Models\CbtQuestion::class, CbtQuestionPolicy::class);
        Gate::policy(\App\Models\CbtTestSession::class, CbtTestSessionPolicy::class);
        Gate::policy(\App\Models\CbtResult::class, CbtResultPolicy::class);
        Gate::policy(\App\Models\Tryout\Session::class, TryoutSessionPolicy::class);
        Gate::policy(\App\Models\Tryout\Result::class, TryoutResultPolicy::class);
        Gate::policy(\App\Models\Tka\StudentAssessment::class, TkaAssessmentPolicy::class);

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
                    'layout-foto-' . $user->id,
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
                        'waktu' => $last['created_at'] ? \Carbon\Carbon::parse($last['created_at'])->diffForHumans() : '-',
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
    }
}
