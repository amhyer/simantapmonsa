{{-- Filter: tahun ajaran, semester, jenjang, rombel, indikator, rentang waktu, jenis sumber --}}
<form method="GET" class="card" style="padding:12px;margin-bottom:16px">
    <div class="grid grid-4" style="gap:10px">
        <label>Tahun ajaran
            <select name="tahun_ajaran" class="form-control">
                <option value="">Semua</option>
                @foreach($opsi['tahun_ajaran'] as $t)
                    <option value="{{ $t }}" @selected($filter->tahunAjaran === $t)>{{ $t }}</option>
                @endforeach
            </select>
        </label>
        <label>Semester
            <select name="semester" class="form-control">
                <option value="">Semua</option>
                @foreach(['Ganjil','Genap'] as $s)
                    <option value="{{ $s }}" @selected($filter->semester === $s)>{{ $s }}</option>
                @endforeach
            </select>
        </label>
        <label>Jenjang kelas
            <select name="tingkat" class="form-control">
                <option value="">Semua</option>
                @foreach([4 => 'Kelas 4 (fondasi)', 5 => 'Kelas 5 (penguatan)', 6 => 'Kelas 6 (pemantapan)'] as $v => $l)
                    <option value="{{ $v }}" @selected($filter->tingkat === $v)>{{ $l }}</option>
                @endforeach
            </select>
        </label>
        @isset($opsi['rombel'])
        <label>Rombel
            <select name="rombel_id" class="form-control">
                <option value="">Semua</option>
                @foreach($opsi['rombel'] as $r)
                    <option value="{{ $r->id }}" @selected($filter->rombelId === $r->id)>{{ $r->nama_rombel }}</option>
                @endforeach
            </select>
        </label>
        @endisset
        <label>Mata pelajaran / indikator
            <select name="indikator" class="form-control">
                <option value="">Semua</option>
                @foreach($indikator as $kode => $d)
                    <option value="{{ $kode }}" @selected($filter->indikator === $kode)>{{ $d['nama'] }}</option>
                @endforeach
            </select>
        </label>
        <label>Dari tanggal <input type="date" name="dari" value="{{ $filter->dari }}" class="form-control"></label>
        <label>Sampai tanggal <input type="date" name="sampai" value="{{ $filter->sampai }}" class="form-control"></label>
        <div>
            <div style="font-size:.8rem">Jenis sumber bukti</div>
            @foreach($opsi['sumber'] as $kode => $s)
                <label style="display:block;font-size:.8rem">
                    <input type="checkbox" name="sumber[]" value="{{ $kode }}" @checked(in_array($kode, $filter->sumber, true))> {{ $s['label'] }}
                </label>
            @endforeach
        </div>
    </div>
    <div style="margin-top:10px;display:flex;gap:8px">
        <button class="btn btn-primary btn-sm" type="submit">Terapkan filter</button>
        <a href="{{ url()->current() }}" class="btn btn-ghost btn-sm">Reset</a>
    </div>
</form>
