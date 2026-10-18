@extends('layouts.app')
@section('title', 'Ujian CBT - SIMANTAP')
@section('sidebar')
    @include('siswa.partials.sidebar')
@endsection
@section('page_title', 'Ujian CBT')
@section('page_subtitle', 'Computer Based Test • Pilihan ganda')
@section('content')
@include('cbt.feedback')
<div class="card"><div class="card-body"><table class="cbt-list"><thead><tr><th>Ujian</th><th>Jadwal</th><th>Durasi</th><th>Aksi</th></tr></thead><tbody>
@forelse($sessions as $session)<tr><td>{{ $session->title }}</td><td>{{ $session->opens_at->format('d/m/Y H:i') }} — {{ $session->closes_at->format('d/m/Y H:i') }}</td><td>{{ $session->duration_minutes }} menit</td><td><a href="{{ route('cbt.student.start', $session) }}">Buka / lanjutkan</a></td></tr>
@empty<tr><td colspan="4">Belum ada ujian untuk Anda.</td></tr>@endforelse
</tbody></table>{{ $sessions->links() }}</div></div>
@endsection
