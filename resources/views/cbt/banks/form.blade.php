@extends('layouts.app')
@section('title', 'Identitas Bank Soal - SIMANTAP')
@section('sidebar')
    @include('guru.partials.sidebar')
@endsection
@section('page_title', 'Identitas Bank Soal')
@section('page_subtitle', 'Computer Based Test • Pilihan ganda')
@section('content')
@include('cbt.feedback')
<form class="card cbt-form" method="POST" action="{{ $bank->exists ? route('cbt.question-banks.update', $bank) : route('cbt.question-banks.store') }}"><div class="card-body">
@csrf @if($bank->exists) @method('PUT') @endif
<label for="title">Nama bank soal</label><input id="title" name="title" maxlength="200" required value="{{ old('title', $bank->title) }}">
<label for="subject">Mata pelajaran</label><input id="subject" name="subject" maxlength="100" required value="{{ old('subject', $bank->subject) }}">
<div class="cbt-actions"><button class="btn btn-primary">Simpan</button><a class="btn" href="{{ route('cbt.question-banks.index') }}">Kembali</a></div>
</div></form>
@endsection
