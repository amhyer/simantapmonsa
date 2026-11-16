@extends('layouts.app')

@section('title', 'Status Penilaian & Statistik Kelas - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Status Penilaian & Statistik Kelas (Wali Kelas)')
@section('page_subtitle', 'Pantau kelengkapan input nilai antar mata pelajaran, distribusi predikat rapor, dan capaian P5 kelas binaan')

@section('content')
    <div class="card" style="margin-bottom:16px">
        <div class="card-body">
            <form method="GET" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
                <div>
                    <label class="form-label">Kelas Binaan</label>
                    <select name="kelas" class="form-control" onchange="this.form.submit()">
                        @foreach($kelasList as $k)
                            <option value="{{ $k }}" @selected($k === $kelas)>{{ $k }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Semester</label>
                    <select name="semester" class="form-control" onchange="this.form.submit()">
                        <option value="Ganjil" @selected($semester === 'Ganjil')>Ganjil</option>
                        <option value="Genap" @selected($semester === 'Genap')>Genap</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Tahun Ajaran</label>
                    <input type="text" name="tahun_ajaran" value="{{ $tahunAjaran }}" maxlength="9" class="form-control" style="width:120px">
                </div>
                <button type="submit" class="btn btn-sm btn-primary">Tampilkan</button>
            </form>
        </div>
    </div>

    <div class="grid grid-2" style="margin-bottom:20px">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-pie" style="margin-right:6px;color:var(--navy)"></i>Statistik Predikat Nilai Rapor</h3>
            </div>
            <div class="card-body">
                <div class="grid grid-4">
                    @foreach($sebaranPredikat as $huruf => $jml)
                        <div style="padding:12px;border:1px solid var(--line);border-radius:8px;text-align:center">
                            <div style="font-size:12px;color:var(--muted);font-weight:700">Predikat {{ $huruf }}</div>
                            <div style="font-size:24px;font-weight:800;color:var(--navy);margin-top:4px">{{ $jml }}</div>
                            <div style="font-size:11px;color:var(--muted)">entri mapel</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-seedling" style="margin-right:6px;color:var(--ok)"></i>Statistik Predikat Projek P5 / P3</h3>
            </div>
            <div class="card-body">
                <div class="grid grid-4">
                    @foreach($sebaranP5 as $kode => $jml)
                        <div style="padding:12px;border:1px solid var(--line);border-radius:8px;text-align:center">
                            <div style="font-size:12px;color:var(--muted);font-weight:700">{{ $kode }}</div>
                            <div style="font-size:24px;font-weight:800;color:var(--ok);margin-top:4px">{{ $jml }}</div>
                            <div style="font-size:11px;color:var(--muted)">capaian dimensi</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Status Kelengkapan Input Nilai per Mata Pelajaran — Kelas {{ $kelas ?? '-' }}</h3>
            <span class="tag tag-mut">{{ $siswa->count() }} siswa</span>
        </div>
        <div class="card-body tight">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width:44px">No</th>
                            <th>Mata Pelajaran</th>
                            <th style="text-align:center">Siswa Dinilai</th>
                            <th style="text-align:center">Rata-rata</th>
                            <th style="text-align:center">Tertinggi</th>
                            <th style="text-align:center">Terendah</th>
                            <th style="width:200px">Progres Kelengkapan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rekapMapel as $i => $row)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td><strong>{{ $row['mata_pelajaran'] }}</strong></td>
                                <td style="text-align:center">{{ $row['terisi'] }} / {{ $row['total_siswa'] }}</td>
                                <td style="text-align:center;font-weight:700">{{ $row['rata_rata'] ?: '-' }}</td>
                                <td style="text-align:center;color:var(--ok)">{{ $row['tertinggi'] ?: '-' }}</td>
                                <td style="text-align:center;color:var(--bad)">{{ $row['terendah'] ?: '-' }}</td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:8px">
                                        <div style="flex:1;background:#EEF2F6;border-radius:6px;height:12px;overflow:hidden">
                                            <div style="width:{{ min(100, $row['persen']) }}%;background:{{ $row['persen'] >= 100 ? 'var(--ok)' : ($row['persen'] > 0 ? 'var(--gold)' : 'transparent') }};height:12px;border-radius:6px"></div>
                                        </div>
                                        <span style="font-size:11.5px;font-weight:700;min-width:44px;text-align:right">{{ $row['persen'] }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr class="table-empty"><td colspan="7" style="padding:20px;text-align:center;color:var(--muted)">Belum ada data mata pelajaran.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
