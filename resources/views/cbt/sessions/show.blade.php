@extends('layouts.app')
@section('title', 'Detail Sesi CBT - ' . $session->title)

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Kelola Sesi Ujian CBT')
@section('page_subtitle', $session->title)

@section('header_actions')
    <div style="display:flex;align-items:center;gap:8px">
        <a href="{{ route('cbt.sessions.monitoring', $session) }}" class="btn btn-primary btn-sm">
            <i class="fas fa-chart-line"></i> Monitoring & Nilai
        </a>
        <a href="{{ route('cbt.sessions.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>
@endsection

@section('content')
@include('cbt.feedback')

{{-- Workflow Stepper Banner --}}
@php
    $steps = [
        'draft' => ['title' => '1. Draf Sesi', 'desc' => 'Menyiapkan data'],
        'published' => ['title' => '2. Dipublikasikan', 'desc' => 'Peserta & soal dibekukan'],
        'running' => ['title' => '3. Ujian Dibuka', 'desc' => 'Siswa dapat mengerjakan'],
        'completed' => ['title' => '4. Selesai', 'desc' => 'Jawaban dinilai final'],
    ];
    $statusIndex = array_search($session->status, array_keys($steps));
@endphp

<div class="card" style="margin-bottom:24px">
    <div class="card-header" style="background:#FAFBFD">
        <h3 style="font-size:14px;color:var(--text)">
            <i class="fas fa-network-wired" style="color:var(--navy);margin-right:8px"></i> Tahapan Siklus Sesi Ujian
        </h3>
        <span class="tag {{ $session->status === 'running' ? 'tag-ok' : ($session->status === 'completed' ? 'tag-primary' : 'tag-warn') }}" style="font-weight:700">
            Status: {{ strtoupper($session->status) }}
        </span>
    </div>

    <div class="card-body" style="padding:20px 24px">
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:14px">
            @foreach($steps as $key => $st)
                @php
                    $thisIndex = array_search($key, array_keys($steps));
                    $isActive = $key === $session->status;
                    $isPassed = $thisIndex < $statusIndex;
                @endphp
                <div style="padding:14px 16px;border-radius:10px;border:1.5px solid {{ $isActive ? 'var(--navy)' : ($isPassed ? 'var(--ok)' : 'var(--line)') }};background:{{ $isActive ? '#EEF2F9' : ($isPassed ? '#E6F5F0' : '#FAFBFD') }}">
                    <div style="font-size:11px;font-weight:700;color:{{ $isActive ? 'var(--navy)' : ($isPassed ? 'var(--ok)' : 'var(--muted)') }};text-transform:uppercase">
                        {{ $isPassed ? '✓ Selesai' : ($isActive ? '● Aktif Saat Ini' : '○ Menunggu') }}
                    </div>
                    <div style="font-size:14px;font-weight:800;color:var(--text);margin-top:4px">{{ $st['title'] }}</div>
                    <div style="font-size:12px;color:var(--muted);margin-top:2px">{{ $st['desc'] }}</div>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Detail Parameters & Actions --}}
<div class="grid grid-3" style="margin-bottom:24px">
    {{-- Parameter Sesi --}}
    <div class="card" style="grid-column: span 2">
        <div class="card-header">
            <h3><i class="fas fa-info-circle" style="color:var(--navy);margin-right:8px"></i>Parameter Sesi Ujian</h3>
            <span class="tag tag-primary">Kelas {{ $session->kelas }}</span>
        </div>
        <div class="card-body tight">
            <div class="table-wrapper">
                <table>
                    <tbody>
                        <tr>
                            <td style="width:30%;font-weight:600;color:var(--muted)">Judul Sesi</td>
                            <td style="font-weight:700;color:var(--text)">{{ $session->title }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;color:var(--muted)">Jadwal Pelaksanaan</td>
                            <td>
                                <div><i class="far fa-calendar-check" style="color:var(--ok);margin-right:6px"></i> Buka: {{ $session->opens_at->format('d M Y, H:i') }} ({{ config('app.timezone') }})</div>
                                <div style="margin-top:2px"><i class="far fa-calendar-times" style="color:var(--bad);margin-right:6px"></i> Tutup: {{ $session->closes_at->format('d M Y, H:i') }} ({{ config('app.timezone') }})</div>
                            </td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;color:var(--muted)">Alokasi Durasi Pengerjaan</td>
                            <td><span class="tag tag-gold">{{ $session->duration_minutes }} Menit per siswa</span></td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;color:var(--muted)">Snapshot Soal Ujian</td>
                            <td>
                                <span class="tag tag-ok">{{ count($session->question_snapshot ?? []) }} Butir Soal Terkunci</span>
                            </td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;color:var(--muted)">Daftar Peserta Terdaftar</td>
                            <td>
                                <span class="tag tag-primary">{{ count($session->participant_ids ?? []) }} Siswa Kelas {{ $session->kelas }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Control Panel Card --}}
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-bolt" style="color:var(--gold);margin-right:8px"></i>Aksi Pengawas</h3>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:14px">
            @if($session->status === 'draft')
                <div class="note note-warn" style="font-size:12px;margin:0">
                    Sesi masih draf. Klik tombol di bawah untuk membekukan snapshot soal dan peserta ujian.
                </div>
                <form method="POST" action="{{ route('cbt.sessions.publish', $session) }}" onsubmit="return confirm('Publikasikan sesi ini? Soal dan peserta akan dibekukan.')">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-lock"></i> Publikasikan Sesi
                    </button>
                </form>
            @endif

            @if($session->status === 'published')
                <div class="note note-ok" style="font-size:12px;margin:0">
                    Sesi telah dipublikasikan. Klik Buka Sesi saat waktu ujian tiba agar siswa dapat mulai mengerjakan.
                </div>
                <form method="POST" action="{{ route('cbt.sessions.start', $session) }}" onsubmit="return confirm('Buka sesi ujian sekarang?')">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-block" style="background:var(--ok);border-color:var(--ok)">
                        <i class="fas fa-play"></i> Buka Sesi Ujian
                    </button>
                </form>
            @endif

            @if($session->status === 'running')
                <div class="note note-ok" style="font-size:12px;margin:0">
                    Sesi sedang berjalan. Anda dapat memantau pengerjaan siswa secara langsung.
                </div>
                <a href="{{ route('cbt.sessions.monitoring', $session) }}" class="btn btn-primary btn-block">
                    <i class="fas fa-chart-line"></i> Buka Layar Monitoring
                </a>
                <form method="POST" action="{{ route('cbt.sessions.complete', $session) }}" onsubmit="return confirm('Akhiri sesi sekarang? Semua jawaban tersimpan akan langsung dinilai server.')">
                    @csrf
                    <button type="submit" class="btn btn-danger btn-block">
                        <i class="fas fa-stop"></i> Akhiri Sesi Ujian
                    </button>
                </form>
            @endif

            @if($session->status === 'completed')
                <div class="note note-ok" style="font-size:12px;margin:0">
                    Sesi telah selesai dan seluruh hasil ujian telah dinilai.
                </div>
                <a href="{{ route('cbt.sessions.monitoring', $session) }}" class="btn btn-primary btn-block">
                    <i class="fas fa-poll"></i> Lihat Laporan Hasil & Nilai
                </a>
            @endif

            <div style="border-top:1px solid var(--line);padding-top:14px;margin-top:4px">
                <a href="{{ route('cbt.sessions.index') }}" class="btn btn-secondary btn-block">
                    <i class="fas fa-list"></i> Kembali ke Daftar Sesi
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
