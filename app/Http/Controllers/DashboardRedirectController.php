<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

/**
 * Pengalihan `/dashboard` (route `home`) ke dasbor sesuai peran.
 *
 * Dulu berupa Closure di routes/web.php. Closure membuat `php artisan
 * route:cache` gagal ("Unable to prepare route for serialization"), sehingga
 * produksi tidak bisa memakai route cache. Dipindahkan ke controller invokable
 * agar rute bisa di-cache tanpa mengubah perilaku.
 */
class DashboardRedirectController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        return match (auth()->user()->peran) {
            'super_admin' => redirect()->route('superadmin.dashboard'),
            'admin' => redirect()->route('admin.dashboard'),
            'guru' => redirect()->route('guru.dashboard'),
            'siswa' => redirect()->route('siswa.dashboard'),
            'ortu' => redirect()->route('ortu.dashboard'),
            'kepsek' => redirect()->route('kepsek.dashboard'),
            'pengawas' => redirect()->route('pengawas.dashboard'),
            'tendik' => redirect()->route('presensi.pegawai.index'),
            default => redirect()->route('login'),
        };
    }
}
