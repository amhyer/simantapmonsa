<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

/**
 * Permintaan tautan reset sandi.
 *
 * SIMANTAP login memakai `nama_pengguna`, bukan email. Form menerima keduanya,
 * lalu hanya akun yang sudah punya email terdaftar yang dapat menerima tautan.
 * Akun tanpa email tetap harus direset admin; pesan ke pengguna sengaja dibuat
 * generik agar tidak membocorkan akun mana yang punya email.
 */
class ForgotPasswordController extends Controller
{
    public function showLinkRequestForm()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'login' => ['required', 'string', 'max:255'],
        ]);

        $login = trim((string) $request->input('login'));

        $user = User::query()
            ->where('aktif', true)
            ->where(function ($query) use ($login) {
                $query->where('nama_pengguna', $login)
                    ->orWhere('email', $login);
            })
            ->first();

        if ($user?->email) {
            try {
                $status = Password::sendResetLink(['email' => $user->email]);

                if ($status === Password::RESET_THROTTLED) {
                    return back()->withInput()
                        ->with('status', 'Permintaan terlalu sering. Coba lagi beberapa saat lagi.');
                }
            } catch (\Throwable $e) {
                Log::error('Gagal mengirim tautan reset sandi: ' . $e->getMessage());
            }
        }

        return back()->withInput()
            ->with('status', 'Jika akun memiliki email terdaftar, tautan reset sandi sudah dikirim. Jika belum, hubungi admin untuk reset sandi.');
    }
}
