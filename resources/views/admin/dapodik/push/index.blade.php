@extends('layouts.app')

@section('title', 'Push Data ke Dapodik - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Push Data ke Dapodik')
@section('page_subtitle', 'Kirim data master dari SIMANTAP ke Server Dapodik Pusat')

@section('content')
    <div class="grid grid-4" style="margin-bottom:24px">
        <div class="stat-card">
            <div class="stat-value">{{ $stats['configured'] ?? false ? 'Terkonfigurasi' : 'Belum Konfigurasi' }}</div>
            <div class="stat-label">Koneksi Dapodik</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $stats['last_sync'] ?? 'Belum pernah' }}</div>
            <div class="stat-label">Sinkron Terakhir</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $stats['last_sync_by'] ?? '-' }}</div>
            <div class="stat-label">Oleh</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $stats['last_sync_at'] ?? '-' }}</div>
            <div class="stat-label">Waktu Sync</div>
        </div>
    </div>

    <div class="grid grid-2" style="margin-bottom:24px">
        @foreach($menus as $menu)
            <a href="{{ $menu['route'] }}" class="card push-card" style="text-decoration:none;color:inherit;cursor:pointer">
                <div class="card-body" style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:120px;text-align:center">
                    <div class="icon-wrapper" style="width:64px;height:64px;border-radius:12px;background:var(--primary-100);display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
                        <i class="{{ $menu['icon'] }}" style="font-size:28px;color:var(--primary)"></i>
                    </div>
                    <h4 style="margin:0 0 8px;font-size:16px;font-weight:600">{{ $menu['label'] }}</h4>
                    <p class="text-sm" style="color:var(--muted);margin:0">{{ $menu['description'] }}</p>
                </div>
            </a>
        @endforeach
    </div>

    <div class="card" style="margin-top:24px">
        <div class="card-header">
            <h3><x-lucide-activity class="w-5 h-5 mr-2" /> Status Koneksi & Log</div>
        </div>
        <div class="card-body">
            <div class="grid grid-4" style="margin-bottom:16px">
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
            
            <div class="card" style="margin-top:16px">
                <div class="card-header">
                    <h4>Log Sinkronisasi Terbaru</h4>
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
                                        <td style="padding:11px 14px"><span class="tag {{ $log->tipe === 'siswa' ? 'tag-primary' : ($log->tipe === 'gtk' ? 'tag-primary' : ($log->tipe === 'rombel' ? 'tag-ok' : ($log->tipe === 'sekolah' ? 'tag-gold' : 'tag-mut'))) }}">{{ $log->tipe }}</span></td>
                                        <td style="text-align:right;padding:11px 14px">{{ $log->berhasil }}</td>
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
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>