<?php

namespace App\Http\Controllers\Ortu;

use App\Models\Siswa;
use Illuminate\Support\Collection;

trait HasAnakLookup
{
    protected function anakIds(): array
    {
        return array_values(array_map('intval', (array) (auth()->user()->terhubung_dengan ?? [])));
    }

    protected function anakList(): Collection
    {
        $ids = $this->anakIds();

        return $ids ? Siswa::whereIn('id', $ids)->orderBy('nama_peserta_didik')->get() : collect();
    }

    /**
     * Anak yang dipilih lewat ?siswa_id=, dibatasi pada anak yang terhubung ke akun ortu.
     */
    protected function getAnak(Collection $anakList): ?Siswa
    {
        if (! request()->filled('siswa_id')) {
            return $anakList->first();
        }

        $anak = $anakList->firstWhere('id', (int) request('siswa_id'));
        abort_unless($anak, 403, 'Anda tidak memiliki akses ke data siswa ini.');

        return $anak;
    }
}
