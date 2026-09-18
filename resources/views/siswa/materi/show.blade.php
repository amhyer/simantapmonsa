@extends('layouts.app')

@section('title', $materi->judul . ' - SIMANTAP')

@section('sidebar')
    @include('siswa.partials.sidebar')
@endsection

@section('page_title', $materi->judul)
@section('page_subtitle', $materi->mata_pelajaran . ' · Kelas ' . $materi->kelas)

@section('header_actions')
    <a href="{{ route('siswa.materi.index') }}" class="btn btn-ghost btn-sm">← Kembali</a>
@endsection

@section('content')
    @php
        $dimensiList = ['Keimanan dan Ketakwaan', 'Kewargaan', 'Penalaran Kritis', 'Kreativitas', 'Kolaborasi', 'Kemandirian', 'Kesehatan', 'Komunikasi'];
        $pmLabels = ['berkesadaran' => 'Berkesadaran', 'bermakna' => 'Bermakna', 'menggembirakan' => 'Menggembirakan'];
    @endphp

    <div class="card" style="margin-bottom:16px">
        <div class="card-body">
            <div style="font-size:12.5px;color:#667085;margin-bottom:12px">
                {{ \Carbon\Carbon::parse($materi->tanggal)->translatedFormat('l, d F Y') }} · Oleh {{ $materi->nama_guru }}
            </div>

            @if($materi->pm)
                <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px">
                    @foreach($pmLabels as $key => $label)
                        @if($materi->pm[$key] ?? false)
                            <span class="tag tag-gold">{{ $label }}</span>
                        @endif
                    @endforeach
                </div>
            @endif

            @if($materi->dimensi)
                <div style="margin-bottom:14px">
                    <div style="font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085;font-weight:700;margin-bottom:6px">Dimensi Profil Lulusan</div>
                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                        @foreach($materi->dimensi as $d)
                            <span class="tag tag-gold">{{ $dimensiList[$d] ?? $d }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="note" style="margin-bottom:16px">
                <b>Tujuan Pembelajaran</b><br>{{ $materi->tujuan_pembelajaran }}
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3><i class="fas fa-book" style="margin-right:8px;color:var(--navy)"></i>Materi Pokok</h3>
        </div>
        <div class="card-body">
            <div style="font-size:15px;line-height:1.8;white-space:pre-wrap">{{ $materi->materi_pokok }}</div>
        </div>
    </div>

    @if($materi->kegiatan)
        <div class="card" style="margin-bottom:16px">
            <div class="card-header">
                <h3><i class="fas fa-tasks" style="margin-right:8px;color:var(--gold)"></i>Kegiatan</h3>
            </div>
            <div class="card-body">
                <div style="font-size:14px;line-height:1.7;white-space:pre-wrap">{{ $materi->kegiatan }}</div>
            </div>
        </div>
    @endif

    @if($materi->pengalaman)
        <div class="card" style="margin-bottom:16px">
            <div class="card-header">
                <h3><i class="fas fa-star" style="margin-right:8px;color:var(--gold)"></i>Pengalaman Belajar</h3>
            </div>
            <div class="card-body">
                @if(is_array($materi->pengalaman))
                    <ul style="margin:0;padding-left:20px">
                        @foreach($materi->pengalaman as $item)
                            <li style="margin-bottom:6px">{{ $item }}</li>
                        @endforeach
                    </ul>
                @else
                    <div style="font-size:14px;line-height:1.7;white-space:pre-wrap">{{ $materi->pengalaman }}</div>
                @endif
            </div>
        </div>
    @endif

    @if($materi->tautan_sumber)
        <div style="margin-bottom:16px">
            <a href="{{ $materi->tautan_sumber }}" target="_blank" class="btn btn-gold btn-sm">
                <i class="fas fa-external-link-alt"></i> Buka Sumber
            </a>
        </div>
    @endif
@endsection
