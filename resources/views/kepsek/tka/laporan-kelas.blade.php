@extends('layouts.app')

@section('title', 'Heatmap & Laporan Kognitif TKA - Kepala Sekolah')

@section('sidebar')
    @include('kepsek.partials.sidebar')
@endsection

@section('page_title', 'Laporan Sekolah per Kelas/Jenjang & Heatmap TKA')
@section('page_subtitle', 'Pemantauan Eksekutif Distribusi Indikator TKA dan Kesenjangan Capaian Antar-Rombel')

@section('content')
    @include('tka.partials.heatmap-matrix', [
        'heatmap' => $heatmap,
        'filterRoute' => 'kepsek.tka-laporan.index',
        'exportRoute' => 'kepsek.tka-laporan.export',
    ])
@endsection
