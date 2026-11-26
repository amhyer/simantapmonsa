<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AktivitasService;
use App\Services\Auth\TotpService;
use App\Support\SessionSecurity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MfaSettingsController extends Controller
{
    public function show(Request $request, TotpService $totp): View
    {
        /** @var User $user */
        $user = auth()->user();
        abort_unless($user && $user->isMfaAllowed(), 403, 'MFA tidak tersedia untuk peran akun Anda.');

        $pendingSecret = null;
        $qrSvg = null;

        if (! $user->hasMfaEnabled()) {
            $pendingSecret = (string) $request->session()->get('mfa_setup_secret', '');
            if ($pendingSecret === '') {
                $pendingSecret = $totp->generateSecret();
                $request->session()->put('mfa_setup_secret', $pendingSecret);
            }

            $issuer = (string) config('app.name', 'SIMANTAP');
            $account = $user->email ?: $user->nama_pengguna;
            $uri = $totp->provisioningUri($issuer, $account, $pendingSecret);
            $qrSvg = $totp->qrCodeSvg($uri);
        }

        $sidebarPartial = match ($user->peran) {
            'super_admin' => 'superadmin.partials.sidebar',
            'admin' => 'admin.partials.sidebar',
            'kepsek' => 'kepsek.partials.sidebar',
            'pengawas' => 'pengawas.partials.sidebar',
            'guru' => 'guru.partials.sidebar',
            'tendik' => 'pegawai.partials.sidebar',
            'siswa' => 'siswa.partials.sidebar',
            'ortu' => 'ortu.partials.sidebar',
            default => null,
        };

        return view('account.mfa', [
            'user' => $user,
            'sidebarPartial' => $sidebarPartial,
            'mfaEnabled' => $user->hasMfaEnabled(),
            'mfaEnforced' => $user->isMfaEnforced(),
            'pendingSecret' => $pendingSecret,
            'qrSvg' => $qrSvg,
            'remainingRecoveryCodes' => count((array) ($user->mfa_recovery_codes ?? [])),
            'freshRecoveryCodes' => $request->session()->get('mfa_fresh_recovery_codes'),
        ]);
    }

    public function enable(Request $request, TotpService $totp): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();
        abort_unless($user && $user->isMfaAllowed(), 403);

        $request->validate([
            'kata_sandi_saat_ini' => ['required', 'string'],
            'code' => ['required', 'string', 'max:16'],
        ], [
            'kata_sandi_saat_ini.required' => 'Kata sandi saat ini wajib diisi.',
            'code.required' => 'Kode OTP 6 digit dari aplikasi authenticator wajib diisi.',
        ]);

        if (! Hash::check((string) $request->input('kata_sandi_saat_ini'), (string) $user->kata_sandi)) {
            return back()->withErrors(['kata_sandi_saat_ini' => 'Kata sandi saat ini tidak sesuai.']);
        }

        $secret = (string) $request->session()->get('mfa_setup_secret', '');
        if ($secret === '') {
            return back()->withErrors(['code' => 'Sesi aktivasi MFA kedaluwarsa. Muat ulang halaman untuk membuat QR baru.']);
        }

        $matchedWindow = $totp->verifyCode($secret, (string) $request->input('code'));
        if ($matchedWindow === null) {
            return back()->withErrors(['code' => 'Kode OTP tidak valid. Pastikan jam perangkat Anda sinkron dan coba kode terbaru.']);
        }

        $recovery = $totp->generateRecoveryCodes(8);

        $user->forceFill([
            'mfa_secret' => $secret,
            'mfa_recovery_codes' => $recovery['hashes'],
            'mfa_confirmed_at' => now(),
            'mfa_last_used_window' => $matchedWindow,
            'remember_token' => Str::random(60),
        ])->save();

        $request->session()->forget('mfa_setup_secret');
        $request->session()->regenerate();
        SessionSecurity::invalidateUserSessions((int) $user->id, $request->session()->getId());

        AktivitasService::ubah(
            'Aktivasi MFA',
            'Pengguna mengaktifkan Autentikasi Dua Faktor (TOTP)',
            'users',
            $user->id
        );

        return redirect()->route('account.mfa.show')
            ->with('success', 'Autentikasi Dua Faktor (MFA) berhasil diaktifkan. Simpan kode pemulihan di tempat aman!')
            ->with('mfa_fresh_recovery_codes', $recovery['plain']);
    }

    public function regenerateRecoveryCodes(Request $request, TotpService $totp): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();
        abort_unless($user && $user->isMfaAllowed() && $user->hasMfaEnabled(), 403);

        $request->validate([
            'kata_sandi_saat_ini' => ['required', 'string'],
        ], [
            'kata_sandi_saat_ini.required' => 'Kata sandi saat ini wajib diisi untuk membuat ulang kode pemulihan.',
        ]);

        if (! Hash::check((string) $request->input('kata_sandi_saat_ini'), (string) $user->kata_sandi)) {
            return back()->withErrors(['kata_sandi_saat_ini' => 'Kata sandi saat ini tidak sesuai.']);
        }

        $recovery = $totp->generateRecoveryCodes(8);
        $user->forceFill([
            'mfa_recovery_codes' => $recovery['hashes'],
        ])->save();

        AktivitasService::ubah(
            'Regenerasi Kode Pemulihan MFA',
            'Pengguna membuat ulang 8 kode pemulihan MFA',
            'users',
            $user->id
        );

        return redirect()->route('account.mfa.show')
            ->with('success', 'Kode pemulihan baru berhasil dibuat. Kode lama sudah tidak berlaku.')
            ->with('mfa_fresh_recovery_codes', $recovery['plain']);
    }

    public function disable(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();
        abort_unless($user && $user->isMfaAllowed(), 403);

        if ($user->isMfaEnforced()) {
            return back()->withErrors([
                'kata_sandi_saat_ini' => 'MFA wajib aktif untuk peran Anda dan tidak dapat dinonaktifkan sendiri.',
            ]);
        }

        $request->validate([
            'kata_sandi_saat_ini' => ['required', 'string'],
        ], [
            'kata_sandi_saat_ini.required' => 'Kata sandi saat ini wajib diisi untuk menonaktifkan MFA.',
        ]);

        if (! Hash::check((string) $request->input('kata_sandi_saat_ini'), (string) $user->kata_sandi)) {
            return back()->withErrors(['kata_sandi_saat_ini' => 'Kata sandi saat ini tidak sesuai.']);
        }

        $user->forceFill([
            'mfa_secret' => null,
            'mfa_recovery_codes' => null,
            'mfa_confirmed_at' => null,
            'mfa_last_used_window' => null,
            'remember_token' => Str::random(60),
        ])->save();

        $request->session()->regenerate();
        SessionSecurity::invalidateUserSessions((int) $user->id, $request->session()->getId());

        AktivitasService::ubah(
            'Nonaktifkan MFA',
            'Pengguna menonaktifkan Autentikasi Dua Faktor (TOTP)',
            'users',
            $user->id
        );

        return redirect()->route('account.mfa.show')
            ->with('success', 'Autentikasi Dua Faktor (MFA) berhasil dinonaktifkan.');
    }
}
