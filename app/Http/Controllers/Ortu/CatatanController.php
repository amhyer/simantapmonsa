<?php

namespace App\Http\Controllers\Ortu;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\Catatan;

class CatatanController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $anakIds = $user->terhubung_dengan ?? [];

        if (empty($anakIds)) {
            return view('ortu.catatan.index', ['anak' => null, 'catatan' => collect()]);
        }

        $anak = Siswa::whereIn('id', $anakIds)->first();

        if (!$anak) {
            return view('ortu.catatan.index', ['anak' => null, 'catatan' => collect()]);
        }

        $catatan = Catatan::where('siswa_id', $anak->id)->orderBy('tanggal', 'desc')->get();
        $anakList = Siswa::whereIn('id', $anakIds)->get();

        return view('ortu.catatan.index', compact('anak', 'anakList', 'catatan'));
    }
}
