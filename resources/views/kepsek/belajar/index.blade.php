@extends('layouts.app')

@section('title', 'Hasil Belajar - SIMANTAP')

@section('sidebar')
    @include('kepsek.partials.sidebar')
@endsection

@section('page_title', 'Hasil Belajar')
@section('page_subtitle', 'Analisis hasil belajar siswa per mata pelajaran')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="card" style="margin-bottom:16px">
    <div class="card-header"><h3>🔍 Filter</h3></div>
    <div class="card-body">
        <form method="GET" style="display:flex;gap:12px;align-items:end">
            <div>
                <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Kelas</label>
                <select name="kelas" style="padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px;min-width:150px">
                    <option value="">Semua Kelas</option>
                    @foreach($listKelas as $k)
                        <option value="{{ $k }}" {{ $kelas == $k ? 'selected' : '' }}>{{ $k }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-sm" style="background:#1F3864;color:#fff">🔍 Filter</button>
        </form>
    </div>
</div>

<div class="grid grid-2" style="margin-bottom:16px">
    <div class="card">
        <div class="card-header"><h3>📊 Per Mata Pelajaran</h3></div>
        <div class="card-body tight">
            @if($perMapel->count())
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Mapel</th>
                                <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Total</th>
                                <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Rata-rata</th>
                                <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Tertinggi</th>
                                <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Terendah</th>
                                <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Tuntas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($perMapel as $m)
                                <tr style="border-bottom:1px solid #F2F4F7">
                                    <td style="padding:8px 12px;font-weight:600">{{ $m['mapel'] }}</td>
                                    <td style="padding:8px 12px;text-align:center">{{ $m['total'] }}</td>
                                    <td style="padding:8px 12px;text-align:center">
                                        <span style="background:{{ $m['rata'] >= 80 ? '#D1FAE5' : ($m['rata'] >= 70 ? '#FEF3C7' : '#FEE2E2') }};color:{{ $m['rata'] >= 80 ? '#065F46' : ($m['rata'] >= 70 ? '#92400E' : '#991B1B') }};padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600">{{ $m['rata'] }}</span>
                                    </td>
                                    <td style="padding:8px 12px;text-align:center;font-weight:600;color:#059669">{{ $m['tertinggi'] }}</td>
                                    <td style="padding:8px 12px;text-align:center;font-weight:600;color:#DC2626">{{ $m['terendah'] }}</td>
                                    <td style="padding:8px 12px;text-align:center">{{ $m['tuntas'] }}/{{ $m['total'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div style="text-align:center;padding:30px;color:#888">Belum ada data nilai</div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>🏫 Per Kelas</h3></div>
        <div class="card-body tight">
            @if($perKelas->count())
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Kelas</th>
                                <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Total</th>
                                <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Rata-rata</th>
                                <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Tuntas</th>
                                <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Tidak Tuntas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($perKelas as $k)
                                <tr style="border-bottom:1px solid #F2F4F7">
                                    <td style="padding:8px 12px;font-weight:600">{{ $k['kelas'] }}</td>
                                    <td style="padding:8px 12px;text-align:center">{{ $k['total'] }}</td>
                                    <td style="padding:8px 12px;text-align:center">
                                        <span style="background:{{ $k['rata'] >= 80 ? '#D1FAE5' : ($k['rata'] >= 70 ? '#FEF3C7' : '#FEE2E2') }};color:{{ $k['rata'] >= 80 ? '#065F46' : ($k['rata'] >= 70 ? '#92400E' : '#991B1B') }};padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600">{{ $k['rata'] }}</span>
                                    </td>
                                    <td style="padding:8px 12px;text-align:center"><span style="background:#D1FAE5;color:#065F46;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600">{{ $k['tuntas'] }}</span></td>
                                    <td style="padding:8px 12px;text-align:center"><span style="background:{{ $k['tidak_tuntas'] > 0 ? '#FEE2E2;color:#991B1B' : '#F3F4F6;color:#6B7280' }};padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600">{{ $k['tidak_tuntas'] }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div style="text-align:center;padding:30px;color:#888">Belum ada data</div>
            @endif
        </div>
    </div>
</div>
@endsection
