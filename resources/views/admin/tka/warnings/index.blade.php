@extends('layouts.admin-with-tabs')

@section('title', 'Early Warning TKA - Admin')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Sistem Deteksi Dini (Early Warning)')
@section('page_subtitle', 'Monitoring dan eskalasi intervensi bagi siswa yang membutuhkan bimbingan karakter')

@section('header_actions')
    <a href="{{ route('admin.tka.dashboard') }}" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Dashboard TKA
    </a>
@endsection

@section('content')
{{-- Filter Bar --}}
<div class="filter-bar">
    <form method="GET" action="{{ route('admin.tka.warnings.index') }}" style="display:contents">
        <div class="filter-group">
            <label class="form-label">Status Tindak Lanjut</label>
            <select name="status" class="form-control">
                <option value="">— Semua Status —</option>
                <option value="pending" @selected(request('status') === 'pending')>Pending (Menunggu)</option>
                <option value="sedang_diproses" @selected(request('status') === 'sedang_diproses')>Sedang Diproses</option>
                <option value="selesai" @selected(request('status') === 'selesai')>Selesai Ditindaklanjuti</option>
            </select>
        </div>

        <div class="filter-group">
            <label class="form-label">Tingkat Peringatan</label>
            <select name="tingkat_peringatan" class="form-control">
                <option value="">— Semua Tingkat —</option>
                <option value="kritis" @selected(request('tingkat_peringatan') === 'kritis')>Kritis (Sangat Mendesak)</option>
                <option value="tinggi" @selected(request('tingkat_peringatan') === 'tinggi')>Tinggi</option>
                <option value="sedang" @selected(request('tingkat_peringatan') === 'sedang')>Sedang</option>
                <option value="rendah" @selected(request('tingkat_peringatan') === 'rendah')>Rendah</option>
            </select>
        </div>

        <div class="filter-group">
            <label class="form-label">Dimensi P5</label>
            <select name="dimension_id" class="form-control">
                <option value="">— Semua Dimensi —</option>
                @foreach($dimensions as $dim)
                    <option value="{{ $dim->id }}" @selected(request('dimension_id') == $dim->id)>
                        {{ $dim->nama_dimensi }}
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
            <a href="{{ route('admin.tka.warnings.index') }}" class="btn btn-secondary">
                <i class="fas fa-times"></i> Reset
            </a>
        </div>
    </form>
</div>

{{-- Level Stat Cards --}}
<div class="grid grid-4" style="margin-bottom:20px">
    <div class="stat-card bad">
        <i class="fas fa-radiation stat-icon"></i>
        <div class="stat-label">Peringatan Kritis</div>
        <div class="stat-value">{{ $levelCounts->get('kritis', 0) }}</div>
        <div class="stat-change down">Butuh intervensi darurat</div>
    </div>

    <div class="stat-card warn">
        <i class="fas fa-triangle-exclamation stat-icon"></i>
        <div class="stat-label">Peringatan Tinggi</div>
        <div class="stat-value">{{ $levelCounts->get('tinggi', 0) }}</div>
        <div class="stat-change">Prioritas bimbingan</div>
    </div>

    <div class="stat-card gold">
        <i class="fas fa-clock-rotate-left stat-icon"></i>
        <div class="stat-label">Dalam Proses</div>
        <div class="stat-value">{{ $statusCounts->get('sedang_diproses', 0) }}</div>
        <div class="stat-change">Sedang ditangani BK/Wali</div>
    </div>

    <div class="stat-card ok">
        <i class="fas fa-circle-check stat-icon"></i>
        <div class="stat-label">Selesai Ditangani</div>
        <div class="stat-value">{{ $statusCounts->get('selesai', 0) }}</div>
        <div class="stat-change up">Telah terintervensi</div>
    </div>
</div>

