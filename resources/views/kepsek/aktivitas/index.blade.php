@extends('layouts.app')

@section('title', 'Pantau Aktivitas - SIMANTAP')

@section('sidebar')
    @include('kepsek.partials.sidebar')
@endsection

@section('page_title', 'Pantau Aktivitas')
@section('page_subtitle', 'Log aktivitas dan statistik penggunaan sistem')

@section('content')
    <div class="grid grid-4" style="margin-bottom:20px">
        <div class="stat-card primary">
            <i class="fas fa-users stat-icon"></i>
            <div class="stat-label">Pengguna Aktif</div>
            <div class="stat-value">{{ $penggunaAktif ?? 0 }}</div>
            <div class="stat-change">7 hari terakhir</div>
        </div>
        <div class="stat-card gold">
            <i class="fas fa-file-alt stat-icon"></i>
            <div class="stat-label">Total Aktivitas</div>
            <div class="stat-value">{{ $totalAktivitas ?? 0 }}</div>
            <div class="stat-change">semua pengguna</div>
        </div>
        <div class="stat-card ok">
            <i class="fas fa-chalkboard-teacher stat-icon"></i>
            <div class="stat-label">Guru Aktif</div>
            <div class="stat-value">{{ count($guruAktif) }}</div>
            <div class="stat-change">mengajar</div>
        </div>
        <div class="stat-card primary">
            <i class="fas fa-user-graduate stat-icon"></i>
            <div class="stat-label">Siswa Aktif</div>
            <div class="stat-value">{{ $siswaAktif ?? 0 }}</div>
            <div class="stat-change">mengerjakan tugas</div>
        </div>
    </div>

    <div class="grid grid-2" style="margin-bottom:20px">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-line" style="margin-right:8px;color:var(--navy)"></i>Aktivitas 7 Hari Terakhir</h3>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="chartAktivitas"></canvas>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-pie" style="margin-right:8px;color:var(--gold)"></i>Distribusi Aktivitas Per Role</h3>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="chartDistribusi"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-2" style="margin-bottom:20px">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list" style="margin-right:8px;color:var(--navy)"></i>Log Aktivitas Terbaru</h3>
            </div>
            <div class="card-body tight">
                @if(isset($aktivitas) && count($aktivitas))
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Waktu</th>
                                    <th>Pengguna</th>
                                    <th>Peran</th>
                                    <th>Aktivitas</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($aktivitas as $log)
                                    <tr>
                                        <td>
                                            <div style="font-size:12px;color:var(--muted)">{{ $log->created_at?->format('d M Y H:i') ?? '—' }}</div>
                                        </td>
                                        <td>
<div style="display:flex;align-items:center;gap:8px">
    <span class="avatar" style="background:var(--navy);color:#fff;font-size:11px;width:28px;height:28px">
        {{ substr($log->guru?->nama_lengkap ?? $log->nama_guru ?? 'U', 0, 2) }}
    </span>
    <b style="font-size:13px">{{ $log->guru?->nama_lengkap ?? $log->nama_guru ?? '—' }}</b>
</div>
                                        </td>
                                        <td>
@php
    $peran = $log->guru?->peran ?? '';
