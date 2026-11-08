@extends('layouts.app')

@section('title', 'Simulasi Adaptif & Perkembangan Kognitif TKA Anak - Orang Tua')

@section('sidebar')
    @include('ortu.partials.sidebar')
@endsection

@section('page_title', 'Laporan Simulasi Adaptif & Profil Kognitif TKA Anak')
@section('page_subtitle', 'Pantau Riwayat Simulasi, Waktu Pengerjaan, Tingkat Kesulitan, serta Panduan Pendampingan di Rumah')

@section('content')
    @include('ortu.partials.pilih-anak', ['route' => 'ortu.tka-riwayat.index', 'terpilih' => $anak, 'anakList' => $anakList])

    @if(session('success'))
        <div class="note note-ok" style="margin-bottom:16px"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
    @endif

    @if(!$anak || !$profile)
        <div class="note note-warn">
            <i class="fas fa-exclamation-triangle"></i> Akun Anda belum terhubung dengan data peserta didik aktif. Silakan hubungi pihak sekolah.
        </div>
    @else
        @include('tka.partials.student-cognitive-detail', [
            'profile' => $profile,
            'rekomendasi' => $rekomendasi,
            'showStudentLaunchButton' => false,
        ])

        {{-- Tabel Riwayat Simulasi TKA yang Dilaporkan ke Wali/Orang Tua --}}
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-clipboard-list" style="color:var(--navy);margin-right:8px"></i>Riwayat Simulasi TKA Ananda {{ $anak->nama_peserta_didik }}</h3>
                <span class="tag tag-secondary">Laporan Real-Time ke Wali</span>
            </div>
            <div class="card-body" style="padding:0">
                @if($riwayatSimulasi->isEmpty())
                    <div style="padding:32px 20px;text-align:center;color:var(--muted);font-size:13.5px">
                        Ananda belum menyelesaikan sesi simulasi TKA. Hasil simulasi akan otomatis tampil di halaman ini segera setelah ananda menyelesaikannya.
                    </div>
                @else
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Tanggal Selesai</th>
                                    <th>Subtes Simulasi</th>
                                    <th style="text-align:center">Tingkat Kesulitan</th>
                                    <th style="text-align:center">Waktu Pengerjaan</th>
                                    <th style="text-align:center">Hasil & Level</th>
                                    <th style="text-align:center">Konfirmasi Wali</th>
                                    <th style="text-align:right">Laporan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($riwayatSimulasi as $sim)
                                    <tr>
                                        <td style="font-size:12.5px">{{ $sim->submitted_at?->format('d M Y, H:i') ?? '-' }}</td>
                                        <td>
                                            <div style="font-weight:700;color:var(--navy)">{{ $sim->paket_judul }}</div>
                                            <small style="color:var(--muted)">Mode: {{ strtoupper($sim->mode_simulasi) }} ({{ $sim->benar }}/{{ $sim->total_soal }} benar)</small>
                                        </td>
                                        <td style="text-align:center">
                                            <span class="tag tag-primary">{{ ucfirst($sim->tingkat_awal) }} → {{ ucfirst($sim->tingkat_akhir) }}</span>
                                        </td>
                                        <td style="text-align:center;font-weight:700">{{ $sim->durasiLabel() }}</td>
                                        <td style="text-align:center">
                                            <div style="font-weight:800;font-size:14.5px">{{ number_format((float) $sim->skor_akhir, 1) }}</div>
                                            <span class="tag {{ $sim->skor_akhir >= 70 ? 'tag-ok' : 'tag-gold' }}" style="font-size:10.5px">{{ $sim->level_capaian ?? '-' }}</span>
                                        </td>
                                        <td style="text-align:center">
                                            @if($sim->dibaca_ortu_at)
                                                <span class="tag tag-ok"><i class="fas fa-check"></i> Sudah Dibaca</span>
                                            @else
                                                <span class="tag tag-gold">Baru</span>
                                            @endif
                                        </td>
                                        <td style="text-align:right">
                                            <a href="{{ route('ortu.tka-riwayat.show', $sim) }}" class="btn btn-primary btn-sm">
                                                Lihat Detail
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div style="padding:14px 20px">
                        {{ $riwayatSimulasi->links() }}
                    </div>
                @endif
            </div>
        </div>
    @endif
@endsection
