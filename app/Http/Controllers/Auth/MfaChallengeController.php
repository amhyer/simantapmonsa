<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AktivitasService;
use App\Services\Auth\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class MfaChallengeController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login')
                ->withErrors(['nama_pengguna' => 'Sesi verifikasi MFA telah kedaluwarsa. Silakan masuk kembali.']);
        }

        return view('auth.mfa-challenge', [
            'namaPengguna' => $user->nama_pengguna,
        ]);
    }

    public function verify(Request $request, TotpService $totp): RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login')
                ->withErrors(['nama_pengguna' => 'Sesi verifikasi MFA telah kedaluwarsa. Silakan masuk kembali.']);
        }

        $throttleKey = 'mfa-challenge:'.$user->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'code' => "Terlalu banyak percobaan verifikasi. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ], [
            'code.required' => 'Masukkan kode OTP 6 digit atau kode pemulihan.',
        ]);

        $input = trim((string) $validated['code']);
        $verified = false;
        $usedRecovery = false;

        $matchedWindow = $totp->verifyCode(
            (string) $user->mfa_secret,
            $input,
            $user->mfa_last_used_window !== null ? (int) $user->mfa_last_used_window : null
        );

        if ($matchedWindow !== null) {
            $user->forceFill([
                'mfa_last_used_window' => $matchedWindow,
            ])->save();
            $verified = true;
        } else {
            $storedHashes = is_array($user->mfa_recovery_codes) ? $user->mfa_recovery_codes : [];
            $remaining = $totp->consumeRecoveryCode($storedHashes, $input);

            if ($remaining !== null) {
                $user->forceFill([
                    'mfa_recovery_codes' => $remaining,
                ])->save();
                $verified = true;
                $usedRecovery = true;
            }
        }

        if (! $verified) {
            RateLimiter::hit($throttleKey, 60);

            return back()->withErrors([
                'code' => 'Kode OTP atau kode pemulihan tidak valid atau sudah digunakan.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->forget(['mfa_pending_user_id', 'mfa_pending_expires_at']);

        Auth::login($user);
        $request->session()->regenerate();

        $user->update(['terakhir_masuk' => now()]);
        AktivitasService::login(
            'Login (MFA '.($usedRecovery ? 'recovery code' : 'TOTP').') sebagai '.$user->peran.' dari IP '.$request->ip()
        );

        $redirect = LoginController::redirectForRole($user);

        if ($usedRecovery) {
            $sisa = count((array) ($user->mfa_recovery_codes ?? []));

            return $redirect->with(
                'warning',
                "Anda masuk menggunakan kode pemulihan MFA. Sisa kode pemulihan: {$sisa}. Segera perbarui perangkat authenticator Anda."
            );
        }

        return $redirect;
    }

    private function pendingUser(Request $request): ?User
    {
        $userId = $request->session()->get('mfa_pending_user_id');
        $expiresAt = (int) $request->session()->get('mfa_pending_expires_at', 0);

        if (! $userId || $expiresAt < now()->timestamp) {
            $request->session()->forget(['mfa_pending_user_id', 'mfa_pending_expires_at']);

            return null;
        }

        $user = User::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereKey($userId)
            ->first();

        if (! $user || ! $user->aktif || $user->archived_at !== null || ! $user->hasMfaEnabled()) {
            $request->session()->forget(['mfa_pending_user_id', 'mfa_pending_expires_at']);

            return null;
        }

        return $user;
    }
}
