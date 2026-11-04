@extends('layouts.app')
@section('title', 'Sesi Ujian CBT - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Sesi Ujian CBT')
@section('page_subtitle', 'Jadwalkan, buka, dan pantau jalannya ujian berbasis komputer untuk kelas Anda')

@section('header_actions')
    <a href="{{ route('cbt.sessions.create') }}" class="btn btn-gold btn-sm">
        <i class="fas fa-plus"></i> Buat Sesi Baru
    </a>
@endsection

@section('content')
@include('cbt.feedback')

{{-- Quick Stats --}}
<div class="grid grid-4" style="margin-bottom:20px">
    <div class="stat-card primary">
        <i class="fas fa-calendar-alt stat-icon"></i>
        <div class="stat-label">Total Sesi Ujian</div>
        <div class="stat-value">{{ $sessions->total() }}</div>
        <div class="stat-change">Kelas Anda</div>
    </div>

    <div class="stat-card ok">
        <i class="fas fa-play-circle stat-icon"></i>
        <div class="stat-label">Sesi Berjalan</div>
        <div class="stat-value">
            {{ $sessions->getCollection()->where('status', 'running')->count() }}
        </div>
        <div class="stat-change up">Siswa sedang ujian</div>
    </div>

    <div class="stat-card gold">
        <i class="fas fa-file-signature stat-icon"></i>
        <div class="stat-label">Sesi Draf / Siap</div>
        <div class="stat-value">
            {{ $sessions->getCollection()->whereIn('status', ['draft', 'published'])->count() }}
        </div>
        <div class="stat-change">Menunggu jadwal</div>
    </div>

    <div class="stat-card" style="border-left:4px solid var(--muted)">
        <i class="fas fa-folder-open stat-icon"></i>
        <div class="stat-label">Bank Soal</div>
        <div class="stat-value" style="font-size:18px;margin-top:8px">
            <a href="{{ route('cbt.question-banks.index') }}" style="color:var(--navy);text-decoration:none;font-weight:700">
                Kelola Bank →
            </a>
        </div>
        <div class="stat-change">Sumber butir soal</div>
    </div>
</div>

{{-- Sesi Table --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-stopwatch" style="margin-right:8px;color:var(--navy)"></i>Daftar Sesi Ujian Anda</h3>
        <div style="display:flex;align-items:center;gap:10px">
            <a href="{{ route('cbt.sessions.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Tambah Sesi Ujian
            </a>
            <a href="{{ route('cbt.question-banks.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-database"></i> Bank Soal
            </a>
        </div>
    </div>

    <div class="card-body tight">
        @if($sessions->isEmpty())
            <div class="empty-state">
                <div class="icon">🗓️</div>
                <h4>Belum Ada Sesi Ujian</h4>
                <p>Jadwalkan sesi ujian pertama untuk siswa kelas Anda.</p>
                <div style="margin-top:16px">
                    <a href="{{ route('cbt.sessions.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Buat Sesi Sekarang
                    </a>
                </div>
            </div>
        @else
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width:5%">No</th>
                            <th style="width:30%">Judul Sesi & Kelas</th>
                            <th style="width:12%">Status</th>
                            <th style="width:25%">Jadwal Pelaksanaan</th>
                            <th style="width:10%">Peserta</th>
                            <th style="width:18%;text-align:right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sessions as $index => $session)
                            @php
                                $statusStyles = [
                                    'draft' => ['class' => 'tag-secondary', 'label' => 'Draf', 'icon' => 'fa-pencil'],
                                    'published' => ['class' => 'tag-warn', 'label' => 'Dipublikasikan', 'icon' => 'fa-lock'],
                                    'running' => ['class' => 'tag-ok', 'label' => 'Sedang Berjalan', 'icon' => 'fa-play'],
                                    'completed' => ['class' => 'tag-primary', 'label' => 'Selesai', 'icon' => 'fa-check'],
                                ];
                                $st = $statusStyles[$session->status] ?? ['class' => 'tag-secondary', 'label' => ucfirst($session->status), 'icon' => 'fa-circle'];
                            @endphp
                            <tr>
                                <td>{{ $sessions->firstItem() + $index }}</td>
                                <td>
                                    <div style="font-weight:700;font-size:14px;color:var(--text)">
                                        <a href="{{ route('cbt.sessions.show', $session) }}" style="color:var(--navy);text-decoration:none">
                                            {{ $session->title }}
                                        </a>
                                    </div>
                                    <div style="font-size:12px;color:var(--muted);margin-top:2px;display:flex;align-items:center;gap:6px">
                                        <span class="tag tag-primary" style="font-size:10.5px">Kelas {{ $session->kelas }}</span>
                                        <span>• {{ $session->duration_minutes }} Menit</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="tag {{ $st['class'] }}">
                                        <i class="fas {{ $st['icon'] }}" style="margin-right:3px"></i> {{ $st['label'] }}
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size:12.5px;color:var(--text)">
                                        <i class="far fa-calendar-alt" style="color:var(--muted);margin-right:4px"></i>
                                        {{ $session->opens_at->format('d/m/Y H:i') }}
                                    </div>
                                    <div style="font-size:11.5px;color:var(--muted);margin-top:2px">
                                        s/d {{ $session->closes_at->format('d/m/Y H:i') }}
                                    </div>
                                </td>
                                <td>
                                    <span style="font-weight:700;font-size:13px;color:var(--navy)">
                                        {{ $session->results_count ?? 0 }}
                                    </span>
                                    <span style="font-size:11px;color:var(--muted)">mulai</span>
                                </td>
                                <td style="text-align:right">
                                    <div style="display:inline-flex;align-items:center;gap:6px">
                                        <a href="{{ route('cbt.sessions.show', $session) }}" class="btn btn-secondary btn-sm" title="Kelola Alur Sesi">
                                            <i class="fas fa-sliders-h"></i> Detail
                                        </a>
                                        <a href="{{ route('cbt.sessions.monitoring', $session) }}" class="btn btn-primary btn-sm" title="Monitoring Real-Time">
                                            <i class="fas fa-chart-line"></i> Pantau
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($sessions->hasPages())
                <div style="padding:16px 20px;border-top:1px solid var(--line)">
                    {{ $sessions->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
