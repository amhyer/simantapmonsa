@extends('layouts.app')

@section('title', $materi->judul . ' - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', $materi->judul)
@section('page_subtitle', $materi->mata_pelajaran . ' · Kelas ' . $materi->kelas)

@section('header_actions')
    <a href="{{ route('guru.materi.index') }}" class="btn btn-ghost btn-sm">← Kembali</a>
    <a href="{{ route('guru.materi.edit', $materi) }}" class="btn btn-ghost btn-sm">Ubah</a>
@endsection

@section('content')
    <div class="card" style="margin-bottom:16px">
        <div class="card-body">
            <div style="font-size:12.5px;color:#667085;margin-bottom:12px">
                {{ \Carbon\Carbon::parse($materi->tanggal)->translatedFormat('l, d F Y') }} · Oleh {{ $materi->nama_guru }}
            </div>
            <div class="note" style="margin-bottom:16px">
                <b>Tujuan Pembelajaran</b><br>{{ $materi->tujuan_pembelajaran }}
            </div>
            <div style="font-size:15px;line-height:1.7;white-space:pre-wrap">{{ $materi->materi_pokok }}</div>
            @if($materi->tautan_sumber)
                <div style="margin-top:16px">
                    <a href="{{ $materi->tautan_sumber }}" target="_blank" class="btn btn-gold btn-sm">🔗 Buka Sumber</a>
                </div>
            @endif
        </div>
    </div>
@endsection
