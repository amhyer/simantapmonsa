@extends('layouts.admin-with-tabs')

@section('title', 'Laporan Kelas & Heatmap TKA - SIMANTAP_MONSA')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Laporan Sekolah per Kelas/Jenjang & Heatmap TKA')
@section('page_subtitle', 'Distribusi Capaian Indikator Kognitif per Kelas serta Pemetaan Kesenjangan terhadap Target Sekolah')

@section('header_actions')
    <a href="{{ route('admin.tka.kognitif.index') }}" class="btn btn-outline btn-sm">
        <i class="fas fa-user-graduate"></i> Rekap Kognitif per Siswa
    </a>
@endsection

@section('content')
    @include('tka.partials.heatmap-matrix', [
        'heatmap' => $heatmap,
        'filterRoute' => 'admin.tka.laporan-kelas.index',
        'exportRoute' => 'admin.tka.laporan-kelas.export',
    ])
@endsection
