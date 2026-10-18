@extends('layouts.app')
@section('title', 'Buat Sesi Ujian - SIMANTAP')
@section('sidebar')
    @include('guru.partials.sidebar')
@endsection
@section('page_title', 'Buat Sesi Ujian')
@section('page_subtitle', 'Computer Based Test • Pilihan ganda')
@section('content')
@include('cbt.feedback')
<form class="card cbt-form" method="POST" action="{{ route('cbt.sessions.store') }}"><div class="card-body">@csrf
<label for="title">Judul ujian</label><input id="title" name="title" maxlength="200" required value="{{ old('title') }}">
<label for="bank">Bank soal</label><select id="bank" name="bank_id" required><option value="">Pilih bank</option>@foreach($banks as $bank)<option value="{{ $bank->id }}" @selected(old('bank_id') == $bank->id)>{{ $bank->title }} — {{ $bank->subject }}</option>@endforeach</select>
<label for="kelas">Kelas peserta</label><select id="kelas" name="kelas" required><option value="">Pilih kelas</option>@foreach($classes as $kelas)<option @selected(old('kelas') === $kelas)>{{ $kelas }}</option>@endforeach</select>
<p>Seluruh siswa aktif kelas ini dibekukan sebagai daftar peserta saat publikasi. Maksimal 500 peserta.</p>
<label for="opens">Buka pada ({{ config('app.timezone') }})</label><input id="opens" type="datetime-local" name="opens_at" required value="{{ old('opens_at') }}">
<label for="closes">Tutup pada ({{ config('app.timezone') }})</label><input id="closes" type="datetime-local" name="closes_at" required value="{{ old('closes_at') }}">
<label for="duration">Durasi per siswa (menit, 1–300)</label><input id="duration" type="number" name="duration_minutes" min="1" max="300" required value="{{ old('duration_minutes', 60) }}">
<p class="cbt-note">Deadline siswa adalah yang lebih awal: waktu mulai + durasi atau waktu tutup sesi.</p>
<div class="cbt-actions"><button class="btn btn-primary">Buat draf</button><a class="btn" href="{{ route('cbt.sessions.index') }}">Batal</a></div></div></form>
@endsection
