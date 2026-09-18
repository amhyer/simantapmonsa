@extends('layouts.app')

@section('title', 'Analisis TKA - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Analisis Kesiapan TKA')
@section('page_subtitle', 'Analisis dimensi profil lulusan per siswa')

@section('content')
<div class="card" style="margin-bottom:16px">
    <div class="card-header"><h3>📊 Ringkasan Analisis</h3><a href="{{ route('guru.tka.index') }}" class="btn btn-ghost btn-sm">← Kembali</a></div>
    <div class="card-body">
        <div style="display:flex;gap:16px;flex-wrap:wrap">
            <div style="background:#EEF2FF;border-radius:8px;padding:12px 20px;text-align:center">
                <div style="font-size:11px;color:#667085;text-transform:uppercase;font-weight:600">Total Siswa</div>
                <div style="font-size:24px;font-weight:700;color:#1F3864">{{ count($analisis) }}</div>
            </div>
            <div style="background:#D1FAE5;border-radius:8px;padding:12px 20px;text-align:center">
                <div style="font-size:11px;color:#065F46;text-transform:uppercase;font-weight:600">Sangat Baik</div>
                <div style="font-size:24px;font-weight:700;color:#065F46">{{ collect($analisis)->where('level_kognitif', 'Sangat Baik')->count() }}</div>
            </div>
            <div style="background:#DBEAFE;border-radius:8px;padding:12px 20px;text-align:center">
                <div style="font-size:11px;color:#1E40AF;text-transform:uppercase;font-weight:600">Baik</div>
                <div style="font-size:24px;font-weight:700;color:#1E40AF">{{ collect($analisis)->where('level_kognitif', 'Baik')->count() }}</div>
            </div>
            <div style="background:#FEF3C7;border-radius:8px;padding:12px 20px;text-align:center">
                <div style="font-size:11px;color:#92400E;text-transform:uppercase;font-weight:600">Cukup</div>
                <div style="font-size:24px;font-weight:700;color:#92400E">{{ collect($analisis)->where('level_kognitif', 'Cukup')->count() }}</div>
            </div>
            <div style="background:#FEE2E2;border-radius:8px;padding:12px 20px;text-align:center">
                <div style="font-size:11px;color:#991B1B;text-transform:uppercase;font-weight:600">Perlu Bimbingan</div>
                <div style="font-size:24px;font-weight:700;color:#991B1B">{{ collect($analisis)->where('level_kognitif', 'Perlu Bimbingan')->count() }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>📋 Detail Per Siswa</h3></div>
    <div class="card-body tight">
        @if(count($analisis))
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse">
                    <thead>
                        <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">No</th>
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Siswa</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Rata Skor</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Level Kognitif</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Dimensi</th>
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Kebiasaan Terakhir</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($analisis as $i => $a)
                            @php $levelColor = match($a['level_kognitif']){'Sangat Baik'=>'#D1FAE5,#065F46','Baik'=>'#DBEAFE,#1E40AF','Cukup'=>'#FEF3C7,#92400E',default=>'#FEE2E2,#991B1B'}; @endphp
                            @php [$bg,$fg] = explode(',', $levelColor); @endphp
                            <tr style="border-bottom:1px solid #F2F4F7">
                                <td style="padding:8px 12px;font-size:12px;color:#888">{{ $i + 1 }}</td>
                                <td style="padding:8px 12px;font-weight:600;font-size:12px">{{ $a['siswa']->nama_peserta_didik }}</td>
                                <td style="padding:8px 12px;text-align:center;font-weight:700">{{ number_format($a['rata_skor'], 1) }}</td>
                                <td style="padding:8px 12px;text-align:center"><span style="background:{{ $bg }};color:{{ $fg }};padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600">{{ $a['level_kognitif'] }}</span></td>
                                <td style="padding:8px 12px;text-align:center">{{ count($a['dimensi']) }}</td>
                                <td style="padding:8px 12px;font-size:12px;color:#667085">{{ $a['kebiasaan'] ? \Carbon\Carbon::parse($a['kebiasaan']->tanggal)->format('d/m/Y') : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div style="text-align:center;padding:40px;color:#888">Belum ada data analisis</div>
        @endif
    </div>
</div>
@endsection
