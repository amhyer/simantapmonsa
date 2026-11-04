@extends('layouts.admin-with-tabs')

@section('title', 'Dashboard Tes Kemampuan Akademik (TKA) - Admin')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Dashboard Tes Kemampuan Akademik (TKA)')
@section('page_subtitle', 'Monitoring Kesiapan Asesmen Siswa, Kisi-kisi Pusmendik & Sistem Deteksi Dini (Early Warning)')

@section('header_actions')
    <a href="https://pusmendik.kemendikdasmen.go.id/tka/simulasi_tka/" target="_blank" rel="noopener noreferrer" class="btn btn-outline btn-sm" style="color:var(--navy, #1F3864);border-color:currentColor">
        <i class="fas fa-external-link-alt"></i> Portal Pusmendik
    </a>
    <a href="{{ route('admin.tka.dimensions.index') }}" class="btn btn-gold btn-sm">
        <i class="fas fa-th-large"></i> Kelola Kisi-kisi TKA
    </a>
@endsection

@section('content')
{{-- Statistics Cards --}}
<div class="grid grid-4" style="margin-bottom:24px">
    <div class="stat-card primary">
        <i class="fas fa-th-large stat-icon"></i>
        <div class="stat-label">Total Dimensi Profil</div>
        <div class="stat-value">{{ $stats['total_dimensions'] }}</div>
        <div class="stat-change up">{{ $stats['active_dimensions'] }} dimensi aktif</div>
    </div>

    <div class="stat-card ok">
        <i class="fas fa-clipboard-check stat-icon"></i>
        <div class="stat-label">Total Penilaian Siswa</div>
        <div class="stat-value">{{ $stats['total_assessments'] }}</div>
        <div class="stat-change up">{{ $stats['assessments_this_month'] }} penilaian bulan ini</div>
    </div>

    <div class="stat-card warn">
        <i class="fas fa-exclamation-triangle stat-icon"></i>
        <div class="stat-label">Early Warnings</div>
        <div class="stat-value">{{ $stats['early_warnings']['total'] }}</div>
        <div class="stat-change">{{ $stats['early_warnings']['pending'] }} butuh tindak lanjut</div>
    </div>

    <div class="stat-card bad">
        <i class="fas fa-bell stat-icon"></i>
        <div class="stat-label">Peringatan Kritis</div>
        <div class="stat-value">{{ $stats['critical_warnings']->count() }}</div>
        <div class="stat-change down">Perlu bimbingan segera</div>
    </div>
</div>

{{-- Row: Distribusi Kesiapan & Critical Warnings --}}
<div class="grid grid-2" style="margin-bottom:24px">
    {{-- Status Distribution Card --}}
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-pie" style="color:var(--navy);margin-right:8px"></i>Distribusi Status Kesiapan Siswa</h3>
            <span class="tag tag-secondary">Profil Pelajar</span>
        </div>
        <div class="card-body">
            @php
                $statusBadges = [
                    'sangat_siap' => ['tag' => 'tag-ok', 'fill' => 'fill-ok', 'label' => 'Sangat Siap'],
                    'siap' => ['tag' => 'tag-primary', 'fill' => '', 'label' => 'Siap'],
                    'perlu_bimbingan' => ['tag' => 'tag-gold', 'fill' => 'fill-warn', 'label' => 'Perlu Bimbingan'],
                    'belum_siap' => ['tag' => 'tag-bad', 'fill' => 'fill-bad', 'label' => 'Belum Siap'],
                ];
                $totalAssessments = max(1, $stats['total_assessments']);
            @endphp

            <div style="display:flex;flex-direction:column;gap:16px">
                @foreach($stats['status_distribution'] as $status => $count)
                    @php
                        $cfg = $statusBadges[$status] ?? ['tag' => 'tag-secondary', 'fill' => '', 'label' => ucfirst($status)];
                        $pct = round(($count / $totalAssessments) * 100);
                    @endphp
                    <div>
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
                            <span class="tag {{ $cfg['tag'] }}">{{ $cfg['label'] }}</span>
                            <span style="font-weight:700;font-size:13px">{{ $count }} Siswa <small style="color:var(--muted);font-weight:400">({{ $pct }}%)</small></span>
                        </div>
                        <div class="bar">
                            <span class="fill {{ $cfg['fill'] }}" style="width:{{ $pct }}%"></span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Critical Warnings List --}}
    <div class="card" style="border-left:4px solid var(--bad)">
        <div class="card-header">
            <h3><i class="fas fa-radiation" style="color:var(--bad);margin-right:8px"></i>Peringatan Dini Kritis</h3>
            <span class="tag tag-bad">{{ $stats['critical_warnings']->count() }} Kritis</span>
        </div>
        <div class="card-body tight">
            @if($stats['critical_warnings']->count() > 0)
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Siswa & Kelas</th>
                                <th>Dimensi</th>
                                <th>Tanggal</th>
                                <th style="text-align:right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($stats['critical_warnings'] as $warning)
                                <tr>
                                    <td>
                                        <div style="font-weight:700;font-size:13px;color:var(--text)">
                                            {{ $warning->siswa->nama_peserta_didik }}
                                        </div>
                                        <div style="font-size:11px;color:var(--muted)">Kelas {{ $warning->siswa->kelas }}</div>
                                    </td>
                                    <td>
                                        <span class="tag tag-warn" style="font-size:11px">
                                            {{ $warning->dimension->nama_dimensi ?? '-' }}
                                        </span>
                                    </td>
                                    <td style="font-size:12px;color:var(--muted)">{{ $warning->created_at->format('d/m/Y') }}</td>
                                    <td style="text-align:right">
                                        <a href="{{ route('admin.tka.warnings.index', ['status' => 'pending', 'tingkat_peringatan' => 'kritis']) }}" class="btn btn-primary btn-sm" style="background:var(--bad);border-color:var(--bad)">
                                            Tindak Lanjut →
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state" style="padding:30px 20px">
                    <div class="icon">✅</div>
                    <h4>Kondisi Terkendali</h4>
                    <p>Tidak ada indikator early warning kategori kritis saat ini.</p>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Row: Recent Assessments & Quick Links --}}