@endphp
                                            <span class="tag {{ $peran === 'guru' ? 'tag-primary' : ($peran === 'siswa' ? 'tag-ok' : ($peran === 'admin' ? 'tag-gold' : 'tag-mut')) }}">
                                                {{ ucfirst($peran) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span style="font-size:13px">{{ $log->jenis ?? '—' }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="empty-state">
                        <div class="icon">📋</div>
                        <h4>Belum ada log aktivitas</h4>
                        <p>Aktivitas pengguna akan tercatat di sini.</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-trophy" style="margin-right:8px;color:var(--gold)"></i>Pengguna Paling Aktif</h3>
            </div>
            <div class="card-body tight">
                @if(isset($penggunaAktifList) && count($penggunaAktifList))
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Pengguna</th>
                                    <th>Peran</th>
                                    <th style="text-align:right">Total Aktivitas</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($penggunaAktifList as $i => $p)
                                    <tr>
                                        <td>
                                            <div style="display:flex;align-items:center;gap:10px">
                                                <span class="avatar" style="background:{{ $i < 3 ? 'var(--gold)' : 'var(--navy)' }};color:#fff;font-size:11px">
                                                    {{ substr($p->nama ?? $p->user ?? 'U', 0, 2) }}
                                                </span>
                                                <div>
                                                    <b>{{ $p->nama ?? $p->user ?? '—' }}</b>
                                                    <div style="font-size:11px;color:var(--muted)">{{ $p->peran ?? '' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            @php
                                                $peran = $p->peran ?? $p->role ?? '';
                                            @endphp
                                            <span class="tag {{ $peran === 'guru' ? 'tag-primary' : ($peran === 'siswa' ? 'tag-ok' : 'tag-mut') }}">
                                                {{ ucfirst($peran) }}
                                            </span>
                                        </td>
                                        <td style="text-align:right"><b>{{ $p->total ?? $p->jumlah ?? 0 }}</b></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="empty-state">
                        <div class="icon">🏆</div>
                        <h4>Belum ada data</h4>
                        <p>Data pengguna aktif akan muncul di sini.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-bar" style="margin-right:8px;color:var(--navy)"></i>Aktivitas Per Jenis</h3>
        </div>
        <div class="card-body tight">
            @if(isset($statistikAktivitas) && count($statistikAktivitas))
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Jenis Aktivitas</th>
                                <th style="text-align:right">Jumlah</th>
                                <th>Persentase</th>
                                <th>Trend</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($statistikAktivitas as $sa)
                                <tr>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:10px">
                                            <span style="font-size:18px">{{ $sa->ikon ?? '📋' }}</span>
                                            <b>{{ $sa->jenis ?? '—' }}</b>
                                        </div>
                                    </td>
                                    <td style="text-align:right"><b>{{ $sa->jumlah ?? 0 }}</b></td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:8px">
                                            <div class="bar" style="flex:1">
                                                <div class="fill" style="width:{{ $sa->persentase ?? 0 }}%"></div>
                                            </div>
                                            <span style="font-size:12px;font-weight:600">{{ number_format($sa->persentase ?? 0, 1) }}%</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="tag tag-mut">-</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">
                    <div class="icon">📊</div>
                    <h4>Belum ada statistik</h4>
                    <p>Statistik aktivitas akan muncul setelah ada penggunaan sistem.</p>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx1 = document.getElementById('chartAktivitas').getContext('2d');
            new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: {!! json_encode($chartHariLabels ?? ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min']) !!},
                    datasets: [{
                        label: 'Guru',
                        data: {!! json_encode($chartGuruData ?? [12, 15, 14, 16, 13, 5, 2]) !!},
                        borderColor: '#1F3864',
                        backgroundColor: 'rgba(31,56,100,0.09)',
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2.5,
                        pointRadius: 4,
                        pointBackgroundColor: '#1F3864'
                    }, {
                        label: 'Siswa',
                        data: {!! json_encode($chartSiswaData ?? [25, 30, 28, 32, 27, 8, 3]) !!},
                        borderColor: '#B8860B',
                        backgroundColor: 'rgba(184,134,11,0.09)',
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2.5,
                        pointRadius: 4,
                        pointBackgroundColor: '#B8860B'
                    }, {
                        label: 'Admin',
                        data: {!! json_encode($chartAdminData ?? [3, 4, 3, 5, 4, 1, 0]) !!},
                        borderColor: '#12805C',
                        backgroundColor: 'rgba(18,128,92,0.09)',
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2.5,
                        pointRadius: 4,
                        pointBackgroundColor: '#12805C'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { labels: { boxWidth: 12, padding: 14, font: { size: 12 } } } },
                    scales: {
                        y: { min: 0, grid: { color: '#EDF0F5' } },
                        x: { grid: { display: false } }
                    }
                }
            });

            const ctx2 = document.getElementById('chartDistribusi').getContext('2d');
            new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: ['Guru', 'Siswa', 'Admin', 'Orang Tua', 'Kepsek'],
                    datasets: [{
                        data: {!! json_encode($chartDistribusiData ?? [35, 40, 5, 15, 5]) !!},
                        backgroundColor: ['#1F3864', '#B8860B', '#12805C', '#2B4A80', '#D4A017'],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '58%',
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 11, padding: 12, font: { size: 11.5 } } } }
                }
            });
        });
    </script>
    @endpush
@endsection
