@extends('layouts.app')

@section('title', 'Laporan / Rapor - SIMANTAP')

@section('sidebar')
    @include('ortu.partials.sidebar')
@endsection

@section('page_title', 'Laporan / Rapor')
@section('page_subtitle', 'Laporan perkembangan lengkap anak')

@section('header_actions')
    <button onclick="window.print()" class="btn btn-ghost btn-sm">🖨️ Cetak</button>
@endsection

@section('content')
    @if(!$anak)
        <div class="card">
            <div class="card-body">
                <div class="empty">
                    <div class="icon">👪</div>
                    <b>Belum ada anak terhubung</b>
                    <div>Hubungi guru untuk mendapatkan akses ke data anak Anda.</div>
                </div>
            </div>
        </div>
    @else
        @if(isset($anakList) && $anakList->count() > 1)
            <div style="margin-bottom:16px">
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Pilih Anak</label>
                <select onchange="window.location.href='{{ route('ortu.laporan.index') }}?siswa_id='+this.value" style="padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px;min-width:180px">
                    @foreach($anakList as $a)
                        <option value="{{ $a->id }}" {{ $a->id == $anak->id ? 'selected' : '' }}>{{ $a->nama_peserta_didik }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="card" style="margin-bottom:20px" id="rapor-header">
            <div class="card-body" style="display:flex;gap:18px;align-items:center;flex-wrap:wrap">
                <span class="avatar avatar-lg" style="background:var(--navy);color:#fff">{{ substr($anak->nama_peserta_didik, 0, 2) }}</span>
                <div style="flex:1;min-width:200px">
                    <div style="font-size:11.5px;font-weight:800;letter-spacing:.7px;text-transform:uppercase;color:#667085">Laporan Perkembangan</div>
                    <div style="font-size:20px;font-weight:800;letter-spacing:-.4px">{{ $anak->nama_peserta_didik }}</div>
                    <div style="font-size:13px;color:#667085">NIS: {{ $anak->nis }} · Kelas: {{ $anak->kelas }}</div>
                </div>
                <div style="text-align:right">
                    <div style="font-size:11.5px;font-weight:800;letter-spacing:.7px;text-transform:uppercase;color:#667085">Nilai Akhir</div>
                    <div style="font-size:38px;font-weight:800;letter-spacing:-1.5px;line-height:1.1;color:{{ $laporan['nilai_akhir'] >= 70 ? '#12805C' : ($laporan['nilai_akhir'] > 0 ? '#B42318' : '#667085') }}">
                        {{ $laporan['nilai_akhir'] > 0 ? number_format($laporan['nilai_akhir'], 1) : '—' }}
                    </div>
                    @if(isset($laporan['predikat']) && $laporan['predikat'])
                        <span class="tag {{ $laporan['predikat']['kelas'] }}">{{ $laporan['predikat']['huruf'] }} · {{ $laporan['predikat']['label'] }}</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid grid-3" style="margin-bottom:20px">
            <div class="stat-card {{ $laporan['kehadiran_persen'] >= 90 ? 'ok' : ($laporan['kehadiran_persen'] >= 75 ? 'gold' : 'bad') }}">
                <i class="fas fa-calendar-check stat-icon"></i>
                <div class="stat-label">Kehadiran</div>
                <div class="stat-value">{{ $laporan['kehadiran_persen'] }}%</div>
                <div class="stat-change">{{ $laporan['total_hadir'] }} / {{ $laporan['total_kehadiran'] }} hari hadir</div>
            </div>
            <div class="stat-card primary">
                <i class="fas fa-heart stat-icon"></i>
                <div class="stat-label">7 Kebiasaan</div>
                <div class="stat-value">{{ $laporan['kebiasaan_rata_val'] > 0 ? number_format($laporan['kebiasaan_rata_val'], 1) : '—' }}</div>
                @if(isset($laporan['predikat_kebiasaan']) && $laporan['predikat_kebiasaan'])
                    <div class="stat-change"><span class="tag {{ $laporan['predikat_kebiasaan']['kelas'] }}">{{ $laporan['predikat_kebiasaan']['label'] }}</span></div>
                @endif
            </div>
            <div class="stat-card {{ $laporan['nilai_akhir'] >= 70 ? 'ok' : ($laporan['nilai_akhir'] > 0 ? 'bad' : 'primary') }}">
                <i class="fas fa-graduation-cap stat-icon"></i>
                <div class="stat-label">Status Akademik</div>
                <div class="stat-value" style="font-size:16px">{{ $laporan['nilai_akhir'] >= 70 ? 'Tuntas' : ($laporan['nilai_akhir'] > 0 ? 'Belum Tuntas' : 'Belum Dinilai') }}</div>
                <div class="stat-change">KKM: 70</div>
            </div>
        </div>

        <div class="grid grid-2" style="margin-bottom:20px">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-bar" style="margin-right:8px;color:var(--navy)"></i>Nilai per Jenis</h3>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="chartNilai"></canvas>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-heart" style="margin-right:8px;color:var(--gold)"></i>Rata-rata 7 Kebiasaan</h3>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="chartKebiasaan"></canvas>
                    </div>
                </div>
            </div>
        </div>

        @if($laporan['nilai_per_jenis']->count())
            <div class="card" style="margin-bottom:20px">
                <div class="card-header">
                    <h3><i class="fas fa-table" style="margin-right:8px;color:var(--navy)"></i>Detail Nilai per Jenis</h3>
                </div>
                <div class="card-body tight">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Jenis Penilaian</th>
                                    <th style="text-align:right">Rata-rata</th>
                                    <th style="text-align:right">Jumlah Penilaian</th>
                                    <th style="text-align:center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($laporan['nilai_per_jenis'] as $item)
                                    @php
                                        $rata = round($item->rata_rata, 1);
                                        $status = $rata >= 70 ? 'Tuntas' : 'Perlu Bimbingan';
                                        $statusClass = $rata >= 70 ? 'tag-ok' : 'tag-bad';
                                    @endphp
                                    <tr>
                                        <td><b>{{ $item->jenis }}</b></td>
                                        <td style="text-align:right"><b style="font-size:15px;{{ $rata >= 70 ? 'color:var(--ok)' : 'color:var(--bad)' }}">{{ $rata }}</b></td>
                                        <td style="text-align:right">{{ $item->jumlah }} penilaian</td>
                                        <td style="text-align:center"><span class="tag {{ $statusClass }}">{{ $status }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-heart" style="margin-right:8px;color:var(--gold)"></i>Detail 7 Kebiasaan</h3>
            </div>
            <div class="card-body">
                <div style="display:grid;gap:10px">
                    @php
                        $kaihLabels = [
                            'bangun_pagi' => ['🌅', 'Bangun Pagi'],
                            'beribadah' => ['🕋', 'Beribadah'],
                            'berolahraga' => ['🏃', 'Berolahraga'],
                            'makan_sehat' => ['🍎', 'Makan Sehat dan Bergizi'],
                            'gemar_belajar' => ['📖', 'Gemar Belajar'],
                            'bermasyarakat' => ['🤝', 'Bermasyarakat'],
                            'tidur_cepat' => ['🌙', 'Tidur Cepat'],
                        ];
                    @endphp
                    @foreach($kaihLabels as $field => [$icon, $nama])
                        @php $avg = $laporan['kebiasaan_rata'][$field] ?? 0; @endphp
                        <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:10px;background:{{ $avg >= 3.5 ? '#E6F5F0' : ($avg >= 2.5 ? '#EEF2F9' : '#FAFBFD') }}">
                            <span style="font-size:20px">{{ $icon }}</span>
                            <div style="flex:1;min-width:0">
                                <div style="font-weight:600;font-size:13px">{{ $nama }}</div>
                            </div>
                            <div class="bar" style="flex:0 0 120px">
                                <div class="fill {{ $avg >= 3.5 ? 'fill-ok' : ($avg >= 2.5 ? '' : 'fill-bad') }}" style="width:{{ $avg > 0 ? ($avg / 4 * 100) : 0 }}%"></div>
                            </div>
                            <div style="min-width:50px;text-align:right">
                                <b style="font-size:14px;{{ $avg >= 3.5 ? 'color:var(--ok)' : ($avg >= 2.5 ? 'color:var(--navy)' : ($avg > 0 ? 'color:var(--bad)' : 'color:var(--muted)')) }}">{{ $avg > 0 ? number_format($avg, 1) : '—' }}</b>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                @if($laporan['nilai_per_jenis']->count())
                    const jenisLabels = {!! json_encode($laporan['nilai_per_jenis']->pluck('jenis')->toArray()) !!};
                    const jenisValues = {!! json_encode($laporan['nilai_per_jenis']->map(fn($i) => round($i->rata_rata, 1))->toArray()) !!};

                    const ctx1 = document.getElementById('chartNilai').getContext('2d');
                    new Chart(ctx1, {
                        type: 'bar',
                        data: {
                            labels: jenisLabels,
                            datasets: [{
                                label: 'Rata-rata Nilai',
                                data: jenisValues,
                                backgroundColor: jenisValues.map(v => v >= 70 ? 'rgba(18,128,92,0.75)' : 'rgba(180,35,24,0.75)'),
                                borderColor: jenisValues.map(v => v >= 70 ? '#12805C' : '#B42318'),
                                borderWidth: 1.5,
                                borderRadius: 6
                            }, {
                                label: 'KKM',
                                data: Array(jenisLabels.length).fill(70),
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

                const kaihLabels = ['Bangun Pagi', 'Beribadah', 'Olahraga', 'Makan Sehat', 'Belajar', 'Bermasyarakat', 'Tidur Cepat'];
                const kaihValues = {!! json_encode(array_values($laporan['kebiasaan_rata'])) !!};

                const ctx2 = document.getElementById('chartKebiasaan').getContext('2d');
                new Chart(ctx2, {
                    type: 'radar',
                    data: {
                        labels: kaihLabels,
                        datasets: [{
                            label: 'Rata-rata',
                            data: kaihValues,
                            backgroundColor: 'rgba(31,56,100,0.15)',
                            borderColor: '#1F3864',
                            borderWidth: 2,
                            pointBackgroundColor: '#1F3864',
                            pointRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            r: {
                                min: 0,
                                max: 4,
                                ticks: { stepSize: 1, font: { size: 10 } },
                                pointLabels: { font: { size: 10 } },
                                grid: { color: '#EDF0F5' }
                            }
                        }
                    }
                });
            });
        </script>
        <style>
            @media print {
                .sidebar, .topbar, .sidebar-overlay, .toast-container, [onclick*="print"],
                .filter-btn, select, .btn { display: none !important; }
                .main-content { margin-left: 0 !important; }
                .page-content { padding: 0 !important; }
                .card { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd !important; }
            }
        </style>
        @endpush
    @endif
@endsection
