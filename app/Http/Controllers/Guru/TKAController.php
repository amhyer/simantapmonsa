<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\Dimensi;
use App\Models\Siswa;
use App\Models\Kebiasaan;
use Illuminate\Http\Request;

class TKAController extends Controller
{
    public function index()
    {
        $guruId = auth()->id();
        $siswaList = Siswa::where('guru_id', $guruId)->where('aktif', true)->get();

        $dimensiRataRata = [];
        for ($d = 1; $d <= 6; $d++) {
            $skorDimensi = Dimensi::where('guru_id', $guruId)->where('no_dimensi', $d)->pluck('skor');
            $dimensiRataRata[$d] = $skorDimensi->count() > 0 ? round($skorDimensi->avg(), 1) : 0;
        }

        $jumlahSiap = 0;
        $jumlahPerluBimbingan = 0;
        $totalSkor = 0;
        $countSkor = 0;

        foreach ($siswaList as $siswa) {
            $dimensiSiswa = Dimensi::where('siswa_id', $siswa->id)->pluck('skor');
            if ($dimensiSiswa->count() > 0) {
                $avg = $dimensiSiswa->avg();
                $totalSkor += $avg;
                $countSkor++;
                if ($avg >= getKKM()) $jumlahSiap++;
                else $jumlahPerluBimbingan++;
            }
        }

        $rataRata = $countSkor > 0 ? $totalSkor / $countSkor : 0;

        return view('guru.tka.index', compact(
            'siswaList', 'jumlahSiap', 'jumlahPerluBimbingan', 'rataRata', 'dimensiRataRata'
        ));
    }

    public function show($id)
    {
        $dimensi = Dimensi::where('guru_id', auth()->id())->findOrFail($id);
        return view('guru.tka.show', compact('dimensi'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'siswa_id' => 'required|exists:siswa,id',
            'no_dimensi' => 'required|integer|min:1|max:8',
            'dimensi' => 'required|string|max:100',
            'skor' => 'required|integer|min:1|max:4',
            'predikat' => 'nullable|string|max:50',
            'catatan' => 'nullable|string',
        ]);

        $guru = auth()->user();
        $siswa = Siswa::find($validated['siswa_id']);
        if (!$siswa || $siswa->guru_id !== $guru->id) {
            return back()->withErrors(['siswa_id' => 'Siswa tidak ditemukan atau bukan milik Anda.'])->withInput();
        }

        Dimensi::updateOrCreate(
            ['siswa_id' => $validated['siswa_id'], 'no_dimensi' => $validated['no_dimensi'], 'guru_id' => $guru->id],
            [
                'guru_id' => $guru->id,
                'nama_guru' => $guru->nama_lengkap,
                'nama_siswa' => $siswa->nama_peserta_didik,
                'dimensi' => $validated['dimensi'],
                'skor' => $validated['skor'],
                'predikat' => $validated['predikat'] ?? null,
                'catatan' => $validated['catatan'] ?? null,
            ]
        );

        return back()->with('success', 'Analisis TKA berhasil disimpan.');
    }

    public function analysis()
    {
        $guruId = auth()->id();
        $siswaList = Siswa::where('guru_id', $guruId)->where('aktif', true)->get();

        $analisis = $siswaList->map(function ($siswa) {
            $dimensi = Dimensi::where('siswa_id', $siswa->id)->get();
            $kebiasaan = Kebiasaan::where('siswa_id', $siswa->id)->latest('tanggal')->first();

            $rataSkor = $dimensi->count() > 0 ? $dimensi->avg('skor') : 0;
            $levelKognitif = $this->getLevelKognitif($rataSkor);

            return [
                'siswa' => $siswa,
                'dimensi' => $dimensi,
                'kebiasaan' => $kebiasaan,
                'rata_skor' => round($rataSkor, 2),
                'level_kognitif' => $levelKognitif,
            ];
        });

        return view('guru.tka.analysis', compact('analisis'));
    }

    private function getLevelKognitif($rataSkor)
    {
        if ($rataSkor >= 3.5) return 'Sangat Baik';
        if ($rataSkor >= 2.5) return 'Baik';
        if ($rataSkor >= 1.5) return 'Cukup';
        return 'Perlu Bimbingan';
    }
}
