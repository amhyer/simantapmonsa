@extends('layouts.app')

@section('title', 'Rekap Kebiasaan - SIMANTAP')

@section('sidebar')
    @include('kepsek.partials.sidebar')
@endsection

@section('page_title', 'Rekap 7 Kebiasaan')
@section('page_subtitle', 'Partisipasi kebiasaan seluruh siswa')

@section('content')
    <div class="grid grid-4" style="margin-bottom:20px">
        <div class="stat-card primary">
            <i class="fas fa-star stat-icon"></i>
            <div class="stat-label">Total Kebiasaan</div>
            <div class="stat-value">7</div>
        </div>
        <div class="stat-card gold">
            <i class="fas fa-users stat-icon"></i>
            <div class="stat-label">Total Siswa</div>
            <div class="stat-value">{{ $totalSiswa ?? 0 }}</div>
        </div>
        <div class="stat-card ok">
            <i class="fas fa-percentage stat-icon"></i>
            <div class="stat-label">Partisipasi Rata-rata</div>
            <div class="stat-value">{{ number_format($partisipasiRataRata ?? 0, 1) }}%</div>
        </div>
        <div class="stat-card primary">
            <i class="fas fa-trophy stat-icon"></i>
            <div class="stat-label">Kebiasaan Terbaik</div>
            <div class="stat-value" style="font-size:20px">{{ $kebiasaanTerbaik ?? '—' }}</div>
        </div>
    </div>

    <div class="grid grid-2" style="margin-bottom:20px">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-bar" style="margin-right:8px;color:var(--navy)"></i>Partisipasi Per Kebiasaan</h3>
            </div>
            <div class="card-body">
                <div class="chart-container large">
                    <canvas id="chartKebiasaan"></canvas>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-pie" style="margin-right:8px;color:var(--gold)"></i>Rata-rata Partisipasi Per Kelas</h3>
            </div>
            <div class="card-body">
                <div class="chart-container large">
                    <canvas id="chartKelasKebiasaan"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:20px">
        <div class="card-header">
            <h3><i class="fas fa-table" style="margin-right:8px;color:var(--navy)"></i>Rekap 7 Kebiasaan Per Kelas</h3>
        </div>
        <div class="card-body tight">
            @if(isset($tabelKebiasaan) && count($tabelKebiasaan))
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Kelas</th>
                                <th style="text-align:right">Siswa</th>
                                @foreach($daftarKebiasaan ?? [] as $kb)
                                    <th style="text-align:center">{{ Str::limit($kb, 8) }}</th>
                                @endforeach
                                <th style="text-align:center">Rata-rata</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tabelKebiasaan as $t)
                                <tr>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:10px">
                                            <span class="avatar" style="background:var(--navy);color:#fff;font-size:11px">{{ substr($t->nama_kelas ?? $t->kelas ?? 'K', 0, 2) }}</span>
                                            <b>{{ $t->nama_kelas ?? $t->kelas ?? '—' }}</b>
                                        </div>
                                    </td>
                                    <td style="text-align:right"><b>{{ $t->jumlah_siswa ?? 0 }}</b></td>
                                    @foreach($daftarKebiasaan ?? [] as $kb)
                                        <td style="text-align:center">
                                            @php
                                                $nilai = $t->kebiasaan[$kb] ?? 0;
                                            @endphp
                                            <span class="tag {{ $nilai >= 80 ? 'tag-ok' : ($nilai >= 60 ? 'tag-warn' : 'tag-bad') }}">{{ number_format($nilai, 1) }}%</span>
                                        </td>
                                    @endforeach
                                    <td style="text-align:center">
                                        <b>{{ number_format($t->rata_rata_kebiasaan ?? 0, 1) }}%</b>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">
                    <div class="icon">⭐</div>
                    <h4>Belum ada data kebiasaan</h4>
                    <p>Data kebiasaan akan muncul setelah guru dan orang tua mengisi.</p>
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list" style="margin-right:8px;color:var(--gold)"></i>Detail 7 Kebiasaan</h3>
        </div>
        <div class="card-body tight">
            @if(isset($detailKebiasaan) && count($detailKebiasaan))
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Kebiasaan</th>
                                <th>Kategori</th>
                                <th style="text-align:right">Total Siswa</th>
                                <th style="text-align:right">Partisipasi</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($detailKebiasaan as $i => $kb)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:10px">
                                            <span style="font-size:20px">{{ $kb->ikon ?? '⭐' }}</span>
                                            <div>
                                                <b>{{ $kb->nama ?? 'Kebiasaan' }}</b>
                                                <div style="font-size:11px;color:var(--muted)">{{ $kb->deskripsi ?? '' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="tag tag-primary">{{ $kb->kategori ?? 'Umum' }}</span></td>
                                    <td style="text-align:right">{{ $kb->total_siswa ?? 0 }}</td>
                                    <td style="text-align:right">
                                        <div style="display:flex;align-items:center;justify-content:flex-end;gap:8px">
                                            <div class="bar" style="width:80px">
                                                <div class="fill {{ ($kb->partisipasi ?? 0) >= 80 ? 'fill-ok' : (($kb->partisipasi ?? 0) >= 60 ? 'fill-warn' : 'fill-bad') }}" style="width:{{ min($kb->partisipasi ?? 0, 100) }}%"></div>
                                            </div>
                                            <span style="font-size:12px;font-weight:600">{{ number_format($kb->partisipasi ?? 0, 1) }}%</span>
                                        </div>
                                    </td>
                                    <td>
                                        @if(($kb->partisipasi ?? 0) >= 80)
                                            <span class="tag tag-ok">Baik</span>
                                        @elseif(($kb->partisipasi ?? 0) >= 60)
                                            <span class="tag tag-warn">Cukup</span>
                                        @else
                                            <span class="tag tag-bad">Perlu Perbaikan</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">
                    <div class="icon">⭐</div>
                    <h4>Belum ada data</h4>
                    <p>Detail 7 kebiasaan akan muncul setelah data terkumpul.</p>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx1 = document.getElementById('chartKebiasaan').getContext('2d');
            new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: {!! json_encode($chartKebiasaanLabels ?? ['Tepat Waktu', 'Beribadah', 'Baca Buku', ' Olahraga', 'Gotong Royong', 'Literasi', 'Peduli Lingkungan']) !!},
                    datasets: [{
                        label: 'Partisipasi %',
                        data: {!! json_encode($chartKebiasaanData ?? [85, 78, 72, 80, 88, 65, 75]) !!},
                        backgroundColor: ['#1F3864', '#B8860B', '#12805C', '#2B4A80', '#D4A017', '#667085', '#12805C'],
                        borderWidth: 0,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { min: 0, max: 100, grid: { color: '#EDF0F5' }, ticks: { callback: function(v) { return v + '%'; } } },
                        x: { grid: { display: false } }
                    }
                }
            });

            const ctx2 = document.getElementById('chartKelasKebiasaan').getContext('2d');
            new Chart(ctx2, {
                type: 'radar',
                data: {
                    labels: {!! json_encode($chartKebiasaanLabels ?? ['Tepat Waktu', 'Beribadah', 'Baca Buku', 'Olahraga', 'Gotong Royong', 'Literasi', 'Peduli Lingkungan']) !!},
                    datasets: [
                        {
                            label: {!! json_encode($chartKelasLabels ?? ['Kelas 1']) !!}[0] ?? 'Kelas 1',
                            data: {!! json_encode($chartRadarKelas1 ?? [85, 78, 72, 80, 88, 65, 75]) !!},
                            borderColor: '#1F3864',
                            backgroundColor: 'rgba(31,56,100,0.1)',
                            borderWidth: 2,
                            pointRadius: 3
                        },
                        {
                            label: {!! json_encode($chartKelasLabels ?? ['Kelas 2']) !!}[1] ?? 'Kelas 2',
                            data: {!! json_encode($chartRadarKelas2 ?? [80, 75, 68, 78, 85, 60, 72]) !!},
                            borderColor: '#B8860B',
                            backgroundColor: 'rgba(184,134,11,0.1)',
                            borderWidth: 2,
                            pointRadius: 3
                        },
                        {
                            label: {!! json_encode($chartKelasLabels ?? ['Kelas 3']) !!}[2] ?? 'Kelas 3',
                            data: {!! json_encode($chartRadarKelas3 ?? [78, 80, 75, 82, 90, 70, 78]) !!},
                            borderColor: '#12805C',
                            backgroundColor: 'rgba(18,128,92,0.1)',
                            borderWidth: 2,
                            pointRadius: 3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 11, padding: 12, font: { size: 11 } } } },
                    scales: {
                        r: { min: 0, max: 100, ticks: { stepSize: 20, display: false }, grid: { color: '#EDF0F5' }, pointLabels: { font: { size: 11 } } }
                    }
                }
            });
        });
    </script>
    @endpush
@endsection
