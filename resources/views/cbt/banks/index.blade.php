@extends('layouts.app')
@section('title', 'Bank Soal CBT - SIMANTAP')
@section('sidebar')
    @include('guru.partials.sidebar')
@endsection
@section('page_title', 'Bank Soal CBT')
@section('page_subtitle', 'Computer Based Test • Pilihan ganda')
@section('content')
@include('cbt.feedback')
<div class="cbt-actions"><a class="btn btn-primary" href="{{ route('cbt.question-banks.create') }}">+ Bank soal</a><a class="btn" href="{{ route('cbt.sessions.index') }}">Sesi ujian →</a></div>
<div class="card"><div class="card-body"><table class="cbt-list"><thead><tr><th>Bank soal</th><th>Mata pelajaran</th><th>Soal</th><th>Aksi</th></tr></thead><tbody>
@forelse($banks as $bank)
<tr><td>{{ $bank->title }}</td><td>{{ $bank->subject }}</td><td>{{ $bank->questions_count }}</td><td><a href="{{ route('cbt.question-banks.show', $bank) }}">Kelola soal</a></td></tr>
@empty<tr><td colspan="4">Belum ada bank soal. Buat bank pertama untuk mulai.</td></tr>@endforelse
</tbody></table>{{ $banks->links() }}</div></div>
@endsection
