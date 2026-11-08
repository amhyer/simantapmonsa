@extends('layouts.app')

@section('title', 'Profil Kognitif ' . $siswa->nama_peserta_didik . ' - Guru')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Analisis Kognitif & Personalisasi: ' . $siswa->nama_peserta_didik)
@section('page_subtitle', 'Kelas ' . ($siswa->kelas ?? '-') . ' • NIS: ' . ($siswa->nis ?? '-') . ' • Pemetaan Indikator, Tren & Paket Tindak Lanjut')

@section('content')
@if(session('success'))
    <div class="note note-ok" style="margin-bottom:16px"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="note note-warn" style="margin-bottom:16px"><i class="fas fa-exclamation-circle"></i> {{ session('error') }}</div>
@endif

<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px">
    <a href="{{ route('guru.tka.kognitif.index', ['kelas' => $siswa->kelas]) }}" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Kembali ke Rekap Kelas
    </a>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <form method="POST" action="{{ route('guru.tka.rekomendasi.generate') }}">
            @csrf
            <input type="hidden" name="siswa_uuid" value="{{ $siswa->uuid }}">
            <button type="submit" class="btn btn-gold btn-sm">
                <i class="fas fa-magic"></i> {{ $rekomendasi ? 'Perbarui Paket Otomatis' : 'Susun Paket Rekomendasi' }}
            </button>
        </form>
        <a href="{{ route('guru.tka.integrasi-erapor.index', ['kelas' => $siswa->kelas]) }}" class="btn btn-primary btn-sm">
            <i class="fas fa-sync-alt"></i> Sinkronkan ke e-Rapor
        </a>
    </div>
</div>

@include('tka.partials.student-cognitive-detail', [
    'profile' => $profile,
    'rekomendasi' => $rekomendasi,
    'showStudentLaunchButton' => false,
])

{{-- Form Pemantauan Guru atas Paket Tindak Lanjut --}}
@if($rekomendasi)
    <div class="card" style="margin-bottom:20px">
        <div class="card-header">
            <h3><i class="fas fa-check-double" style="color:var(--navy);margin-right:8px"></i>Pemantauan Pelaksanaan Paket Tindak Lanjut</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('guru.tka.rekomendasi.update', $rekomendasi) }}" class="grid grid-3" style="align-items:end;gap:14px">
                @csrf
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px">Status Tindak Lanjut</label>
                    <select name="status_tindak_lanjut" class="form-control">
                        <option value="direkomendasikan" @selected($rekomendasi->status_tindak_lanjut === 'direkomendasikan')>Direkomendasikan</option>
                        <option value="berjalan" @selected($rekomendasi->status_tindak_lanjut === 'berjalan')>Sedang Berjalan</option>
                        <option value="tuntas" @selected($rekomendasi->status_tindak_lanjut === 'tuntas')>Tuntas Dilaksanakan</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px">Catatan Pendampingan Guru</label>
                    <input type="text" name="catatan_guru" value="{{ old('catatan_guru', $rekomendasi->catatan_guru) }}" class="form-control" placeholder="Catatan progres latihan siswa...">
                </div>
                <div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Pemantauan</button>
                </div>
            </form>
        </div>
    </div>
@endif

{{-- Riwayat Simulasi Adaptif & Mandiri Siswa --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-laptop-code" style="color:var(--navy);margin-right:8px"></i>Riwayat Simulasi Adaptif & Mandiri Siswa</h3>
        <span class="tag tag-secondary">{{ $riwayatSimulasi->count() }} Sesi</span>
    </div>
    <div class="card-body" style="padding:0">
        @if($riwayatSimulasi->isEmpty())
            <div style="padding:28px 20px;text-align:center;color:var(--muted);font-size:13px">
                Siswa ini belum mengerjakan sesi Simulasi Adaptif maupun Simulasi Mandiri TKA.
            </div>
        @else
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Paket Simulasi</th>
                            <th style="text-align:center">Mode & Kesulitan</th>
                            <th style="text-align:center">Waktu Pengerjaan</th>
                            <th style="text-align:center">Skor & Level</th>
                            <th style="text-align:center">Status Wali/Ortu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($riwayatSimulasi as $sim)
                            <tr>
                                <td style="font-size:12.5px">{{ $sim->submitted_at?->format('d M Y H:i') ?? '-' }}</td>
                                <td style="font-weight:700">{{ $sim->paket_judul }}</td>
                                <td style="text-align:center">
                                    <span class="tag tag-primary">{{ strtoupper($sim->mode_simulasi) }}</span>
                                    <div style="font-size:11px;color:var(--muted);margin-top:2px">
                                        {{ ucfirst($sim->tingkat_awal) }} → {{ ucfirst($sim->tingkat_akhir) }}
                                    </div>
                                </td>
                                <td style="text-align:center;font-weight:700">{{ $sim->durasiLabel() }}</td>
                                <td style="text-align:center">
                                    <strong style="font-size:14px">{{ number_format((float) $sim->skor_akhir, 1) }}</strong>
                                    <div><span class="tag tag-ok" style="font-size:10.5px">{{ $sim->level_capaian ?? '-' }}</span></div>
                                </td>
                                <td style="text-align:center">
                                    @if($sim->dibaca_ortu_at)
                                        <span class="tag tag-ok">Dibaca {{ $sim->dibaca_ortu_at->format('d/m') }}</span>
                                    @else
                                        <span class="tag tag-secondary">Belum dibaca</span>
                                    @endif
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
