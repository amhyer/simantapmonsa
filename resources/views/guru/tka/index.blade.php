@extends('layouts.app')

@section('title', 'Kesiapan TKA - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Kesiapan TKA')
@section('page_subtitle', 'Analisis kesiapan siswa menghadapi Asesmen Nasional')

@section('content')
    <div class="grid grid-4" style="margin-bottom:20px">
        <div class="stat-card primary">
            <i class="fas fa-users stat-icon"></i>
            <div class="stat-label">Total Siswa</div>
            <div class="stat-value">{{ count($siswaList) }}</div>
            <div class="stat-change">terdaftar</div>
        </div>
        <div class="stat-card ok">
            <i class="fas fa-check-circle stat-icon"></i>
            <div class="stat-label">Siap TKA</div>
            <div class="stat-value">{{ $jumlahSiap }}</div>
            <div class="stat-change">skor ≥ 70</div>
        </div>
        <div class="stat-card warn">
            <i class="fas fa-exclamation-triangle stat-icon"></i>
            <div class="stat-label">Perlu Pendampingan</div>
            <div class="stat-value">{{ $jumlahPerluBimbingan }}</div>
            <div class="stat-change">skor &lt; 70</div>
        </div>
        <div class="stat-card gold">
            <i class="fas fa-chart-bar stat-icon"></i>
            <div class="stat-label">Rata-rata Skor</div>
            <div class="stat-value">{{ number_format($rataRata, 1) }}</div>
            <div class="stat-change">seluruh siswa</div>
        </div>
    </div>

    <div class="grid grid-2" style="margin-bottom:20px">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-radar" style="margin-right:8px;color:var(--navy)"></i>Analisis Dimensi Profil Pelajar Pancasila</h3>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="chartDimensi"></canvas>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-info-circle" style="margin-right:8px;color:var(--gold)"></i>Panduan Interpretasi</h3>
            </div>
            <div class="card-body">
                <div style="display:grid;gap:10px">
                    <div class="note note-ok">
                        <b>Skor ≥ 70</b> — Siswa menunjukkan kesiapan yang baik pada dimensi terkait.
                    </div>
                    <div class="note note-warn">
                        <b>Skor 50–69</b> — Siswa perlu pendampingan tambahan pada area tertentu.
                    </div>
                    <div class="note">
                        <b>Skor &lt; 50</b> — Siswa memerlukan intervensi dan bimbingan intensif.
                    </div>
                </div>
                <div style="margin-top:16px">
                    <h4 style="font-size:14px;margin-bottom:8px">Dimensi yang Dinilai:</h4>
                    <div style="display:grid;gap:6px;font-size:13px">
                        <div style="display:flex;align-items:center;gap:8px">
                            <span class="tag tag-primary" style="min-width:24px;text-align:center">1</span>
                            Beriman, Bertakwa kepada Tuhan YME, dan Berakhlak Mulia
                        </div>
                        <div style="display:flex;align-items:center;gap:8px">
                            <span class="tag tag-primary" style="min-width:24px;text-align:center">2</span>
                            Berkebinekaan Global
                        </div>
                        <div style="display:flex;align-items:center;gap:8px">
                            <span class="tag tag-primary" style="min-width:24px;text-align:center">3</span>
                            Bernalar Kritis
                        </div>
                        <div style="display:flex;align-items:center;gap:8px">
                            <span class="tag tag-primary" style="min-width:24px;text-align:center">4</span>
                            Gotong Royong
                        </div>
                        <div style="display:flex;align-items:center;gap:8px">
                            <span class="tag tag-primary" style="min-width:24px;text-align:center">5</span>
                            Mandiri
                        </div>
                        <div style="display:flex;align-items:center;gap:8px">
                            <span class="tag tag-primary" style="min-width:24px;text-align:center">6</span>
                            Kreatif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list" style="margin-right:8px;color:var(--navy)"></i>Daftar Siswa & Skor Kesiapan</h3>
            <span class="tag tag-mut">{{ count($siswaList) }} siswa</span>
        </div>
        <div class="card-body tight">
            @if(count($siswaList))
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Siswa</th>
                                <th style="text-align:center">NIS</th>
                                <th style="text-align:center">Dim 1</th>
                                <th style="text-align:center">Dim 2</th>
                                <th style="text-align:center">Dim 3</th>
                                <th style="text-align:center">Dim 4</th>
                                <th style="text-align:center">Dim 5</th>
                                <th style="text-align:center">Dim 6</th>
                                <th style="text-align:right">Rata-rata</th>
                                <th style="text-align:center">Status</th>
                                <th style="text-align:center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($siswaList as $i => $siswa)
                                @php
                                    $skorDimensi = $siswa->dimensi->groupBy('no_dimensi');
                                    $scores = [];
                                    for ($d = 1; $d <= 6; $d++) {
                                        $scores[$d] = isset($skorDimensi[$d]) ? $skorDimensi[$d]->avg('skor') : null;
                                    }
                                    $avg = collect(array_filter($scores))->avg();
                                @endphp
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:10px">
                                            <span class="avatar" style="{{ $avg && $avg >= 70 ? 'background:var(--ok);color:#fff' : ($avg && $avg < 50 ? 'background:var(--bad);color:#fff' : '') }}">{{ substr($siswa->nama_peserta_didik, 0, 2) }}</span>
                                            <div>
                                                <b>{{ $siswa->nama_peserta_didik }}</b>
                                                <div style="font-size:11px;color:var(--muted)">{{ $siswa->kelas }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="text-align:center">{{ $siswa->nis }}</td>
                                    @for($d = 1; $d <= 6; $d++)
                                        <td style="text-align:center">
                                            @if($scores[$d] !== null)
                                                <span class="tag {{ $scores[$d] >= 70 ? 'tag-ok' : ($scores[$d] >= 50 ? 'tag-warn' : 'tag-bad') }}">
                                                    {{ number_format($scores[$d], 1) }}
                                                </span>
                                            @else
                                                <span class="tag tag-mut">—</span>
                                            @endif
                                        </td>
                                    @endfor
                                    <td style="text-align:right">
                                        @if($avg)
                                            <b style="font-size:15px;{{ $avg >= 70 ? 'color:var(--ok)' : ($avg < 50 ? 'color:var(--bad)' : '') }}">{{ number_format($avg, 1) }}</b>
                                        @else
                                            <span style="color:var(--muted)">—</span>
                                        @endif
                                    </td>
                                    <td style="text-align:center">
                                        @if($avg)
                                            @if($avg >= 70)
                                                <span class="tag tag-ok">Siap</span>
                                            @elseif($avg >= 50)
                                                <span class="tag tag-warn">Perlu Bimbingan</span>
                                            @else
                                                <span class="tag tag-bad">Tidak Siap</span>
                                            @endif
                                        @else
                                            <span class="tag tag-mut">Belum Dinilai</span>
                                        @endif
                                    </td>
                                    <td style="text-align:center">
                                        <a href="{{ route('guru.dimensi.index') }}?siswa_id={{ $siswa->id }}" class="btn btn-ghost btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">
                    <div class="icon">📊</div>
                    <h4>Belum ada data</h4>
                    <p>Input penilaian dimensi pada menu Profil Lulusan terlebih dahulu.</p>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const labels = ['Beriman & Berakhlak', 'Berkebinekaan', 'Bernalar Kritis', 'Gotong Royong', 'Mandiri', 'Kreatif'];
            const data = {!! json_encode($dimensiRataRata ?? [0, 0, 0, 0, 0, 0]) !!};
            const ctx = document.getElementById('chartDimensi').getContext('2d');
            new Chart(ctx, {
                type: 'radar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Rata-rata Kelas',
                        data: data,
                        borderColor: '#1F3864',
                        backgroundColor: 'rgba(31,56,100,0.12)',
                        borderWidth: 2.5,
                        pointRadius: 4,
                        pointBackgroundColor: '#1F3864'
                    }, {
                        label: 'KKM (70)',
                        data: [70, 70, 70, 70, 70, 70],
                        borderColor: '#B8860B',
                        borderDash: [6, 4],
                        borderWidth: 1.5,
                        pointRadius: 0,
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        r: { min: 0, max: 100, ticks: { stepSize: 20, font: { size: 10 } }, grid: { color: '#E4E7EC' } }
                    },
                    plugins: { legend: { labels: { boxWidth: 12, padding: 14, font: { size: 12 } } } }
                }
            });
        });
    </script>
    @endpush
@endsection
