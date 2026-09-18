@extends('layouts.app')

@section('title', 'Materi Pembelajaran - SIMANTAP')

@section('sidebar')
    @include('siswa.partials.sidebar')
@endsection

@section('page_title', 'Materi Pembelajaran')
@section('page_subtitle', 'Daftar materi dari guru Anda')

@section('content')
    @if(!$siswa)
        <div class="note note-warn">
            <i class="fas fa-exclamation-triangle"></i> Akun Anda belum terhubung dengan data siswa. Hubungi guru.
        </div>
    @else

    @if($materi->count())
        @php
            $dimensiList = ['Keimanan dan Ketakwaan', 'Kewargaan', 'Penalaran Kritis', 'Kreativitas', 'Kolaborasi', 'Kemandirian', 'Kesehatan', 'Komunikasi'];
            $pmLabels = ['berkesadaran' => 'Berkesadaran', 'bermakna' => 'Bermakna', 'menggembirakan' => 'Menggembirakan'];
        @endphp

        @foreach($materi as $m)
            <div class="card" style="margin-bottom:16px">
                <div class="card-header">
                    <h3>{{ $m->judul }}</h3>
                    <span class="tag tag-gold">{{ $m->mata_pelajaran }}</span>
                </div>
                <div class="card-body">
                    <div style="font-size:12.5px;color:#667085;margin-bottom:10px">
                        {{ \Carbon\Carbon::parse($m->tanggal)->translatedFormat('l, d F Y') }} · Kelas {{ $m->kelas }}
                    </div>

                    @if($m->pm)
                        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px">
                            @foreach($pmLabels as $key => $label)
                                @if($m->pm[$key] ?? false)
                                    <span class="tag tag-gold">{{ $label }}</span>
                                @endif
                            @endforeach
                        </div>
                    @endif

                    @if($m->dimensi)
                        <div style="margin-bottom:12px">
                            <div style="font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085;font-weight:700;margin-bottom:6px">Dimensi Profil Lulusan</div>
                            <div style="display:flex;gap:6px;flex-wrap:wrap">
                                @foreach($m->dimensi as $d)
                                    <span class="tag tag-gold">{{ $dimensiList[$d] ?? $d }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="note" style="margin-bottom:12px">
                        <b>Tujuan pembelajaran</b><br>{{ Str::limit($m->tujuan_pembelajaran, 200) }}
                    </div>

                    <div style="font-size:14px;color:#344054;max-height:100px;overflow:hidden;position:relative">
                        {{ Str::limit($m->materi_pokok, 200) }}
                    </div>

                    <div style="margin-top:14px">
                        <a href="{{ route('siswa.materi.show', $m->id) }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-book-open"></i> Baca Materi
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    @else
        <div class="card">
            <div class="card-body">
                <div class="empty">
                    <div class="icon">📚</div>
                    <b>Belum ada materi</b>
                    <div>Guru Anda belum mengunggah materi pembelajaran.</div>
                </div>
            </div>
        </div>
    @endif
    @endif
@endsection
