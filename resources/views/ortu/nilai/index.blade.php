@extends('layouts.app')
@section('title', 'Perkembangan Nilai - SIMANTAP')
@section('sidebar')
    @include('ortu.partials.sidebar')
@endsection
@section('page_title', 'Perkembangan Nilai')
@section('content')
    @if(!$siswa)
        <div class="card"><div class="card-body"><div class="empty"><b>Belum ada data</b></div></div></div>
    @else
        @php $nilaiService = app(\App\Services\NilaiService::class); $na = $nilaiService->hitungNilaiAkhir($siswa->id); @endphp
        <div class="card">
            <div class="card-header"><h3>Nilai {{ $siswa->nama_peserta_didik }}</h3></div>
            <div class="card-body">
                <div class="stat" style="max-width:300px"><div class="label">Nilai Akhir</div><div class="value">{{ $na['nilai_akhir'] > 0 ? number_format($na['nilai_akhir'], 1) : '—' }}</div></div>
            </div>
        </div>
    @endif
@endsection
