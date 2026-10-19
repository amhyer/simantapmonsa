<?php

namespace App\Http\Controllers\Ortu;

use App\Models\Siswa;
use Illuminate\Support\Collection;

/**
 * Lookup anak untuk akun orang tua.
 *
 * Akun ortu menyimpan daftar anak pada kolom JSON `terhubung_dengan`. Trait ini
 * memusatkan tiga hal yang sebelumnya disalin di setiap controller ortu:
 * normalisasi daftar ID, pengambilan daftar anak untuk dropdown, dan otorisasi
 * `?siswa_id=` sehingga ortu tidak bisa membuka data siswa lain.
 */
trait HasAnakLookup
{
    /**
     * ID anak yang terhubung dengan akun ortu, sudah dinormalisasi menjadi int.
     *
     * @return array<int, int>
     */
    protected function anakIds(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        return array_values(array_map('intval', (array) ($user->terhubung_dengan ?? [])));
    }

    /**
     * Seluruh anak yang terhubung, diurutkan sesuai urutan tautan agar anak
     * pertama tetap menjadi pilihan default seperti perilaku sebelumnya.
     */
    protected function anakList(): Collection
    {
        $ids = $this->anakIds();

        if ($ids === []) {
            return collect();
        }

        return Siswa::whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Siswa $siswa) => array_search($siswa->id, $ids, true))
            ->values();
    }

    /**
     * Anak yang dipilih lewat `?siswa_id=`.
     *
     * Tanpa parameter, dipakai anak pertama pada `terhubung_dengan`. Bila ID
     * yang diminta bukan milik akun ini, permintaan ditolak dengan 403.
     */
    protected function getAnak(Collection $anakList): ?Siswa
    {
        if ($anakList->isEmpty()) {
            return null;
        }

        if (! request()->filled('siswa_id')) {
            return $anakList->first();
        }

        $diminta = (int) request('siswa_id');
        $anak = $anakList->firstWhere('id', $diminta);

        abort_unless($anak, 403, 'Anda tidak memiliki akses ke data siswa ini.');

        return $anak;
    }
}
