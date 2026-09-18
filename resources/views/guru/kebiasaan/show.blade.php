@extends('layouts.app')

@section('title', 'Detail Kebiasaan - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Detail Kebiasaan Siswa')
@section('page_subtitle', $siswa->nama_peserta_didik ?? '-')

@section('content')
<div class="card" style="margin-bottom:16px">
    <div class="card-header">
        <h3>📋 Kebiasaan {{ $siswa->nama_peserta_didik }}</h3>
        <a href="{{ route('guru.kebiasaan.index') }}" class="btn btn-ghost btn-sm">← Kembali</a>
    </div>
    <div class="card-body">
        <div style="display:flex;gap:16px;align-items:center;margin-bottom:16px">
            <span style="width:40px;height:40px;border-radius:50%;background:#EEF2FF;color:#4338CA;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700">{{ substr($siswa->nama_peserta_didik, 0, 2) }}</span>
            <div>
                <div style="font-weight:700;font-size:16px">{{ $siswa->nama_peserta_didik }}</div>
                <div style="font-size:13px;color:#667085">NIS: {{ $siswa->nis }} | Kelas: {{ $siswa->kelas }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>📊 Riwayat 7 Kebiasaan</h3></div>
    <div class="card-body tight">
        @if($kebiasaan->count())
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse">
                    <thead>
                        <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Tanggal</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Bangun Pagi</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Beribadah</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Olahraga</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Makan Sehat</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Belajar</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Masyarakat</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Tidur Cepat</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Rata</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($kebiasaan as $k)
                            @php
                                $fields = ['bangun_pagi','beribadah','berolahraga','makan_sehat','gemar_belajar','bermasyarakat','tidur_cepat'];
                                $values = collect($fields)->map(fn($f) => $k->$f)->filter();
                                $rata = $values->count() ? $values->avg() : 0;
                            @endphp
                            <tr style="border-bottom:1px solid #F2F4F7">
                                <td style="padding:8px 12px;font-size:12px;font-weight:600">{{ \Carbon\Carbon::parse($k->tanggal)->format('d/m/Y') }}</td>
                                @foreach($fields as $f)
                                    <td style="padding:8px 12px;text-align:center">
                                        @if($k->$f)
                                            <span style="display:inline-block;width:24px;height:24px;line-height:24px;text-align:center;border-radius:50%;font-weight:700;font-size:11px;background:{{ $k->$f >= 3 ? '#D1FAE5;color:#065F46' : ($k->$f >= 2 ? '#FEF3C7;color:#92400E' : '#FEE2E2;color:#991B1B') }}">{{ $k->$f }}</span>
                                        @else
                                            <span style="color:#888">-</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td style="padding:8px 12px;text-align:center;font-weight:700">{{ number_format($rata, 1) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div style="text-align:center;padding:40px;color:#888">
                <div style="font-size:48px;margin-bottom:12px">📋</div>
                <h4>Belum ada data kebiasaan</h4>
            </div>
        @endif
    </div>
</div>
@endsection
