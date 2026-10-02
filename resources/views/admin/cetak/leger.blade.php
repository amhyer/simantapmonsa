@extends('layouts.app')

@section('title', 'Leger Rapor - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Leger Rapor')
@section('page_subtitle', 'Rekap nilai akhir semua mapel per kelas')

@section('content')
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Filter</h3>
            @if($kelas)
                <button class="btn btn-sm" onclick="window.print()">Cetak</button>
            @endif
        </div>
        <div class="card-body">
            <form method="GET" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Kelas</label>
                    <select name="kelas" style="padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
                        @foreach($kelasList as $k)
                            <option value="{{ $k }}" @selected($k === $kelas)>{{ $k }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Semester</label>
                    <select name="semester" style="padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
                        <option value="Ganjil" @selected($semester === 'Ganjil')>Ganjil</option>
                        <option value="Genap" @selected($semester === 'Genap')>Genap</option>
                    </select>
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Tahun Ajaran</label>
                    <input type="text" name="tahun_ajaran" value="{{ $tahunAjaran }}" maxlength="9" style="width:120px;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div>
                    <button type="submit" class="btn btn-sm">Tampilkan</button>
                </div>
            </form>
        </div>
    </div>

    @if($kelas)
        <div class="card">
            <div class="card-header">
                <h3>Leger Kelas {{ $kelas }} — {{ $semester }} {{ $tahunAjaran }}</h3>
                <span class="tag tag-mut">{{ $siswa->count() }} siswa &times; {{ $mapel->count() }} mapel</span>
            </div>
            <div class="card-body tight">
                @if($siswa->isEmpty() || $mapel->isEmpty())
                    <p style="color:#667085;padding:12px 4px">Belum ada data nilai rapor untuk filter ini.</p>
                @else
                    <div style="overflow-x:auto">
                        <table style="width:100%;border-collapse:collapse;font-size:13px">
                            <thead>
                                <tr style="text-align:left;border-bottom:2px solid #EAECF0">
                                    <th style="padding:10px">No</th>
                                    <th style="padding:10px">Nama</th>
                                    @foreach($mapel as $m)
                                        <th style="padding:10px;text-align:center">{{ $m }}</th>
                                    @endforeach
                                    <th style="padding:10px;text-align:center">Rata-rata</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($siswa as $i => $row)
                                    @php
                                        $baris = array_filter(array_map(fn($m) => $matriks[$row->id][$m] ?? null, $mapel->all()));
                                        $rata = count($baris) ? round(array_sum($baris) / count($baris), 2) : null;
                                    @endphp
                                    <tr style="border-bottom:1px solid #EAECF0">
                                        <td style="padding:10px">{{ $i + 1 }}</td>
                                        <td style="padding:10px">{{ $row->nama_peserta_didik }}</td>
                                        @foreach($mapel as $m)
                                            <td style="padding:10px;text-align:center">{{ isset($matriks[$row->id][$m]) ? number_format((float) $matriks[$row->id][$m], 2, ',', '.') : '-' }}</td>
                                        @endforeach
                                        <td style="padding:10px;text-align:center"><strong>{{ $rata !== null ? number_format($rata, 2, ',', '.') : '-' }}</strong></td>
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
