@extends('layouts.app')

@section('title', 'Rekap Sekolah - SIMANTAP')

@section('sidebar')
    @include('kepsek.partials.sidebar')
@endsection

@section('page_title', 'Rekap Sekolah')
@section('page_subtitle', 'Gabungan data seluruh guru')

@section('header_actions')
    <a href="{{ route('kepsek.rekap.unduh') }}" class="btn btn-ghost btn-sm">
        <i class="fas fa-download"></i> Unduh CSV
    </a>
@endsection

@section('content')
    <div class="grid grid-4" style="margin-bottom:20px">
        <div class="stat-card primary">
            <i class="fas fa-chalkboard-teacher stat-icon"></i>
            <div class="stat-label">Guru Berdata</div>
            <div class="stat-value">{{ $ringkasan->count() }}</div>
            <div class="stat-change">dari {{ $guruTanpaData->count() + $ringkasan->count() }} guru</div>
        </div>
        <div class="stat-card gold">
            <i class="fas fa-users stat-icon"></i>
            <div class="stat-label">Total Siswa</div>
            <div class="stat-value">{{ $totalSiswa }}</div>
        </div>
        <div class="stat-card {{ $ketuntasan >= 75 ? 'ok' : 'bad' }}">
            <i class="fas fa-check-circle stat-icon"></i>
            <div class="stat-label">Ketuntasan</div>
            <div class="stat-value">{{ number_format($ketuntasan, 1) }}%</div>
        </div>
        <div class="stat-card ok">
            <i class="fas fa-calendar-check stat-icon"></i>
            <div class="stat-label">Kehadiran</div>
            <div class="stat-value">{{ number_format($kehadiran, 1) }}%</div>
        </div>
    </div>

    @if($guruTanpaData->count())
        <div class="note note-warn" style="margin-bottom:16px">
            <i class="fas fa-exclamation-triangle"></i> <b>{{ $guruTanpaData->count() }} guru belum mengisi data:</b> {{ $guruTanpaData->pluck('nama_lengkap')->join(', ') }}
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-table" style="margin-right:8px;color:var(--navy)"></i>Rekap Per Guru</h3>
            <span class="tag tag-primary">rata-rata sekolah {{ number_format($ringkasan->avg('rata_rata'), 1) }}</span>
        </div>
        <div class="card-body tight">
            @if($ringkasan->count())
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Guru</th>
                                <th>Mata Pelajaran</th>
                                <th>Kelas</th>
                                <th style="text-align:right">Siswa</th>
                                <th style="text-align:right">Rata-rata</th>
                                <th>Ketuntasan</th>
                                <th style="text-align:right">Kehadiran</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($ringkasan as $r)
                                <tr>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:10px">
                                            <span class="avatar" style="background:var(--navy);color:#fff">{{ substr($r->nama_guru, 0, 2) }}</span>
                                            <div>
                                                <b>{{ $r->nama_guru }}</b>
                                                <div style="font-size:11px;color:var(--muted)">KKM {{ $r->kkm }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $r->mata_pelajaran ?? '—' }}</td>
                                    <td>{{ $r->kelas ?? '—' }}</td>
                                    <td style="text-align:right"><b>{{ $r->jumlah_siswa }}</b></td>
                                    <td style="text-align:right"><b>{{ number_format($r->rata_rata, 1) }}</b></td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:8px">
                                            <div class="bar" style="flex:1"><div class="fill {{ $r->ketuntasan_persen >= 80 ? 'fill-ok' : ($r->ketuntasan_persen >= 60 ? 'fill-warn' : 'fill-bad') }}" style="width:{{ min($r->ketuntasan_persen, 100) }}%"></div></div>
                                            <span style="font-size:12px;font-weight:600">{{ number_format($r->ketuntasan_persen, 1) }}%</span>
                                        </div>
                                    </td>
                                    <td style="text-align:right">{{ number_format($r->kehadiran_persen, 1) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">
                    <div class="icon">📊</div>
                    <h4>Belum ada data</h4>
                    <p>Belum ada guru yang menyimpan data ke sistem.</p>
                </div>
            @endif
        </div>
    </div>
@endsection
