<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LandingController extends Controller
{
    public function index()
    {
        return view('landing');
    }

    public function register()
    {
        return view('auth.register');
    }

    public function storeRegister(Request $request)
    {
        $validated = $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'nama_pengguna' => 'required|string|max:100|unique:users',
            'kata_sandi' => 'required|string|min:8|confirmed',
            'peran' => 'required|in:guru,siswa,ortu',
        ]);

        // PERBAIKAN KEAMANAN: pendaftaran publik sebelumnya membuat akun
        // berperan "guru" yang LANGSUNG aktif (siapa pun bisa mendaftar sebagai
        // guru tanpa verifikasi). Kini akun dibuat NONAKTIF dan harus
        // diaktifkan admin (Admin > Pengguna) sebelum bisa dipakai login.
        User::create([
            'uuid' => Str::uuid(),
            'nama_lengkap' => $validated['nama_lengkap'],
            'nama_pengguna' => $validated['nama_pengguna'],
            'kata_sandi' => Hash::make($validated['kata_sandi']),
            'peran' => $validated['peran'],
            'terhubung_dengan' => [],
            'kelas_mata_pelajaran' => null,
            'aktif' => false,
            'force_password_change' => false,
        ]);

        return redirect()->route('login')
            ->with('success', 'Pendaftaran berhasil. Akun Anda menunggu aktivasi administrator sekolah sebelum dapat digunakan.');
    }

    public function demo()
    {
        if (!app()->environment('local')) {
            return redirect()->route('login');
        }
        return redirect()->route('login')->with('info', 'Demo: admin/admin123, guru/guru123');
    }
}
