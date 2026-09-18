<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JadwalPelajaran;
use App\Models\Ptk;

class DataReferensiController extends Controller
{
    public function guru()
    {
        $ptk = Ptk::with('semester')->orderBy('nama')->get();

        return view('admin.referensi.guru', compact('ptk'));
    }

    public function pembelajaran()
    {
        $jadwal = JadwalPelajaran::with(['guru', 'rombel', 'semester'])
            ->orderBy('hari')
            ->orderBy('jam_mulai')
            ->get();

        return view('admin.referensi.pembelajaran', compact('jadwal'));
    }
}
