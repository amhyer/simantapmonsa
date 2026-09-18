@extends('layouts.app')

@section('title', 'Detail Dimensi - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Detail Dimensi Profil Lulusan')
@section('page_subtitle', $dimensi->dimensi ?? '-')

@section('content')
<div class="card" style="margin-bottom:16px">
    <div class="card-header"><h3>📋 Detail Dimensi</h3><a href="{{ route('guru.tka.index') }}" class="btn btn-ghost btn-sm">← Kembali</a></div>
    <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div>
                <div style="font-size:12px;color:#888;margin-bottom:2px">Dimensi</div>
                <div style="font-weight:700;font-size:16px">{{ $dimensi->dimensi }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:#888;margin-bottom:2px">Siswa</div>
                <div style="font-weight:700;font-size:16px">{{ $dimensi->nama_siswa ?? $dimensi->siswa?->nama_peserta_didik ?? '-' }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:#888;margin-bottom:2px">Skor</div>
                <div style="font-weight:700;font-size:24px;color:#1F3864">{{ $dimensi->skor }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:#888;margin-bottom:2px">Predikat</div>
                <div style="font-weight:700;font-size:16px">{{ $dimensi->predikat ?? '-' }}</div>
            </div>
        </div>
        @if($dimensi->catatan)
            <div style="margin-top:16px">
                <div style="font-size:12px;color:#888;margin-bottom:4px">Catatan</div>
                <div style="background:#F9FAFB;border:1px solid #E4E7EC;border-radius:8px;padding:12px;font-size:13px">{{ $dimensi->catatan }}</div>
            </div>
        @endif
    </div>
</div>
@endsection
