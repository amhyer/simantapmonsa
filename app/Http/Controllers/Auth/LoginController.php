<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Services\AktivitasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        if (auth()->check()) {
            return redirect()->route('home');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $throttleKey = 'login:'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'nama_pengguna' => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        $credentials = $request->validate([
            'nama_pengguna' => 'required|string',
            'kata_sandi' => 'required|string',
        ]);

        $attemptSuccess = false;
        if (TenantContext::check()) {
            $attemptSuccess = Auth::attempt([
                'nama_pengguna' => $credentials['nama_pengguna'],
                'password' => $credentials['kata_sandi'],
                'tenant_id' => TenantContext::id(),
            ]);

            // Izinkan Super Admin login dari portal mana saja (tanpa terhalang TenantScope karena super_admin memiliki tenant_id = null)
            if (! $attemptSuccess) {
                $superAdminUser = User::withoutGlobalScopes()
                    ->whereNull('deleted_at')
                    ->where('nama_pengguna', $credentials['nama_pengguna'])
                    ->where('peran', 'super_admin')
                    ->first();

                if ($superAdminUser && Hash::check($credentials['kata_sandi'], $superAdminUser->kata_sandi)) {
                    Auth::login($superAdminUser);
                    $attemptSuccess = true;
                }
            }
        } else {
            $attemptSuccess = Auth::attempt([
                'nama_pengguna' => $credentials['nama_pengguna'],
                'password' => $credentials['kata_sandi'],
            ]);
        }

        if ($attemptSuccess) {
            $user = auth()->user();

            // PERBAIKAN: kolom `aktif`/`archived_at` sebelumnya tidak pernah
            // diperiksa — akun yang dinonaktifkan admin tetap bisa login.
            if (! $user->aktif || $user->archived_at !== null) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                RateLimiter::hit($throttleKey, 300);

                return back()->withErrors([
                    'nama_pengguna' => 'Akun Anda dinonaktifkan. Hubungi administrator sekolah.',
                ])->onlyInput('nama_pengguna');
            }

            try {
                $user->update(['terakhir_masuk' => now()]);
                AktivitasService::login('Login sebagai '.$user->peran.' dari IP '.$request->ip());
            } catch (\Throwable $e) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                throw $e;
            }

            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            return match ($user->peran) {
                'super_admin' => redirect()->route('superadmin.dashboard'),
                'admin' => redirect()->route('admin.dashboard'),
                'guru' => redirect()->route('guru.dashboard'),
                'siswa' => redirect()->route('siswa.dashboard'),
                'ortu' => redirect()->route('ortu.dashboard'),
                'kepsek' => redirect()->route('kepsek.dashboard'),
                'tendik' => redirect()->route('presensi.pegawai.index'),
                default => redirect()->route('login'),
            };
        }

        RateLimiter::hit($throttleKey, 300);

        return back()->withErrors([
            'nama_pengguna' => 'Nama pengguna atau kata sandi salah.',
        ])->onlyInput('nama_pengguna');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
