<?php

namespace App\Http\Controllers\Ortu;

use App\Http\Controllers\Controller;
use App\Models\Kehadiran;
use App\Models\PengajuanIzinPresensi;

class KehadiranController extends Controller
{
    use HasAnakLookup;

    public function index()
    {
        $anakList = $this->anakList();
        $anak = $this->getAnak($anakList);

        if (! $anak) {
            return view('ortu.kehadiran.index', [
                'anak' => null, 'kehadiran' => collect(),
                'anakList' => $anakList, 'totalHadir' => 0, 'total' => 0, 'persentase' => 0,
            ]);
        }

        $kehadiran = Kehadiran::where('siswa_id', $anak->id)->orderBy('tanggal', 'desc')->get();

        $pengajuan = PengajuanIzinPresensi::where('siswa_id', $anak->id)->where('orang_tua_id', auth()->id())
            ->orderByDesc('tanggal')->limit(30)->get();
        $totalHadir = $kehadiran->where('status', 'H')->count();
        $total = $kehadiran->count();
        $persentase = $total > 0 ? round($totalHadir / $total * 100, 1) : 0;

        return view('ortu.kehadiran.index', compact(
            'anak', 'anakList', 'kehadiran', 'pengajuan', 'totalHadir', 'total', 'persentase'
        ));
    }
}
