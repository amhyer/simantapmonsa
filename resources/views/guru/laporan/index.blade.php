@extends('layouts.app')

@section('title', 'Laporan & Rapor - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Laporan & Rapor')
@section('page_subtitle', 'Rekap nilai seluruh siswa')

@section('header_actions')
    <a href="{{ route('guru.laporan.pdf', array_filter(['kelas' => $kelasFilter, 'semester_id' => $semesterId, 'mata_pelajaran' => $mapelFilter])) }}" class="btn btn-primary btn-sm" target="_blank">
        <i class="fas fa-file-pdf" style="margin-right:6px"></i>Download PDF
    </a>
    <button onclick="window.print()" class="btn btn-ghost btn-sm"><i class="fas fa-print" style="margin-right:6px"></i>Cetak</button>
@endsection

@section('content')
    <form method="GET" action="{{ route('guru.laporan.index') }}" style="margin-bottom:20px">
        <div class="card">
            <div class="card-body" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
                <div style="flex:1;min-width:150px">
                    <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px;color:#667085">Kelas</label>
                    <select name="kelas" onchange="this.form.submit()" style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px;font-size:13px">
                        <option value="">Semua Kelas</option>
                        @foreach($allKelas as $k)
                            <option value="{{ $k }}" {{ $kelasFilter == $k ? 'selected' : '' }}>{{ $k }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="flex:1;min-width:150px">
                    <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px;color:#667085">Semester</label>
                    <select name="semester_id" onchange="this.form.submit()" style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px;font-size:13px">
                        <option value="">Semua Semester</option>
                        @foreach($allSemesters as $s)
                            <option value="{{ $s->id }}" {{ $semesterId == $s->id ? 'selected' : '' }}>{{ $s->nama_semester ?? $s->id }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="flex:1;min-width:150px">
                    <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px;color:#667085">Mata Pelajaran</label>
                    <select name="mata_pelajaran" onchange="this.form.submit()" style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px;font-size:13px">
                        <option value="">Semua Mapel</option>
                        @foreach($allMapel as $m)
                            <option value="{{ $m }}" {{ $mapelFilter == $m ? 'selected' : '' }}>{{ $m }}</option>
                        @endforeach
                    </select>
                </div>
                @if($kelasFilter || $semesterId || $mapelFilter)
                    <a href="{{ route('guru.laporan.index') }}" class="btn btn-ghost btn-sm" style="margin-bottom:0">
                        <i class="fas fa-times" style="margin-right:4px"></i>Reset
                    </a>
                @endif
            </div>
        </div>
    </form>

    <div class="grid grid-4" style="margin-bottom:20px">
        <div class="stat-card primary">
            <i class="fas fa-users stat-icon"></i>
            <div class="stat-label">Total Siswa</div>
            <div class="stat-value">{{ $stats['total_siswa'] }}</div>
            <div class="stat-change">Aktif</div>
        </div>
        <div class="stat-card {{ $stats['rata_nilai'] >= 70 ? 'ok' : ($stats['rata_nilai'] > 0 ? 'bad' : 'primary') }}">
            <i class="fas fa-chart-line stat-icon"></i>
            <div class="stat-label">Rata-rata Nilai</div>
            <div class="stat-value">{{ $stats['rata_nilai'] > 0 ? number_format($stats['rata_nilai'], 1) : '—' }}</div>
            <div class="stat-change">KKM: 70</div>
        </div>
        <div class="stat-card ok">
            <i class="fas fa-check-circle stat-icon"></i>
            <div class="stat-label">Tuntas</div>
            <div class="stat-value">{{ $stats['tuntas'] }}</div>
            <div class="stat-change">{{ $stats['belum_tuntas'] }} belum tuntas · {{ $stats['belum_dinilai'] }} belum dinilai</div>
        </div>
        <div class="stat-card {{ $stats['rata_kehadiran'] >= 90 ? 'ok' : ($stats['rata_kehadiran'] >= 75 ? 'gold' : 'bad') }}">
            <i class="fas fa-calendar-check stat-icon"></i>
            <div class="stat-label">Rata-rata Kehadiran</div>
            <div class="stat-value">{{ $stats['rata_kehadiran'] }}%</div>
            <div class="stat-change">{{ $stats['total_siswa'] > 0 ? round($stats['rata_kehadiran'] * 0.01 * 200) : 0 }} / 200 hari</div>
        </div>
    </div>

    <div class="grid grid-2" style="margin-bottom:20px">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-bar" style="margin-right:8px;color:var(--navy)"></i>Distribusi Predikat</h3>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="chartPredikat"></canvas>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-bar" style="margin-right:8px;color:var(--gold)"></i>Rata-rata per Mata Pelajaran</h3>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="chartMapel"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-table" style="margin-right:8px;color:var(--navy)"></i>Data Laporan Siswa</h3>
            <span class="tag tag-mut">{{ count($dataLaporan) }} siswa</span>
        </div>
        <div class="card-body tight">
            @if(count($dataLaporan))
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">No</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Nama Siswa</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">NIS</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Kelas</th>
                                <th style="padding:11px 14px;text-align:right;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Nilai Akhir</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Predikat</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Mapel</th>
                                <th style="padding:11px 14px;text-align:right;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Kehadiran</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($dataLaporan as $i => $data)
                                <tr>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">{{ $i + 1 }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                        <div style="display:flex;align-items:center;gap:11px">
                                            <span class="avatar">{{ substr($data['siswa']->nama_peserta_didik, 0, 2) }}</span>
                                            <b>{{ $data['siswa']->nama_peserta_didik }}</b>
                                        </div>
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">{{ $data['siswa']->nis }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">{{ $data['siswa']->kelas }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:right">
                                        <b style="font-size:15px;{{ ($data['nilai_akhir'] ?? 0) >= 70 ? 'color:var(--ok)' : (($data['nilai_akhir'] ?? 0) > 0 ? 'color:var(--bad)' : '') }}">
                                            {{ ($data['nilai_akhir'] ?? 0) > 0 ? number_format($data['nilai_akhir'], 1) : '—' }}
                                        </b>
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">
                                        @if($data['predikat'])
                                            <span class="tag {{ $data['predikat']['kelas'] }}">{{ $data['predikat']['huruf'] }} · {{ $data['predikat']['label'] }}</span>
                                        @else
                                            <span class="tag tag-mut">—</span>
                                        @endif
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">
                                        @if($data['nilai_per_mapel']->count())
                                            <span style="font-size:12px;color:#667085">{{ $data['nilai_per_mapel']->count() }} mapel</span>
                                        @else
                                            <span class="tag tag-mut">—</span>
                                        @endif
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:right">
                                        <span style="{{ $data['kehadiran_persen'] >= 90 ? 'color:var(--ok)' : ($data['kehadiran_persen'] >= 75 ? 'color:var(--gold)' : 'color:var(--bad)') }}">
                                            <b>{{ $data['kehadiran_persen'] }}%</b>
                                        </span>
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">
                                        @if(($data['nilai_akhir'] ?? 0) >= 70)
                                            <span class="tag tag-ok">Tuntas</span>
                                        @elseif(($data['nilai_akhir'] ?? 0) > 0)
                                            <span class="tag tag-bad">Belum Tuntas</span>
                                        @else
                                            <span class="tag tag-mut">Belum Dinilai</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if(count($dataLaporan) > 0 && collect($dataLaporan)->first()['nilai_per_mapel']->count() > 0)
                    <div style="margin-top:20px">
                        <h3 style="font-size:14px;font-weight:700;margin-bottom:12px;color:var(--navy)">
                            <i class="fas fa-layer-group" style="margin-right:6px"></i>Detail Nilai per Mata Pelajaran
                        </h3>
                        <div style="overflow-x:auto">
                            <table style="width:100%;border-collapse:collapse">
                                <thead>
                                    <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                        <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">No</th>
                                        <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Nama Siswa</th>
                                        @php
                                            $allMapels = collect($dataLaporan)->flatMap(fn($d) => $d['nilai_per_mapel']->pluck('nama'))->unique()->sort()->values();
                                        @endphp
                                        @foreach($allMapels as $m)
                                            <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">{{ $m }}</th>
                                        @endforeach
                                        <th style="padding:11px 14px;text-align:right;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Rata-rata</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($dataLaporan as $i => $data)
                                        <tr>
                                            <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">{{ $i + 1 }}</td>
                                            <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC"><b>{{ $data['siswa']->nama_peserta_didik }}</b></td>
                                            @foreach($allMapels as $m)
                                                @php
                                                    $mapelData = $data['nilai_per_mapel']->firstWhere('nama', $m);
                                                @endphp
                                                <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">
                                                    @if($mapelData)
                                                        <b style="color:{{ $mapelData['rata_rata'] >= 70 ? 'var(--ok)' : 'var(--bad)' }}">{{ $mapelData['rata_rata'] }}</b>
                                                        <span style="font-size:10px;color:#667085"> ({{ $mapelData['predikat'] }})</span>
                                                    @else
                                                        <span style="color:#999">—</span>
                                                    @endif
                                                </td>
                                            @endforeach
                                            <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:right">
                                                <b style="font-size:14px">{{ number_format($data['nilai_akhir'], 1) }}</b>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            @else
                <div class="empty">
                    <div class="icon">🖨️</div>
                    <b>Belum ada data laporan</b>
                    <div>Masukkan nilai siswa untuk melihat laporan.</div>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const predikatLabels = ['A', 'B', 'C', 'D'];
            const predikatValues = [
                {{ $predikatDistribusi['A'] }},
                {{ $predikatDistribusi['B'] }},
                {{ $predikatDistribusi['C'] }},
                {{ $predikatDistribusi['D'] }}
            ];
            const predikatColors = ['rgba(18,128,92,0.75)', 'rgba(31,56,100,0.75)', 'rgba(184,134,11,0.75)', 'rgba(180,35,24,0.75)'];

            const ctx1 = document.getElementById('chartPredikat').getContext('2d');
            new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: predikatLabels,
                    datasets: [{
                        label: 'Jumlah Siswa',
                        data: predikatValues,
                        backgroundColor: predikatColors,
                        borderColor: predikatColors.map(c => c.replace('0.75', '1')),
                        borderWidth: 1.5,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { min: 0, grid: { color: '#EDF0F5' }, ticks: { stepSize: 1 } },
                        x: { grid: { display: false } }
                    }
                }
            });

            @php
                $mapelAgg = collect($dataLaporan)->flatMap(fn($d) => $d['nilai_per_mapel'])
                    ->groupBy('nama')
                    ->map(fn($items) => round($items->avg('rata_rata'), 1))
                    ->sortDesc()
                    ->take(10);
            @endphp

            @if($mapelAgg->count())
                const mapelLabels = {!! json_encode($mapelAgg->keys()->toArray()) !!};
                const mapelValues = {!! json_encode($mapelAgg->values()->toArray()) !!};

                const ctx2 = document.getElementById('chartMapel').getContext('2d');
                new Chart(ctx2, {
                    type: 'bar',
                    data: {
                        labels: mapelLabels,
                        datasets: [{
                            label: 'Rata-rata',
                            data: mapelValues,
                            backgroundColor: mapelValues.map(v => v >= 70 ? 'rgba(18,128,92,0.75)' : 'rgba(180,35,24,0.75)'),
                            borderColor: mapelValues.map(v => v >= 70 ? '#12805C' : '#B42318'),
                            borderWidth: 1.5,
                            borderRadius: 6
                        }, {
                            label: 'KKM',
                            data: Array(mapelLabels.length).fill(70),
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
            @endif
        });
    </script>
    <style>
        @media print {
            .sidebar, .topbar, .sidebar-overlay, .toast-container,
            [onclick*="print"], .btn, form, .card-header .tag { display: none !important; }
            .main-content { margin-left: 0 !important; }
            .page-content { padding: 0 !important; }
            .card { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd !important; margin-bottom: 12px !important; }
            .grid { display: block !important; }
            .grid .card, .grid .stat-card { margin-bottom: 12px !important; }
            .stat-card { border: 1px solid #ddd !important; box-shadow: none !important; }
            body { font-size: 11px !important; }
            .chart-container { page-break-inside: avoid; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; }
        }
    </style>
    @endpush
@endsection
