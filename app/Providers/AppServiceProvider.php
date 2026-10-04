<?php

namespace App\Providers;

use App\Models\Siswa;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
    }
}
