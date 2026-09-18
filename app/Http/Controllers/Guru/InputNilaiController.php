<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\Nilai;
use App\Models\Kuis;
use App\Models\PengaturanGuru;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class InputNilaiController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $siswa = Siswa::where('guru_id', $user->id)->orderBy('nama_peserta_didik')->get();
        $pengaturan = PengaturanGuru::where('guru_id', $user->id)->first();
        $kuis = Kuis::where('guru_id', $user->id)->orderByDesc('created_at')->get();

        return view('guru.input-nilai.index', compact('siswa', 'pengaturan', 'kuis'));
    }

    public function getNilai(Request $request): JsonResponse
    {
        $user = Auth::user();
        $jenis = $request->jenis ?? 'UH';
        $judul = $request->judul ?? '';

        $siswa = Siswa::where('guru_id', $user->id)->orderBy('nama_peserta_didik')->get();

        $nilaiData = Nilai::where('guru_id', $user->id)
            ->where('jenis', $jenis)
            ->when($judul, fn($q) => $q->where('judul_penilaian', $judul))
            ->get()
            ->keyBy('siswa_id');

        $result = $siswa->map(function ($s) use ($nilaiData, $user) {
            $n = $nilaiData->get($s->id);
            $nilai = $n ? $n->nilai : null;
            $kkm = getKKM($user->id);
            $predikat = $this->getPredikat($nilai);
            $status = $nilai !== null ? ($nilai >= $kkm ? 'Tuntas' : 'Remidi') : '-';

            return [
                'siswa_id' => $s->id,
                'nama' => $s->nama_peserta_didik,
                'nis' => $s->nis,
                'nilai' => $nilai,
                'predikat' => $predikat,
                'status' => $status,
                'kkm' => $kkm,
            ];
        });

        return response()->json(['siswa' => $result]);
    }

    public function simpanNilai(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'jenis' => 'required|string',
            'judul_penilaian' => 'nullable|string',
            'nilai' => 'required|array',
            'nilai.*.siswa_id' => 'required|exists:siswa,id',
            'nilai.*.nilai' => 'nullable|numeric|between:0,100',
        ]);

        $saved = 0;
        $ownedSiswaIds = Siswa::where('guru_id', $user->id)->pluck('id')->toArray();

        DB::transaction(function () use ($validated, $user, $ownedSiswaIds, &$saved) {
            foreach ($validated['nilai'] as $item) {
                if ($item['nilai'] === null || $item['nilai'] === '') continue;
                if (!in_array($item['siswa_id'], $ownedSiswaIds)) continue;

                Nilai::updateOrCreate(
                    [
                        'guru_id' => $user->id,
                        'siswa_id' => $item['siswa_id'],
                        'jenis' => $validated['jenis'],
                        'judul_penilaian' => $validated['judul_penilaian'] ?? null,
                    ],
                    [
                        'nilai' => $item['nilai'],
                        'mata_pelajaran' => $user->kelas_mata_pelajaran ?? 'Umum',
                    ]
                );
                $saved++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => "{$saved} nilai berhasil disimpan.",
        ]);
    }

    public function pasteFromExcel(Request $request): JsonResponse
    {
        $request->validate(['data' => 'required|string']);

        $lines = explode("\n", trim($request->data));
        $results = [];

        foreach ($lines as $line) {
            $parts = preg_split('/[\t,;]/', trim($line));
            if (count($parts) >= 2) {
                $results[] = [
                    'nama' => trim($parts[0]),
                    'nilai' => is_numeric(trim($parts[1])) ? (float) trim($parts[1]) : null,
                ];
            }
        }

        return response()->json(['parsed' => $results]);
    }

    private function getPredikat($nilai, $kkm = null)
    {
        if ($nilai === null) return '-';
        $kkm = $kkm ?? getKKM();
        if ($nilai >= 90) return 'A';
        if ($nilai >= 80) return 'B';
        if ($nilai >= $kkm) return 'C';
        return 'D';
    }
}
