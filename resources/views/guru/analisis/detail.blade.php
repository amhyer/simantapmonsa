@extends('layouts.app')

@section('title', 'Analisis Siswa - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Analisis Belajar Siswa')
@section('page_subtitle', $siswa->nama_peserta_didik ?? '-')

@section('content')
<div class="card" style="margin-bottom:16px">
    <div class="card-header">
        <h3>📊 Ringkasan {{ $siswa->nama_peserta_didik }}</h3>
        <a href="{{ route('guru.analisis.index') }}" class="btn btn-ghost btn-sm">← Kembali</a>
    </div>
    <div class="card-body">
        <div style="display:flex;gap:16px;align-items:center;margin-bottom:16px">
            <span style="width:40px;height:40px;border-radius:50%;background:#EEF2FF;color:#4338CA;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700">{{ substr($siswa->nama_peserta_didik, 0, 2) }}</span>
            <div>
                <div style="font-weight:700;font-size:16px">{{ $siswa->nama_peserta_didik }}</div>
                <div style="font-size:13px;color:#667085">NIS: {{ $siswa->nis }} | Kelas: {{ $siswa->kelas }}</div>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
            <div style="background:#EEF2FF;border-radius:8px;padding:12px;text-align:center">
                <div style="font-size:11px;color:#667085;text-transform:uppercase;font-weight:600">Rata-rata Nilai</div>
                <div style="font-size:24px;font-weight:700;color:#1F3864">{{ $rataRata }}</div>
            </div>
            <div style="background:#D1FAE5;border-radius:8px;padding:12px;text-align:center">
                <div style="font-size:11px;color:#065F46;text-transform:uppercase;font-weight:600">Rata-rata Kuis</div>
                <div style="font-size:24px;font-weight:700;color:#065F46">{{ $rataKuis }}</div>
            </div>
            <div style="background:#FEF3C7;border-radius:8px;padding:12px;text-align:center">
                <div style="font-size:11px;color:#92400E;text-transform:uppercase;font-weight:600">Total Penilaian</div>
                <div style="font-size:24px;font-weight:700;color:#92400E">{{ $nilai->count() + $hasilKuis->count() }}</div>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-header"><h3>📝 Daftar Nilai</h3></div>
        <div class="card-body tight">
            @if($nilai->count())
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse">
                        <thead><tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC"><th style="padding:8px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Tanggal</th><th style="padding:8px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Jenis</th><th style="padding:8px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Judul</th><th style="padding:8px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Nilai</th></tr></thead>
                        <tbody>
                            @foreach($nilai as $n)
                                <tr style="border-bottom:1px solid #F2F4F7">
                                    <td style="padding:6px 12px;font-size:12px">{{ \Carbon\Carbon::parse($n->tanggal)->format('d/m/Y') }}</td>
                                    <td style="padding:6px 12px"><span style="background:#EEF2FF;color:#4338CA;padding:1px 6px;border-radius:8px;font-size:10px">{{ $n->jenis }}</span></td>
                                    <td style="padding:6px 12px;font-size:12px">{{ $n->judul_penilaian ?? '-' }}</td>
                                    <td style="padding:6px 12px;text-align:center;font-weight:700">{{ $n->nilai }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div style="text-align:center;padding:20px;color:#888;font-size:13px">Belum ada nilai</div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>📝 Daftar Kuis</h3></div>
        <div class="card-body tight">
            @if($hasilKuis->count())
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse">
                        <thead><tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC"><th style="padding:8px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Waktu</th><th style="padding:8px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Kuis</th><th style="padding:8px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Skor</th><th style="padding:8px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Benar</th></tr></thead>
                        <tbody>
                            @foreach($hasilKuis as $h)
                                <tr style="border-bottom:1px solid #F2F4F7">
                                    <td style="padding:6px 12px;font-size:12px">{{ $h->waktu ? \Carbon\Carbon::parse($h->waktu)->format('d/m/Y H:i') : '-' }}</td>
                                    <td style="padding:6px 12px;font-size:12px">{{ $h->kuis?->judul ?? '-' }}</td>
                                    <td style="padding:6px 12px;text-align:center;font-weight:700">{{ $h->skor }}</td>
                                    <td style="padding:6px 12px;text-align:center">{{ $h->benar ?? '-' }}/{{ $h->total ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div style="text-align:center;padding:20px;color:#888;font-size:13px">Belum ada kuis</div>
            @endif
        </div>
    </div>
</div>
@endsection
