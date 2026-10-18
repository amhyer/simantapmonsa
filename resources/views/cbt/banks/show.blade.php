@extends('layouts.app')
@section('title', 'Kelola Soal CBT - SIMANTAP')
@section('sidebar')
    @include('guru.partials.sidebar')
@endsection
@section('page_title', 'Kelola Soal CBT')
@section('page_subtitle', 'Computer Based Test • Pilihan ganda')
@section('content')
@include('cbt.feedback')
<h2>{{ $bank->title }}</h2><p>{{ $bank->subject }}</p>
<p class="cbt-note">Maksimal 200 soal. Sesi yang sudah dipublikasikan memakai snapshot: perubahan bank tidak mengubah ujian tersebut.</p>
<div class="cbt-actions"><a class="btn" href="{{ route('cbt.question-banks.edit', $bank) }}">Ubah identitas</a><a class="btn" href="{{ route('cbt.question-banks.index') }}">Semua bank</a></div>
<form method="POST" action="{{ route('cbt.question-banks.destroy', $bank) }}" onsubmit="return confirm('Hapus bank soal? Bank yang dipakai sesi tidak dapat dihapus.')">@csrf @method('DELETE')<button class="btn">Hapus bank</button></form>
<details class="card" style="margin:16px 0"><summary class="card-body">+ Tambah soal</summary>
<form class="card-body cbt-form" method="POST" action="{{ route('cbt.questions.store', $bank) }}">@csrf
@include('cbt.banks.question-fields', ['question' => null])
<div class="cbt-actions"><button class="btn btn-primary">Tambah soal</button></div></form></details>
@forelse($questions as $question)
<details class="card" style="margin:12px 0"><summary class="card-body">#{{ $questions->firstItem() + $loop->index }} — {{ Str::limit($question->prompt, 100) }}</summary>
<form class="card-body cbt-form" method="POST" action="{{ route('cbt.questions.update', $question) }}">@csrf @method('PUT')
@include('cbt.banks.question-fields', ['question' => $question])
<div class="cbt-actions"><button class="btn btn-primary">Simpan perubahan</button></div></form>
<form class="card-body" method="POST" action="{{ route('cbt.questions.destroy', $question) }}" onsubmit="return confirm('Hapus soal ini?')">@csrf @method('DELETE')<button class="btn">Hapus soal</button></form>
</details>
@empty<p class="cbt-note">Bank belum berisi soal.</p>@endforelse
{{ $questions->links() }}
@endsection
