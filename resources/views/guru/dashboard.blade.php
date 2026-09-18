@extends('layouts.app')

@section('title', 'Dashboard Guru - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Dasbor Guru')
@section('page_subtitle', auth()->user()->kelas_mata_pelajaran ?? 'Belum diatur')

@section('header_actions')
    <a href="{{ route('guru.laporan.index') }}" class="btn btn-ghost btn-sm">
        <i class="fas fa-print"></i> Cetak Laporan
    </a>
@endsection

@section('content')
    @php
        $syncedSiswa = \App\Models\Siswa::where('guru_id', auth()->id())->whereNotNull('dapodik_id')->count();
        $totalGuruSiswa = \App\Models\Siswa::where('guru_id', auth()->id())->count();
    @endphp

    @if($totalGuruSiswa > 0 && $syncedSiswa > 0)
        <div style="display:flex;align-items:center;gap:8px;padding:10px 16px;background:#EEF2F9;border-radius:8px;border-left:3px solid var(--navy);margin-bottom:16px;font-size:13px">
            <i class="fas fa-link" style="color:var(--navy)"></i>
            <span>{{ $syncedSiswa }} dari {{ $totalGuruSiswa }} siswa Anda bersumber dari <b>Dapodik Bridge</b></span>
        </div>
    @elseif($totalGuruSiswa == 0)
        <div class="note note-gold" style="margin-bottom:16px">
            <b>Belum ada siswa.</b> Data siswa akan masuk setelah admin melakukan sinkronisasi dari Dapodik melalui SIMANTAP Bridge.
        </div>
    @endif

    <div class="grid grid-4" style="margin-bottom:20px">
        <div class="stat-card primary">
            <i class="fas fa-users stat-icon"></i>
            <div class="stat-label">Siswa aktif</div>
            <div class="stat-value">{{ $ringkasan['jumlah'] }}</div>
            <div class="stat-change">terdaftar di kelas</div>
        </div>
        <div class="stat-card gold">
            <i class="fas fa-chart-line stat-icon"></i>
            <div class="stat-label">Rata-rata kelas</div>
            <div class="stat-value">{{ number_format($ringkasan['rata_rata'], 1) }}</div>
            <div class="stat-change">KKM 70 · tertinggi {{ number_format($ringkasan['tertinggi'], 1) }}</div>
        </div>
        <div class="stat-card {{ $ringkasan['belum'] ? 'bad' : 'ok' }}">
            <i class="fas fa-check-circle stat-icon"></i>
            <div class="stat-label">Ketuntasan</div>
            <div class="stat-value">{{ $ringkasan['jumlah'] ? round($ringkasan['tuntas'] / $ringkasan['jumlah'] * 100) : 0 }}%</div>
            <div class="stat-change">{{ $ringkasan['tuntas'] }} tuntas · {{ $ringkasan['belum'] }} belum</div>
        </div>
        <div class="stat-card ok">
            <i class="fas fa-calendar-check stat-icon"></i>
            <div class="stat-label">Kehadiran</div>
            <div class="stat-value">{{ number_format($ringkasan['kehadiran'], 1) }}%</div>
            <div class="stat-change">rata-rata seluruh siswa</div>
        </div>
    </div>

    <div class="grid grid-2" style="margin-bottom:20px">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-line" style="margin-right:8px;color:var(--navy)"></i>Perkembangan Rata-rata Kelas</h3>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="chartTren"></canvas>
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
                <h3><i class="fas fa-exclamation-triangle" style="margin-right:8px;color:var(--bad)"></i>Perlu Pendampingan</h3>
                <span class="tag {{ count($perluPendampingan) ? 'tag-bad' : 'tag-ok' }}">{{ count($perluPendampingan) }} siswa</span>
            </div>
            <div class="card-body tight">
                @if(count($perluPendampingan))
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Siswa</th>
                                    <th style="text-align:right">Nilai</th>
                                    <th style="text-align:center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($perluPendampingan as $item)
                                    <tr>
                                        <td>
                                            <div style="display:flex;align-items:center;gap:10px">
                                                <span class="avatar" style="background:var(--bad);color:#fff">{{ substr($item['siswa']->nama_peserta_didik, 0, 2) }}</span>
                                                <div>
                                                    <b>{{ $item['siswa']->nama_peserta_didik }}</b>
                                                    <div style="font-size:11px;color:var(--muted)">NIS {{ $item['siswa']->nis }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="text-align:right"><b style="color:var(--bad)">{{ number_format($item['nilai'], 1) }}</b></td>
                                        <td style="text-align:center"><button class="btn btn-ghost btn-sm">Telaah</button></td>
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
                <h3><i class="fas fa-star" style="margin-right:8px;color:var(--gold)"></i>Capaian Menonjol</h3>
            </div>
            <div class="card-body tight">
                @if(count($menonjol))
                    <div class="table-wrapper">
                        <table>
                            <tbody>
                                @foreach($menonjol as $item)
                                    <tr>
                                        <td>
                                            <div style="display:flex;align-items:center;gap:10px">
                                                <span class="avatar" style="background:var(--ok);color:#fff">{{ substr($item['siswa']->nama_peserta_didik, 0, 2) }}</span>
                                                <div>
                                                    <b>{{ $item['siswa']->nama_peserta_didik }}</b>
                                                    <div style="font-size:11px;color:var(--muted)">{{ $item['predikat']['label'] }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="text-align:right"><b style="font-size:18px;color:var(--ok)">{{ number_format($item['nilai'], 1) }}</b></td>
                                        <td style="width:120px">
                                            <div class="bar"><div class="fill fill-ok" style="width:{{ $item['nilai'] }}%"></div></div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="empty-state">
                        <div class="icon">📘</div>
                        <h4>Belum ada data</h4>
                        <p>Masukkan nilai untuk melihat capaian menonjol.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-3">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-book" style="margin-right:8px;color:var(--navy)"></i>Materi Terbaru</h3>
                <a href="{{ route('guru.materi.index') }}" class="btn btn-ghost btn-sm">Kelola</a>
            </div>
            <div class="card-body">
                @if(count($materiTerbaru))
                    @foreach($materiTerbaru as $materi)
                        <div style="padding:10px 0;border-bottom:1px solid var(--line)">
                            <b style="font-size:14px">{{ $materi->judul }}</b>
                            <div style="font-size:12px;color:var(--muted)">
                                {{ \Carbon\Carbon::parse($materi->tanggal)->translatedFormat('d M Y') }} · {{ $materi->mata_pelajaran }}
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="empty-state" style="padding:20px">
                        <h4>Belum ada materi</h4>
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-clipboard-list" style="margin-right:8px;color:var(--gold)"></i>Kuis Berjalan</h3>
                <a href="{{ route('guru.kuis.index') }}" class="btn btn-ghost btn-sm">Kelola</a>
            </div>
            <div class="card-body">
                @if(count($kuisAktif))
                    @foreach($kuisAktif as $kuis)
                        <div style="padding:10px 0;border-bottom:1px solid var(--line)">
                            <b style="font-size:14px">{{ $kuis->judul }}</b>
                            <div style="font-size:12px;color:var(--muted)">
                                {{ $kuis->hasilKuis->count() }}/{{ $ringkasan['jumlah'] }} siswa
                            </div>
                            <div class="bar" style="margin-top:6px">
                                <div class="fill" style="width:{{ $ringkasan['jumlah'] ? $kuis->hasilKuis->count() / $ringkasan['jumlah'] * 100 : 0 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="empty-state" style="padding:20px">
                        <h4>Belum ada kuis aktif</h4>
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-bolt" style="margin-right:8px;color:var(--gold)"></i>Langkah Cepat</h3>
            </div>
            <div class="card-body" style="display:grid;gap:8px">
                <a href="{{ route('guru.nilai.create') }}" class="btn btn-ghost btn-block"><i class="fas fa-plus"></i> Masukkan Nilai</a>
                <a href="{{ route('guru.kehadiran.index') }}" class="btn btn-ghost btn-block"><i class="fas fa-calendar"></i> Isi Kehadiran</a>
                <a href="{{ route('guru.materi.create') }}" class="btn btn-ghost btn-block"><i class="fas fa-book"></i> Tambah Materi</a>
                <a href="{{ route('guru.kuis.create') }}" class="btn btn-ghost btn-block"><i class="fas fa-clipboard"></i> Susun Kuis</a>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx1 = document.getElementById('chartTren').getContext('2d');
            new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: @json($trendLabels),
                    datasets: [{
                        label: 'Rata-rata kelas',
                        data: @json($trendData),
                        borderColor: '#1F3864',
                        backgroundColor: 'rgba(31,56,100,.09)',
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2.5,
                        pointRadius: 4,
                        pointBackgroundColor: '#1F3864'
                    }, {
                        label: 'KKM',
                        data: Array({{ count($trendLabels) }}).fill({{ getKKM(auth()->id()) }}),
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
                        data: [{{ $predikat['A'] ?? 0 }}, {{ $predikat['B'] ?? 0 }}, {{ $predikat['C'] ?? 0 }}, {{ $predikat['D'] ?? 0 }}],
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
