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

        // Hoist: 1 query (cached) untuk semua baris, bukan 1 query per siswa.
        $kkm = getKKM($user->id);

        $result = $siswa->map(function ($s) use ($nilaiData, $kkm) {
            $n = $nilaiData->get($s->id);
            $nilai = $n ? $n->nilai : null;
            $predikat = $this->getPredikat($nilai, $kkm);
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
        $request->validate(['data' => 'required|string|max:20000']);

        $user = Auth::user();
        $lines = preg_split('/\r\n|\r|\n/', trim($request->data));
        // Batas pengaman: satu kelas tidak melebihi 1000 baris per paste.
        $lines = array_slice($lines, 0, 1000);

        // Peta siswa milik guru untuk pencocokan di server (hindari salah
        // tempel ke siswa guru lain / nama mirip). Kunci: NIS + nama lower.
        $milik = Siswa::where('guru_id', $user->id)
            ->get(['id', 'nis', 'nama_peserta_didik']);
        $byNis = [];
        $byNama = [];
        foreach ($milik as $s) {
            if ($s->nis) {
                $byNis[strtolower(trim($s->nis))] = $s->id;
            }
            $byNama[strtolower(trim($s->nama_peserta_didik))] = $s->id;
        }

        $results = [];
        foreach ($lines as $line) {
            $parts = preg_split('/[\t,;]/', trim($line));
            if (count($parts) < 2) {
                continue;
            }
            $nama = trim($parts[0]);
            $angka = trim($parts[1]);
            $nilai = is_numeric($angka) ? (float) $angka : null;
            if ($nilai !== null && ($nilai < 0 || $nilai > 100)) {
                $nilai = null;
            }

            // Urutan cocok: NIS persis -> nama persis -> nama mengandung.
            $siswaId = $byNis[strtolower($nama)] ?? $byNama[strtolower($nama)] ?? null;
            if ($siswaId === null && $nama !== '') {
                foreach ($byNama as $namaSiswa => $id) {
                    if (str_contains($namaSiswa, strtolower($nama)) || str_contains(strtolower($nama), $namaSiswa)) {
                        $siswaId = $id;
                        break;
                    }
                }
            }

            $results[] = [
                'nama' => $nama,
                'nilai' => $nilai,
                'siswa_id' => $siswaId,
                'cocok' => $siswaId !== null,
            ];
        }

        return response()->json(['parsed' => $results]);
    }

    private function getPredikat($nilai, $kkm = null)
    {
        if ($nilai === null) return '-';
        // Satu sumber kebenaran predikat: NilaiService (relatif terhadap KKM).
        return app(\App\Services\NilaiService::class)
            ->getPredikat($nilai, $kkm ?? getKKM())['huruf'] ?? '-';
    }
}
