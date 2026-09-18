<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\Materi;
use App\Models\Siswa;

class MateriController extends Controller
{
    use HasSiswaLookup;
    public function index()
    {
        $siswa = $this->getSiswa();

        if (!$siswa) {
            return view('siswa.materi.index', ['materi' => collect(), 'siswa' => null]);
        }

        $materi = Materi::where('guru_id', $siswa->guru_id)
            ->orderBy('tanggal', 'desc')
            ->get();

        return view('siswa.materi.index', compact('materi', 'siswa'));
    }

    public function show($id)
    {
        $siswa = $this->getSiswa();

        if (!$siswa) {
            abort(404);
        }

        $materi = Materi::where('id', $id)
            ->where('guru_id', $siswa->guru_id)
            ->firstOrFail();

        return view('siswa.materi.show', compact('materi', 'siswa'));
    }
}
