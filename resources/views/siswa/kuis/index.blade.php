@extends('layouts.app')

@section('title', 'Daftar Kuis - SIMANTAP')

@section('sidebar')
    @include('siswa.partials.sidebar')
@endsection

@section('page_title', 'Daftar Kuis')
@section('page_subtitle', 'Kerjakan kuis dari guru Anda')

@section('content')
    @if(!$siswa)
        <div class="note note-warn">
            <i class="fas fa-exclamation-triangle"></i> Akun Anda belum terhubung dengan data siswa. Hubungi guru.
        </div>
    @else

    <div class="grid grid-4" style="margin-bottom:20px">
        @php
            $totalKuis = $kuis->count();
            $sudahDikerjakan = $kuis->filter(function($k) use ($hasilKuis) {
                return $hasilKuis->contains('kuis_id', $k->id);
            })->count();
            $belumDikerjakan = $totalKuis - $sudahDikerjakan;
            $rataSkor = $hasilKuis->count() ? round($hasilKuis->avg('skor'), 1) : 0;
        @endphp
        <div class="stat-card primary">
            <i class="fas fa-clipboard-list stat-icon"></i>
            <div class="stat-label">Total Kuis</div>
            <div class="stat-value">{{ $totalKuis }}</div>
        </div>
        <div class="stat-card ok">
            <i class="fas fa-check-circle stat-icon"></i>
            <div class="stat-label">Dikerjakan</div>
            <div class="stat-value">{{ $sudahDikerjakan }}</div>
        </div>
        <div class="stat-card bad">
            <i class="fas fa-clock stat-icon"></i>
            <div class="stat-label">Belum Dikerjakan</div>
            <div class="stat-value">{{ $belumDikerjakan }}</div>
        </div>
        <div class="stat-card gold">
            <i class="fas fa-star stat-icon"></i>
            <div class="stat-label">Rata-rata Skor</div>
            <div class="stat-value">{{ $rataSkor }}</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Daftar Kuis</h3>
            <span class="tag tag-mut">{{ $totalKuis }} total</span>
        </div>
        <div class="card-body tight">
            @if($kuis->count())
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Judul</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Mata Pelajaran</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Soal</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Status</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Skor</th>
                                <th style="padding:11px 14px;text-align:right;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($kuis as $k)
                                @php
                                    $hasil = $hasilKuis->firstWhere('kuis_id', $k->id);
                                @endphp
                                <tr>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                        <b>{{ $k->judul }}</b>
                                        <div style="font-size:12px;color:#667085">
                                            {{ \Carbon\Carbon::parse($k->tanggal)->translatedFormat('d M Y') }}
                                            @if($k->batas_waktu)
                                                · Batas: {{ \Carbon\Carbon::parse($k->batas_waktu)->translatedFormat('d M Y H:i') }}
                                            @endif
                                        </div>
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                        <span class="tag tag-gold">{{ $k->mata_pelajaran }}</span>
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">
                                        {{ $k->jumlah_soal }}
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">
                                        @if($hasil)
                                            <span class="tag tag-ok">Sudah Dikerjakan</span>
                                        @else
                                            <span class="tag tag-warn">Belum Dikerjakan</span>
                                        @endif
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">
                                        @if($hasil)
                                            @if($hasil->tuntas)
                                                <span class="tag tag-ok">{{ $hasil->skor }}</span>
                                            @else
                                                <span class="tag tag-bad">{{ $hasil->skor }}</span>
                                            @endif
                                        @else
                                            <span class="tag tag-mut">—</span>
                                        @endif
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:right">
                                        @if($hasil)
                                            <span class="tag tag-mut">
                                                <i class="fas fa-check"></i> Selesai
                                            </span>
                                        @else
                                            <a href="{{ route('siswa.kuis.show', $k->id) }}" class="btn btn-primary btn-sm">
                                                <i class="fas fa-play"></i> Kerjakan
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty">
                    <div class="icon">📝</div>
                    <b>Belum ada kuis</b>
                    <div>Belum ada kuis aktif dari guru Anda.</div>
                </div>
            @endif
        </div>
    </div>
    @endif
@endsection
