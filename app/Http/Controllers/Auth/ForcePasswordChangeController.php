<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'password.required' => 'Password baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.min' => 'Password minimal 8 karakter.',
        ]);

        $user = Auth::user();

        if (Hash::check($request->password, $user->kata_sandi)) {
            return back()->withErrors(['password' => 'Password baru harus berbeda dari password lama.']);
        }

        $user->update([
            'kata_sandi' => bcrypt($request->password),
            'force_password_change' => false,
        ]);

        return redirect()->intended('/dashboard')
            ->with('success', 'Password berhasil diubah. Selamat datang!');
    }
}
