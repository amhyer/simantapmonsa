<?php

namespace App\Http\Controllers\Ortu;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\Kebiasaan;

class RekapController extends Controller
{
    use HasPredikatKaih;
    public function index()
    {
        $user = auth()->user();
        $anakIds = $user->terhubung_dengan ?? [];

        if (empty($anakIds)) {
            return view('ortu.rekap.index', [
                'anak' => null, 'semuaKebiasaan' => collect(),
                'hari' => 0, 'rataKebiasaan' => [], 'rata' => 0, 'predikat' => null, 'fields' => [],
            ]);
        }

        $anak = Siswa::whereIn('id', $anakIds)->first();

        if (!$anak) {
            return view('ortu.rekap.index', [
                'anak' => null, 'semuaKebiasaan' => collect(),
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
        $anakList = Siswa::whereIn('id', $anakIds)->get();

        return view('ortu.rekap.index', compact(
            'anak', 'anakList', 'semuaKebiasaan', 'hari', 'rataKebiasaan', 'rata', 'predikat', 'fields'
        ));
    }
}
