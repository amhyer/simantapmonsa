@extends('layouts.app')

@section('title', 'Pelengkap Rapor - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Pelengkap Rapor')
@section('page_subtitle', 'Ketidakhadiran, catatan, dan data fisik per siswa')

@section('content')
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Filter</h3>
            @if($kelas)
                <button class="btn btn-sm" onclick="window.print()">Cetak</button>
            @endif
        </div>
        <div class="card-body">
            <form method="GET" style="display:flex;gap:12px;align-items:end">
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Kelas</label>
                    <select name="kelas" style="padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
                        @foreach($kelasList as $k)
                            <option value="{{ $k }}" @selected($k === $kelas)>{{ $k }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-sm">Tampilkan</button>
                </div>
            </form>
            <small style="color:#667085">Rekap kehadiran dihitung dari seluruh periode tercatat.</small>
        </div>
    </div>

    @if($kelas)
        <div class="card">
            <div class="card-header">
                <h3>Pelengkap Kelas {{ $kelas }}</h3>
                <span class="tag tag-mut">{{ $siswa->count() }} siswa</span>
            </div>
            <div class="card-body tight">
                @if($siswa->isEmpty())
                    <p style="color:#667085;padding:12px 4px">Belum ada data siswa di kelas ini.</p>
                @else
                    <div style="overflow-x:auto">
                        <table style="width:100%;border-collapse:collapse;font-size:14px">
                            <thead>
                                <tr style="text-align:left;border-bottom:2px solid #EAECF0">
                                    <th style="padding:10px">Nama</th>
                                    <th style="padding:10px;text-align:center">Sakit</th>
                                    <th style="padding:10px;text-align:center">Izin</th>
                                    <th style="padding:10px;text-align:center">Alpa</th>
                                    <th style="padding:10px">Tinggi / Berat</th>
                                    <th style="padding:10px">Catatan Terakhir</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($siswa as $row)
                                    <tr style="border-bottom:1px solid #EAECF0">
                                        <td style="padding:10px">{{ $row->nama_peserta_didik }}</td>
                                        <td style="padding:10px;text-align:center">{{ $row->rekap_hadir['S'] }} hari</td>
                                        <td style="padding:10px;text-align:center">{{ $row->rekap_hadir['I'] }} hari</td>
                                        <td style="padding:10px;text-align:center">{{ $row->rekap_hadir['A'] }} hari</td>
                                        <td style="padding:10px">{{ $row->tinggi_badan ? $row->tinggi_badan . ' cm' : '-' }} / {{ $row->berat_badan ? $row->berat_badan . ' kg' : '-' }}</td>
                                        <td style="padding:10px">{{ $row->catatan_terakhir->catatan ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endif
@endsection
