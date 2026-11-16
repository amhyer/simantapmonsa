<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Services\Elearning\SiswaMateriVisibilityService;

class MateriController extends Controller
{
    use HasSiswaLookup;

    public function __construct(
        private readonly SiswaMateriVisibilityService $visibility,
    ) {}

    public function index()
    {
        $siswa = $this->getSiswa();

        if (! $siswa) {
            return view('siswa.materi.index', ['materi' => collect(), 'siswa' => null]);
        }

        $materi = $this->visibility
            ->queryForSiswa($siswa)
            ->orderByDesc('sematkan')
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get();

        return view('siswa.materi.index', compact('materi', 'siswa'));
    }

    public function show($id)
    {
        $siswa = $this->getSiswa();

        if (! $siswa || ! ctype_digit((string) $id)) {
            abort(404);
        }

        $materi = $this->visibility
            ->queryForSiswa($siswa)
            ->where('materi.id', (int) $id)
            ->firstOrFail();

        return view('siswa.materi.show', compact('materi', 'siswa'));
    }
}
