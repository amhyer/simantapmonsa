@extends('layouts.app')
@section('title', 'Sesi Ujian CBT - SIMANTAP')
@section('sidebar')
    @include('guru.partials.sidebar')
@endsection
@section('page_title', 'Sesi Ujian CBT')
@section('page_subtitle', 'Computer Based Test • Pilihan ganda')
@section('content')
@include('cbt.feedback')
<div class="cbt-actions"><a class="btn btn-primary" href="{{ route('cbt.sessions.create') }}">+ Sesi ujian</a><a class="btn" href="{{ route('cbt.question-banks.index') }}">Bank soal</a></div>
<div class="card"><div class="card-body"><table class="cbt-list"><thead><tr><th>Ujian</th><th>Kelas</th><th>Status</th><th>Mulai</th><th>Peserta mulai</th></tr></thead><tbody>
@forelse($sessions as $session)<tr><td><a href="{{ route('cbt.sessions.show', $session) }}">{{ $session->title }}</a></td><td>{{ $session->kelas }}</td><td>{{ $session->status }}</td><td>{{ $session->opens_at->format('d/m/Y H:i') }}</td><td>{{ $session->results_count }}</td></tr>
@empty<tr><td colspan="5">Belum ada sesi ujian.</td></tr>@endforelse
</tbody></table>{{ $sessions->links() }}</div></div>
@endsection
