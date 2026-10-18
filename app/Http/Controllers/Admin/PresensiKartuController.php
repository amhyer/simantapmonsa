<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Services\AktivitasService;
use App\Services\Presensi\StudentQrHasher;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PresensiKartuController extends Controller
{
    public function index(Request $request)
    {
        $query = Siswa::query()->with('rombel')->where('aktif', true);
        if ($request->filled('q')) {
            $term = trim((string) $request->query('q'));
            $query->where(function ($builder) use ($term) {
                $pattern = '%' . mb_strtolower($term) . '%';
                $builder->whereRaw('LOWER(nama_peserta_didik) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(nis) LIKE ?', [$pattern]);
            });
        }

        $students = $query->orderBy('nama_peserta_didik')->paginate(40)->withQueryString();

        return view('admin.presensi.kartu', compact('students'));
    }

    public function bind(Request $request, Siswa $siswa, StudentQrHasher $hasher)
    {
        abort_unless($siswa->aktif, 422, 'Siswa tidak aktif.');
        $validated = $request->validate([
            'qr_payload' => 'required|string|max:2048',
        ]);
        $digest = $hasher->hash($validated['qr_payload']);

        $otherStudent = Siswa::where('qr_token_hash', $digest)->where('id', '!=', $siswa->id)->first();
        if ($otherStudent) {
            return response()->json([
                'success' => false,
                'message' => 'QR ini sudah terhubung ke siswa lain. Lepaskan kartu lama terlebih dahulu.',
            ], Response::HTTP_CONFLICT);
        }

        $hadCard = filled($siswa->qr_token_hash);
        $siswa->forceFill(['qr_token_hash' => $digest])->save();

        AktivitasService::log(
            'kehadiran',
            $hadCard ? 'Ganti QR kartu siswa' : 'Hubungkan QR kartu siswa',
            'QR kartu untuk siswa ' . $siswa->nama_peserta_didik . ' ' . ($hadCard ? 'diperbarui.' : 'dihubungkan.'),
            'siswa',
            $siswa->id,
            ['qr_terhubung' => $hadCard],
            ['qr_terhubung' => true],
        );

        return response()->json([
            'success' => true,
            'message' => 'Kartu QR berhasil dihubungkan ke ' . $siswa->nama_peserta_didik . '.',
            'student' => ['id' => $siswa->id, 'nama' => $siswa->nama_peserta_didik],
        ]);
    }

    public function reset(Siswa $siswa)
    {
        abort_unless(filled($siswa->qr_token_hash), 404);
        $siswa->forceFill(['qr_token_hash' => null])->save();

        AktivitasService::log(
            'kehadiran',
            'Lepas QR kartu siswa',
            'QR kartu siswa ' . $siswa->nama_peserta_didik . ' dinonaktifkan oleh admin.',
            'siswa',
            $siswa->id,
            ['qr_terhubung' => true],
            ['qr_terhubung' => false],
        );

        return redirect()->route('admin.presensi.kartu.index')
            ->with('success', 'QR kartu berhasil dilepas.');
    }
}
