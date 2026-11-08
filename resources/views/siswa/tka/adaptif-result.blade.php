@extends('layouts.app')
@section('title', 'Hasil Simulasi Adaptif TKA - SIMANTAP_MONSA')

@section('sidebar')
    @include('siswa.partials.sidebar')
@endsection

@section('page_title', 'Hasil Simulasi Adaptif TKA')
@section('page_subtitle', $simulation->paket_judul . ' • Dilaporkan Otomatis ke Profil Kognitif & Akun Orang Tua')

@section('content')
@if(session('success'))
    <div class="note note-ok" style="margin-bottom:16px"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
@endif

<div class="grid grid-4" style="margin-bottom:20px">
    <div class="stat-card primary">
        <i class="fas fa-award stat-icon"></i>
        <div class="stat-label">Skor Akhir Berbobot</div>
        <div class="stat-value">{{ number_format((float) $simulation->skor_akhir, 1) }}</div>
        <div class="stat-change">{{ $simulation->level_capaian ?? 'Selesai' }}</div>
    </div>
    <div class="stat-card ok">
        <i class="fas fa-check-double stat-icon"></i>
        <div class="stat-label">Ketepatan Jawaban</div>
        <div class="stat-value">{{ $simulation->benar }} / {{ $simulation->total_soal }}</div>
        <div class="stat-change">Butir soal terjawab benar</div>
    </div>
    <div class="stat-card gold">
        <i class="fas fa-stopwatch stat-icon"></i>
        <div class="stat-label">Total Waktu Pengerjaan</div>
        <div class="stat-value" style="font-size:20px">{{ $simulation->durasiLabel() }}</div>
        <div class="stat-change">Tercatat per langkah soal</div>
    </div>
    <div class="stat-card primary">
        <i class="fas fa-sliders-h stat-icon"></i>
        <div class="stat-label">Lintasan Kesulitan</div>
        <div class="stat-value" style="font-size:18px">{{ ucfirst($simulation->tingkat_awal) }} → {{ ucfirst($simulation->tingkat_akhir) }}</div>
        <div class="stat-change">{{ $simulation->dibaca_ortu_at ? 'Sudah dibaca Ortu' : 'Terkirim ke akun Ortu' }}</div>
    </div>
</div>

{{-- Jejak Adaptif Per Langkah --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
        <h3><i class="fas fa-route" style="color:var(--violet);margin-right:8px"></i>Jejak Adaptif & Pembahasan Butir Soal</h3>
        <div style="display:flex;gap:8px">
            <a href="{{ route('siswa.tka-simulasi.kognitif') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-brain"></i> Lihat Profil Kognitif & Paket Latihanku
            </a>
            <a href="{{ route('siswa.tka-simulasi.index') }}" class="btn btn-secondary btn-sm">Daftar Simulasi</a>
        </div>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Langkah</th>
                        <th>Indikator & Pertanyaan</th>
                        <th style="text-align:center">Kesulitan</th>
                        <th style="text-align:center">Waktu</th>
                        <th style="text-align:center">Jawaban</th>
                        <th>Pembahasan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(($simulation->jejak_adaptif ?? []) as $step)
                        <tr>
                            <td style="font-weight:800">#{{ $step['step'] ?? $loop->iteration }}</td>
                            <td>
                                <div style="font-size:11.5px;font-weight:700;color:var(--violet)">{{ $step['indikator'] ?? '-' }}</div>
                                <div style="font-size:13px;font-weight:600;color:var(--text)">{{ $step['prompt'] ?? '-' }}</div>
                            </td>
                            <td style="text-align:center">
                                <span class="tag {{ ($step['tingkat'] ?? '') === 'sulit' ? 'tag-bad' : (($step['tingkat'] ?? '') === 'mudah' ? 'tag-ok' : 'tag-primary') }}">
                                    {{ strtoupper((string) ($step['tingkat'] ?? 'sedang')) }}
                                </span>
                            </td>
                            <td style="text-align:center;font-size:12.5px">{{ (int) ($step['durasi_detik'] ?? 0) }} dtk</td>
                            <td style="text-align:center">
                                @if(!empty($step['benar']))
                                    <span class="tag tag-ok">Benar ({{ $step['jawaban'] ?? '-' }})</span>
                                @else
                                    <span class="tag tag-bad">{{ $step['jawaban'] ?? '-' }} (Kunci: {{ $step['kunci'] ?? '-' }})</span>
                                @endif
                            </td>
                            <td style="font-size:12.5px;color:var(--muted);max-width:300px">{{ $step['pembahasan'] ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
