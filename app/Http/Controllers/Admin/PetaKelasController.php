<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rombel;
use App\Models\User;
use App\Models\Siswa;
use App\Models\PengaturanGuru;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PetaKelasController extends Controller
{
    public function daftar(Request $request)
    {
        $jenis = $request->get('jenis');
        $jenisList = ['Reguler', 'Pilihan', 'Ekskul', 'Lainnya'];

        $query = Rombel::with(['guru', 'semester'])->withCount('siswa')->orderBy('nama_rombel');
        if ($jenis) {
            $query->where('jenis_rombel', $jenis);
        }
        $rombel = $query->get();

        return view('admin.peta-kelas.daftar', compact('rombel', 'jenis', 'jenisList'));
    }

    public function detail(Rombel $rombel)
    {
        $rombel->load(['guru', 'semester', 'siswa' => fn($q) => $q->orderBy('nama_peserta_didik')]);

        return view('admin.peta-kelas.detail', compact('rombel'));
    }

    public function index()
    {
        $guru = User::where('peran', 'guru')->orderBy('nama_lengkap')->get();
        $siswa = Siswa::with('guru')->orderBy('nama_peserta_didik')->get();
        $pengaturanGuru = PengaturanGuru::all();

        $siswaPerGuru = $siswa->groupBy('guru_id');
        $tidakDitugaskan = $siswa->filter(fn($s) => empty($s->guru_id));

        $guruStats = $guru->map(function ($g) use ($siswaPerGuru, $pengaturanGuru) {
            $count = $siswaPerGuru->get($g->id, collect())->count();
            $kelasList = $pengaturanGuru->where('guru_id', $g->id)->pluck('kelas')->unique()->values();
            return [
                'guru' => $g,
                'jumlah_siswa' => $count,
                'kelas_list' => $kelasList,
            ];
        });

        return view('admin.peta-kelas.index', compact('guru', 'siswa', 'pengaturanGuru', 'guruStats', 'siswaPerGuru', 'tidakDitugaskan'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'guru_id' => 'required|exists:users,id',
            'kelas' => 'required|string|max:255',
            'mata_pelajaran' => 'nullable|string|max:255',
        ]);

        $guru = User::findOrFail($validated['guru_id']);

        $pengaturan = PengaturanGuru::updateOrCreate(
            ['guru_id' => $guru->id],
            [
                'nama_guru' => $guru->nama_lengkap,
                'kelas' => $validated['kelas'],
                'mata_pelajaran' => $validated['mata_pelajaran'] ?? null,
                'kkm' => getKKM(),
                'semester' => 'Ganjil',
                'tahun_pelajaran' => date('Y') . '/' . (date('Y') + 1),
                'pembaruan' => now(),
            ]
        );

        $message = $pengaturan->wasRecentlyCreated
            ? 'Kelas berhasil ditambahkan ke guru.'
            : 'Pengaturan guru berhasil diperbarui.';

        return redirect()->route('admin.peta-kelas.index')
            ->with('success', $message);
    }

    public function destroy($id)
    {
        $pengaturan = PengaturanGuru::findOrFail($id);

        if ($pengaturan->siswa->count() > 0) {
            return redirect()->route('admin.peta-kelas.index')
                ->with('error', 'Tidak bisa menghapus pengaturan kelas yang masih memiliki siswa terkait.');
        }

        $pengaturan->delete();

        return redirect()->route('admin.peta-kelas.index')
            ->with('success', 'Pengaturan kelas berhasil dihapus.');
    }

    public function pindahSiswa(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'siswa_id' => 'required|exists:siswa,id',
            'guru_id' => 'required|exists:users,id',
            'kelas' => 'required|string',
        ]);

        $siswa = Siswa::findOrFail($validated['siswa_id']);
        $guru = User::findOrFail($validated['guru_id']);

        $siswa->update([
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'kelas' => $validated['kelas'],
        ]);

        PengaturanGuru::firstOrCreate(
            ['guru_id' => $guru->id],
            [
                'nama_guru' => $guru->nama_lengkap,
                'kelas' => $validated['kelas'],
                'kkm' => getKKM(),
                'semester' => 'Ganjil',
                'tahun_pelajaran' => date('Y') . '/' . (date('Y') + 1),
                'pembaruan' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => "{$siswa->nama_peserta_didik} dipindahkan ke {$guru->nama_lengkap} ({$validated['kelas']})",
        ]);
    }

    public function keluarkanSiswa(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'siswa_id' => 'required|exists:siswa,id',
        ]);

        $siswa = Siswa::findOrFail($validated['siswa_id']);
        $nama = $siswa->nama_peserta_didik;

        $siswa->update([
            'guru_id' => null,
            'nama_guru' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => "{$nama} dikeluarkan dari kelas",
        ]);
    }

    public function getKelasOptions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'guru_id' => 'required|exists:users,id',
        ]);

        $kelasList = PengaturanGuru::where('guru_id', $validated['guru_id'])
            ->pluck('kelas')
            ->unique()
            ->values();

        return response()->json(['kelas' => $kelasList]);
    }
}
