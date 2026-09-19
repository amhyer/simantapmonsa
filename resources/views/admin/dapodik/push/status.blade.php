@extends('layouts.app')

@section('title', 'Status Push Dapodik - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Status Push Dapodik')
@section('page_subtitle', 'Monitor status koneksi dan log push ke server Dapodik')

@section('content')
    <div class="grid grid-4" style="margin-bottom:24px">
        <div class="stat-card">
            <div class="stat-value">{{ $status['configured'] ? 'Terkonfigurasi' : 'Belum' }}</div>
            <div class="stat-label">Konfigurasi</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $status['last_sync'] ?? 'Belum pernah' }}</div>
            <div class="stat-label">Sinkron Terakhir</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $status['last_sync_by'] ?? '-' }}</div>
            <div class="stat-label">Oleh</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $status['configured'] ? 'Siap' : 'Belum' }}</div>
            <div class="stat-label">Status</div>
        </div>
    </div>

    <div class="card" style="margin-bottom:24px">
        <div class="card-header">
            <h3><x-lucide-activity class="w-5 h-5 mr-2" /> Status Koneksi Dapodik</h3>
        </div>
        <div class="card-body">
            <div class="grid grid-4" style="margin-bottom:16px">
                <div class="stat-card"><div class="stat-value">{{ $status['npsn'] ?? 'Belum diset' }}</div><div class="stat-label">NPSN Sekolah</div></div>
                <div class="stat-card"><div class="stat-value">{{ $status['host'] ?? 'localhost' }}</div><div class="stat-label">Host Server</div></div>
                <div class="stat-card"><div class="stat-value">{{ $status['port'] ?? '5774' }}</div><div class="stat-label">Port</div></div>
                <div class="stat-card"><div class="stat-value">{{ $status['protocol'] ?? 'http' }}</div><div class="stat-label">Protokol</div></div>
            </div>
            
            <div style="margin-top:16px;padding:12px;background:#FAFBFD;border:1px solid #E4E7EC;border-radius:8px">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
                    <x-lucide-{{ $status['configured'] ? 'check-circle' : 'alert-circle' }} class="w-5 h-5 {{ $status['configured'] ? 'text-green-600' : 'text-red-600' }}" />
                    <strong>{{ $status['configured'] ? 'Konfigurasi Lengkap - Siap Push' : 'Konfigurasi Belum Lengkap' }}</div>
                </div>
                @if(!$status['configured'])
                    <div class="text-sm" style="color:#667085">Silakan lengkapi konfigurasi di <a href="{{ route('admin.dapodik.index') }}" class="text-primary underline">Menu Dapodik</a> (NPSN, Token, Host, Port)</div>
                @endif
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:24px">
        <div class="card-header">
            <h3><x-lucide-history class="w-5 h-5 mr-2" /> Log Sinkronisasi Terbaru</h3>
        </div>
        <div class="card-body">
            @if($logs->count())
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Waktu</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Tipe</th>
                                <th style="padding:11px 14px;text-align:right;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Berhasil</th>
                                <th style="padding:11px 14px;text-align:right;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Gagal</th>
                                <th style="padding:11px 14px;text-align:right;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Durasi</th>
                                <th style="padding:11px 14px;text-align:right;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Oleh</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($logs as $log)
                                <tr style="border-bottom:1px solid #E4E7EC">
                                    <td style="padding:11px 14px;font-size:13px">{{ $log->created_at->diffForHumans() }}</td>
                                    <td style="padding:11px 14px">
                                        <span class="tag {{ $log->tipe === 'siswa' ? 'tag-primary' : ($log->tipe === 'gtk' ? 'tag-primary' : ($log->tipe === 'rombel' ? 'tag-ok' : ($log->tipe === 'sekolah' ? 'tag-gold' : 'tag-mut'))) }}">{{ $log->tipe }}</span>
                                    </td>
                                    <td style="padding:11px 14px;text-align:right"><b>{{ $log->berhasil }}</b></td>
                                    <td style="padding:11px 14px;text-align:right">{{ $log->gagal }}</td>
                                    <td style="padding:11px 14px;text-align:right">{{ $log->duration_seconds }} detik</td>
                                    <td style="padding:11px 14px;text-align:right">{{ $log->user?->nama_lengkap ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">
                    <div class="icon">📋</div>
                    <h4>Belum ada log sinkronisasi</h4>
                    <p>Log akan muncul setelah melakukan sinkronisasi/push pertama kali.</p>
                </div>
            @endif
        </div>
    </div>

    <div class="card" style="margin-top:24px">
        <div class="card-header">
            <h3><x-lucide-database class="w-5 h-5 mr-2" /> Status Data Siap Push</h3>
        </div>
        <div class="card-body">
            <div class="grid grid-4" style="margin-bottom:16px">
                <div class="stat-card"><div class="stat-value">{{ \App\Models\SekolahSettings::first()?->npsn ? 'Ada' : 'Kosong' }}</div><div class="stat-label">Data Sekolah</div></div>
                <div class="stat-card"><div class="stat-value">{{ \App\Models\User::where('peran','guru')->whereNotNull('dapodik_id')->count() }}</div><div class="stat-label">Guru Siap Push</div></div>
                <div class="stat-card"><div class="stat-value">{{ \App\Models\Siswa::whereNotNull('dapodik_id')->whereNull('archived_at')->count() }}</div><div class="stat-label">Siswa Siap Push</div></div>
                <div class="stat-card"><div class="stat-value">{{ \App\Models\Rombel::whereNotNull('dapodik_id')->count() }}</div><div class="stat-label">Rombel Siap Push</div></div>
            </div>
        </div>
    </div>
</section>