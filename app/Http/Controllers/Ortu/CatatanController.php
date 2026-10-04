<?php

namespace App\Http\Controllers\Ortu;

use App\Http\Controllers\Controller;
use App\Models\Catatan;

class CatatanController extends Controller
{
    use HasAnakLookup;

    public function index()
    {
        $anakList = $this->anakList();
        $anak = $this->getAnak($anakList);

        if (!$anak) {
            return view('ortu.catatan.index', ['anak' => null, 'anakList' => $anakList, 'catatan' => collect()]);
        }

        $catatan = Catatan::where('siswa_id', $anak->id)->orderBy('tanggal', 'desc')->get();

        return view('ortu.catatan.index', compact('anak', 'anakList', 'catatan'));
    }
}