<div class="grid grid-2" style="margin-bottom:24px">
    {{-- Recent Assessments --}}
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-clock-rotate-left" style="color:var(--navy);margin-right:8px"></i>Penilaian Karakter Terbaru</h3>
            <a href="{{ route('admin.tka.assessments.index') }}" class="btn btn-secondary btn-sm">Lihat Semua</a>
        </div>
        <div class="card-body tight">
            @if($stats['recent_assessments']->count() > 0)
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Siswa</th>
                                <th>Tanggal</th>
                                <th>Kesiapan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($stats['recent_assessments'] as $assessment)
                                @php
                                    $cfg = $statusBadges[$assessment->status_kesiapan] ?? ['tag' => 'tag-secondary', 'label' => ucfirst($assessment->status_kesiapan)];
                                @endphp
                                <tr>
                                    <td>
                                        <div style="font-weight:700;font-size:13px">{{ $assessment->siswa->nama_peserta_didik }}</div>
                                        <div style="font-size:11px;color:var(--muted)">{{ $assessment->semester->label ?? '-' }}</div>
                                    </td>
                                    <td style="font-size:12px">{{ $assessment->tanggal_penilaian->format('d/m/Y') }}</td>
                                    <td>
                                        <span class="tag {{ $cfg['tag'] }}">{{ $cfg['label'] }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state" style="padding:30px 20px">
                    <div class="icon">📝</div>
                    <h4>Belum Ada Penilaian</h4>
                    <p>Belum ada rekaman penilaian kesiapan belajar siswa.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Quick Menu & Actions --}}
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-compass" style="color:var(--navy);margin-right:8px"></i>Akses Cepat Modul TKA</h3>
        </div>
        <div class="card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <a href="{{ route('admin.tka.dimensions.index') }}" class="btn btn-secondary" style="padding:16px;text-align:left;display:flex;flex-direction:column;gap:6px">
                <i class="fas fa-th-large" style="font-size:20px;color:var(--navy)"></i>
                <span style="font-weight:700">Kelola Dimensi P5</span>
                <small style="color:var(--muted)">6 Dimensi Profil Pelajar</small>
            </a>

            <a href="{{ route('admin.tka.assessments.index') }}" class="btn btn-secondary" style="padding:16px;text-align:left;display:flex;flex-direction:column;gap:6px">
                <i class="fas fa-clipboard-check" style="font-size:20px;color:var(--ok)"></i>
                <span style="font-weight:700">Semua Penilaian</span>
                <small style="color:var(--muted)">Data riwayat asesmen</small>
            </a>

            <a href="{{ route('admin.tka.warnings.index') }}" class="btn btn-secondary" style="padding:16px;text-align:left;display:flex;flex-direction:column;gap:6px">
                <i class="fas fa-exclamation-triangle" style="font-size:20px;color:var(--warn)"></i>
                <span style="font-weight:700">Early Warning Hub</span>
                <small style="color:var(--muted)">Peringatan & intervensi</small>
            </a>

            <a href="#tka-exports" class="btn btn-secondary" style="padding:16px;text-align:left;display:flex;flex-direction:column;gap:6px">
                <i class="fas fa-file-export" style="font-size:20px;color:var(--gold)"></i>
                <span style="font-weight:700">Laporan & Ekspor</span>
                <small style="color:var(--muted)">Unduh format Excel & PDF</small>
            </a>
        </div>
    </div>
