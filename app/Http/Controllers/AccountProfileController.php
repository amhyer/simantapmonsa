<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AktivitasService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Halaman profil dan ubah kata sandi mandiri untuk seluruh peran pengguna
 * (Guru, Orang Tua, Kepala Sekolah, Pengawas Sekolah, Tendik, Admin, Siswa, Super Admin).
 */
class AccountProfileController extends Controller
{
    public function show(): View
    {
        /** @var User $user */
        $user = auth()->user();

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

        return view('account.profile', [
            'user' => $user,
            'sidebarPartial' => $sidebarPartial,
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $validated = $request->validate([
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ], [
            'email.email' => 'Format alamat email tidak valid.',
            'email.unique' => 'Alamat email ini sudah digunakan akun lain.',
        ]);

        $email = isset($validated['email']) ? trim((string) $validated['email']) : '';
        $user->update([
            'email' => $email !== '' ? $email : null,
        ]);

        AktivitasService::ubah(
            'Pembaruan Profil Mandiri',
            'Pengguna memperbarui alamat email pemulihan akun',
            'users',
            $user->id
        );

        return back()->with('success', 'Data profil akun berhasil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'kata_sandi_saat_ini' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'kata_sandi_saat_ini.required' => 'Kata sandi saat ini wajib diisi.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
        ]);

        /** @var User $user */
        $user = auth()->user();

        if (! Hash::check((string) $request->input('kata_sandi_saat_ini'), (string) $user->kata_sandi)) {
            return back()->withErrors([
                'kata_sandi_saat_ini' => 'Kata sandi saat ini tidak sesuai.',
            ]);
        }

        if (Hash::check((string) $request->input('password'), (string) $user->kata_sandi)) {
            return back()->withErrors([
                'password' => 'Kata sandi baru harus berbeda dari kata sandi saat ini.',
            ]);
        }

        $user->update([
            'kata_sandi' => Hash::make((string) $request->input('password')),
            'force_password_change' => false,
        ]);

        AktivitasService::ubah(
            'Ubah Kata Sandi Mandiri',
            'Pengguna memperbarui kata sandi melalui halaman profil mandiri',
            'users',
            $user->id
        );

        return back()->with('success', 'Kata sandi berhasil diperbarui.');
    }
}
