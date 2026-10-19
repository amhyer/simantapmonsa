@extends('layouts.app')
@section('title', 'Monitoring CBT - SIMANTAP')
@section('sidebar')
    @include('guru.partials.sidebar')
@endsection
@section('page_title', 'Monitoring CBT')
@section('page_subtitle', 'Computer Based Test • Pilihan ganda')
@section('content')
@include('cbt.feedback')
<h2>{{ $session->title }}</h2><p>Status: {{ $session->status }} • {{ count($session->participant_ids ?? []) }} peserta terdaftar; {{ $results->total() }} telah mulai.</p>
<div class="cbt-actions"><button class="btn" onclick="location.reload()">Segarkan monitoring</button><a class="btn" href="{{ route('cbt.sessions.show', $session) }}">Detail sesi</a></div>
<p class="cbt-note">Pindah tab hanya indikasi aktivitas browser, bukan bukti kecurangan dan tidak mengurangi nilai. Percobaan kedaluwarsa difinalisasi scheduler setiap menit.</p>
<div class="card"><div class="card-body" style="overflow:auto"><table class="cbt-list"><thead><tr><th>Siswa</th><th>Dijawab</th><th>Deadline</th><th>Status</th><th>Nilai</th><th>Indikasi</th></tr></thead><tbody>
@forelse($results as $result)<tr><td>{{ $result->siswa?->nama_peserta_didik ?? 'Siswa dihapus' }}</td><td>{{ count($result->answers) }} / {{ count($session->question_snapshot) }}</td><td>{{ $result->deadline_at->format('H:i:s') }}</td><td>{{ $result->submitted_at ? 'Terkumpul' : (now()->gte($result->deadline_at) ? 'Menunggu finalisasi' : 'Mengerjakan') }}</td><td>{{ $result->score ?? '—' }}</td><td><a href="{{ route('cbt.integrity.violations', $result) }}">{{ $result->violations_count }} catatan (JSON)</a></td></tr>
@empty<tr><td colspan="6">Belum ada siswa yang mulai.</td></tr>@endforelse
</tbody></table>{{ $results->links() }}</div></div>
@endsection
