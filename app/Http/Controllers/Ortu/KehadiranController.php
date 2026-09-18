<?php

namespace App\Http\Controllers\Ortu;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\Kehadiran;

class KehadiranController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $anakIds = $user->terhubung_dengan ?? [];

        if (empty($anakIds)) {
            return view('ortu.kehadiran.index', [
                'anak' => null, 'kehadiran' => collect(),
                'anakList' => collect(), 'totalHadir' => 0, 'total' => 0, 'persentase' => 0,
            ]);
        }

        $anak = Siswa::whereIn('id', $anakIds)->first();

        if (!$anak) {
            return view('ortu.kehadiran.index', [
                'anak' => null, 'kehadiran' => collect(),
                'anakList' => collect(), 'totalHadir' => 0, 'total' => 0, 'persentase' => 0,
            ]);
        }

        $kehadiran = Kehadiran::where('siswa_id', $anak->id)->orderBy('tanggal', 'desc')->get();
        $anakList = Siswa::whereIn('id', $anakIds)->get();

        $totalHadir = $kehadiran->where('status', 'H')->count();
        $total = $kehadiran->count();
        $persentase = $total > 0 ? round($totalHadir / $total * 100, 1) : 0;

        return view('ortu.kehadiran.index', compact(
            'anak', 'anakList', 'kehadiran', 'totalHadir', 'total', 'persentase'
        ));
    }
}
