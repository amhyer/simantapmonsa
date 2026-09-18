@extends('layouts.app')

@section('title', 'Analisis Belajar - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Analisis Belajar')
@section('page_subtitle', 'Evaluasi mendalam hasil pembelajaran kelas')

@section('content')
    <div class="grid grid-4" style="margin-bottom:20px">
        <div class="stat-card primary">
            <i class="fas fa-clipboard-list stat-icon"></i>
            <div class="stat-label">Total Kuis</div>
            <div class="stat-value">{{ $totalKuis }}</div>
            <div class="stat-change">telah dikerjakan</div>
        </div>
        <div class="stat-card gold">
            <i class="fas fa-chart-line stat-icon"></i>
            <div class="stat-label">Rata-rata Kelas</div>
            <div class="stat-value">{{ number_format($rataRataKelas, 1) }}</div>
            <div class="stat-change">seluruh kuis</div>
        </div>
        <div class="stat-card {{ $ketuntasan >= 70 ? 'ok' : 'bad' }}">
            <i class="fas fa-check-double stat-icon"></i>
            <div class="stat-label">Ketuntasan</div>
            <div class="stat-value">{{ number_format($ketuntasan, 1) }}%</div>
            <div class="stat-change">KKM {{ $kkm }}</div>
        </div>
        <div class="stat-card ok">
            <i class="fas fa-trophy stat-icon"></i>
            <div class="stat-label">Tertinggi</div>
            <div class="stat-value">{{ number_format($skorTertinggi, 1) }}</div>
            <div class="stat-change">skor maksimal</div>
        </div>
    </div>

    <div class="grid grid-2" style="margin-bottom:20px">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-bar" style="margin-right:8px;color:var(--navy)"></i>Performa Kelas per Kuis</h3>
            </div>
            <div class="card-body">
                <div class="chart-container large">
                    <canvas id="chartPerforma"></canvas>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-pie" style="margin-right:8px;color:var(--gold)"></i>Sebaran Predikat</h3>
            </div>
            <div class="card-body">
                <div class="chart-container large">
                    <canvas id="chartPredikat"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:20px">
        <div class="card-header">
            <h3><i class="fas fa-exclamation-circle" style="margin-right:8px;color:var(--bad)"></i>10 Soal Paling Sulit</h3>
            <span class="tag tag-bad">rata-rata skor terendah</span>
        </div>
        <div class="card-body tight">
            @if(count($soalSulit))
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th style="text-align:center">No</th>
                                <th>Kuis / Soal</th>
                                <th style="text-align:center">Jumlah Pilihan Jawaban</th>
                                <th style="text-align:right">Rata-rata Jawaban Benar</th>
                                <th style="text-align:center">% Salah</th>
                                <th style="text-align:center">Tingkat Kesulitan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($soalSulit as $i => $soal)
                                <tr>
                                    <td style="text-align:center">{{ $i + 1 }}</td>
                                    <td>
                                        <div>
                                            <b>{{ $soal['judul_kuis'] ?? 'Kuis #' . $soal['kuis_id'] }}</b>
                                            <div style="font-size:11px;color:var(--muted)">{{ Str::limit($soal['pertanyaan'], 60) }}</div>
                                        </div>
                                    </td>
                                    <td style="text-align:center">{{ $soal['total_jawaban'] ?? '-' }}</td>
                                    <td style="text-align:right">
                                        <b style="color:var(--bad)">{{ number_format($soal['rata_rata_benar'], 1) }}%</b>
                                    </td>
                                    <td style="text-align:center">
                                        @php $persenSalah = 100 - $soal['rata_rata_benar']; @endphp
                                        <div class="bar" style="min-width:100px">
                                            <div class="fill {{ $persenSalah >= 60 ? 'fill-bad' : ($persenSalah >= 40 ? 'fill-warn' : 'fill') }}" style="width:{{ $persenSalah }}%"></div>
                                        </div>
                                    </td>
                                    <td style="text-align:center">
                                        @if($persenSalah >= 70)
                                            <span class="tag tag-bad">Sangat Sulit</span>
                                        @elseif($persenSalah >= 50)
                                            <span class="tag tag-warn">Sulit</span>
                                        @else
                                            <span class="tag tag-mut">Sedang</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">
                    <div class="icon">📝</div>
                    <h4>Belum ada data soal</h4>
                    <p>Selesaikan kuis terlebih dahulu untuk melihat analisis soal.</p>
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-th" style="margin-right:8px;color:var(--navy)"></i>Matriks Capaian Per Siswa</h3>
            <span class="tag tag-mut">{{ count($matriksSiswa) }} siswa</span>
        </div>
        <div class="card-body tight">
            @if(count($matriksSiswa))
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr>
                                <th style="padding:10px 14px;text-align:left;min-width:180px">Siswa</th>
                                @foreach($kuisList as $kuis)
                                    <th style="padding:10px 8px;text-align:center;min-width:60px;font-size:10px">
                                        {{ Str::limit($kuis->judul, 12) }}
                                    </th>
                                @endforeach
                                <th style="padding:10px 8px;text-align:center;min-width:70px">Rata-rata</th>
                                <th style="padding:10px 8px;text-align:center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($matriksSiswa as $siswa)
                                <tr>
                                    <td style="padding:10px 14px;border-bottom:1px solid var(--line)">
                                        <div style="display:flex;align-items:center;gap:10px">
                                            <span class="avatar" style="width:30px;height:30px;font-size:11px">{{ substr($siswa['nama'], 0, 2) }}</span>
                                            <div>
                                                <b style="font-size:13px">{{ $siswa['nama'] }}</b>
                                            </div>
                                        </div>
                                    </td>
                                    @foreach($kuisList as $kuis)
                                        @php $skor = $siswa['skor'][$kuis->id] ?? null; @endphp
                                        <td style="padding:10px 8px;border-bottom:1px solid var(--line);text-align:center">
                                            @if($skor !== null)
                                                <span style="display:inline-block;min-width:36px;padding:3px 6px;border-radius:6px;font-size:11px;font-weight:700;background:{{ $skor >= 70 ? '#E6F5F0' : ($skor >= 50 ? '#FEF3E2' : '#FEE9E7') }};color:{{ $skor >= 70 ? 'var(--ok)' : ($skor >= 50 ? 'var(--warn)' : 'var(--bad)') }}">
                                                    {{ number_format($skor, 0) }}
                                                </span>
                                            @else
                                                <span style="color:var(--muted);font-size:11px">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td style="padding:10px 8px;border-bottom:1px solid var(--line);text-align:center">
                                        @if($siswa['rata_rata'] > 0)
                                            <b style="font-size:13px;{{ $siswa['rata_rata'] >= 70 ? 'color:var(--ok)' : 'color:var(--bad)' }}">{{ number_format($siswa['rata_rata'], 1) }}</b>
                                        @else
                                            <span style="color:var(--muted)">—</span>
                                        @endif
                                    </td>
                                    <td style="padding:10px 8px;border-bottom:1px solid var(--line);text-align:center">
                                        @if($siswa['rata_rata'] >= 70)
                                            <span class="tag tag-ok">Tuntas</span>
                                        @elseif($siswa['rata_rata'] > 0)
                                            <span class="tag tag-bad">Belum</span>
                                        @else
                                            <span class="tag tag-mut">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">
                    <div class="icon">📋</div>
                    <h4>Belum ada data</h4>
                    <p>Hasil kuis akan ditampilkan di matriks ini.</p>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const labels = {!! json_encode($kuisLabels ?? []) !!};
            const rataData = {!! json_encode($kuisRataRata ?? []) !!};

            const ctx1 = document.getElementById('chartPerforma').getContext('2d');
            new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Rata-rata Kelas',
                        data: rataData,
                        backgroundColor: rataData.map(v => v >= 70 ? 'rgba(18,128,92,0.75)' : 'rgba(180,35,24,0.75)'),
                        borderColor: rataData.map(v => v >= 70 ? '#12805C' : '#B42318'),
                        borderWidth: 1.5,
                        borderRadius: 6
                    }, {
                        label: 'KKM',
                        data: Array(labels.length).fill(70),
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

            const ctx2 = document.getElementById('chartPredikat').getContext('2d');
            new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: ['A · Sangat Baik', 'B · Baik', 'C · Cukup', 'D · Perlu Bimbingan'],
                    datasets: [{
                        data: {!! json_encode($predikatCount ?? [0, 0, 0, 0]) !!},
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
