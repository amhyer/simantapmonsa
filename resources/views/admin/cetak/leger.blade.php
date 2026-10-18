@extends('layouts.admin-with-tabs')

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
                    <label class="form-label">Kelas</label>
                    <select name="kelas" class="form-control">
                        @foreach($kelasList as $k)
                            <option value="{{ $k }}" @selected($k === $kelas)>{{ $k }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Semester</label>
                    <select name="semester" class="form-control">
                        <option value="Ganjil" @selected($semester === 'Ganjil')>Ganjil</option>
                        <option value="Genap" @selected($semester === 'Genap')>Genap</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Tahun Ajaran</label>
                    <input type="text" name="tahun_ajaran" value="{{ $tahunAjaran }}" maxlength="9" class="form-control" style="width:120px">
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
                <h3>Leger Kelas {{ $kelas }} â€” {{ $semester }} {{ $tahunAjaran }}</h3>
                <span class="tag tag-mut">{{ $siswa->count() }} siswa &times; {{ $mapel->count() }} mapel</span>
            </div>
            <div class="card-body tight">
                @if($siswa->isEmpty() || $mapel->isEmpty())
                    <p style="color:#667085;padding:12px 4px">Belum ada data nilai rapor untuk filter ini.</p>
                @else
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama</th>
                                    @foreach($mapel as $m)
                                        <th style="text-align:center">{{ $m }}</th>
                                    @endforeach
                                    <th style="text-align:center">Rata-rata</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($siswa as $i => $row)
                                    @php
                                        $baris = array_filter(array_map(fn($m) => $matriks[$row->id][$m] ?? null, $mapel->all()));
                                        $rata = count($baris) ? round(array_sum($baris) / count($baris), 2) : null;
                                    @endphp
                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td>{{ $row->nama_peserta_didik }}</td>
                                        @foreach($mapel as $m)
                                            <td style="text-align:center">{{ isset($matriks[$row->id][$m]) ? number_format((float) $matriks[$row->id][$m], 2, ',', '.') : '-' }}</td>
                                        @endforeach
                                        <td style="text-align:center"><strong>{{ $rata !== null ? number_format($rata, 2, ',', '.') : '-' }}</strong></td>
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

