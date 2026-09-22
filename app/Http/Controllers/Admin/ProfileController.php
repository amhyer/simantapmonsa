<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aktivitas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Halaman profil admin (cermin menu Profile e-Rapor).
     */
    public function show(): View
    {
        $user = auth()->user();
        $aktivitasTerakhir = Aktivitas::where('guru_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return view('admin.profile', compact('user', 'aktivitasTerakhir'));
    }

    /**
     * Ubah password dari halaman profil.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'kata_sandi_saat_ini' => ['required'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'kata_sandi_saat_ini.required' => 'Password saat ini wajib diisi.',
            'password.required' => 'Password baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.min' => 'Password minimal 8 karakter.',
        ]);

        $user = auth()->user();

        if (! Hash::check($request->kata_sandi_saat_ini, $user->kata_sandi)) {
            return back()->withErrors(['kata_sandi_saat_ini' => 'Password saat ini salah.']);
        }

        if (Hash::check($request->password, $user->kata_sandi)) {
            return back()->withErrors(['password' => 'Password baru harus berbeda dari password lama.']);
        }

        $user->update(['kata_sandi' => bcrypt($request->password)]);

        return back()->with('success', 'Password berhasil diubah.');
    }
}