{{-- Warnings Table Card --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-bell" style="color:var(--bad);margin-right:8px"></i>Daftar Peringatan Dini Siswa ({{ $warnings->total() }})</h3>
        <span class="tag tag-secondary">Sistem Peringatan Dini</span>
    </div>

    <div class="card-body tight">
        @if($warnings->isEmpty())
            <div class="empty-state">
                <div class="icon">✅</div>
                <h4>Tidak Ada Early Warning</h4>
                <p>Tidak ada catatan peringatan dini yang sesuai dengan filter pencarian.</p>
            </div>
        @else
            @php
                $statusBadges = [
                    'pending' => ['tag' => 'tag-warn', 'label' => 'Pending'],
                    'sedang_diproses' => ['tag' => 'tag-primary', 'label' => 'Diproses'],
                    'selesai' => ['tag' => 'tag-ok', 'label' => 'Selesai'],
                ];
                $levelBadges = [
                    'kritis' => ['tag' => 'tag-bad', 'label' => 'Kritis'],
                    'tinggi' => ['tag' => 'tag-warn', 'label' => 'Tinggi'],
                    'sedang' => ['tag' => 'tag-gold', 'label' => 'Sedang'],
                    'rendah' => ['tag' => 'tag-secondary', 'label' => 'Rendah'],
                ];
            @endphp
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width:5%">No</th>
                            <th style="width:25%">Siswa & Kelas</th>
                            <th style="width:22%">Dimensi Terindikasi</th>
                            <th style="width:13%">Tingkat</th>
                            <th style="width:12%">Tanggal</th>
                            <th style="width:11%">Status</th>
                            <th style="width:12%;text-align:right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($warnings as $index => $w)
                            @php
                                $sCfg = $statusBadges[$w->status] ?? ['tag' => 'tag-secondary', 'label' => ucfirst($w->status)];
                                $lCfg = $levelBadges[$w->tingkat_peringatan] ?? ['tag' => 'tag-secondary', 'label' => ucfirst($w->tingkat_peringatan)];
                            @endphp
                            <tr style="{{ $w->tingkat_peringatan === 'kritis' ? 'background:#FEF2F2' : '' }}">
                                <td>{{ ($warnings->currentPage() - 1) * $warnings->perPage() + $index + 1 }}</td>
                                <td>
                                    <div style="font-weight:700;font-size:13.5px;color:var(--text)">
                                        {{ $w->siswa->nama_peserta_didik ?? '-' }}
                                    </div>
                                    <div style="font-size:11.5px;color:var(--muted)">
                                        Kelas {{ $w->siswa->kelas ?? '-' }} • NIS: {{ $w->siswa->nis ?? '-' }}
                                    </div>
                                </td>
                                <td>
                                    <span class="tag tag-primary">
                                        {{ $w->dimension->nama_dimensi ?? '-' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="tag {{ $lCfg['tag'] }}" style="font-weight:700">
                                        {{ $lCfg['label'] }}
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size:12.5px;color:var(--muted)">
                                        {{ $w->created_at->format('d/m/Y') }}
                                    </span>
                                </td>
                                <td>
                                    <span class="tag {{ $sCfg['tag'] }}">
                                        {{ $sCfg['label'] }}
                                    </span>
                                </td>
                                <td style="text-align:right">
                                    <div style="display:inline-flex;align-items:center;gap:6px">
                                        <a href="{{ route('admin.tka.warnings.show', $w) }}" class="btn btn-secondary btn-sm" title="Lihat Detail Warning">
                                            <i class="fas fa-eye"></i> Detail
                                        </a>
                                        @if($w->status !== 'selesai')
                                            <form action="{{ route('admin.tka.warnings.update-status', $w) }}" method="POST" style="display:inline">
                                                @csrf
                                                <input type="hidden" name="status" value="{{ $w->status === 'pending' ? 'sedang_diproses' : 'selesai' }}">
                                                <button type="submit" class="btn btn-primary btn-sm" title="Eskalasi Status">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($warnings->hasPages())
                <div style="padding:16px 20px;border-top:1px solid var(--line)">
                    {{ $warnings->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
