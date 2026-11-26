<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\SessionSecurity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class ForcePasswordChangeController extends Controller
{
    public function show()
    {
        return view('auth.force-change-password');
    }

    public function update(Request $request)
    {
        $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'password.required' => 'Password baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.min' => 'Password minimal 8 karakter.',
        ]);

        $user = Auth::user();

        if (Hash::check($request->password, $user->kata_sandi)) {
            return back()->withErrors(['password' => 'Password baru harus berbeda dari password lama.']);
        }

        $user->forceFill([
            'kata_sandi' => $request->password,
            'force_password_change' => false,
            'sandi_awal_diterbitkan_at' => null,
            'remember_token' => Str::random(60),
        ])->save();

        if ($request->hasSession()) {
            $request->session()->regenerate();
            SessionSecurity::invalidateUserSessions((int) $user->id, $request->session()->getId());
        } else {
            SessionSecurity::invalidateUserSessions((int) $user->id);
        }

        return redirect()->intended('/dashboard')
            ->with('success', 'Password berhasil diubah. Selamat datang!');
    }
}
