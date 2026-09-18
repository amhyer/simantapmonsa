@extends('layouts.app')

@section('title', 'Detail Nilai - SIMANTAP')

@section('sidebar')
    @include('siswa.partials.sidebar')
@endsection

@section('page_title', 'Detail Nilai: {{ $jenis }}')
@section('page_subtitle', 'Rincian penilaian')

@section('content')
<div class="card" style="margin-bottom:16px">
    <div class="card-header">
        <h3>📊 {{ $jenis }}</h3>
        <a href="{{ route('siswa.nilai.index') }}" class="btn btn-ghost btn-sm">← Kembali</a>
    </div>
    <div class="card-body">
        <div style="display:flex;gap:16px;align-items:center">
            <span style="width:40px;height:40px;border-radius:50%;background:#EEF2FF;color:#4338CA;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700">{{ substr($siswa->nama_peserta_didik, 0, 2) }}</span>
            <div>
                <div style="font-weight:700;font-size:16px">{{ $siswa->nama_peserta_didik }}</div>
                <div style="font-size:13px;color:#667085">NIS: {{ $siswa->nis }} | Kelas: {{ $siswa->kelas }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>📋 Riwayat {{ $jenis }}</h3></div>
    <div class="card-body tight">
        @if($jenis === 'Kuis' && $hasilKuis->count())
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse">
                    <thead>
                        <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Waktu</th>
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Judul Kuis</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Skor</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Benar/Salah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($hasilKuis as $h)
                            <tr style="border-bottom:1px solid #F2F4F7">
                                <td style="padding:8px 12px;font-size:12px">{{ $h->waktu ? \Carbon\Carbon::parse($h->waktu)->format('d/m/Y H:i') : '-' }}</td>
                                <td style="padding:8px 12px;font-weight:600;font-size:12px">{{ $h->kuis?->judul ?? '-' }}</td>
                                <td style="padding:8px 12px;text-align:center">
                                    <span style="background:{{ ($h->skor ?? 0) >= 70 ? '#D1FAE5;color:#065F46' : '#FEE2E2;color:#991B1B' }};padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600">{{ $h->skor ?? '-' }}</span>
                                </td>
                                <td style="padding:8px 12px;text-align:center;font-size:12px">{{ $h->benar ?? '-' }}/{{ $h->total ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @elseif($nilai->count())
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse">
                    <thead>
                        <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Tanggal</th>
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Judul</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Nilai</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($nilai as $n)
                            <tr style="border-bottom:1px solid #F2F4F7">
                                <td style="padding:8px 12px;font-size:12px">{{ \Carbon\Carbon::parse($n->tanggal)->format('d/m/Y') }}</td>
                                <td style="padding:8px 12px;font-weight:600;font-size:12px">{{ $n->judul_penilaian ?? '-' }}</td>
                                <td style="padding:8px 12px;text-align:center">
                                    <span style="background:{{ $n->nilai >= 70 ? '#D1FAE5;color:#065F46' : '#FEE2E2;color:#991B1B' }};padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600">{{ $n->nilai }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div style="text-align:center;padding:40px;color:#888">
                <div style="font-size:48px;margin-bottom:12px">📊</div>
                <h4>Belum ada data {{ $jenis }}</h4>
            </div>
        @endif
    </div>
</div>
@endsection
