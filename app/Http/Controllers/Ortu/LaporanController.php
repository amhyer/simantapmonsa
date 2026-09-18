<?php

namespace App\Http\Controllers\Ortu;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\Nilai;
use App\Models\Kehadiran;
use App\Models\Kebiasaan;
use App\Services\NilaiService;

class LaporanController extends Controller
{
    use HasPredikatKaih;
    protected $nilaiService;

    public function __construct(NilaiService $nilaiService)
    {
        $this->nilaiService = $nilaiService;
    }

    public function index()
    {
        $user = auth()->user();
        $anakIds = $user->terhubung_dengan ?? [];

        if (empty($anakIds)) {
            return view('ortu.laporan.index', ['anak' => null, 'laporan' => null]);
        }

        $anak = Siswa::whereIn('id', $anakIds)->first();

        if (!$anak) {
            return view('ortu.laporan.index', ['anak' => null, 'laporan' => null]);
        }

        $na = $this->nilaiService->hitungNilaiAkhir($anak->id);
        $predikat = $na['nilai_akhir'] > 0 ? $this->nilaiService->getPredikat($na['nilai_akhir'], 70) : null;
        $kehadiranPersen = $this->nilaiService->hitungKehadiran($anak->id);
        $totalHadir = Kehadiran::where('siswa_id', $anak->id)->where('status', 'H')->count();
        $totalKehadiran = Kehadiran::where('siswa_id', $anak->id)->count();

        $semuaKebiasaan = Kebiasaan::where('siswa_id', $anak->id)->get();
        $fields = ['bangun_pagi', 'beribadah', 'berolahraga', 'makan_sehat', 'gemar_belajar', 'bermasyarakat', 'tidur_cepat'];
        $rataKebiasaan = [];
        foreach ($fields as $field) {
            $values = $semuaKebiasaan->pluck($field)->filter()->values();
            $rataKebiasaan[$field] = $values->count() ? round($values->avg(), 2) : 0;
        }
        $rataKebiasaanVal = round(collect($rataKebiasaan)->avg(), 2);
        $predikatKebiasaan = $this->getPredikatKaih($rataKebiasaanVal);

        $nilaiPerJenis = Nilai::where('siswa_id', $anak->id)
            ->selectRaw('jenis, AVG(nilai) as rata_rata, COUNT(*) as jumlah')
            ->groupBy('jenis')
            ->get();

        $laporan = [
            'nilai_akhir' => $na['nilai_akhir'],
            'rata_per_jenis' => $na['rata_per_jenis'],
            'predikat' => $predikat,
            'kehadiran_persen' => $kehadiranPersen,
            'total_hadir' => $totalHadir,
            'total_kehadiran' => $totalKehadiran,
            'kebiasaan_rata' => $rataKebiasaan,
            'kebiasaan_rata_val' => $rataKebiasaanVal,
            'predikat_kebiasaan' => $predikatKebiasaan,
            'nilai_per_jenis' => $nilaiPerJenis,
        ];

        $anakList = Siswa::whereIn('id', $anakIds)->get();

        return view('ortu.laporan.index', compact('anak', 'anakList', 'laporan'));
    }
}
