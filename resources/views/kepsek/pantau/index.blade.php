@extends('layouts.app')

@section('title', 'Pantau Aktivitas Guru - SIMANTAP')

@section('sidebar')
    @include('kepsek.partials.sidebar')
@endsection

@section('page_title', 'Pantau Aktivitas Guru')
@section('page_subtitle', 'Monitoring aktivitas guru secara real-time')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="card" style="margin-bottom:16px">
    <div class="card-header"><h3>🔍 Filter</h3></div>
    <div class="card-body">
        <form method="GET" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
            <div>
                <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Guru</label>
                <select name="guru_id" style="padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px;min-width:200px">
                    <option value="">Semua Guru</option>
                    @foreach($guru as $g)
                        <option value="{{ $g->id }}" {{ $filterGuru == $g->id ? 'selected' : '' }}>{{ $g->nama_lengkap }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Tanggal</label>
                <input type="date" name="tanggal" value="{{ $filterTanggal }}" style="padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
            </div>
            <button type="submit" class="btn btn-sm" style="background:#1F3864;color:#fff">🔍 Filter</button>
        </form>
    </div>
</div>

<div class="grid grid-2" style="margin-bottom:16px">
    <div class="card">
        <div class="card-header"><h3>👨‍🏫 Statistik Guru</h3></div>
        <div class="card-body tight">
            @if($statistikGuru->count())
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Guru</th>
                                <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Aktivitas</th>
                                <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Nilai</th>
                                <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Materi</th>
                                <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Kuis</th>
                                <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Siswa</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($statistikGuru as $item)
                                <tr style="border-bottom:1px solid #F2F4F7">
                                    <td style="padding:8px 12px">
                                        <div style="display:flex;align-items:center;gap:8px">
                                            <span style="width:28px;height:28px;border-radius:50%;background:#EEF2FF;color:#4338CA;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700">{{ substr($item['guru']->nama_lengkap, 0, 2) }}</span>
                                            <div>
                                                <div style="font-weight:600;font-size:12px">{{ $item['guru']->nama_lengkap }}</div>
                                                <div style="font-size:11px;color:#667085">{{ $item['guru']->kelas_mata_pelajaran ?? '-' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding:8px 12px;text-align:center"><span style="background:#EEF2FF;color:#4338CA;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600">{{ $item['total_aktivitas'] }}</span></td>
                                    <td style="padding:8px 12px;text-align:center"><span style="background:#D1FAE5;color:#065F46;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600">{{ $item['total_nilai'] }}</span></td>
                                    <td style="padding:8px 12px;text-align:center"><span style="background:#DBEAFE;color:#1E40AF;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600">{{ $item['total_materi'] }}</span></td>
                                    <td style="padding:8px 12px;text-align:center"><span style="background:#FEF3C7;color:#92400E;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600">{{ $item['total_kuis'] }}</span></td>
                                    <td style="padding:8px 12px;text-align:center;font-weight:700">{{ $item['total_siswa'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div style="text-align:center;padding:30px;color:#888">Belum ada data guru</div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>📋 Log Aktivitas Terbaru</h3></div>
        <div class="card-body tight">
            @if($aktivitas->count())
                @foreach($aktivitas->take(15) as $a)
                    <div style="padding:10px 12px;border-bottom:1px solid #F2F4F7;display:flex;gap:10px;align-items:start">
                        <div style="width:8px;height:8px;border-radius:50%;background:#1F3864;margin-top:6px;flex-shrink:0"></div>
                        <div>
                            <div style="font-weight:600;font-size:13px">{{ $a->nama_pengguna ?? 'Sistem' }}</div>
                            <div style="font-size:12px;color:#667085">{{ $a->kegiatan }} — {{ $a->rincian ?? '-' }}</div>
                            <div style="font-size:11px;color:#888;margin-top:2px">{{ $a->waktu ? \Carbon\Carbon::parse($a->waktu)->diffForHumans() : '-' }}</div>
                        </div>
                    </div>
                @endforeach
                <div style="padding:10px;text-align:center">{{ $aktivitas->links() }}</div>
            @else
                <div style="text-align:center;padding:30px;color:#888">Belum ada aktivitas</div>
            @endif
        </div>
    </div>
</div>
@endsection
