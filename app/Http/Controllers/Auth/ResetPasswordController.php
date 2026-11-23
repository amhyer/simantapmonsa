<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Menyelesaikan reset sandi memakai token dari email.
 *
 * Kolom sandi tetap `kata_sandi` dan cast `hashed` pada model User yang
 * melakukan hashing, sehingga controller tidak pernah menyimpan sandi mentah.
 */
class ResetPasswordController extends Controller
{
    public function showResetForm(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'kata_sandi' => ['required', 'string', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            [
                'email' => $request->input('email'),
                'password' => $request->input('kata_sandi'),
                'password_confirmation' => $request->input('kata_sandi_confirmation'),
                'token' => $request->input('token'),
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'kata_sandi' => $password,
                    'force_password_change' => false,
                    'sandi_awal_diterbitkan_at' => null,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')
                ->with('status', 'Kata sandi berhasil diubah. Silakan masuk dengan sandi baru.');
        }

        $pesan = match ($status) {
            Password::INVALID_TOKEN, Password::INVALID_USER => 'Tautan reset tidak valid atau sudah kedaluwarsa. Minta tautan baru.',
            Password::RESET_THROTTLED => 'Terlalu banyak percobaan. Coba lagi beberapa saat lagi.',
            default => 'Kata sandi gagal diubah. Minta tautan reset baru.',
        };

        return back()->withInput($request->only('email'))
            ->withErrors(['email' => $pesan]);
    }
}
