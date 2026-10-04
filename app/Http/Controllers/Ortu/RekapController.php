<?php

namespace App\Http\Controllers\Ortu;

use App\Http\Controllers\Controller;
use App\Models\Kebiasaan;

class RekapController extends Controller
{
    use HasAnakLookup;
    use HasPredikatKaih;

    public function index()
    {
        $anakList = $this->anakList();
        $anak = $this->getAnak($anakList);

        if (!$anak) {
            return view('ortu.rekap.index', [
                'anak' => null, 'anakList' => $anakList, 'semuaKebiasaan' => collect(),
                'hari' => 0, 'rataKebiasaan' => [], 'rata' => 0, 'predikat' => null, 'fields' => [],
            ]);
        }

        $semuaKebiasaan = Kebiasaan::where('siswa_id', $anak->id)->orderBy('tanggal', 'desc')->get();
        $hari = $semuaKebiasaan->count();

        $fields = ['bangun_pagi', 'beribadah', 'berolahraga', 'makan_sehat', 'gemar_belajar', 'bermasyarakat', 'tidur_cepat'];
        $rataKebiasaan = [];
        foreach ($fields as $field) {
            $values = $semuaKebiasaan->pluck($field)->filter()->values();
            $rataKebiasaan[$field] = $values->count() ? round($values->avg(), 2) : 0;
        }

        $rata = round(collect($rataKebiasaan)->avg(), 2);
        $predikat = $this->getPredikatKaih($rata);

        return view('ortu.rekap.index', compact(
            'anak', 'anakList', 'semuaKebiasaan', 'hari', 'rataKebiasaan', 'rata', 'predikat', 'fields'
        ));
    }
}
