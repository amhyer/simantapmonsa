@extends('layouts.app')

@section('title', 'Log Aktivitas - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Log Aktivitas')
@section('page_subtitle', 'Riwayat aktivitas seluruh pengguna sistem')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Aktivitas Terbaru</h3>
            <span class="tag tag-mut">{{ $logs->total() }} aktivitas</span>
        </div>
        <div class="card-body tight">
            @if($logs->count())
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Waktu</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Pengguna</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Jenis</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Judul</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Deskripsi</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Tabel</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($logs as $log)
                                @php
                                    $jenisColors = [
                                        'tambah' => 'tag-ok',
                                        'ubah' => 'tag-gold',
                                        'hapus' => 'tag-bad',
                                        'lihat' => 'tag-primary',
                                        'login' => 'tag-mut',
                                    ];
                                    $jenisLabel = [
                                        'tambah' => 'Tambah',
                                        'ubah' => 'Ubah',
                                        'hapus' => 'Hapus',
                                        'lihat' => 'Lihat',
                                        'login' => 'Login',
                                    ];
                                @endphp
                                <tr>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;white-space:nowrap;font-size:13px">
                                        {{ $log->created_at->format('d M Y') }}
                                        <br><span style="color:var(--muted);font-size:12px">{{ $log->created_at->format('H:i:s') }}</span>
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                        <div style="display:flex;align-items:center;gap:8px">
                                            <span class="avatar" style="width:28px;height:28px;font-size:11px">{{ $log->guru ? substr($log->nama_guru ?? $log->guru->nama_lengkap, 0, 2) : '?' }}</span>
                                            <span style="font-weight:600;font-size:13px">{{ $log->nama_guru ?? $log->guru?->nama_lengkap ?? '-' }}</span>
                                        </div>
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                        <span class="tag {{ $jenisColors[$log->jenis] ?? 'tag-mut' }}">{{ $jenisLabel[$log->jenis] ?? $log->jenis }}</span>
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;font-weight:600;font-size:13px">
                                        {{ $log->judul ?? '-' }}
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;font-size:13px;color:var(--muted);max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                        {{ $log->deskripsi ?? '-' }}
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                        @if($log->tabel_terkait)
                                            <code style="background:#EEF2F9;padding:2px 8px;border-radius:6px;font-size:12px">{{ $log->tabel_terkait }}</code>
                                        @else
                                            <span style="color:var(--muted)">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div style="padding:16px 14px;display:flex;justify-content:center">
                    {{ $logs->links() }}
                </div>
            @else
                <div class="empty"><div class="icon">📋</div><b>Belum ada aktivitas</b><p>Log aktivitas akan muncul di sini setelah pengguna mulai menggunakan sistem.</p></div>
            @endif
        </div>
    </div>
@endsection
