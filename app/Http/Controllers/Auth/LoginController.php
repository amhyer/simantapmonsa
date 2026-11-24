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
    private const DUMMY_HASH = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    public function showLoginForm()
    {
        if (auth()->check()) {
            return redirect()->route('home');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'nama_pengguna' => 'required|string',
            'kata_sandi' => 'required|string',
        ]);

        // Tiga lapis throttle: per-IP (5x), pasangan IP+akun (5x), dan per-akun (20x).
        // Ambang per-akun lebih longgar agar pelaku tidak mudah mengunci akun orang lain
        // secara sengaja, sambil tetap memblokir credential-stuffing terdistribusi.
        $normalizedUsername = mb_strtolower(trim((string) $credentials['nama_pengguna']));
        $throttleKey = 'login:'.$request->ip();
        $pairKey = 'login-pair:'.$request->ip().'|'.$normalizedUsername;
        $accountKey = 'login-account:'.$normalizedUsername;

        foreach ([[$throttleKey, 5], [$pairKey, 5], [$accountKey, 20]] as [$key, $maxAttempts]) {
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                $seconds = RateLimiter::availableIn($key);
                throw ValidationException::withMessages([
                    'nama_pengguna' => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.",
                ]);
            }
        }

        $attemptSuccess = false;
        if (TenantContext::check()) {
            $attemptSuccess = Auth::attempt([
                'nama_pengguna' => $credentials['nama_pengguna'],
                'password' => $credentials['kata_sandi'],
                'tenant_id' => TenantContext::id(),
            ]);

            // Izinkan Super Admin login dari portal mana saja (tanpa terhalang TenantScope karena super_admin memiliki tenant_id = null).
            // Selalu jalankan Hash::check (dengan dummy hash bila tidak ditemukan) untuk mencegah timing-oracle username.
            if (! $attemptSuccess) {
                $superAdminUser = User::withoutGlobalScopes()
                    ->whereNull('deleted_at')
                    ->where('nama_pengguna', $credentials['nama_pengguna'])
                    ->where('peran', 'super_admin')
                    ->first();

                $passwordValid = Hash::check(
                    $credentials['kata_sandi'],
                    $superAdminUser ? (string) $superAdminUser->kata_sandi : self::DUMMY_HASH
                );

                if ($superAdminUser && $passwordValid) {
                    Auth::login($superAdminUser);
                    $attemptSuccess = true;
                }
            }
        } else {
            $kandidatAktif = User::query()
                ->where('nama_pengguna', $credentials['nama_pengguna'])
                ->where('aktif', true)
                ->whereNull('archived_at')
                ->get();

            if ($kandidatAktif->isNotEmpty()) {
                $cocokSandi = $kandidatAktif->filter(
                    fn (User $u) => Hash::check($credentials['kata_sandi'], (string) $u->kata_sandi)
                );

                if ($cocokSandi->pluck('tenant_id')->unique()->count() > 1) {
                    RateLimiter::hit($throttleKey, 300);
                    RateLimiter::hit($pairKey, 300);
                    RateLimiter::hit($accountKey, 300);

                    return back()->withErrors([
                        'nama_pengguna' => 'Nama pengguna terdaftar di lebih dari satu sekolah. Silakan masuk melalui alamat portal/subdomain sekolah Anda.',
                    ])->onlyInput('nama_pengguna');
                }

                if ($cocokSandi->count() === 1) {
                    Auth::login($cocokSandi->first());
                    $attemptSuccess = true;
                } else {
                    $attemptSuccess = false;
                }
            } else {
                Hash::check($credentials['kata_sandi'], self::DUMMY_HASH);
                $attemptSuccess = false;
            }
        }

        if ($attemptSuccess) {
            $user = auth()->user();

            // Tolak akun nonaktif/terarsip dengan pesan generik yang sama agar
            // penyerang tidak dapat memastikan validitas kata sandi akun nonaktif.
            if (! $user->aktif || $user->archived_at !== null) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                RateLimiter::hit($throttleKey, 300);
                RateLimiter::hit($pairKey, 300);
                RateLimiter::hit($accountKey, 300);

                return back()->withErrors([
                    'nama_pengguna' => 'Nama pengguna atau kata sandi salah.',
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
            RateLimiter::clear($pairKey);
            RateLimiter::clear($accountKey);
            $request->session()->regenerate();

            return match ($user->peran) {
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

        RateLimiter::hit($throttleKey, 300);
        RateLimiter::hit($pairKey, 300);
        RateLimiter::hit($accountKey, 300);

        // Jika operator sekolah yang belum aktif/belum verifikasi mencoba login dengan sandi yang benar,
        // arahkan atau tampilkan tombol ke halaman status pendaftaran & kirim ulang verifikasi email.
        $pendingAdmin = User::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('nama_pengguna', $credentials['nama_pengguna'])
            ->where('peran', 'admin')
            ->where('aktif', false)
            ->whereNotNull('tenant_id')
            ->with('tenant')
            ->get()
            ->first(fn (User $u) => Hash::check($credentials['kata_sandi'], (string) $u->kata_sandi));

        if ($pendingAdmin && $pendingAdmin->tenant) {
            return back()
                ->with('pending_tenant_uuid', $pendingAdmin->tenant->uuid)
                ->with('pending_school_name', $pendingAdmin->tenant->nama_sekolah)
                ->withErrors([
                    'nama_pengguna' => "Akun sekolah \"{$pendingAdmin->tenant->nama_sekolah}\" belum aktif. Silakan verifikasi email operator atau cek status persetujuan pendaftaran.",
                ])->onlyInput('nama_pengguna');
        }

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
