@extends('layouts.app')

@section('title', 'Detail Kelas - SIMANTAP')

@section('sidebar')
    @include('kepsek.partials.sidebar')
@endsection

@section('page_title', 'Kelas ' . ($guru->nama_lengkap ?? 'Guru'))
@section('page_subtitle', 'Peta kelas wali ' . ($guru->nama_lengkap ?? ''))

@section('header_actions')
    <a href="{{ route('kepsek.peta-kelas.index') }}" class="btn btn-ghost btn-sm">
        <i class="fas fa-arrow-left"></i> Kembali
    </a>
@endsection

@section('content')
    <div class="grid grid-4" style="margin-bottom:20px">
        <div class="stat-card primary">
            <i class="fas fa-users stat-icon"></i>
            <div class="stat-label">Total Siswa</div>
            <div class="stat-value">{{ count($siswaList ?? []) }}</div>
        </div>
        <div class="stat-card gold">
            <i class="fas fa-chart-line stat-icon"></i>
            <div class="stat-label">Rata-rata Nilai</div>
            <div class="stat-value">{{ number_format($kelas->rata_rata ?? 0, 1) }}</div>
        </div>
        <div class="stat-card {{ ($kelas->ketuntasan_persen ?? 0) >= 75 ? 'ok' : 'bad' }}">
            <i class="fas fa-check-circle stat-icon"></i>
            <div class="stat-label">Ketuntasan</div>
            <div class="stat-value">{{ number_format($kelas->ketuntasan_persen ?? 0, 1) }}%</div>
            <div class="stat-change">{{ $kelas->tuntas ?? 0 }} tuntas · {{ $kelas->belum ?? 0 }} belum</div>
        </div>
        <div class="stat-card ok">
            <i class="fas fa-calendar-check stat-icon"></i>
            <div class="stat-label">Kehadiran</div>
            <div class="stat-value">{{ number_format($kelas->kehadiran_persen ?? 0, 1) }}%</div>
        </div>
    </div>

    <div class="grid grid-2" style="margin-bottom:20px">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-line" style="margin-right:8px;color:var(--navy)"></i>Perkembangan Nilai</h3>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="chartPerkembangan"></canvas>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-pie" style="margin-right:8px;color:var(--gold)"></i>Sebaran Predikat</h3>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="chartPredikat"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-2" style="margin-bottom:20px">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-exclamation-triangle" style="margin-right:8px;color:var(--bad)"></i>Siswa Perlu Pendampingan</h3>
                <span class="tag {{ count($siswaPerluPendampingan ?? []) ? 'tag-bad' : 'tag-ok' }}">{{ count($siswaPerluPendampingan ?? []) }} siswa</span>
            </div>
            <div class="card-body tight">
                @if(isset($siswaPerluPendampingan) && count($siswaPerluPendampingan))
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Siswa</th>
                                    <th style="text-align:right">Nilai</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($siswaPerluPendampingan as $s)
                                    <tr>
                                        <td>
                                            <div style="display:flex;align-items:center;gap:10px">
                                                <span class="avatar" style="background:var(--bad);color:#fff">{{ substr($s->nama ?? 'S', 0, 2) }}</span>
                                                <div>
                                                    <b>{{ $s->nama ?? $s->nama_peserta_didik ?? '—' }}</b>
                                                    <div style="font-size:11px;color:var(--muted)">NIS {{ $s->nis ?? '—' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="text-align:right"><b style="color:var(--bad)">{{ number_format($s->nilai ?? 0, 1) }}</b></td>
                                        <td><span class="tag tag-bad">{{ $s->keterangan ?? 'Di bawah KKM' }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="empty-state">
                        <div class="icon">🎉</div>
                        <h4>Semua siswa tuntas</h4>
                        <p>Tidak ada siswa yang memerlukan pendampingan.</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-star" style="margin-right:8px;color:var(--gold)"></i>Siswa Berprestasi</h3>
            </div>
            <div class="card-body tight">
                @if(isset($siswaBerprestasi) && count($siswaBerprestasi))
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Siswa</th>
                                    <th style="text-align:right">Nilai</th>
                                    <th>Predikat</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($siswaBerprestasi as $s)
                                    <tr>
                                        <td>
                                            <div style="display:flex;align-items:center;gap:10px">
                                                <span class="avatar" style="background:var(--ok);color:#fff">{{ substr($s->nama ?? 'S', 0, 2) }}</span>
                                                <div>
                                                    <b>{{ $s->nama ?? $s->nama_peserta_didik ?? '—' }}</b>
                                                    <div style="font-size:11px;color:var(--muted)">NIS {{ $s->nis ?? '—' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="text-align:right"><b style="color:var(--ok)">{{ number_format($s->nilai ?? 0, 1) }}</b></td>
                                        <td><span class="tag tag-ok">{{ $s->predikat ?? 'A' }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="empty-state">
                        <div class="icon">📘</div>
                        <h4>Belum ada data</h4>
                        <p>Belum ada siswa berprestasi tercatat.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-users" style="margin-right:8px;color:var(--navy)"></i>Daftar Siswa</h3>
            <span class="tag tag-primary">{{ count($siswaList ?? []) }} siswa</span>
        </div>
        <div class="card-body tight">
            @if(isset($siswaList) && count($siswaList))
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Siswa</th>
                                <th>NIS</th>
                                <th style="text-align:right">Rata-rata Nilai</th>
                                <th>Ketuntasan</th>
                                <th style="text-align:right">Kehadiran</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($siswaList as $i => $s)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:10px">
                                            <span class="avatar" style="background:var(--navy);color:#fff">{{ substr($s->nama ?? $s->nama_peserta_didik ?? 'S', 0, 2) }}</span>
                                            <b>{{ $s->nama ?? $s->nama_peserta_didik ?? '—' }}</b>
                                        </div>
                                    </td>
                                    <td>{{ $s->nis ?? '—' }}</td>
                                    <td style="text-align:right"><b>{{ number_format($s->rata_rata ?? 0, 1) }}</b></td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:8px">
                                            <div class="bar" style="flex:1">
                                                <div class="fill {{ ($s->ketuntasan_persen ?? 0) >= 80 ? 'fill-ok' : (($s->ketuntasan_persen ?? 0) >= 60 ? 'fill-warn' : 'fill-bad') }}" style="width:{{ min($s->ketuntasan_persen ?? 0, 100) }}%"></div>
                                            </div>
                                            <span style="font-size:12px;font-weight:600">{{ number_format($s->ketuntasan_persen ?? 0, 1) }}%</span>
                                        </div>
                                    </td>
                                    <td style="text-align:right">{{ number_format($s->kehadiran_persen ?? 0, 1) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">
                    <div class="icon">👥</div>
                    <h4>Belum ada data siswa</h4>
                    <p>Data siswa akan muncul setelah guru mengimpor data.</p>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx1 = document.getElementById('chartPerkembangan').getContext('2d');
            new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: {!! json_encode($chartLabels ?? ['PTS 1', 'UH 1', 'Tugas 1', 'PTS 2', 'UH 2', 'PAS']) !!},
                    datasets: [{
                        label: 'Rata-rata Kelas',
                        data: {!! json_encode($chartData ?? [72, 75, 78, 76, 80, 82]) !!},
                        borderColor: '#1F3864',
                        backgroundColor: 'rgba(31,56,100,.09)',
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2.5,
                        pointRadius: 4,
                        pointBackgroundColor: '#1F3864'
                    }, {
                        label: 'KKM',
                        data: {!! json_encode($chartKKM ?? [70, 70, 70, 70, 70, 70]) !!},
                        borderColor: '#B8860B',
                        borderDash: [6, 4],
                        borderWidth: 2,
                        pointRadius: 0,
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { labels: { boxWidth: 12, padding: 14, font: { size: 12 } } } },
                    scales: {
                        y: { min: 0, max: 100, grid: { color: '#EDF0F5' }, ticks: { stepSize: 20 } },
                        x: { grid: { display: false } }
                    }
                }
            });

            const ctx2 = document.getElementById('chartPredikat').getContext('2d');
            new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: ['A · Sangat Baik', 'B · Baik', 'C · Cukup', 'D · Perlu Bimbingan'],
                    datasets: [{
                        data: {!! json_encode($chartPredikatData ?? [3, 5, 2, 1]) !!},
                        backgroundColor: ['#12805C', '#1F3864', '#B8860B', '#B42318'],
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
