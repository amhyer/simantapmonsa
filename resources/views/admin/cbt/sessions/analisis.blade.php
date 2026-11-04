@extends('layouts.admin-with-tabs')

@section('title', 'Analisis Butir Soal - ' . $session->title)

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Analisis Butir Soal CBT')
@section('page_subtitle', $session->title)

@section('header_actions')
    <a href="{{ route('admin.cbt.sessions.show', $session) }}" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Kembali ke Sesi
    </a>
@endsection

@section('content')
{{-- Source Info Banner --}}
<div class="note note-ok" style="margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <div>
        <i class="fas fa-info-circle"></i> 
        <strong>Metrik Psikometri:</strong> 
        Tingkat Kesukaran (P) = Peserta Benar / Total Peserta • 
        Daya Pembeda (D) = Proporsi Kelompok Atas (27%) - Proporsi Kelompok Bawah (27%).
    </div>
    <div>
        <span class="tag tag-primary">Status: {{ ucfirst($hasil['status_data']) }}</span>
    </div>
</div>

{{-- Metric Stat Cards --}}
<div class="grid grid-4" style="margin-bottom:24px">
    <div class="stat-card primary">
        <i class="fas fa-users stat-icon"></i>
        <div class="stat-label">Peserta Dianalisis</div>
        <div class="stat-value">{{ $hasil['jumlah_peserta'] }}</div>
        <div class="stat-change">Siswa terekam</div>
    </div>

    <div class="stat-card gold">
        <i class="fas fa-list-ol stat-icon"></i>
        <div class="stat-label">Jumlah Butir Soal</div>
        <div class="stat-value">{{ $hasil['jumlah_soal'] }}</div>
        <div class="stat-change">Pilihan Ganda</div>
    </div>

    <div class="stat-card ok">
        <i class="fas fa-gauge-high stat-icon"></i>
        <div class="stat-label">Rata-rata Kesukaran (P)</div>
        <div class="stat-value">
            {{ $hasil['rata_p'] !== null ? number_format($hasil['rata_p'] * 100, 1) . '%' : '-' }}
        </div>
        <div class="stat-change up">Tingkat kemudahan</div>
    </div>

    <div class="stat-card violet">
        <i class="fas fa-chart-line stat-icon"></i>
        <div class="stat-label">Rata-rata Pembeda (D)</div>
        <div class="stat-value">
            {{ $hasil['rata_d'] !== null ? number_format($hasil['rata_d'], 2) : '-' }}
        </div>
        <div class="stat-change">Kualitas diskriminasi</div>
    </div>
</div>

{{-- Butir Soal Analysis Table --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-microscope" style="color:var(--navy);margin-right:8px"></i>Hasil Analisis Per Butir Soal</h3>
        <span class="tag tag-secondary">Evaluasi Kelayakan Butir</span>
    </div>

    <div class="card-body tight">
        @if(empty($hasil['butir']))
            <div class="empty-state">
                <div class="icon">📊</div>
                <h4>Belum Ada Data untuk Dianalisis</h4>
                <p>Ujian membutuhkan minimal siswa yang mengumpulkan jawaban untuk menghitung daya pembeda.</p>
            </div>
        @else
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width:6%">No</th>
                            <th style="width:20%">Tingkat Kesukaran (P)</th>
                            <th style="width:20%">Daya Pembeda (D)</th>
                            <th style="width:24%">Rekomendasi Butir</th>
                            <th style="width:30%;text-align:right">Pengecoh / Distraktor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($hasil['butir'] as $b)
                            @php
                                $pPercent = round(($b['p'] ?? 0) * 100);
                                $dVal = $b['d'] ?? 0;
                                $rekomendasi = 'Diterima';
                                $rekomClass = 'tag-ok';
                                if ($dVal < 0.2) {
                                    $rekomendasi = 'Perlu Revisi';
                                    $rekomClass = 'tag-warn';
                                }
                                if ($dVal < 0) {
                                    $rekomendasi = 'Dibuang / Perbaiki Kunci';
                                    $rekomClass = 'tag-bad';
                                }
                            @endphp
                            <tr>
                                <td style="font-weight:700;color:var(--navy)">#{{ $b['nomor'] }} — {{ Str::limit($b['teks'] ?? '', 60) }}</td>
                                <td>
                                    <div style="font-weight:700;font-size:13.5px">
                                        {{ number_format($pPercent, 1) }}%
                                    </div>
                                    <div style="font-size:11px;color:var(--muted)">
                                        Kategori: <strong>{{ ucfirst($b['kategori_p'] ?? '-') }}</strong>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight:700;font-size:13.5px;color:{{ $dVal >= 0.3 ? 'var(--ok)' : ($dVal >= 0.2 ? 'var(--warn)' : 'var(--bad)') }}">
                                        {{ number_format($dVal, 2) }}
                                    </div>
                                    <div style="font-size:11px;color:var(--muted)">
                                        Kategori: <strong>{{ ucfirst($b['kategori_d'] ?? '-') }}</strong>
                                    </div>
                                </td>
                                <td>
                                    <span class="tag {{ $rekomClass }}" style="font-weight:700">
                                        {{ $rekomendasi }}
                                    </span>
                                </td>
                                <td style="text-align:right">
                                    <div style="display:inline-flex;gap:4px;font-size:11px">
                                        @foreach($b['distraktor'] ?? [] as $opt => $cnt)
                                            <span style="padding:2px 6px;border-radius:4px;background:#FAFBFD;border:1px solid var(--line)">
                                                <strong>{{ $opt }}:</strong> {{ $cnt }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
