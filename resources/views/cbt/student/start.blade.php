@extends('layouts.app')
@section('title', 'Petunjuk Ujian CBT - SIMANTAP')
@section('sidebar')
    @include('siswa.partials.sidebar')
@endsection
@section('page_title', 'Petunjuk Ujian CBT')
@section('page_subtitle', 'Computer Based Test • Pilihan ganda')
@section('content')
@include('cbt.feedback')
<div class="card"><div class="card-body"><h2>{{ $session->title }}</h2>
<ul><li>{{ count($session->question_snapshot ?? []) }} soal pilihan ganda, satu percobaan.</li><li>Durasi {{ $session->duration_minutes }} menit sejak menekan Mulai.</li><li>Waktu tutup {{ $session->closes_at->format('d/m/Y H:i') }} ({{ config('app.timezone') }}). Jadwal dan deadline ditentukan server.</li><li>Jawaban disimpan setiap pilihan berubah. Pastikan indikator tersimpan sebelum meninggalkan halaman.</li><li>Jika internet terputus, jangan tutup halaman; sambungkan lagi sebelum deadline dan kirim ulang.</li><li>Perpindahan tab dapat dicatat sebagai indikasi, tidak otomatis mengurangi nilai.</li></ul>
@if($session->status === 'running' && now()->greaterThanOrEqualTo($session->opens_at) && now()->lessThan($session->closes_at))
<form method="POST" action="{{ route('cbt.student.begin', $session) }}">@csrf<button class="btn btn-primary">Mulai ujian sekarang</button></form>
@else<p class="cbt-note">Sesi belum dibuka, sudah ditutup, atau di luar jadwal. Hubungi guru.</p>@endif
</div></div>
@endsection
