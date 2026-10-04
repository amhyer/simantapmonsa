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
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th style="text-align:center">Sakit</th>
                                    <th style="text-align:center">Izin</th>
                                    <th style="text-align:center">Alpa</th>
                                    <th>Tinggi / Berat</th>
                                    <th>Catatan Terakhir</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($siswa as $row)
                                    <tr>
                                        <td>{{ $row->nama_peserta_didik }}</td>
                                        <td style="text-align:center">{{ $row->rekap_hadir['S'] }} hari</td>
                                        <td style="text-align:center">{{ $row->rekap_hadir['I'] }} hari</td>
                                        <td style="text-align:center">{{ $row->rekap_hadir['A'] }} hari</td>
                                        <td>{{ $row->tinggi_badan ? $row->tinggi_badan . ' cm' : '-' }} / {{ $row->berat_badan ? $row->berat_badan . ' kg' : '-' }}</td>
                                        <td>{{ $row->catatan_terakhir->catatan ?? '-' }}</td>
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
