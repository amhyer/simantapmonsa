<?php

namespace App\Providers;

use App\Models\DapodikSyncLog;
use App\Models\Siswa;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        // Register activeAdminTab Blade macro
        \Illuminate\Support\Facades\Blade::macro('activeAdminTab', function () {
            return activeAdminTab();
        });

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
                $notifs = Cache::remember('layout-notif-admin', 60, function () {
                    $items = [];
                    $last = DapodikSyncLog::orderByDesc('created_at')->first();
                    if ($last) {
                        $items[] = [
                            'icon' => 'fas fa-sync',
                            'teks' => "Sinkron Dapodik: {$last->berhasil} berhasil, {$last->gagal} gagal",
                            'waktu' => $last->created_at?->diffForHumans() ?? '-',
                        ];
                    }
                    if (Schema::hasTable('failed_jobs')) {
                        $gagal = DB::table('failed_jobs')->count();
                        if ($gagal > 0) {
                            $items[] = [
                                'icon' => 'fas fa-exclamation-triangle',
                                'teks' => "{$gagal} job antrean gagal perlu perhatian",
                                'waktu' => 'sekarang',
                            ];
                        }
                    }

                    return $items;
                });
            }

            $view->with('layoutNotifs', $notifs);
        });
    }
}
