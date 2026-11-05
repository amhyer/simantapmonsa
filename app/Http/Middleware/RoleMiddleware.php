<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        
        if (!in_array($user->peran, $roles)) {
            // Pengalihan ramah untuk Admin / Guru yang membuka link kartu/profil siswa
            if ($request->isMethod('GET')) {
                if ($user->peran === 'admin') {
                    if ($request->is('siswa/profil*') || $request->is('siswa/kartu-identitas*')) {
                        return redirect()->route('admin.presensi.kartu.index')
                            ->with('info', 'Halaman profil siswa mandiri ditujukan untuk akun Siswa. Sebagai Administrator, Anda dialihkan ke Manajemen Kartu Siswa.');
                    }
                    if ($request->is('siswa/dashboard') || $request->is('guru/dashboard') || $request->is('ortu/dashboard') || $request->is('kepsek/dashboard')) {
                        return redirect()->route('admin.dashboard');
                    }
                } elseif ($user->peran === 'guru') {
                    if ($request->is('siswa/profil*') || $request->is('siswa/kartu-identitas*')) {
                        return redirect()->route('guru.siswa.index')
                            ->with('info', 'Halaman profil siswa mandiri ditujukan untuk akun Siswa. Sebagai Guru, Anda dialihkan ke Data Siswa.');
                    }
                }
            }

            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}