</div>

{{-- Export Section --}}
<div class="grid grid-2" id="tka-exports" style="margin-bottom:24px">
    {{-- Laporan Massal --}}
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-file-pdf" style="color:var(--navy);margin-right:8px"></i>Laporan Massal per Semester</h3>
        </div>
        <div class="card-body">
            <p style="font-size:13px;color:var(--muted);margin-bottom:16px">
                Unduh rekapitulasi penilaian kesiapan siswa secara kolektif untuk semester yang dipilih.
            </p>
            <form method="POST" action="{{ route('admin.tka.generate-report') }}">
                @csrf
                <div style="margin-bottom:14px">
                    <label for="reportSemester" class="form-label">Pilih Semester</label>
                    <select name="semester_id" id="reportSemester" class="form-control" required style="width:100%">
                        <option value="">— Pilih Semester —</option>
                        @foreach($semesters as $semester)
                            <option value="{{ $semester->id }}" @selected(old('semester_id') == $semester->id)>
                                {{ $semester->label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div style="margin-bottom:18px">
                    <label for="reportFormat" class="form-label">Format Dokumen</label>
                    <select name="format" id="reportFormat" class="form-control" required style="width:100%">
                        <option value="excel" @selected(old('format', 'excel') === 'excel')>Microsoft Excel (.xlsx)</option>
                        <option value="pdf" @selected(old('format') === 'pdf')>Dokumen PDF (.pdf)</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-download"></i> Unduh Laporan Massal
                </button>
            </form>
        </div>
    </div>

    {{-- Ekspor Data --}}
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-database" style="color:var(--gold);margin-right:8px"></i>Ekspor Data Terperinci</h3>
        </div>
        <div class="card-body">
            <p style="font-size:13px;color:var(--muted);margin-bottom:16px">
                Ekspor tabel data mentah asesmen, early warning, atau dimensi ke spreadsheet.
            </p>
            <form method="POST" action="{{ route('admin.tka.export') }}">
                @csrf
                <div style="margin-bottom:14px">
                    <label for="exportType" class="form-label">Jenis Data</label>
                    <select name="type" id="exportType" class="form-control" required style="width:100%">
                        <option value="assessments" @selected(old('type', 'assessments') === 'assessments')>Seluruh Penilaian Siswa</option>
                        <option value="warnings" @selected(old('type') === 'warnings')>Peringatan Dini (Early Warning)</option>
                        <option value="dimensions" @selected(old('type') === 'dimensions')>Dimensi &amp; Indikator</option>
                    </select>
                </div>

                <div style="margin-bottom:14px">
                    <label for="exportSemester" class="form-label">Semester (Opsional)</label>
                    <select name="semester_id" id="exportSemester" class="form-control" style="width:100%">
                        <option value="">— Semua Semester —</option>
                        @foreach($semesters as $semester)
                            <option value="{{ $semester->id }}" @selected(old('semester_id') == $semester->id)>
                                {{ $semester->label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div style="margin-bottom:18px">
                    <label for="exportFormat" class="form-label">Format File</label>
                    <select name="format" id="exportFormat" class="form-control" required style="width:100%">
                        <option value="excel" @selected(old('format', 'excel') === 'excel')>Microsoft Excel (.xlsx)</option>
                        <option value="pdf" @selected(old('format') === 'pdf')>Dokumen PDF (.pdf)</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-secondary btn-block">
                    <i class="fas fa-file-export"></i> Ekspor Data Sekarang
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
