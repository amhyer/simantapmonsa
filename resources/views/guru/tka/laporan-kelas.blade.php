@extends('layouts.app')

@section('title', 'Heatmap Kelas TKA - Guru')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Laporan Kelas & Heatmap Kesenjangan TKA')
@section('page_subtitle', 'Distribusi Indikator Kognitif Siswa Binaan per Kelas terhadap Target Kesiapan')

@section('content')
    <div style="display:flex;justify-content:flex-end;gap:8px;margin-bottom:16px;flex-wrap:wrap">
        <a href="{{ route('guru.tka.kognitif.index') }}" class="btn btn-outline btn-sm">
            <i class="fas fa-brain"></i> Rekap Kognitif & Rekomendasi
        </a>
        <a href="{{ route('guru.tka.integrasi-erapor.index') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-sync-alt"></i> Integrasi ke e-Rapor
        </a>
    </div>

    @include('tka.partials.heatmap-matrix', [
        'heatmap' => $heatmap,
        'filterRoute' => 'guru.tka.laporan-kelas.index',
        'exportRoute' => null,
    ])
@endsection
