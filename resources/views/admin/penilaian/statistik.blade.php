@extends('layouts.app')

@section('title', 'Statistik Nilai Rapor - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Statistik Nilai Rapor')
@section('page_subtitle', 'Sebaran predikat dan rata-rata per mata pelajaran (dari nilai e-rapor)')

@section('content')
    <div class="card" style="margin-bottom:16px">
        <div class="card-body">
            <form action="{{ route('admin.penilaian.statistik') }}" method="GET" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
                <div style="flex:1;min-width:200px">
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Pilih Kelas</label>
                    <select name="kelas" style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
                        <option value="">Semua Kelas</option>
                        @foreach($kelasList as $k)
                            <option value="{{ $k }}" {{ $kelasFilter === $k ? 'selected' : '' }}>{{ $k }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-sm">Tampilkan</button>
                    @if($kelasFilter)
                        <a href="{{ route('admin.penilaian.statistik') }}" class="btn btn-sm btn-ghost">Reset</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="grid grid-4" style="margin-bottom:16px">
        <div class="stat"><div class="label">Total nilai</div><div class="value">{{ $total }}</div></div>
        <div class="stat ok"><div class="label">Rata-rata sekolah</div><div class="value">{{ $rataSekolah }}</div></div>
        <div class="stat gold"><div class="label">Predikat A</div><div class="value">{{ $sebaran['A']['jumlah'] }} ({{ $sebaran['A']['persen'] }}%)</div></div>
        <div class="stat"><div class="label">Perlu bimbingan (D)</div><div class="value">{{ $sebaran['D']['jumlah'] }} ({{ $sebaran['D']['persen'] }}%)</div></div>
    </div>

    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Sebaran Predikat</h3>
        </div>
        <div class="card-body">
            @foreach(['A' => 'var(--ok)', 'B' => 'var(--navy)', 'C' => '#B8860B', 'D' => '#B42318'] as $p => $warna)
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
                    <b style="width:20px">{{ $p }}</b>
                    <div style="flex:1;background:#EEF2F6;border-radius:6px;height:18px">
                        <div style="width:{{ $sebaran[$p]['persen'] }}%;background:{{ $warna }};height:18px;border-radius:6px"></div>
                    </div>
                    <span style="font-size:12px;color:#667085;width:110px;text-align:right">{{ $sebaran[$p]['jumlah'] }} ({{ $sebaran[$p]['persen'] }}%)</span>
                </div>
            @endforeach
            @if(!$total)
                <p style="font-size:13px;color:#667085">Belum ada nilai e-rapor. Grafik muncul setelah guru menginput nilai.</p>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Rata-rata per Mata Pelajaran</h3>
        </div>
        <div class="card-body tight">
            @if($perMapel->count())
                <div class="table-wrapper">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Mata Pelajaran</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Jumlah</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Rata-rata</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Tuntas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($perMapel as $m)
                                <tr>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC"><b>{{ $m['mapel'] }}</b></td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">{{ $m['jumlah'] }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">{{ $m['rata'] }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">{{ $m['tuntas'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">
                    <div class="icon">📊</div>
                    <h4>Belum ada data</h4>
                    <p>Statistik muncul setelah ada nilai e-rapor.</p>
                </div>
            @endif
        </div>
    </div>
@endsection
