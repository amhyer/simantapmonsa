<?php

namespace App\Http\Controllers\Ortu;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\Catatan;
use Illuminate\Http\Request;

class CatatanController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $anakIds = $user->terhubung_dengan ?? [];

        if (empty($anakIds)) {
            return view('ortu.catatan.index', ['anak' => null, 'catatan' => collect(), 'anakList' => collect()]);
        }

        $siswaId = $request->get('siswa_id') ?? $anakIds[0];

        if (!in_array($siswaId, $anakIds)) {
            abort(403, 'Anda tidak memiliki akses ke data siswa ini.');
        }

        $anak = Siswa::find($siswaId);

        if (!$anak) {
            return view('ortu.catatan.index', ['anak' => null, 'catatan' => collect(), 'anakList' => Siswa::whereIn('id', $anakIds)->get()]);
        }

        $catatan = Catatan::where('siswa_id', $anak->id)->orderBy('tanggal', 'desc')->get();
        $anakList = Siswa::whereIn('id', $anakIds)->get();

        return view('ortu.catatan.index', compact('anak', 'anakList', 'catatan'));
    }
}
