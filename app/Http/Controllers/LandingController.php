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
            'peran' => 'required|in:guru,siswa,ortu,kepsek',
        ]);

        User::create([
            'uuid' => Str::uuid(),
            'nama_lengkap' => $validated['nama_lengkap'],
            'nama_pengguna' => $validated['nama_pengguna'],
            'kata_sandi' => Hash::make($validated['kata_sandi']),
            'peran' => $validated['peran'],
            'terhubung_dengan' => [],
            'kelas_mata_pelajaran' => null,
            'aktif' => true,
        ]);

        return redirect()->route('login')
            ->with('success', 'Akun berhasil dibuat. Silakan login.');
    }

    public function demo()
    {
        if (!app()->environment('local')) {
            return redirect()->route('login');
        }
        return redirect()->route('login')->with('info', 'Demo: admin/admin123, guru/guru123');
    }
}
