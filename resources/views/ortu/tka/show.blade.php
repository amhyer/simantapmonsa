@extends('layouts.app')

@section('title', 'Detail Simulasi TKA Anak - Orang Tua')

@section('sidebar')
    @include('ortu.partials.sidebar')
@endsection

@section('page_title', 'Laporan Detail Simulasi TKA Ananda ' . ($anak->nama_peserta_didik ?? ''))
@section('page_subtitle', $simulation->paket_judul . ' • Diselesaikan pada ' . ($simulation->submitted_at?->format('d M Y, H:i') ?? '-'))

@section('content')
@if(session('success'))
    <div class="note note-ok" style="margin-bottom:16px"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
@endif

<div style="margin-bottom:16px">
    <a href="{{ route('ortu.tka-riwayat.index', ['siswa_id' => $simulation->siswa_id]) }}" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Kembali ke Riwayat Simulasi Anak
    </a>
</div>

<div class="grid grid-4" style="margin-bottom:20px">
    <div class="stat-card primary">
        <i class="fas fa-award stat-icon"></i>
        <div class="stat-label">Skor Simulasi</div>
        <div class="stat-value">{{ number_format((float) $simulation->skor_akhir, 1) }}</div>
        <div class="stat-change">Level: {{ $simulation->level_capaian ?? '-' }}</div>
    </div>
    <div class="stat-card ok">
        <i class="fas fa-check-circle stat-icon"></i>
        <div class="stat-label">Jawaban Tepat</div>
        <div class="stat-value">{{ $simulation->benar }} / {{ $simulation->total_soal }}</div>
        <div class="stat-change">Mode {{ strtoupper($simulation->mode_simulasi) }}</div>
    </div>
    <div class="stat-card gold">
        <i class="fas fa-clock stat-icon"></i>
        <div class="stat-label">Waktu Pengerjaan</div>
        <div class="stat-value" style="font-size:20px">{{ $simulation->durasiLabel() }}</div>
        <div class="stat-change">Durasi aktif pengerjaan</div>
    </div>
    <div class="stat-card primary">
        <i class="fas fa-layer-group stat-icon"></i>
        <div class="stat-label">Tingkat Kesulitan</div>
        <div class="stat-value" style="font-size:18px">{{ ucfirst($simulation->tingkat_awal) }} → {{ ucfirst($simulation->tingkat_akhir) }}</div>
        <div class="stat-change">Adaptasi butir otomatis</div>
    </div>
</div>

{{-- Rincian Jejak Pengerjaan --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-header">
        <h3><i class="fas fa-list-check" style="color:var(--navy);margin-right:8px"></i>Rincian Butir, Tingkat Kesulitan & Waktu per Soal</h3>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Indikator Kognitif</th>
                        <th style="text-align:center">Tingkat Kesulitan</th>
                        <th style="text-align:center">Waktu Jawab</th>
                        <th style="text-align:center">Hasil</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(($simulation->jejak_adaptif ?? []) as $step)
                        <tr>
                            <td style="font-weight:700">{{ $step['step'] ?? $loop->iteration }}</td>
                            <td>
                                <div style="font-weight:700">{{ $step['indikator'] ?? '-' }}</div>
                                <small style="color:var(--muted)">{{ $step['sub_indikator'] ?? '' }}</small>
                            </td>
                            <td style="text-align:center">
                                <span class="tag {{ ($step['tingkat'] ?? '') === 'sulit' ? 'tag-bad' : (($step['tingkat'] ?? '') === 'mudah' ? 'tag-ok' : 'tag-primary') }}">
                                    {{ ucfirst((string) ($step['tingkat'] ?? 'sedang')) }}
                                </span>
                            </td>
                            <td style="text-align:center">{{ (int) ($step['durasi_detik'] ?? 0) }} detik</td>
                            <td style="text-align:center">
                                @if(!empty($step['benar']))
                                    <span class="tag tag-ok">Tepat</span>
                                @else
                                    <span class="tag tag-bad">Perlu Penguatan</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Form Konfirmasi & Catatan Pendampingan Orang Tua --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-comment-dots" style="color:var(--navy);margin-right:8px"></i>Konfirmasi Pemantauan & Catatan Orang Tua/Wali</h3>
        @if($simulation->dibaca_ortu_at)
            <span class="tag tag-ok">Telah dikonfirmasi {{ $simulation->dibaca_ortu_at->format('d M Y H:i') }}</span>
        @endif
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('ortu.tka-riwayat.konfirmasi', $simulation) }}">
            @csrf
            <div style="margin-bottom:12px">
                <label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px">Catatan / Apresiasi Pendampingan di Rumah (Opsional)</label>
                <textarea name="catatan_ortu" rows="2" class="form-control" placeholder="Contoh: Alhamdulillah ananda sudah berlatih rutin 20 menit malam ini...">{{ old('catatan_ortu', $simulation->catatan_ortu) }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-check-circle"></i> Tandai Sudah Dibaca & Simpan Catatan Wali
            </button>
        </form>
    </div>
</div>
@endsection
