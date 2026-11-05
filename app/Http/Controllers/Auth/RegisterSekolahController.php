<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SekolahSettings;
use App\Models\User;
use App\Modules\Tenant\Models\Tenant;
use App\Services\AktivitasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterSekolahController extends Controller
{
    public function create()
    {
        if (Auth::check()) {
            return redirect()->route('home');
        }

        return view('auth.daftar-sekolah');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            // Identitas Sekolah
            'nama_sekolah' => 'required|string|max:255',
            'npsn' => 'required|numeric|digits:8|unique:tenants,npsn',
            'jenjang' => 'required|in:SD,SMP,SMA,SMK',
            'subdomain' => 'required|string|min:3|max:30|regex:/^[a-z0-9\-]+$/|unique:tenants,subdomain',
            'alamat' => 'nullable|string|max:500',
            'kota' => 'nullable|string|max:100',

            // Akun Administrator Sekolah
            'nama_lengkap' => 'required|string|max:255',
            'nama_pengguna' => 'required|string|min:3|max:50|regex:/^[a-zA-Z0-9_\-\.]+$/|unique:users,nama_pengguna',
            'email' => 'required|email|max:255|unique:users,email',
            'telepon' => 'nullable|string|max:20',
            'kata_sandi' => 'required|string|min:8|confirmed',
        ], [
            'npsn.digits' => 'NPSN harus terdiri dari 8 digit angka.',
            'npsn.unique' => 'NPSN sekolah ini sudah terdaftar di SIMANTAP.',
            'subdomain.regex' => 'Subdomain hanya boleh berupa huruf kecil, angka, dan tanda hubung (-).',
            'subdomain.unique' => 'Subdomain ini sudah digunakan sekolah lain. Pilih subdomain lain.',
            'nama_pengguna.unique' => 'Nama pengguna admin ini sudah dipakai. Gunakan nama pengguna lain.',
            'nama_pengguna.regex' => 'Nama pengguna hanya boleh mengandung huruf, angka, titik, atau garis bawah.',
            'email.unique' => 'Email ini sudah terdaftar di SIMANTAP.',
            'kata_sandi.min' => 'Kata sandi minimal 8 karakter.',
            'kata_sandi.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $subdomain = strtolower(trim($validated['subdomain']));

        $result = DB::transaction(function () use ($validated, $subdomain, $request) {
            // 1. Buat Tenant Sekolah
            $tenant = Tenant::create([
                'uuid' => (string) Str::uuid(),
                'npsn' => $validated['npsn'],
                'nama_sekolah' => $validated['nama_sekolah'],
                'subdomain' => $subdomain,
                'aktif' => true,
                'meta' => [
                    'jenjang' => $validated['jenjang'],
                    'kota' => $validated['kota'] ?? null,
                    'registered_at' => now()->toIso8601String(),
                    'ip' => $request->ip(),
                ],
            ]);

            // 2. Buat Akun Administrator Sekolah
            $admin = User::create([
                'uuid' => (string) Str::uuid(),
                'nama_lengkap' => $validated['nama_lengkap'],
                'nama_pengguna' => $validated['nama_pengguna'],
                'email' => $validated['email'],
                'telepon' => $validated['telepon'] ?? null,
                'kata_sandi' => $validated['kata_sandi'],
                'peran' => 'admin',
                'tenant_id' => $tenant->id,
                'aktif' => true,
                'force_password_change' => false,
            ]);

            // 3. Buat Setting Awal Sekolah
            SekolahSettings::create([
                'tenant_id' => $tenant->id,
                'guru_id' => $admin->id,
                'nama_sekolah' => $validated['nama_sekolah'],
                'npsn' => $validated['npsn'],
                'jenjang' => $validated['jenjang'],
                'alamat' => $validated['alamat'] ?? null,
                'kota' => $validated['kota'] ?? null,
                'email' => $validated['email'],
                'telepon' => $validated['telepon'] ?? null,
                'status' => 'aktif',
            ]);

            return [$tenant, $admin];
        });

        [$tenant, $admin] = $result;

        // Login otomatis admin yang baru mendaftar
        Auth::login($admin);
        $request->session()->regenerate();

        AktivitasService::login("Pendaftaran sekolah baru: {$tenant->nama_sekolah} (NPSN: {$tenant->npsn})");

        return redirect()->route('admin.dashboard')
            ->with('success', "Selamat Datang! Sekolah \"{$tenant->nama_sekolah}\" berhasil didaftarkan. Anda dapat mulai mengelola data atau menyambungkan Dapodik via SIMANTAP Bridge.");
    }
}
