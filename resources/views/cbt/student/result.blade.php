@extends('layouts.app')
@section('title', 'Hasil Ujian CBT - SIMANTAP')
@section('sidebar')
    @include('siswa.partials.sidebar')
@endsection
@section('page_title', 'Hasil Ujian CBT')
@section('page_subtitle', 'Computer Based Test • Pilihan ganda')
@section('content')
@include('cbt.feedback')
<div class="card"><div class="card-body"><h2>{{ $session->title }}</h2>
@if($result->submitted_at)
<p>Jawaban terkumpul: {{ $result->submitted_at->format('d/m/Y H:i:s') }}</p><h3>Nilai {{ number_format($result->score, 2) }}</h3><p>Benar {{ $result->correct_count }} dari {{ count($session->question_snapshot) }} soal.</p>
@else
<p class="cbt-note">Ujian belum difinalisasi. Setelah deadline, hanya jawaban yang sudah tersimpan yang dinilai.</p>
<form method="POST" action="{{ route('cbt.student.submit', $result) }}">@csrf<button class="btn btn-primary">Kumpulkan / finalisasi jawaban tersimpan</button></form>
<a href="{{ route('cbt.student.start', $session) }}">Lanjutkan ujian</a>
@endif
<div class="cbt-actions"><a class="btn" href="{{ route('cbt.student.index') }}">Daftar ujian</a></div></div></div>
@endsection
