<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\SchoolRegistrationStatusMail;
use App\Mail\SchoolRegistrationVerificationMail;
use App\Models\SekolahSettings;
use App\Models\User;
use App\Modules\Tenant\Models\Tenant;
use App\Services\AktivitasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterSekolahController extends Controller
{
    public const RESERVED_SUBDOMAINS = [
        'www', 'app', 'api', 'admin', 'superadmin', 'panel', 'mail', 'smtp', 'imap', 'pop', 'ftp',
        'cbt', 'tryout', 'tka', 'dapodik', 'erapor', 'login', 'register', 'daftar-sekolah', 'auth',
        'cdn', 'assets', 'static', 'storage', 'media', 'help', 'support', 'status', 'docs',
        'dev', 'staging', 'test', 'localhost', 'simantap',
    ];

    public function create()
    {
        abort_unless(config('simantap.school_registration_enabled', true), 404);

        if (Auth::check()) {
            return redirect()->route('home');
        }

        return view('auth.daftar-sekolah');
    }

    public function store(Request $request)
    {
        abort_unless(config('simantap.school_registration_enabled', true), 404);

        $request->merge([
            'subdomain' => strtolower(trim((string) $request->input('subdomain', ''))),
            'email' => strtolower(trim((string) $request->input('email', ''))),
        ]);

        $validated = $request->validate([
            // Identitas Sekolah
            'nama_sekolah' => 'required|string|max:255',
            'npsn' => [
                'required',
                'numeric',
                'digits:8',
                'regex:/^(?!(\d)\1{7}$)[1-9]\d{7}$/',
                'unique:tenants,npsn',
                'unique:sekolah_settings,npsn',
            ],
            'jenjang' => 'required|in:SD,SMP,SMA,SMK',
            'kepala_sekolah' => 'required|string|max:255',
            'subdomain' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-z0-9](?:[a-z0-9\-]{1,28}[a-z0-9])?$/',
                Rule::notIn(self::RESERVED_SUBDOMAINS),
                'unique:tenants,subdomain',
            ],
            'alamat' => 'nullable|string|max:500',
            'kota' => 'nullable|string|max:100',

            // Akun Operator Sekolah (Administrator)
            'nama_lengkap' => 'required|string|max:255',
            'nama_pengguna' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-zA-Z0-9_\-\.]+$/', Rule::unique('users', 'nama_pengguna')->whereNull('tenant_id')],
            'email' => 'required|email|max:255|unique:users,email',
            'telepon' => 'nullable|string|max:20',
            'kata_sandi' => ['required', 'string', 'confirmed', Password::defaults()],
        ], [
            'npsn.digits' => 'NPSN harus terdiri dari 8 digit angka.',
            'npsn.regex' => 'Format NPSN tidak valid menurut standar referensi Kemendikdasmen.',
            'npsn.unique' => 'NPSN sekolah ini sudah terdaftar di SIMANTAP_MONSA.',
            'kepala_sekolah.required' => 'Nama Kepala Sekolah wajib diisi.',
            'subdomain.regex' => 'Subdomain hanya boleh berupa huruf kecil, angka, dan tanda hubung (-) serta tidak boleh diawali/diakhiri tanda hubung.',
            'subdomain.not_in' => 'Subdomain ini dicadangkan untuk sistem. Pilih nama subdomain sekolah yang lain.',
            'subdomain.unique' => 'Subdomain ini sudah digunakan sekolah lain. Pilih subdomain lain.',
            'nama_pengguna.unique' => 'Nama pengguna operator ini sudah dipakai. Gunakan nama pengguna lain.',
            'nama_pengguna.regex' => 'Nama pengguna hanya boleh mengandung huruf, angka, titik, atau garis bawah.',
            'email.unique' => 'Email ini sudah terdaftar di SIMANTAP_MONSA.',
            'kata_sandi.min' => 'Kata sandi minimal 8 karakter.',
            'kata_sandi.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $subdomain = strtolower(trim($validated['subdomain']));
        $autoAktif = ! (bool) config('simantap.school_registration_require_approval', false);
        $verificationToken = Str::random(40);

        $result = DB::transaction(function () use ($validated, $subdomain, $autoAktif, $verificationToken, $request) {
            // 1. Buat Tenant Sekolah
            $tenant = Tenant::create([
                'uuid' => (string) Str::uuid(),
                'npsn' => $validated['npsn'],
                'nama_sekolah' => $validated['nama_sekolah'],
                'subdomain' => $subdomain,
                'aktif' => $autoAktif,
                'meta' => [
                    'jenjang' => $validated['jenjang'],
                    'kota' => $validated['kota'] ?? null,
                    'kepala_sekolah' => $validated['kepala_sekolah'],
                    'registered_at' => now()->toIso8601String(),
                    'approval_status' => $autoAktif ? 'approved' : 'pending',
                    'npsn_format_valid' => true,
                    'npsn_verified' => false,
                    'email_verification_token_hash' => hash('sha256', $verificationToken),
                    'email_verification_sent_at' => now()->toIso8601String(),
                    'email_verification_expires_at' => now()->addHours(24)->toIso8601String(),
                    'ip' => $request->ip(),
                ],
            ]);

            // 2. Buat Akun Administrator Sekolah (hanya akun sekolah; bukan guru/siswa/ortu)
            $admin = User::create([
                'uuid' => (string) Str::uuid(),
                'nama_lengkap' => $validated['nama_lengkap'],
                'nama_pengguna' => $validated['nama_pengguna'],
                'email' => $validated['email'],
                'kata_sandi' => $validated['kata_sandi'],
                'peran' => 'admin',
                'tenant_id' => $tenant->id,
                'tenant_owned' => true,
                'aktif' => $autoAktif,
                'force_password_change' => false,
            ]);

            // 3. Buat Setting Awal Sekolah
            SekolahSettings::create([
                'tenant_id' => $tenant->id,
                'guru_id' => $admin->id,
                'nama_sekolah' => $validated['nama_sekolah'],
                'npsn' => $validated['npsn'],
                'jenjang' => $validated['jenjang'],
                'kepala_sekolah' => $validated['kepala_sekolah'],
                'alamat' => $validated['alamat'] ?? null,
                'kota' => $validated['kota'] ?? null,
                'email' => $validated['email'],
                'telepon' => $validated['telepon'] ?? null,
                'status' => 'aktif',
            ]);

            return [$tenant, $admin];
        });

        [$tenant, $admin] = $result;
        $verifyUrl = route('sekolah.verify-email', ['uuid' => $tenant->uuid, 'token' => $verificationToken]);

        $this->kirimEmailVerifikasi($tenant, $admin->email, $admin->nama_lengkap, $verifyUrl, ! $autoAktif);
        $this->notifikasiSuperAdminSekolahBaru($tenant);

        if (! $autoAktif) {
            AktivitasService::login("Pengajuan pendaftaran sekolah baru (menunggu verifikasi): {$tenant->nama_sekolah} (NPSN: {$tenant->npsn})");

            return redirect()->route('sekolah.status', $tenant->uuid)
                ->with('success', "Pendaftaran sekolah \"{$tenant->nama_sekolah}\" berhasil dikirim. Tautan verifikasi telah dikirim ke email {$admin->email}.");
        }

        // Login otomatis admin yang baru mendaftar
        Auth::login($admin);
        $request->session()->regenerate();

        AktivitasService::login("Pendaftaran sekolah baru: {$tenant->nama_sekolah} (NPSN: {$tenant->npsn})");

        return redirect()->route('admin.dashboard')
            ->with('success', "Selamat Datang! Sekolah \"{$tenant->nama_sekolah}\" berhasil didaftarkan. Tautan verifikasi email telah dikirim ke {$admin->email}.");
    }

    public function status(string $uuid)
    {
        $tenant = Tenant::where('uuid', $uuid)->firstOrFail();
        $admin = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('peran', 'admin')
            ->first();

        $email = (string) ($admin?->email ?? '');
        $maskedEmail = $this->maskEmail($email);

        return view('auth.status-sekolah', compact('tenant', 'maskedEmail'));
    }

    public function verifyEmail(Request $request, string $uuid, string $token)
    {
        $tenant = Tenant::where('uuid', $uuid)->firstOrFail();
        $meta = is_array($tenant->meta) ? $tenant->meta : [];
        $expectedHash = (string) ($meta['email_verification_token_hash'] ?? '');

        if ($expectedHash === '' || ! hash_equals($expectedHash, hash('sha256', $token))) {
            return redirect()->route('login')
                ->withErrors(['nama_pengguna' => 'Tautan verifikasi email tidak valid atau sudah digunakan.']);
        }

        if (! empty($meta['email_verification_expires_at']) && now()->greaterThan(\Carbon\Carbon::parse($meta['email_verification_expires_at']))) {
            return redirect()->route('sekolah.status', $tenant->uuid)
                ->withErrors(['email' => 'Tautan verifikasi email telah kedaluwarsa (berlaku 24 jam). Silakan minta pengiriman ulang tautan verifikasi.']);
        }

        $meta['email_verified_at'] = now()->toIso8601String();
        unset($meta['email_verification_token_hash'], $meta['email_verification_expires_at']);

        DB::transaction(function () use ($tenant, $meta) {
            $tenant->update(['meta' => $meta]);

            User::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('peran', 'admin')
                ->update(['email_verified_at' => now()]);
        });

        if ($tenant->fresh()->isPendingApproval()) {
            return redirect()->route('sekolah.status', $tenant->uuid)
                ->with('success', "Email operator sekolah \"{$tenant->nama_sekolah}\" berhasil diverifikasi. Pendaftaran Anda kini menunggu persetujuan Super Admin.");
        }

        return redirect()->route('login')
            ->with('success', "Email operator sekolah \"{$tenant->nama_sekolah}\" berhasil diverifikasi. Silakan masuk.");
    }

    public function resendVerificationEmail(Request $request, string $uuid)
    {
        $tenant = Tenant::where('uuid', $uuid)->firstOrFail();
        if ($tenant->isEmailVerified()) {
            return redirect()->route('login')
                ->with('status', 'Email operator sekolah ini sudah terverifikasi sebelumnya.');
        }

        $admin = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('peran', 'admin')
            ->first();

        if (! $admin || empty($admin->email)) {
            return redirect()->route('login')
                ->withErrors(['nama_pengguna' => 'Data email operator sekolah tidak ditemukan.']);
        }

        $inputEmail = strtolower(trim((string) $request->input('email', '')));
        if ($inputEmail === '' || ! hash_equals(strtolower(trim((string) $admin->email)), $inputEmail)) {
            return back()
                ->withErrors(['email' => 'Alamat email yang dimasukkan tidak sesuai dengan email operator sekolah yang terdaftar.'])
                ->withInput();
        }

        $verificationToken = Str::random(40);
        $meta = is_array($tenant->meta) ? $tenant->meta : [];
        $meta['email_verification_token_hash'] = hash('sha256', $verificationToken);
        $meta['email_verification_sent_at'] = now()->toIso8601String();
        $meta['email_verification_expires_at'] = now()->addHours(24)->toIso8601String();

        $tenant->update(['meta' => $meta]);
        $verifyUrl = route('sekolah.verify-email', ['uuid' => $tenant->uuid, 'token' => $verificationToken]);

        $this->kirimEmailVerifikasi($tenant, $admin->email, (string) $admin->nama_lengkap, $verifyUrl, $tenant->isPendingApproval());

        return redirect()->route('sekolah.status', $tenant->uuid)
            ->with('success', "Tautan verifikasi email baru untuk \"{$tenant->nama_sekolah}\" telah dikirim ke {$this->maskEmail($admin->email)} (berlaku 24 jam).");
    }

    private function kirimEmailVerifikasi(Tenant $tenant, string $email, string $namaOperator, string $verifyUrl, bool $requireApproval): void
    {
        try {
            Mail::to($email)->send(new SchoolRegistrationVerificationMail(
                $tenant,
                $namaOperator,
                $verifyUrl,
                $requireApproval,
            ));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function notifikasiSuperAdminSekolahBaru(Tenant $tenant): void
    {
        try {
            $superAdmins = User::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->where('peran', 'super_admin')
                ->where('aktif', true)
                ->whereNotNull('email')
                ->get();

            foreach ($superAdmins as $sa) {
                if (filled($sa->email)) {
                    Mail::to($sa->email)->send(new SchoolRegistrationStatusMail($tenant, 'new_registration_admin_alert'));
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function maskEmail(string $email): string
    {
        if (! str_contains($email, '@')) {
            return '***';
        }
        [$local, $domain] = explode('@', $email, 2);
        $prefix = substr($local, 0, min(2, strlen($local)));

        return $prefix.'***@'.$domain;
    }
}
