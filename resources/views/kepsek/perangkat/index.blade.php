@extends('layouts.app')

@section('title', 'Supervisi Perangkat Ajar - SIMANTAP')
@section('page_title', 'Supervisi Perangkat Ajar Guru')
@section('page_subtitle', 'Pemantauan CP, TP, ATP, Prota, Promes, Modul Ajar, KKM & Jurnal Mengajar (Baca-Saja)')

@section('sidebar')
    @include('kepsek.partials.sidebar')
@endsection

@section('content')
    <div class="card" style="margin-bottom:16px">
        <div class="card-body" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between">
            <div style="display:flex;flex-wrap:wrap;gap:6px">
                @foreach($jenisRingkas as $kode => $label)
                    <span class="badge badge-info">{{ $label }}: {{ $ringkasanJenis[$kode] ?? 0 }}</span>
                @endforeach
            </div>
            <form method="GET" action="{{ route('kepsek.perangkat.index') }}" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
                <select name="jenis" style="padding:7px 10px;border:1px solid #E4E7EC;border-radius:8px;font-size:13px">
                    <option value="">Semua Jenis</option>
                    @foreach($jenisLabels as $kode => $label)
                        <option value="{{ $kode }}" {{ $jenisFilter === $kode ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="guru_id" style="padding:7px 10px;border:1px solid #E4E7EC;border-radius:8px;font-size:13px">
                    <option value="0">Semua Guru</option>
                    @foreach($guruList as $g)
                        <option value="{{ $g->id }}" {{ $guruFilter === $g->id ? 'selected' : '' }}>{{ $g->nama_lengkap }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-sm">Terapkan</button>
            </form>
        </div>
    </div>

    <div class="card" style="margin-bottom:18px">
        <div class="card-header"><strong>Daftar Perangkat Pembelajaran Guru</strong></div>
        <div class="card-body" style="padding:0">
            @if($perangkatList->isEmpty())
                <div style="padding:28px;text-align:center;color:#667085">Belum ada dokumen perangkat pembelajaran guru.</div>
            @else
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Jenis</th>
                                <th>Judul Dokumen</th>
                                <th>Guru</th>
                                <th>Mapel &amp; Kelas</th>
                                <th>Status</th>
                                <th style="text-align:right">Cetak</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($perangkatList as $item)
                                <tr>
                                    <td><span class="badge badge-info">{{ $item->jenisRingkas() }}</span></td>
                                    <td><strong>{{ $item->judul }}</strong></td>
                                    <td>{{ $item->guru?->nama_lengkap ?? '-' }}</td>
                                    <td>{{ $item->mata_pelajaran }} • {{ $item->rombel?->nama_rombel ?? $item->kelas }}</td>
                                    <td><span class="badge {{ $item->status === 'final' ? 'badge-ok' : 'badge-warn' }}">{{ ucfirst($item->status) }}</span></td>
                                    <td style="text-align:right">
                                        <a href="{{ route('kepsek.perangkat.pdf', $item) }}" target="_blank" class="btn btn-ghost btn-sm">
                                            <i class="fas fa-file-pdf"></i> PDF
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($perangkatList->hasPages())
                    <div style="padding:12px 16px">{{ $perangkatList->links() }}</div>
                @endif
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header"><strong>Jurnal Mengajar Guru Terbaru</strong></div>
        <div class="card-body" style="padding:0">
            @if($jurnalTerbaru->isEmpty())
                <div style="padding:20px;color:#667085;font-size:13px">Belum ada catatan jurnal mengajar guru.</div>
            @else
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr><th>Tanggal</th><th>Guru</th><th>Kelas / Mapel</th><th>Topik</th><th>Hadir</th></tr>
                        </thead>
                        <tbody>
                            @foreach($jurnalTerbaru as $j)
                                <tr>
                                    <td>{{ $j->tanggal->format('d/m/Y') }}</td>
                                    <td>{{ $j->guru?->nama_lengkap ?? '-' }}</td>
                                    <td>{{ $j->kelas }} • {{ $j->mata_pelajaran }}</td>
                                    <td>{{ $j->topik }}</td>
                                    <td>{{ $j->jumlah_hadir !== null ? $j->jumlah_hadir.' hadir' : '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
