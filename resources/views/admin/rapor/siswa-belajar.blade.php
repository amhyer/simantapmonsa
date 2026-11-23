@extends('layouts.admin-with-tabs')
@section('title', 'Siswa Belajar - SIMANTAP')
@section('page_title', 'Siswa Belajar (Pemetaan Mapel)')
@section('page_subtitle', 'Atur keikutsertaan peserta didik per mata pelajaran pilihan atau pendidikan agama')

@section('content')
    <div class="card" style="margin-bottom:16px">
        <div class="card-body">
            <form method="GET" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
                <div>
                    <label for="fld-rapor-siswa-belajar-kelas" class="form-label">Pilih Kelas</label>
                    <select id="fld-rapor-siswa-belajar-kelas" name="kelas" class="form-control" onchange="this.form.submit()">
                        @foreach($kelasList as $k)
                            <option value="{{ $k }}" @selected($k === $kelas)>{{ $k }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-sm btn-primary">Tampilkan</button>
            </form>
        </div>
    </div>

    @if($kelas)
        <div class="card">
            <div class="card-header">
                <h3>Matriks Siswa Belajar — {{ $kelas }}</h3>
                <span class="tag tag-mut">{{ $siswa->count() }} siswa &bull; {{ $mapelList->count() }} mapel</span>
            </div>
            <div class="card-body tight">
                <form method="POST" action="{{ route('admin.rapor.siswa-belajar.simpan') }}">
                    @csrf
                    <input type="hidden" name="kelas" value="{{ $kelas }}">
                    @foreach($mapelList as $m)
                        <input type="hidden" name="mapel_list[]" value="{{ $m }}">
                    @endforeach

                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width:44px">No</th>
                                    <th style="min-width:190px">Nama Peserta Didik</th>
                                    @foreach($mapelList as $m)
                                        <th style="text-align:center;min-width:100px">{{ $m }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($siswa as $i => $s)
                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td>
                                            <strong>{{ $s->nama_peserta_didik }}</strong>
                                            <div style="font-size:11px;color:var(--muted)">NISN: {{ $s->nisn ?? '-' }}</div>
                                        </td>
                                        @foreach($mapelList as $m)
                                            @php $checked = $statusMap[$s->id][$m] ?? true; @endphp
                                            <td style="text-align:center">
                                                <input type="checkbox" name="belajar[{{ $s->id }}][{{ $m }}]" value="1" @checked($checked)>
                                            </td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr class="table-empty"><td colspan="{{ 2 + $mapelList->count() }}" style="padding:18px;text-align:center;color:var(--muted)">Belum ada siswa aktif di kelas ini.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($siswa->isNotEmpty())
                        <div style="padding:16px;border-top:1px solid #E4E7EC;display:flex;justify-content:flex-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Simpan Pemetaan Siswa Belajar
                            </button>
                        </div>
                    @endif
                </form>
            </div>
        </div>
    @endif
@endsection
