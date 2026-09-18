<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Siswa;
use App\Models\NilaiErapot;
use App\Services\AktivitasService;
use App\Jobs\ProsesAkunMassalJob;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('peran')->orderBy('nama_lengkap')->get();
        $siswa = Siswa::with('guru')->get();
        return view('admin.users.index', compact('users', 'siswa'));
    }

    public function create()
    {
        $siswa = Siswa::all();
        return view('admin.users.create', compact('siswa'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'nama_pengguna' => 'required|string|max:100|unique:users',
            'kata_sandi' => 'required|string|min:8',
            'peran' => 'required|in:admin,guru,siswa,ortu,kepsek',
            'terhubung_dengan' => 'nullable|array',
            'kelas_mata_pelajaran' => 'nullable|string|max:255',
            'aktif' => 'boolean',
        ]);

        $user = User::create([
            'uuid' => Str::uuid(),
            'nama_lengkap' => $validated['nama_lengkap'],
            'nama_pengguna' => $validated['nama_pengguna'],
            'kata_sandi' => Hash::make($validated['kata_sandi']),
            'peran' => $validated['peran'],
            'terhubung_dengan' => $validated['terhubung_dengan'] ?? [],
            'kelas_mata_pelajaran' => $validated['kelas_mata_pelajaran'] ?? null,
            'aktif' => $validated['aktif'] ?? true,
        ]);

        if ($validated['peran'] === 'guru') {
            \App\Models\PengaturanGuru::create([
                'guru_id' => $user->id,
                'nama_guru' => $user->nama_lengkap,
                'kelas' => $validated['kelas_mata_pelajaran'] ?? null,
                'kkm' => getKKM(),
                'semester' => 'Ganjil',
                'tahun_pelajaran' => date('Y') . '/' . (date('Y') + 1),
                'pembaruan' => now(),
            ]);
        }

        AktivitasService::tambah(
            'Buat Akun: ' . $validated['nama_lengkap'],
            'Akun ' . $validated['peran'] . ' berhasil dibuat',
            'users',
            $user->id,
            ['nama_lengkap' => $validated['nama_lengkap'], 'peran' => $validated['peran']]
        );

        return redirect()->route('admin.users.index')
            ->with('success', 'Akun berhasil dibuat.');
    }

    public function edit(User $user)
    {
        $siswa = Siswa::all();
        return view('admin.users.edit', compact('user', 'siswa'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'nama_pengguna' => 'required|string|max:100|unique:users,nama_pengguna,' . $user->id,
            'kata_sandi' => 'nullable|string|min:8',
            'peran' => 'required|in:admin,guru,siswa,ortu,kepsek',
            'terhubung_dengan' => 'nullable|array',
            'kelas_mata_pelajaran' => 'nullable|string|max:255',
            'aktif' => 'boolean',
        ]);

        $data = [
            'nama_lengkap' => $validated['nama_lengkap'],
            'nama_pengguna' => $validated['nama_pengguna'],
            'peran' => $validated['peran'],
            'terhubung_dengan' => $validated['terhubung_dengan'] ?? [],
            'kelas_mata_pelajaran' => $validated['kelas_mata_pelajaran'] ?? null,
            'aktif' => $validated['aktif'] ?? true,
        ];

        if (!empty($validated['kata_sandi'])) {
            $data['kata_sandi'] = Hash::make($validated['kata_sandi']);
        }

        $user->update($data);

        AktivitasService::ubah(
            'Ubah Akun: ' . $user->nama_lengkap,
            'Data akun ' . $user->peran . ' diperbarui',
            'users',
            $user->id,
            null,
            ['nama_lengkap' => $validated['nama_lengkap'], 'peran' => $validated['peran']]
        );

        return redirect()->route('admin.users.index')
            ->with('success', 'Akun berhasil diperbarui.');
    }

    public function destroy(User $user)
    {
        if ($user->nama_pengguna === 'admin') {
            return redirect()->back()->with('error', 'Akun admin utama tidak dapat dihapus.');
        }

        $nama = $user->nama_lengkap;
        $peran = $user->peran;

        DB::transaction(function () use ($user) {
            $this->deleteUserRelations($user);
            $user->delete();
        });

        AktivitasService::hapus(
            'Hapus Akun: ' . $nama,
            'Akun ' . $peran . ' dihapus',
            'users',
            null,
            ['nama_lengkap' => $nama, 'peran' => $peran]
        );

        return redirect()->route('admin.users.index')
            ->with('success', 'Akun berhasil dihapus.');
    }

    public function destroyAll(Request $request, string $peran)
    {
        $allowed = ['guru', 'siswa', 'ortu'];
        if (!in_array($peran, $allowed)) {
            return response()->json(['success' => false, 'message' => 'Peran tidak valid.'], 400);
        }

        $users = User::where('peran', $peran)->where('nama_pengguna', '!=', 'admin')->get();
        $count = $users->count();

        if ($count === 0) {
            return response()->json(['success' => true, 'message' => 'Tidak ada akun ' . $peran . ' yang perlu dihapus.']);
        }

        $labels = ['guru' => 'Guru', 'siswa' => 'Siswa', 'ortu' => 'Orang Tua', 'kepsek' => 'Kepsek'];

        DB::transaction(function () use ($users) {
            foreach ($users as $user) {
                $this->deleteUserRelations($user);
                $user->delete();
            }
        });

        AktivitasService::hapus(
            'Hapus Semua Akun: ' . ($labels[$peran] ?? $peran),
            "{$count} akun " . ($labels[$peran] ?? $peran) . " berhasil dihapus",
            'users'
        );

        return response()->json([
            'success' => true,
            'message' => "{$count} akun " . ($labels[$peran] ?? $peran) . " berhasil dihapus.",
        ]);
    }

    private function deleteUserRelations(User $user): void
    {
        if ($user->peran === 'guru') {
            $user->pengaturanGuru()->delete();
            $user->ringkasanGuru()->delete();
            $user->siswa()->each(function ($siswa) {
                $siswa->nilai()->delete();
                $siswa->kehadiran()->delete();
                $siswa->hasilKuis()->delete();
                $siswa->catatan()->delete();
                $siswa->dimensi()->delete();
                $siswa->kebiasaan()->delete();
                $siswa->nilaiErapot()->delete();
                $siswa->delete();
            });
            $user->materi()->delete();
            $user->kuis()->each(function ($kuis) {
                $kuis->hasilKuis()->delete();
                $kuis->delete();
            });
            NilaiErapot::where('guru_id', $user->id)->delete();
        }

        if ($user->peran === 'siswa') {
            $siswa = Siswa::where('nis', $user->nama_pengguna)->first();
            if ($siswa) {
                $siswa->nilai()->delete();
                $siswa->kehadiran()->delete();
                $siswa->hasilKuis()->delete();
                $siswa->catatan()->delete();
                $siswa->dimensi()->delete();
                $siswa->kebiasaan()->delete();
                $siswa->nilaiErapot()->delete();
                $siswa->delete();
            }
        }
    }

    public function akunMassal()
    {
        $siswa = Siswa::with('guru')->get();
        $users = User::all();
        return view('admin.users.massal', compact('siswa', 'users'));
    }

public function prosesAkunMassal(Request $request)
    {
        $request->validate([
            'siswa_ids' => 'required|array',
            'siswa_ids.*' => 'exists:siswa,id',
            'buat_ortu' => 'boolean',
            'buat_siswa' => 'boolean',
        ]);

        // Dispatch job async
        ProsesAkunMassalJob::dispatch($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Proses akun massal dimulai secara asynchronous. Cek status dalam waktu singkat.',
        ]);
    }

    public function siswaIndex()
    {
        $users = User::all();
        $siswa = Siswa::with('guru')->orderBy('nama_peserta_didik')->get();
        return view('admin.users.siswa', compact('users', 'siswa'));
    }
}
