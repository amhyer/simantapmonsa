@extends('layouts.app')
@section('title', 'Bank Soal CBT - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Bank Soal CBT')
@section('page_subtitle', 'Kelola repositori bank soal pilihan ganda untuk ujian sekolah')

@section('header_actions')
    <a href="{{ route('cbt.question-banks.create') }}" class="btn btn-gold btn-sm">
        <i class="fas fa-plus"></i> Tambah Bank Soal
    </a>
@endsection

@section('content')
@include('cbt.feedback')

{{-- Quick Navigation & Stats --}}
<div class="grid grid-3" style="margin-bottom:20px">
    <div class="stat-card primary">
        <i class="fas fa-database stat-icon"></i>
        <div class="stat-label">Total Bank Soal</div>
        <div class="stat-value">{{ $banks->total() }}</div>
        <div class="stat-change">Mata pelajaran Anda</div>
    </div>

    <div class="stat-card gold">
        <i class="fas fa-calendar-check stat-icon"></i>
        <div class="stat-label">Sesi Ujian Aktif</div>
        <div class="stat-value"><i class="fas fa-clock" style="font-size:20px"></i></div>
        <div class="stat-change">
            <a href="{{ route('cbt.sessions.index') }}" style="color:var(--gold);font-weight:600;text-decoration:none">
                Buka Manajemen Sesi →
            </a>
        </div>
    </div>

    <div class="stat-card ok">
        <i class="fas fa-shield-alt stat-icon"></i>
        <div class="stat-label">Integritas CBT</div>
        <div class="stat-value">Aktif</div>
        <div class="stat-change up">Anti-cheat & offline queue</div>
    </div>
</div>

{{-- Bank Soal Table --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-folder-open" style="margin-right:8px;color:var(--navy)"></i>Daftar Bank Soal Anda</h3>
        <div style="display:flex;align-items:center;gap:10px">
            <a href="{{ route('cbt.question-banks.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Buat Bank Soal
            </a>
            <a href="{{ route('cbt.sessions.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-calendar-alt"></i> Sesi Ujian
            </a>
        </div>
    </div>

    <div class="card-body tight">
        @if($banks->isEmpty())
            <div class="empty-state">
                <div class="icon">📚</div>
                <h4>Belum Ada Bank Soal</h4>
                <p>Mulai susun bank soal pertama Anda untuk dapat membuat sesi ujian CBT.</p>
                <div style="margin-top:16px">
                    <a href="{{ route('cbt.question-banks.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Buat Bank Soal Sekarang
                    </a>
                </div>
            </div>
        @else
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width:5%">No</th>
                            <th style="width:35%">Judul Bank Soal</th>
                            <th style="width:25%">Mata Pelajaran</th>
                            <th style="width:15%">Jumlah Butir</th>
                            <th style="width:20%;text-align:right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($banks as $index => $bank)
                            <tr>
                                <td>{{ $banks->firstItem() + $index }}</td>
                                <td>
                                    <div style="font-weight:700;font-size:14px;color:var(--text)">
                                        <a href="{{ route('cbt.question-banks.show', $bank) }}" style="color:var(--navy);text-decoration:none">
                                            {{ $bank->title }}
                                        </a>
                                    </div>
                                    <div style="font-size:11.5px;color:var(--muted);margin-top:2px">
                                        ID: #{{ $bank->id }} • Dibuat {{ $bank->created_at?->diffForHumans() ?? '-' }}
                                    </div>
                                </td>
                                <td>
                                    <span class="tag tag-primary">
                                        <i class="fas fa-book" style="margin-right:4px"></i> {{ $bank->subject }}
                                    </span>
                                </td>
                                <td>
                                    <span class="tag {{ $bank->questions_count > 0 ? 'tag-ok' : 'tag-warn' }}">
                                        {{ $bank->questions_count }} Butir Soal
                                    </span>
                                </td>
                                <td style="text-align:right">
                                    <div style="display:inline-flex;align-items:center;gap:6px">
                                        <a href="{{ route('cbt.question-banks.show', $bank) }}" class="btn btn-primary btn-sm" title="Kelola Butir Soal">
                                            <i class="fas fa-list-check"></i> Kelola Soal
                                        </a>
                                        <a href="{{ route('cbt.question-banks.edit', $bank) }}" class="btn btn-secondary btn-sm" title="Ubah Nama/Mapel">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($banks->hasPages())
                <div style="padding:16px 20px;border-top:1px solid var(--line)">
                    {{ $banks->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
