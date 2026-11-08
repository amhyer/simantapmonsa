@extends('layouts.app')
@section('title', 'Profil Kognitif & Latihan Personalisasi TKA - Siswa')

@section('sidebar')
    @include('siswa.partials.sidebar')
@endsection

@section('page_title', 'Profil Kognitif & Rekomendasi Belajar Personalisasi')
@section('page_subtitle', 'Pantau Perkembangan Indikator TKA, Zona Kemampuan, dan Paket Latihan Terarahmu')

@section('content')
@if(!$siswa || !$profile)
    <div class="note note-warn">
        <i class="fas fa-exclamation-triangle"></i> Akun Anda belum terhubung dengan data peserta didik aktif. Silakan hubungi wali kelas atau operator sekolah.
    </div>
@else
    <div style="display:flex;justify-content:flex-end;margin-bottom:16px">
        <a href="{{ route('siswa.tka-simulasi.index') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-laptop-code"></i> Pilih Paket Simulasi Adaptif & Mandiri
        </a>
    </div>

    @include('tka.partials.student-cognitive-detail', [
        'profile' => $profile,
        'rekomendasi' => $rekomendasi,
        'showStudentLaunchButton' => true,
    ])

    {{-- Riwayat Simulasi Siswa --}}
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-history" style="color:var(--violet);margin-right:8px"></i>Riwayat Simulasi TKA Saya</h3>
            <span class="tag tag-secondary">{{ $riwayatSimulasi->count() }} Sesi</span>
        </div>
        <div class="card-body" style="padding:0">
            @if($riwayatSimulasi->isEmpty())
                <div style="padding:28px 20px;text-align:center;color:var(--muted);font-size:13px">
                    Kamu belum memiliki riwayat simulasi tersimpan. Yuk mulai sesi Simulasi Adaptif pertamamu!
                </div>
            @else
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Paket Simulasi</th>
                                <th style="text-align:center">Mode & Kesulitan</th>
                                <th style="text-align:center">Waktu</th>
                                <th style="text-align:center">Skor</th>
                                <th style="text-align:right">Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($riwayatSimulasi as $sim)
                                <tr>
                                    <td style="font-size:12.5px">{{ $sim->submitted_at?->format('d M Y H:i') ?? '-' }}</td>
                                    <td style="font-weight:700">{{ $sim->paket_judul }}</td>
                                    <td style="text-align:center">
                                        <span class="tag tag-violet">{{ strtoupper($sim->mode_simulasi) }}</span>
                                        <div style="font-size:11px;color:var(--muted)">{{ ucfirst($sim->tingkat_awal) }} → {{ ucfirst($sim->tingkat_akhir) }}</div>
                                    </td>
                                    <td style="text-align:center">{{ $sim->durasiLabel() }}</td>
                                    <td style="text-align:center;font-weight:800">{{ number_format((float) $sim->skor_akhir, 1) }} ({{ $sim->level_capaian }})</td>
                                    <td style="text-align:right">
                                        <a href="{{ route('siswa.tka-simulasi.adaptif.result', $sim) }}" class="btn btn-outline btn-sm">Pembahasan</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endif
@endsection
