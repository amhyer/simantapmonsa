@extends('layouts.admin-with-tabs')

@section('title', 'Sesi Ujian CBT - Admin')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Sesi Ujian CBT Sekolah')
@section('page_subtitle', 'Pengawasan dan pengelolaan sesi ujian berbasis komputer dari seluruh kelas dan guru')

@section('header_actions')
    <a href="{{ route('admin.cbt.sessions.create') }}" class="btn btn-gold btn-sm">
        <i class="fas fa-plus"></i> Buat Sesi Baru
    </a>
@endsection

@section('content')
{{-- Filter Bar --}}
<div class="filter-bar">
    <form method="GET" action="{{ route('admin.cbt.sessions.index') }}" style="display:contents">
        <div class="filter-group">
            <label class="form-label">Status Sesi</label>
            <select name="status" class="form-control">
                <option value="">— Semua Status —</option>
                @foreach(['draft' => 'Draf', 'published' => 'Dipublikasikan', 'running' => 'Sedang Berjalan', 'completed' => 'Selesai'] as $key => $lbl)
                    <option value="{{ $key }}" @selected(request('status') === $key)>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>

        <div class="filter-group">
            <label class="form-label">Guru Pengampu</label>
            <select name="guru_id" class="form-control">
                <option value="">— Semua Guru —</option>
                @foreach($gurus as $guru)
                    <option value="{{ $guru->id }}" @selected(request('guru_id') == $guru->id)>
                        {{ $guru->nama_lengkap }}
                    </option>
                @endforeach
            </select>
        </div>

        <div style="display:flex;align-items:center;gap:8px">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Terapkan Filter
            </button>
            <a href="{{ route('admin.cbt.sessions.index') }}" class="btn btn-secondary">
                <i class="fas fa-times"></i> Reset
            </a>
        </div>
    </form>
</div>

{{-- Sesi Table Card --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-calendar-check" style="color:var(--navy);margin-right:8px"></i>Daftar Seluruh Sesi Ujian ({{ $sessions->total() }})</h3>
        <a href="{{ route('admin.cbt.sessions.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Tambah Sesi Ujian
        </a>
    </div>

    <div class="card-body tight">
        @if($sessions->isEmpty())
            <div class="empty-state">
                <div class="icon">🗓️</div>
                <h4>Tidak Ada Sesi Ujian</h4>
                <p>Belum ada sesi ujian CBT yang sesuai dengan filter pencarian.</p>
            </div>
        @else
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width:5%">No</th>
                            <th style="width:28%">Judul Ujian & Guru</th>
                            <th style="width:12%">Kelas & Durasi</th>
                            <th style="width:12%">Status</th>
                            <th style="width:23%">Jadwal Pelaksanaan</th>
                            <th style="width:8%">Peserta</th>
                            <th style="width:12%;text-align:right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sessions as $index => $session)
                            @php
                                $statusStyles = [
                                    'draft' => ['class' => 'tag-secondary', 'label' => 'Draf', 'icon' => 'fa-pencil'],
                                    'published' => ['class' => 'tag-warn', 'label' => 'Dipublikasikan', 'icon' => 'fa-lock'],
                                    'running' => ['class' => 'tag-ok', 'label' => 'Berjalan', 'icon' => 'fa-play'],
                                    'completed' => ['class' => 'tag-primary', 'label' => 'Selesai', 'icon' => 'fa-check'],
                                ];
                                $st = $statusStyles[$session->status] ?? ['class' => 'tag-secondary', 'label' => ucfirst($session->status), 'icon' => 'fa-circle'];
                            @endphp
                            <tr>
                                <td>{{ $sessions->firstItem() + $index }}</td>
                                <td>
                                    <div style="font-weight:700;font-size:14px;color:var(--text)">
                                        <a href="{{ route('admin.cbt.sessions.show', $session) }}" style="color:var(--navy);text-decoration:none">
                                            {{ $session->title }}
                                        </a>
                                    </div>
                                    <div style="font-size:11.5px;color:var(--muted);margin-top:2px">
                                        Guru: {{ $session->guru->nama_lengkap ?? '-' }} • Bank: {{ $session->bank->title ?? '-' }}
                                    </div>
                                </td>
                                <td>
                                    <span class="tag tag-primary">Kelas {{ $session->kelas }}</span>
                                    <div style="font-size:11.5px;color:var(--muted);margin-top:2px">{{ $session->duration_minutes }} Menit</div>
                                </td>
                                <td>
                                    <span class="tag {{ $st['class'] }}">
                                        <i class="fas {{ $st['icon'] }}" style="margin-right:3px"></i> {{ $st['label'] }}
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size:12px;color:var(--text)">
                                        <i class="far fa-calendar-alt" style="color:var(--muted);margin-right:4px"></i>
                                        {{ $session->opens_at?->format('d/m/Y H:i') ?? '-' }}
                                    </div>
                                    <div style="font-size:11px;color:var(--muted);margin-top:1px">
                                        s/d {{ $session->closes_at?->format('d/m/Y H:i') ?? '-' }}
                                    </div>
                                </td>
                                <td>
                                    <span style="font-weight:700;color:var(--navy)">{{ $session->results_count ?? 0 }}</span>
                                    <span style="font-size:11px;color:var(--muted)">/ {{ count($session->participant_ids ?? []) }}</span>
                                </td>
                                <td style="text-align:right">
                                    <div style="display:inline-flex;align-items:center;gap:6px">
                                        <a href="{{ route('admin.cbt.sessions.show', $session) }}" class="btn btn-primary btn-sm" title="Kelola Sesi">
                                            <i class="fas fa-sliders-h"></i>
                                        </a>
                                        <a href="{{ route('admin.cbt.sessions.analisis-butir', $session) }}" class="btn btn-secondary btn-sm" title="Analisis Butir Soal">
                                            <i class="fas fa-chart-pie"></i>
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
