@extends('layouts.admin-with-tabs')

@section('title', 'Kelola Penilaian TKA - Admin')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Penilaian Karakter Profil Pelajar (TKA)')
@section('page_subtitle', 'Data seluruh penilaian kesiapan belajar dan asesmen perkembangan siswa')

@section('header_actions')
    <a href="{{ route('admin.tka.dashboard') }}" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Dashboard TKA
    </a>
@endsection

@section('content')
{{-- Filter Bar --}}
<div class="filter-bar">
    <form method="GET" action="{{ route('admin.tka.assessments.index') }}" style="display:contents">
        <div class="filter-group">
            <label class="form-label">Status Kesiapan</label>
            <select name="status_kesiapan" class="form-control">
                <option value="">— Semua Status —</option>
                <option value="sangat_siap" @selected(request('status_kesiapan') === 'sangat_siap')>Sangat Siap</option>
                <option value="siap" @selected(request('status_kesiapan') === 'siap')>Siap</option>
                <option value="perlu_bimbingan" @selected(request('status_kesiapan') === 'perlu_bimbingan')>Perlu Bimbingan</option>
                <option value="belum_siap" @selected(request('status_kesiapan') === 'belum_siap')>Belum Siap</option>
            </select>
        </div>

        <div class="filter-group">
            <label class="form-label">Dimensi P5</label>
            <select name="dimension_id" class="form-control">
                <option value="">— Semua Dimensi —</option>
                @foreach($dimensions ?? [] as $dim)
                    <option value="{{ $dim->id }}" @selected(request('dimension_id') == $dim->id)>
                        {{ $dim->nama_dimensi }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="filter-group">
            <label class="form-label">Semester</label>
            <select name="semester_id" class="form-control">
                <option value="">— Semua Semester —</option>
                @foreach($semesters ?? [] as $sem)
                    <option value="{{ $sem->id }}" @selected(request('semester_id') == $sem->id)>
                        {{ $sem->label ?? $sem->tahun_ajaran }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="filter-group">
            <label class="form-label">Cari Nama Siswa</label>
            <input type="text" name="search" class="form-control" placeholder="Nama siswa..." value="{{ request('search') }}">
        </div>

        <div style="display:flex;align-items:center;gap:8px">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Filter
            </button>
            <a href="{{ route('admin.tka.assessments.index') }}" class="btn btn-secondary">
                <i class="fas fa-times"></i> Reset
            </a>
        </div>
    </form>
</div>

{{-- Readiness Stats --}}
<div class="grid grid-4" style="margin-bottom:20px">
    <div class="stat-card ok">
        <i class="fas fa-star stat-icon"></i>
        <div class="stat-label">Sangat Siap</div>
        <div class="stat-value">{{ $assessments->where('status_kesiapan', 'sangat_siap')->count() }}</div>
        <div class="stat-change up">Perkembangan optimal</div>
    </div>

    <div class="stat-card primary">
        <i class="fas fa-thumbs-up stat-icon"></i>
        <div class="stat-label">Siap</div>
        <div class="stat-value">{{ $assessments->where('status_kesiapan', 'siap')->count() }}</div>
        <div class="stat-change">Memenuhi standar</div>
    </div>

    <div class="stat-card gold">
        <i class="fas fa-hands-helping stat-icon"></i>
        <div class="stat-label">Perlu Bimbingan</div>
        <div class="stat-value">{{ $assessments->where('status_kesiapan', 'perlu_bimbingan')->count() }}</div>
        <div class="stat-change">Pendampingan berkala</div>
    </div>

    <div class="stat-card bad">
        <i class="fas fa-triangle-exclamation stat-icon"></i>
        <div class="stat-label">Belum Siap</div>
        <div class="stat-value">{{ $assessments->where('status_kesiapan', 'belum_siap')->count() }}</div>
        <div class="stat-change down">Intervensi khusus</div>
    </div>
</div>

{{-- Table Card --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-clipboard-list" style="color:var(--navy);margin-right:8px"></i>Daftar Penilaian TKA ({{ $assessments->total() }})</h3>
        <span class="tag tag-secondary">Data Real-Time</span>
    </div>

    <div class="card-body tight">
        @if($assessments->isEmpty())
            <div class="empty-state">
                <div class="icon">📋</div>
                <h4>Tidak Ada Data Penilaian</h4>
                <p>Tidak ada rekaman penilaian yang cocok dengan filter pencarian saat ini.</p>
            </div>
        @else
            @php
                $statusBadges = [
                    'sangat_siap' => ['tag' => 'tag-ok', 'label' => 'Sangat Siap'],
                    'siap' => ['tag' => 'tag-primary', 'label' => 'Siap'],
                    'perlu_bimbingan' => ['tag' => 'tag-gold', 'label' => 'Perlu Bimbingan'],
                    'belum_siap' => ['tag' => 'tag-bad', 'label' => 'Belum Siap'],
                ];
            @endphp
            <div class="table-wrapper">
                <!-- Assessments Table -->
                <table>
                    <thead>
                        <tr>
                            <th style="width:5%">No</th>
                            <th style="width:25%">Siswa & Rombel</th>
                            <th style="width:20%">Dimensi Pengamatan</th>
                            <th style="width:12%">Tanggal</th>
                            <th style="width:10%">Skor</th>
                            <th style="width:15%">Status Kesiapan</th>
                            <th style="width:12%">Semester</th>
                            <th style="width:13%;text-align:right">Riwayat</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($assessments as $index => $assessment)
                            @php
                                $cfg = $statusBadges[$assessment->status_kesiapan] ?? ['tag' => 'tag-secondary', 'label' => ucfirst($assessment->status_kesiapan)];
                                $firstResponse = $assessment->responses->first();
                                $dimName = $firstResponse?->assessmentItem?->dimension?->nama_dimensi ?? 'Multi-Dimensi';
                            @endphp
                            <tr>
                                <td>{{ ($assessments->currentPage() - 1) * $assessments->perPage() + $index + 1 }}</td>
                                <td>
                                    <div style="font-weight:700;font-size:13.5px;color:var(--text)">
                                        {{ $assessment->siswa->nama_peserta_didik ?? '-' }}
                                    </div>
                                    <div style="font-size:11.5px;color:var(--muted)">
                                        Kelas {{ $assessment->siswa->kelas ?? '-' }} • NIS: {{ $assessment->siswa->nis ?? '-' }}
                                    </div>
                                </td>
                                <td>
                                    <span class="tag tag-primary" style="font-size:11.5px">
                                        {{ $dimName }}
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size:12.5px;color:var(--muted)">
                                        {{ $assessment->tanggal_penilaian ? $assessment->tanggal_penilaian->format('d/m/Y') : '-' }}
                                    </span>
                                </td>
                                <td>
                                    <span style="font-weight:800;font-size:14px;color:var(--navy)">
                                        {{ number_format($assessment->total_score ?? $assessment->skor_akhir ?? 0, 1) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="tag {{ $cfg['tag'] }}">
                                        {{ $cfg['label'] }}
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size:12.5px;color:var(--muted)">
                                        {{ $assessment->semester?->tahun_ajaran ?? '-' }}
                                    </span>
                                </td>
                                <td style="text-align:right">
                                    <a href="{{ route('admin.tka.assessments.history', ['siswaId' => $assessment->siswa_id]) }}" class="btn btn-secondary btn-sm" title="Lihat Histori Karakter Siswa">
                                        <i class="fas fa-history"></i> Riwayat
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($assessments->hasPages())
                <div style="padding:16px 20px;border-top:1px solid var(--line)">
                    {{ $assessments->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
