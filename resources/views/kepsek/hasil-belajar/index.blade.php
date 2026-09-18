@extends('layouts.app')

@section('title', 'Hasil Belajar - SIMANTAP')

@section('sidebar')
    @include('kepsek.partials.sidebar')
@endsection

@section('page_title', 'Hasil Belajar')
@section('page_subtitle', 'Analisis lintas kelas dan perbandingan capaian')

@section('content')
    <div class="grid grid-4" style="margin-bottom:20px">
        <div class="stat-card primary">
            <i class="fas fa-chart-line stat-icon"></i>
            <div class="stat-label">Rata-rata Sekolah</div>
            <div class="stat-value">{{ number_format($rataRataSekolah ?? 0, 1) }}</div>
        </div>
        <div class="stat-card gold">
            <i class="fas fa-trophy stat-icon"></i>
            <div class="stat-label">Kelas Tertinggi</div>
            <div class="stat-value">{{ $kelasTertinggi ?? '—' }}</div>
            <div class="stat-change">{{ number_format($nilaiTertinggi ?? 0, 1) }}</div>
        </div>
        <div class="stat-card ok">
            <i class="fas fa-check-circle stat-icon"></i>
            <div class="stat-label">Ketuntasan Rata-rata</div>
            <div class="stat-value">{{ number_format($ketuntasanRataRata ?? 0, 1) }}%</div>
        </div>
        <div class="stat-card {{ ($ketuntasanRataRata ?? 0) < 75 ? 'bad' : 'ok' }}">
            <i class="fas fa-exclamation-circle stat-icon"></i>
            <div class="stat-label">Kelas di Bawah KKM</div>
            <div class="stat-value">{{ $jumlahKelasRendah ?? 0 }}</div>
            <div class="stat-change">perlu perhatian</div>
        </div>
    </div>

    <div class="grid grid-2" style="margin-bottom:20px">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-bar" style="margin-right:8px;color:var(--navy)"></i>Perbandingan Rata-rata Per Kelas</h3>
            </div>
            <div class="card-body">
                <div class="chart-container large">
                    <canvas id="chartPerbandingan"></canvas>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-line" style="margin-right:8px;color:var(--gold)"></i>Tren Perkembangan Lintas Kelas</h3>
            </div>
            <div class="card-body">
                <div class="chart-container large">
                    <canvas id="chartTren"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-2" style="margin-bottom:20px">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-pie" style="margin-right:8px;color:var(--navy)"></i>Sebaran Predikat Per Kelas</h3>
            </div>
            <div class="card-body">
                <div class="chart-container large">
                    <canvas id="chartPredikatKelas"></canvas>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-sort-amount-down" style="margin-right:8px;color:var(--bad)"></i>Ketuntasan Per Kelas</h3>
            </div>
            <div class="card-body">
                <div class="chart-container large">
                    <canvas id="chartKetuntasan"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:20px">
        <div class="card-header">
            <h3><i class="fas fa-table" style="margin-right:8px;color:var(--navy)"></i>Tabel Perbandingan Lintas Kelas</h3>
        </div>
        <div class="card-body tight">
            @if(isset($tabelPerbandingan) && count($tabelPerbandingan))
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Kelas</th>
                                <th>Wali Kelas</th>
                                <th style="text-align:right">Siswa</th>
                                <th style="text-align:right">Rata-rata</th>
                                <th style="text-align:right">Tertinggi</th>
                                <th style="text-align:right">Terendah</th>
                                <th>Ketuntasan</th>
                                <th style="text-align:right">Kehadiran</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tabelPerbandingan as $t)
                                <tr>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:10px">
                                            <span class="avatar" style="background:var(--navy);color:#fff;font-size:11px">{{ substr($t->nama_kelas ?? $t->kelas ?? 'K', 0, 2) }}</span>
                                            <b>{{ $t->nama_kelas ?? $t->kelas ?? '—' }}</b>
                                        </div>
                                    </td>
                                    <td>{{ $t->nama_wali ?? $t->nama_guru ?? '—' }}</td>
                                    <td style="text-align:right"><b>{{ $t->jumlah_siswa ?? 0 }}</b></td>
                                    <td style="text-align:right"><b>{{ number_format($t->rata_rata ?? 0, 1) }}</b></td>
                                    <td style="text-align:right;color:var(--ok)">{{ number_format($t->tertinggi ?? 0, 1) }}</td>
                                    <td style="text-align:right;color:var(--bad)">{{ number_format($t->terendah ?? 0, 1) }}</td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:8px">
                                            <div class="bar" style="flex:1">
                                                <div class="fill {{ ($t->ketuntasan_persen ?? 0) >= 80 ? 'fill-ok' : (($t->ketuntasan_persen ?? 0) >= 60 ? 'fill-warn' : 'fill-bad') }}" style="width:{{ min($t->ketuntasan_persen ?? 0, 100) }}%"></div>
                                            </div>
                                            <span style="font-size:12px;font-weight:600">{{ number_format($t->ketuntasan_persen ?? 0, 1) }}%</span>
                                        </div>
                                    </td>
                                    <td style="text-align:right">{{ number_format($t->kehadiran_persen ?? 0, 1) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">
                    <div class="icon">📊</div>
                    <h4>Belum ada data</h4>
                    <p>Data perbandingan akan muncul setelah semua kelas memiliki data.</p>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const kelasLabels = {!! json_encode($chartKelasLabels ?? ['Kelas 1', 'Kelas 2', 'Kelas 3', 'Kelas 4', 'Kelas 5', 'Kelas 6']) !!};
            const kelasRataRata = {!! json_encode($chartKelasRataRata ?? [75, 78, 72, 80, 76, 82]) !!};

            const ctx1 = document.getElementById('chartPerbandingan').getContext('2d');
            new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: kelasLabels,
                    datasets: [{
                        label: 'Rata-rata Nilai',
                        data: kelasRataRata,
                        backgroundColor: kelasLabels.map(function(_, i) {
                            return kelasRataRata[i] >= 70 ? 'rgba(18,128,92,0.75)' : 'rgba(180,35,24,0.75)';
                        }),
                        borderWidth: 0,
                        borderRadius: 6
                    }, {
                        label: 'KKM',
                        data: Array(kelasLabels.length).fill(70),
                        type: 'line',
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

            const ctx2 = document.getElementById('chartTren').getContext('2d');
            const trenDatasets = {!! json_encode($chartTrenDatasets ?? [
                ['Kelas 1', [72, 75, 78, 76, 80, 82]],
                ['Kelas 2', [70, 73, 76, 74, 78, 80]],
                ['Kelas 3', [68, 71, 74, 72, 76, 78]]
            ]) !!};
            const warna = ['#1F3864', '#B8860B', '#12805C', '#B42318', '#667085', '#2B4A80'];
            const datasets = trenDatasets.map(function(item, i) {
                return {
                    label: item[0],
                    data: item[1],
                    borderColor: warna[i % warna.length],
                    borderWidth: 2,
                    tension: 0.35,
                    pointRadius: 3,
                    fill: false
                };
            });
            new Chart(ctx2, {
                type: 'line',
                data: {
                    labels: {!! json_encode($chartTrenLabels ?? ['PTS 1', 'UH 1', 'Tugas 1', 'PTS 2', 'UH 2', 'PAS']) !!},
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { labels: { boxWidth: 12, padding: 14, font: { size: 11 } } } },
                    scales: {
                        y: { min: 0, max: 100, grid: { color: '#EDF0F5' }, ticks: { stepSize: 20 } },
                        x: { grid: { display: false } }
                    }
                }
            });

            const ctx3 = document.getElementById('chartPredikatKelas').getContext('2d');
            new Chart(ctx3, {
                type: 'bar',
                data: {
                    labels: kelasLabels,
                    datasets: [
                        { label: 'A', data: {!! json_encode($chartPredikatA ?? [3, 4, 2, 5, 3, 4]) !!}, backgroundColor: '#12805C', borderRadius: 4 },
                        { label: 'B', data: {!! json_encode($chartPredikatB ?? [5, 4, 6, 3, 5, 4]) !!}, backgroundColor: '#1F3864', borderRadius: 4 },
                        { label: 'C', data: {!! json_encode($chartPredikatC ?? [2, 2, 3, 1, 2, 3]) !!}, backgroundColor: '#B8860B', borderRadius: 4 },
                        { label: 'D', data: {!! json_encode($chartPredikatD ?? [1, 0, 2, 0, 1, 0]) !!}, backgroundColor: '#B42318', borderRadius: 4 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 11, padding: 12, font: { size: 11 } } } },
                    scales: {
                        x: { stacked: true, grid: { display: false } },
                        y: { stacked: true, grid: { color: '#EDF0F5' } }
                    }
                }
            });

            const ctx4 = document.getElementById('chartKetuntasan').getContext('2d');
            new Chart(ctx4, {
                type: 'bar',
                data: {
                    labels: kelasLabels,
                    datasets: [{
                        label: 'Ketuntasan %',
                        data: {!! json_encode($chartKetuntasanData ?? [85, 90, 70, 95, 80, 88]) !!},
                        backgroundColor: {!! json_encode($chartKetuntasanData ?? [85, 90, 70, 95, 80, 88]) !!}.map(function(v) {
                            return v >= 80 ? 'rgba(18,128,92,0.75)' : (v >= 60 ? 'rgba(181,71,8,0.75)' : 'rgba(180,35,24,0.75)');
                        }),
                        borderWidth: 0,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { min: 0, max: 100, grid: { color: '#EDF0F5' }, ticks: { callback: function(v) { return v + '%'; } } },
                        y: { grid: { display: false } }
                    }
                }
            });
        });
    </script>
    @endpush
@endsection
