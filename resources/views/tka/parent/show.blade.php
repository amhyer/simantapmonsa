@extends('layouts.app')

@section('title', 'Laporan TKA untuk Orang Tua - SIMANTAP')

@section('sidebar')
    @include('ortu.partials.sidebar')
@endsection

@section('page_title', 'Laporan Perkembangan Karakter Anak')
@section('page_subtitle', $report->siswa->nama_peserta_didik)

@section('header_actions')
    <a href="{{ route('tka.parent.index') }}" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Kembali ke Daftar
    </a>
@endsection

@section('sidebar')
    @include('ortu.partials.sidebar')
@endsection

@section('content')
@if(!$report->dibaca_oleh_ortu)
    <div class="note note-ok" style="margin-bottom:20px;display:flex;align-items:center;gap:10px">
        <i class="fas fa-check-circle" style="color:var(--ok)"></i>
        <span>Laporan telah dibuka dan dicatat sebagai sudah dibaca oleh orang tua.</span>
    </div>
@endif

{{-- Student Info & Readiness Hero --}}
<div class="grid grid-3" style="align-items:start;margin-bottom:24px">
    {{-- Student Identity --}}
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user" style="color:var(--navy);margin-right:8px"></i>Identitas Peserta Didik</h3>
        </div>
        <div class="card-body tight">
            <div class="table-wrapper">
                <table>
                    <tbody>
                        <tr>
                            <td style="color:var(--muted);width:35%">Nama</td>
                            <td style="font-weight:700">{{ $report->siswa->nama_peserta_didik }}</td>
                        </tr>
                        <tr>
                            <td style="color:var(--muted)">Kelas</td>
                            <td><span class="tag tag-primary">Kelas {{ $report->siswa->kelas }}</span></td>
                        </tr>
                        <tr>
                            <td style="color:var(--muted)">Semester</td>
                            <td>{{ $report->assessment->semester->label ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td style="color:var(--muted)">Tanggal Asesmen</td>
                            <td>{{ $report->assessment->tanggal_penilaian?->format('d M Y') ?? '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Hero Readiness Status (2 Cols) --}}
    <div class="card" style="grid-column: span 2;border-left:4px solid var(--navy)">
        <div class="card-body" style="padding:28px 24px;text-align:center">
            @php
                $statusBadges = [
                    'sangat_siap' => ['tag' => 'tag-ok', 'label' => 'Sangat Siap (Sangat Berkembang)', 'stars' => '⭐⭐⭐⭐', 'desc' => 'Anak menunjukkan kemandirian dan karakter Pancasila yang sangat baik dan konsisten.'],
                    'siap' => ['tag' => 'tag-primary', 'label' => 'Siap (Berkembang Sesuai Harapan)', 'stars' => '⭐⭐⭐', 'desc' => 'Anak telah mencapai kompetensi dan indikator karakter sesuai tahap perkembangannya.'],
                    'perlu_bimbingan' => ['tag' => 'tag-gold', 'label' => 'Perlu Bimbingan (Mulai Berkembang)', 'stars' => '⭐⭐', 'desc' => 'Anak mulai memperlihatkan sikap positif, namun memerlukan pendampingan orang tua & guru.'],
                    'belum_siap' => ['tag' => 'tag-bad', 'label' => 'Belum Siap (Perlu Intervensi)', 'stars' => '⭐', 'desc' => 'Anak memerlukan perhatian dan latihan pembiasaan karakter lebih intensif di rumah.'],
                ];
                $st = $statusBadges[$report->assessment->status_kesiapan] ?? ['tag' => 'tag-secondary', 'label' => ucfirst($report->assessment->status_kesiapan), 'stars' => '⭐', 'desc' => ''];
            @endphp

            <div style="font-size:32px;margin-bottom:8px">{{ $st['stars'] }}</div>
            <div style="margin-bottom:8px">
                <span class="tag {{ $st['tag'] }}" style="font-size:14px;padding:6px 16px;font-weight:800">
                    {{ $st['label'] }}
                </span>
            </div>
            <div style="font-size:14px;color:var(--text);max-width:540px;margin:0 auto 12px;line-height:1.5">
                {{ $st['desc'] }}
            </div>
            <div style="font-size:12px;color:var(--muted)">
                Pencapaian Asesmen: <strong>{{ number_format($report->assessment->persentase, 1) }}%</strong>
            </div>
        </div>
    </div>
</div>

{{-- Dimension Progress Cards --}}
<div class="card" style="margin-bottom:24px">
    <div class="card-header">
        <h3><i class="fas fa-chart-simple" style="color:var(--navy);margin-right:8px"></i>Capaian Dimensi Profil Pelajar Pancasila</h3>
    </div>
    <div class="card-body">
        <div class="grid grid-3">
            @foreach($report->assessment->skor_per_dimensi ?? [] as $dimensi)
                @php
                    $ratio = $dimensi['maksimal'] > 0 ? ($dimensi['total_skor'] / $dimensi['maksimal']) : 0;
                    $dimPercent = round($ratio * 100);
                    $fillClass = $ratio >= 0.75 ? 'fill-ok' : ($ratio >= 0.5 ? 'fill-warn' : 'fill-bad');
                @endphp
                <div style="background:#FAFBFD;border:1px solid var(--line);border-radius:10px;padding:16px">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                        <span class="tag tag-primary" style="font-size:10px">{{ $dimensi['kategori'] ?? 'Dimensi' }}</span>
                        <span style="font-weight:800;font-size:12px">{{ $dimPercent }}%</span>
                    </div>

                    <h4 style="font-size:13.5px;font-weight:700;color:var(--text);margin:0 0 10px;line-height:1.4">
                        {{ $dimensi['nama_dimensi'] }}
                    </h4>

                    <div class="bar" style="margin-bottom:8px">
                        <span class="fill {{ $fillClass }}" style="width:{{ $dimPercent }}%"></span>
                    </div>

                    <div style="font-size:11.5px;color:var(--muted)">
                        Skor: <strong>{{ number_format($dimensi['total_skor'], 1) }}</strong> dari {{ number_format($dimensi['maksimal'], 1) }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Recommendations for Parents --}}
@if($report->rekomendasi_rumah)
    <div class="card" style="margin-bottom:24px;border-left:4px solid var(--gold)">
        <div class="card-header">
            <h3><i class="fas fa-lightbulb" style="color:var(--gold);margin-right:8px"></i>Catatan & Rekomendasi untuk Orang Tua di Rumah</h3>
        </div>
        <div class="card-body">
            <div style="font-size:14px;color:var(--text);line-height:1.7;white-space:pre-wrap">
                {{ $report->rekomendasi_rumah }}
            </div>
        </div>
    </div>
@endif

{{-- Suggested Activities --}}
@if($report->aktivitas_suggested && count($report->aktivitas_suggested) > 0)
    <div class="card" style="margin-bottom:24px;border-left:4px solid var(--ok)">
        <div class="card-header">
            <h3><i class="fas fa-person-walking" style="color:var(--ok);margin-right:8px"></i>Aktivitas Pendampingan yang Disarankan</h3>
        </div>
        <div class="card-body">
            <div class="grid grid-2">
                @foreach($report->aktivitas_suggested as $aktivitas)
                    <div style="background:#FAFBFD;border:1px solid var(--line);border-radius:10px;padding:16px">
                        <span class="tag tag-primary" style="font-size:10px;margin-bottom:6px">
                            {{ $aktivitas['dimension'] ?? 'Aktivitas' }}
                        </span>
                        <div style="display:flex;flex-direction:column;gap:8px;margin-top:8px">
                            @foreach($aktivitas['activities'] ?? [] as $activity)
                                <div style="display:flex;align-items:flex-start;gap:8px;font-size:13px;color:var(--text);line-height:1.4">
                                    <i class="fas fa-check" style="color:var(--ok);margin-top:3px;font-size:11px"></i>
                                    <span>{{ $activity }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif

<div style="display:flex;justify-content:flex-start">
    <a href="{{ route('tka.parent.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Kembali ke Daftar Laporan
    </a>
</div>
@endsection
