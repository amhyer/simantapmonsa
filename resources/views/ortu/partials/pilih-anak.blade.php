@php
    /*
     * Dropdown "Pilih Anak" untuk akun orang tua dengan lebih dari satu anak.
     *
     * Parameter:
     * - $route    (string) nama route tujuan, mis. 'ortu.dashboard'
     * - $terpilih (Siswa|null) anak yang sedang dilihat
     * - $anakList (Collection) daftar anak dari HasAnakLookup::anakList()
     * - $query    (array, opsional) parameter tambahan, mis. ['tanggal' => $tanggal]
     */
    $daftarAnak = $anakList ?? collect();
    $anakTerpilih = $terpilih ?? null;
    $queryTambahan = $query ?? [];
@endphp

@if($daftarAnak->count() > 1)
    <div style="margin-bottom:16px">
        <label for="pilih-anak" style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Pilih Anak</label>
        <select id="pilih-anak"
                onchange="window.location.href = this.value"
                style="padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px;min-width:180px">
            @foreach($daftarAnak as $a)
                <option value="{{ route($route, array_merge(['siswa_id' => $a->id], $queryTambahan)) }}"
                        @selected($anakTerpilih && $a->id === $anakTerpilih->id)>
                    {{ $a->nama_peserta_didik }}
                </option>
            @endforeach
        </select>
    </div>
@endif
