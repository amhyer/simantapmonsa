@extends('layouts.app')
@section('title', 'Detail Sesi CBT - SIMANTAP')
@section('sidebar')
    @include('guru.partials.sidebar')
@endsection
@section('page_title', 'Detail Sesi CBT')
@section('page_subtitle', 'Computer Based Test • Pilihan ganda')
@section('content')
@include('cbt.feedback')
<div class="card"><div class="card-body"><h2>{{ $session->title }}</h2>
<p>Kelas {{ $session->kelas }} • {{ $session->duration_minutes }} menit • Status: <strong>{{ $session->status }}</strong></p>
<p>{{ $session->opens_at->format('d/m/Y H:i') }} — {{ $session->closes_at->format('d/m/Y H:i') }} ({{ config('app.timezone') }})</p>
<p>Snapshot: {{ count($session->question_snapshot ?? []) }} soal • {{ count($session->participant_ids ?? []) }} peserta.</p>
<p>Urutan: draf → dipublikasikan → dibuka guru → selesai. Draf yang keliru boleh ditinggalkan dan dibuat ulang; jadwal/snapshot tidak dapat diubah setelah publikasi.</p>
<div class="cbt-actions">
@if($session->status === 'draft')<form method="POST" action="{{ route('cbt.sessions.publish', $session) }}">@csrf<button class="btn btn-primary">Publikasikan & bekukan peserta/soal</button></form>@endif
@if($session->status === 'published')<form method="POST" action="{{ route('cbt.sessions.start', $session) }}">@csrf<button class="btn btn-primary">Buka sesi</button></form>@endif
@if($session->status === 'running')<form method="POST" action="{{ route('cbt.sessions.complete', $session) }}" onsubmit="return confirm('Akhiri sesi? Semua jawaban tersimpan akan dinilai.')">@csrf<button class="btn">Akhiri sesi & nilai</button></form>@endif
<a class="btn" href="{{ route('cbt.sessions.monitoring', $session) }}">Monitoring & hasil</a><a class="btn" href="{{ route('cbt.sessions.index') }}">Daftar sesi</a>
</div></div></div>
@endsection
