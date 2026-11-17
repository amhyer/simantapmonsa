@extends('layouts.app')

@section('title', 'Buat Perangkat Ajar - SIMANTAP')
@section('page_title', 'Buat Perangkat Ajar Baru')
@section('page_subtitle', 'CP, TP, ATP, Prota, Promes, Modul Ajar (Pembelajaran Mendalam), atau KKM/KKTP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('header_actions')
    <a href="{{ route('guru.perangkat.index') }}" class="btn btn-ghost btn-sm">← Kembali</a>
@endsection

@section('content')
    <form method="POST" action="{{ route('guru.perangkat.store') }}" enctype="multipart/form-data">
        @csrf
        @include('guru.perangkat._form')
    </form>
@endsection
