@extends('layouts.app')

@section('title', 'Nilai Saya - SIMANTAP')

@section('sidebar')
    @include('siswa.partials.sidebar')
@endsection

@section('page_title', 'Nilai Saya')
@section('page_subtitle', 'Rekapitulasi penilaian')

@section('content')
    @if(!$siswa)
        <div class="note note-warn">
            <i class="fas fa-exclamation-triangle"></i> Akun Anda belum terhubung dengan data siswa. Hubungi guru.
        </div>
    @else

    @php
        $allNilai = $nilai->merge($hasilKuis->map(fn($h) => (object)[
            'jenis' => 'Kuis',
            'judul_penilaian' => $h->judul_kuis,
            'mata_pelajaran' => $h->sumber_soal,
            'nilai' => $h->skor,
            'tanggal' => $h->waktu,
            'nama_guru' => $h->nama_guru,
        ]))->sortByDesc('tanggal');

        $rataNilai = $allNilai->count() ? round($allNilai->avg('nilai'), 1) : 0;
        $nilaiTertinggi = $allNilai->count() ? $allNilai->max('nilai') : 0;
        $nilaiTerendah = $allNilai->count() ? $allNilai->min('nilai') : 0;
        $totalPenilaian = $allNilai->count();

        $jenisValid = ['Tugas', 'Ulangan Harian', 'Praktik', 'PTS', 'PAS', 'Kuis'];
        $nilaiByJenis = collect();
        foreach ($jenisValid as $j) {
            $items = $allNilai->where('jenis', $j);
            $nilaiByJenis[$j] = [
                'count' => $items->count(),
                'avg' => $items->count() ? round($items->avg('nilai'), 1) : null,
            ];
        }
    @endphp

    <div class="grid grid-4" style="margin-bottom:20px">
        <div class="stat-card primary">
            <i class="fas fa-award stat-icon"></i>
            <div class="stat-label">Rata-rata</div>
            <div class="stat-value">{{ $rataNilai }}</div>
        </div>
        <div class="stat-card ok">
            <i class="fas fa-arrow-up stat-icon"></i>
            <div class="stat-label">Tertinggi</div>
            <div class="stat-value">{{ $nilaiTertinggi }}</div>
        </div>
        <div class="stat-card bad">
            <i class="fas fa-arrow-down stat-icon"></i>
            <div class="stat-label">Terendah</div>
            <div class="stat-value">{{ $nilaiTerendah }}</div>
        </div>
        <div class="stat-card gold">
            <i class="fas fa-list-ol stat-icon"></i>
            <div class="stat-label">Total Penilaian</div>
            <div class="stat-value">{{ $totalPenilaian }}</div>
        </div>
    </div>

    @if($allNilai->count())
        <div class="card" style="margin-bottom:16px">
            <div class="card-header">
                <h3><i class="fas fa-chart-line" style="margin-right:8px;color:var(--navy)"></i>Grafik Perkembangan Nilai</h3>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="nilaiChart"></canvas>
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom:16px">
            <div class="card-header">
                <h3><i class="fas fa-chart-bar" style="margin-right:8px;color:var(--gold)"></i>Rata-rata per Jenis</h3>
            </div>
            <div class="card-body">
                <div class="grid grid-3">
                    @foreach($nilaiByJenis as $jenis => $data)
                        @if($data['count'] > 0)
                            <div style="padding:14px;border:1px solid var(--line);border-radius:var(--radius)">
                                <div style="font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085;font-weight:700;margin-bottom:6px">{{ $jenis }}</div>
                                <div style="font-size:24px;font-weight:800;color:var(--navy)">{{ $data['avg'] }}</div>
                                <div style="font-size:12px;color:var(--muted);margin-top:2px">{{ $data['count'] }} penilaian</div>
                                <div class="bar" style="margin-top:8px">
                                    <span class="fill {{ $data['avg'] >= 75 ? 'fill-ok' : ($data['avg'] >= 50 ? 'fill-warn' : 'fill-bad') }}" style="width:{{ $data['avg'] }}%"></span>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Riwayat Penilaian</h3>
                <span class="tag tag-mut">{{ $totalPenilaian }} total</span>
            </div>
            <div class="card-body tight">
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Tanggal</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Jenis</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Judul</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Mapel</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Nilai</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($allNilai as $n)
                                <tr>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;font-size:13px">
                                        {{ \Carbon\Carbon::parse($n->tanggal)->translatedFormat('d M Y') }}
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                        @php
                                            $tagClass = match($n->jenis) {
                                                'Kuis' => 'tag-primary',
                                                'Tugas' => 'tag-gold',
                                                'Ulangan Harian' => 'tag-ok',
                                                'PTS' => 'tag-warn',
                                                'PAS' => 'tag-bad',
                                                default => 'tag-mut',
                                            };
                                        @endphp
                                        <span class="tag {{ $tagClass }}">{{ $n->jenis }}</span>
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;font-size:13px">
                                        {{ $n->judul_penilaian ?? '-' }}
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;font-size:13px;color:var(--muted)">
                                        {{ $n->mata_pelajaran ?? '-' }}
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">
                                        @php
                                            $skor = $n->nilai;
                                        @endphp
                                        <span class="tag {{ $skor >= 75 ? 'tag-ok' : ($skor >= 50 ? 'tag-warn' : 'tag-bad') }}">
                                            {{ $skor }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
            <script>
                const labels = {!! json_encode($allNilai->pluck('tanggal')->reverse()->map(fn($t) => \Carbon\Carbon::parse($t)->translatedFormat('d M'))->toArray()) !!};
                const dataValues = {!! json_encode($allNilai->pluck('nilai')->reverse()->toArray()) !!};
                const types = {!! json_encode($allNilai->pluck('jenis')->reverse()->toArray()) !!};

                const typeColors = {
                    'Kuis': '#1F3864',
                    'Tugas': '#B8860B',
                    'Ulangan Harian': '#12805C',
                    'Praktik': '#B54708',
                    'PTS': '#B42318',
                    'PAS': '#667085',
                };

                const bgColors = types.map(t => typeColors[t] || '#667085');

                new Chart(document.getElementById('nilaiChart'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Nilai',
                            data: dataValues,
                            borderColor: '#1F3864',
                            backgroundColor: 'rgba(31,56,100,0.1)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 5,
                            pointBackgroundColor: bgColors,
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        return types[ctx.dataIndex] + ': ' + ctx.raw;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100,
                                grid: { color: '#E4E7EC' },
                                ticks: { font: { size: 11 } },
                            },
                            x: {
                                grid: { display: false },
                                ticks: { font: { size: 11 } },
                            }
                        }
                    }
                });
            </script>
        @endpush
    @else
        <div class="card">
            <div class="card-body">
                <div class="empty">
                    <div class="icon">📊</div>
                    <b>Belum ada penilaian</b>
                    <div>Nilai Anda akan muncul di sini setelah guru melakukan penilaian.</div>
                </div>
            </div>
        </div>
    @endif
    @endif
@endsection
