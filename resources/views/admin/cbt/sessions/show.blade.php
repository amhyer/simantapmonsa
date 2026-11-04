@extends('layouts.admin-with-tabs')

@section('title', 'Detail Sesi CBT - ' . $session->title)

@section('page_title', 'Detail Sesi Ujian CBT')
@section('page_subtitle', $session->title)

@section('header_actions')
    <div style="display:flex;align-items:center;gap:8px">
        <a href="{{ route('admin.cbt.sessions.analisis-butir', $session) }}" class="btn btn-gold btn-sm">
            <i class="fas fa-chart-pie"></i> Analisis Butir
        </a>
        <a href="{{ route('admin.cbt.sessions.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>
@endsection

@section('content')
{{-- Metadata Banner Card --}}
<div class="card" style="margin-bottom:24px;border-left:4px solid var(--navy)">
    <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px">
        <div>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                <span class="tag tag-primary">Kelas {{ $session->kelas }}</span>
                @php
                    $statusStyles = [
                        'draft' => ['class' => 'tag-secondary', 'label' => 'Draf'],
                        'published' => ['class' => 'tag-warn', 'label' => 'Dipublikasikan'],
                        'running' => ['class' => 'tag-ok', 'label' => 'Sedang Berjalan'],
                        'completed' => ['class' => 'tag-primary', 'label' => 'Selesai'],
                    ];
                    $st = $statusStyles[$session->status] ?? ['class' => 'tag-secondary', 'label' => ucfirst($session->status)];
                @endphp
                <span class="tag {{ $st['class'] }}" style="font-weight:700">Status: {{ $st['label'] }}</span>
            </div>
            <h2 style="font-size:20px;font-weight:800;color:var(--text);margin:0 0 4px">{{ $session->title }}</h2>
            <p style="font-size:13px;color:var(--muted);margin:0">
                Guru: <strong>{{ $session->guru->nama_lengkap ?? '-' }}</strong> • Bank: <strong>{{ $session->bank->title ?? '-' }}</strong> • Durasi: <strong>{{ $session->duration_minutes }} Menit</strong>
            </p>
        </div>

        <div style="display:flex;align-items:center;gap:8px">
            <a href="{{ route('admin.cbt.sessions.export', ['session' => $session, 'format' => 'xlsx']) }}" class="btn btn-secondary btn-sm" title="Export Excel">
                <i class="fas fa-file-excel" style="color:var(--ok)"></i> Export Excel
            </a>
            <a href="{{ route('admin.cbt.sessions.export', ['session' => $session, 'format' => 'csv']) }}" class="btn btn-secondary btn-sm" title="Export CSV">
                <i class="fas fa-file-csv" style="color:var(--navy)"></i> Export CSV
            </a>
            <form method="POST" action="{{ route('admin.cbt.sessions.destroy', $session) }}" onsubmit="return confirm('Hapus seluruh sesi ujian ini?')" style="display:inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-secondary btn-sm" style="color:var(--bad)" title="Hapus Sesi">
                    <i class="fas fa-trash"></i>
                </button>
            </form>
        </div>
    </div>
</div>

