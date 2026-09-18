@extends('layouts.app')

@section('title', 'Detail Rekap Guru - SIMANTAP')

@section('sidebar')
    @include('kepsek.partials.sidebar')
@endsection

@section('page_title', 'Detail Rekap Guru')
@section('page_subtitle', $ringkasan->guru?->nama_lengkap ?? '-')

@section('content')
<div class="card" style="margin-bottom:16px">
    <div class="card-header">
        <h3>👨‍🏫 {{ $ringkasan->guru?->nama_lengkap ?? '-' }}</h3>
        <a href="{{ route('kepsek.rekap.index') }}" class="btn btn-ghost btn-sm">← Kembali</a>
    </div>
    <div class="card-body">
        <div style="display:flex;gap:16px;align-items:center;margin-bottom:16px">
            <span style="width:48px;height:48px;border-radius:50%;background:#1F3864;color:#fff;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700">{{ substr($ringkasan->guru?->nama_lengkap ?? 'G', 0, 2) }}</span>
            <div>
                <div style="font-weight:700;font-size:18px">{{ $ringkasan->guru?->nama_lengkap ?? '-' }}</div>
                <div style="font-size:13px;color:#667085">{{ $ringkasan->guru?->kelas_mata_pelajaran ?? '-' }}</div>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px">
            <div style="background:#EEF2FF;border-radius:8px;padding:12px;text-align:center">
                <div style="font-size:11px;color:#667085;text-transform:uppercase;font-weight:600">Rata-rata</div>
                <div style="font-size:24px;font-weight:700;color:#1F3864">{{ number_format($ringkasan->rata_rata ?? 0, 1) }}</div>
            </div>
            <div style="background:#D1FAE5;border-radius:8px;padding:12px;text-align:center">
                <div style="font-size:11px;color:#065F46;text-transform:uppercase;font-weight:600">Ketuntasan</div>
                <div style="font-size:24px;font-weight:700;color:#065F46">{{ number_format($ringkasan->ketuntasan_persen ?? 0, 1) }}%</div>
            </div>
            <div style="background:#FEF3C7;border-radius:8px;padding:12px;text-align:center">
                <div style="font-size:11px;color:#92400E;text-transform:uppercase;font-weight:600">Kehadiran</div>
                <div style="font-size:24px;font-weight:700;color:#92400E">{{ number_format($ringkasan->kehadiran_persen ?? 0, 1) }}%</div>
            </div>
            <div style="background:#DBEAFE;border-radius:8px;padding:12px;text-align:center">
                <div style="font-size:11px;color:#1E40AF;text-transform:uppercase;font-weight:600">KKM</div>
                <div style="font-size:24px;font-weight:700;color:#1E40AF">{{ $ringkasan->kkm ?? 70 }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>📋 Data Siswa</h3></div>
    <div class="card-body tight">
        @if(count($dataSiswa))
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse">
                    <thead>
                        <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">No</th>
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Nama Siswa</th>
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">NIS</th>
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Kelas</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Nilai Akhir</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Predikat</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($dataSiswa as $i => $ds)
                            @php $kkm = $ringkasan->kkm ?? 70; @endphp
                            @php $pc = match($ds['predikat'] ?? null){'A'=>['#D1FAE5','#065F46'],'B'=>['#DBEAFE','#1E40AF'],'C'=>['#FEF3C7','#92400E'],'D'=>['#FEE2E2','#991B1B'],default=>['#F3F4F6','#6B7280']}; @endphp
                            @php [$bg,$fg] = $pc; @endphp
                            <tr style="border-bottom:1px solid #F2F4F7">
                                <td style="padding:8px 12px;font-size:12px;color:#888">{{ $i + 1 }}</td>
                                <td style="padding:8px 12px;font-weight:600;font-size:12px">{{ $ds['siswa']->nama_peserta_didik }}</td>
                                <td style="padding:8px 12px;font-size:12px;color:#667085">{{ $ds['siswa']->nis }}</td>
                                <td style="padding:8px 12px;font-size:12px">{{ $ds['siswa']->kelas }}</td>
                                <td style="padding:8px 12px;text-align:center">
                                    <span style="background:{{ ($ds['nilai'] ?? 0) >= $kkm ? '#D1FAE5' : '#FEE2E2' }};color:{{ ($ds['nilai'] ?? 0) >= $kkm ? '#065F46' : '#991B1B' }};padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600">{{ number_format($ds['nilai'] ?? 0, 1) }}</span>
                                </td>
                                <td style="padding:8px 12px;text-align:center">
                                    @if($ds['predikat'])
                                        <span style="display:inline-block;width:26px;height:26px;line-height:26px;text-align:center;border-radius:50%;font-weight:700;font-size:11px;background:{{ $bg }};color:{{ $fg }}">{{ $ds['predikat'] }}</span>
                                    @else
                                        <span style="color:#888">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div style="text-align:center;padding:40px;color:#888">Belum ada data siswa</div>
        @endif
    </div>
</div>
@endsection
