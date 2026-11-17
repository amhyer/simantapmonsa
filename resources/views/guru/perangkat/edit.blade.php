@extends('layouts.app')

@section('title', 'Ubah Perangkat Ajar - SIMANTAP')
@section('page_title', 'Ubah Perangkat Ajar')
@section('page_subtitle', $perangkat->judul)

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('header_actions')
    <a href="{{ route('guru.perangkat.show', $perangkat) }}" class="btn btn-ghost btn-sm">← Kembali</a>
@endsection

@section('content')
    <form method="POST" action="{{ route('guru.perangkat.update', $perangkat) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('guru.perangkat._form')
    </form>
@endsection
