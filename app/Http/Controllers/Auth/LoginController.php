<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AktivitasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $throttleKey = 'login:' . $request->ip();

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

        if (Auth::attempt(['nama_pengguna' => $credentials['nama_pengguna'], 'password' => $credentials['kata_sandi']])) {
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();
            
            $user = auth()->user();
            $user->update(['terakhir_masuk' => now()]);

            AktivitasService::login('Login sebagai ' . $user->peran . ' dari IP ' . $request->ip());

            return match($user->peran) {
                'admin' => redirect()->route('admin.dashboard'),
                'guru' => redirect()->route('guru.dashboard'),
                'siswa' => redirect()->route('siswa.dashboard'),
                'ortu' => redirect()->route('ortu.dashboard'),
                'kepsek' => redirect()->route('kepsek.dashboard'),
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
