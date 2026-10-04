@if(isset($anakList) && $anakList->count() > 1 && $terpilih)
    <div style="margin-bottom:16px">
        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Pilih Anak</label>
        <select onchange="window.location.href='{{ $route }}?siswa_id='+this.value" style="padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px;min-width:180px">
            @foreach($anakList as $a)
                <option value="{{ $a->id }}" {{ $a->id == $terpilih->id ? 'selected' : '' }}>{{ $a->nama_peserta_didik }}</option>
            @endforeach
        </select>
    </div>
@endif