{{-- Operational Control Panel --}}
<div class="grid grid-3" style="margin-bottom:24px">
    {{-- Update Status Form --}}
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-toggle-on" style="color:var(--navy);margin-right:8px"></i>Ubah Status Sesi</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.cbt.sessions.update-status', $session) }}">
                @csrf
                <div style="margin-bottom:12px">
                    <label class="form-label">Status Operasional</label>
                    <select name="status" class="form-control" style="width:100%">
                        <option value="draft" @selected($session->status === 'draft')>Draf</option>
                        <option value="published" @selected($session->status === 'published')>Dipublikasikan</option>
                        <option value="running" @selected($session->status === 'running')>Sedang Berjalan</option>
                        <option value="completed" @selected($session->status === 'completed')>Selesai</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-sm">
                    <i class="fas fa-save"></i> Perbarui Status
                </button>
            </form>
        </div>
    </div>

    {{-- Force Complete --}}
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-bolt" style="color:var(--bad);margin-right:8px"></i>Tindakan Darurat</h3>
        </div>
        <div class="card-body">
            <p style="font-size:12px;color:var(--muted);margin:0 0 12px">
                Paksa finalisasi semua siswa yang belum mengumpulkan dan akhiri sesi ujian ini secara instan.
            </p>
            <form method="POST" action="{{ route('admin.cbt.sessions.force-complete', $session) }}" onsubmit="return confirm('Peringatan: Tindakan ini akan mengakhiri ujian bagi seluruh siswa dan menilai jawaban yang ada.')">
                @csrf
                <button type="submit" class="btn btn-danger btn-block btn-sm">
                    <i class="fas fa-flag-checkered"></i> Paksa Selesai Semua
                </button>
            </form>
        </div>
    </div>

    {{-- Schedule Info --}}
    <div class="card">
        <div class="card-header">
            <h3><i class="far fa-clock" style="color:var(--gold);margin-right:8px"></i>Jadwal Server</h3>
        </div>
        <div class="card-body" style="font-size:13px">
            <div style="display:flex;justify-content:space-between;margin-bottom:6px">
                <span style="color:var(--muted)">Buka:</span>
                <span style="font-weight:600">{{ $session->opens_at?->format('d/m/Y H:i') ?? '-' }}</span>
            </div>
            <div style="display:flex;justify-content:space-between;margin-bottom:6px">
                <span style="color:var(--muted)">Tutup:</span>
                <span style="font-weight:600">{{ $session->closes_at?->format('d/m/Y H:i') ?? '-' }}</span>
            </div>
            <div style="display:flex;justify-content:space-between">
                <span style="color:var(--muted)">Peserta:</span>
                <span style="font-weight:700;color:var(--navy)">{{ count($session->participant_ids ?? []) }} Siswa</span>
            </div>
        </div>
    </div>
</div>

{{-- Results Table --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-users-viewfinder" style="color:var(--navy);margin-right:8px"></i>Hasil & Pengerjaan Siswa ({{ $results->count() }})</h3>
        <span class="tag tag-secondary">{{ $results->whereNotNull('submitted_at')->count() }} Selesai Dinilai</span>
    </div>

    <div class="card-body tight">
        @if($results->isEmpty())
            <div class="empty-state">
                <div class="icon">👥</div>
                <h4>Belum Ada Hasil Ujian</h4>
                <p>Belum ada siswa yang memulai ujian pada sesi ini.</p>
            </div>
        @else
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width:5%">No</th>
                            <th style="width:30%">Nama Siswa</th>
                            <th style="width:20%">Jawaban Tersimpan</th>
                            <th style="width:15%">Deadline</th>
                            <th style="width:15%">Status</th>
                            <th style="width:15%;text-align:right">Nilai Akhir</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($results as $index => $res)
                            @php
                                $totalSoal = count($session->question_snapshot ?? []);
                                $ansCount = count($res->answers ?? []);
                                $percent = $totalSoal > 0 ? round(($ansCount / $totalSoal) * 100) : 0;
                            @endphp
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>
                                    <div style="font-weight:700;font-size:13.5px;color:var(--text)">
                                        {{ $res->siswa?->nama_peserta_didik ?? 'Siswa Terhapus' }}
                                    </div>
                                    <div style="font-size:11px;color:var(--muted)">
                                        NIS: {{ $res->siswa?->nis ?? '-' }} • NISN: {{ $res->siswa?->nisn ?? '-' }}
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size:12px;font-weight:600;margin-bottom:2px">
                                        {{ $ansCount }} / {{ $totalSoal }} butir ({{ $percent }}%)
                                    </div>
                                    <div class="bar">
                                        <span class="fill {{ $percent === 100 ? 'fill-ok' : '' }}" style="width:{{ $percent }}%"></span>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-family:monospace;font-size:12px">
                                        {{ $res->deadline_at?->format('d/m H:i:s') ?? '-' }}
                                    </span>
                                </td>
                                <td>
                                    @if($res->submitted_at)
                                        <span class="tag tag-ok"><i class="fas fa-check-circle"></i> Selesai</span>
                                    @else
                                        <span class="tag tag-primary"><i class="fas fa-pencil"></i> Mengerjakan</span>
                                    @endif
                                </td>
                                <td style="text-align:right">
                                    @if(!is_null($res->score))
                                        <span style="font-size:15px;font-weight:800;color:var(--navy)">
                                            {{ number_format($res->score, 1) }}
                                        </span>
                                    @else
                                        <span style="color:var(--muted)">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
