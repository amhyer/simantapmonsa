@extends('layouts.app')

@section('title', 'Detail Log Sinkronisasi - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Detail Log Sinkronisasi')
@section('page_subtitle', $log->nama_sekolah ?? 'Log #' . $log->id)

@section('content')
<div class="card" style="margin-bottom:16px">
    <div class="card-header"><h3>📋 Info Log</h3><a href="{{ route('admin.dapodik.index') }}" class="btn btn-ghost btn-sm">← Kembali</a></div>
    <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div>
                <div style="font-size:12px;color:#667085;margin-bottom:2px">Sekolah</div>
                <div style="font-weight:600">{{ $log->nama_sekolah ?? '-' }}</div>
                <div style="font-size:12px;color:#667085">NPSN: {{ $log->sekolah_npsn ?? '-' }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:#667085;margin-bottom:2px">User</div>
                <div style="font-weight:600">{{ $log->user?->nama_lengkap ?? 'Sistem' }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:#667085;margin-bottom:2px">Tipe</div>
                <div><span class="tag tag-info">{{ ucfirst($log->tipe) }}</span></div>
            </div>
            <div>
                <div style="font-size:12px;color:#667085;margin-bottom:2px">Tahun Ajaran / Semester</div>
                <div>{{ $log->tahun_ajaran ?? '-' }} / {{ $log->semester ?? '-' }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:#667085;margin-bottom:2px">Waktu Mulai</div>
                <div>{{ $log->started_at?->format('d/m/Y H:i:s') ?? '-' }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:#667085;margin-bottom:2px">Durasi</div>
                <div>{{ $log->duration_seconds ? $log->duration_seconds . ' detik' : '-' }}</div>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-4" style="margin-bottom:16px">
    <div class="stat-card primary"><div class="stat-value">{{ $log->total_data }}</div><div class="stat-label">Total Data</div></div>
    <div class="stat-card ok"><div class="stat-value">{{ $log->berhasil }}</div><div class="stat-label">Baru</div></div>
    <div class="stat-card gold"><div class="stat-value">{{ $log->diperbarui }}</div><div class="stat-label">Diperbarui</div></div>
    <div class="stat-card {{ $log->gagal > 0 ? 'bad' : 'ok' }}"><div class="stat-value">{{ $log->gagal }}</div><div class="stat-label">Gagal</div></div>
</div>

@if($log->errors && count($log->errors) > 0)
    <div class="card">
        <div class="card-header"><h3>⚠️ Error Details</h3></div>
        <div class="card-body">
            <div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:8px;padding:12px">
                @foreach($log->errors as $err)
                    <div style="font-size:13px;color:#991B1B;padding:4px 0;border-bottom:1px solid #FECACA">• {{ $err }}</div>
                @endforeach
            </div>
        </div>
    </div>
@endif

@if($log->summary)
    <div class="card" style="margin-top:16px">
        <div class="card-header"><h3>📊 Summary</h3></div>
        <div class="card-body">
            <pre style="background:#F9FAFB;border:1px solid #E4E7EC;border-radius:8px;padding:12px;font-size:13px">{{ json_encode($log->summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>
    </div>
@endif
@endsection
