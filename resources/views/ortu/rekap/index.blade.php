@extends('layouts.app')

@section('title', 'Rekap 7 Kebiasaan - SIMANTAP')

@section('sidebar')
    @include('ortu.partials.sidebar')
@endsection

@section('page_title', 'Rekap 7 Kebiasaan')
@section('page_subtitle', 'Rangkuman perkembangan kebiasaan anak')

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
        <div class="card" style="margin-bottom:16px">
            <div class="card-body" style="display:flex;gap:18px;align-items:center;flex-wrap:wrap">
                <span class="avatar avatar-lg">{{ substr($anak->nama_peserta_didik, 0, 2) }}</span>
                <div style="flex:1;min-width:190px">
                    <div style="font-size:11.5px;font-weight:800;letter-spacing:.7px;text-transform:uppercase;color:#667085">Rekap kebiasaan</div>
                    <div style="font-size:19px;font-weight:800;letter-spacing:-.4px">{{ $anak->nama_peserta_didik }}</div>
                    <div style="font-size:13px;color:#667085">{{ $anak->kelas }} · {{ $hari }} hari terlapor</div>
                </div>
                <div style="text-align:right">
                    <div style="font-size:11.5px;font-weight:800;letter-spacing:.7px;text-transform:uppercase;color:#667085">Rata-rata</div>
                    <div style="font-size:34px;font-weight:800;letter-spacing:-1.5px;line-height:1.1;color:{{ $rata >= 3.5 ? '#12805C' : ($rata >= 2.5 ? '#B8860B' : '#1F3864') }}">{{ number_format($rata, 1) }}</div>
                    @if($predikat)
                        <span class="tag {{ $predikat['kelas'] }}">{{ $predikat['label'] }}</span>
                    @endif
                </div>
            </div>
        </div>

        @if(isset($anakList) && $anakList->count() > 1)
            <div style="margin-bottom:16px">
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Pilih Anak</label>
                <select onchange="window.location.href='{{ route('ortu.rekap.index') }}?siswa_id='+this.value" style="padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px;min-width:180px">
                    @foreach($anakList as $a)
                        <option value="{{ $a->id }}" {{ $a->id == $anak->id ? 'selected' : '' }}>{{ $a->nama_peserta_didik }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="grid grid-4" style="margin-bottom:20px">
            @php
                $labels = ['🌅 Bangun Pagi', '🕋 Beribadah', '🏃 Olahraga', '🍎 Makan Sehat', '📖 Belajar', '🤝 Bermasyarakat', '🌙 Tidur Cepat'];
            @endphp
            @foreach($fields as $i => $field)
                @php $avg = $rataKebiasaan[$field] ?? 0; @endphp
                <div class="stat-card {{ $avg >= 3.5 ? 'ok' : ($avg >= 2.5 ? 'primary' : ($avg >= 1.5 ? 'gold' : 'bad')) }}">
                    <div class="stat-label">{{ $labels[$i] }}</div>
                    <div class="stat-value" style="font-size:24px">{{ $avg > 0 ? number_format($avg, 1) : '—' }}</div>
                    <div class="stat-change">{{ $avg > 0 ? 'dari ' . $hari . ' hari' : 'belum ada data' }}</div>
                </div>
            @endforeach
        </div>

        <div class="grid grid-2" style="margin-bottom:20px">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-bar" style="margin-right:8px;color:var(--navy)"></i>Rata-rata per Kebiasaan</h3>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="chartKebiasaan"></canvas>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line" style="margin-right:8px;color:var(--gold)"></i>Progresi Nilai Harian</h3>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="chartProgresi"></canvas>
                    </div>
                </div>
            </div>
        </div>

        @if($semuaKebiasaan->count())
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-history" style="margin-right:8px;color:var(--navy)"></i>Riwayat Laporan</h3>
                    <span class="tag tag-primary">{{ $hari }} laporan</span>
                </div>
                <div class="card-body tight">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th style="text-align:center">🌅</th>
                                    <th style="text-align:center">🕋</th>
                                    <th style="text-align:center">🏃</th>
                                    <th style="text-align:center">🍎</th>
                                    <th style="text-align:center">📖</th>
                                    <th style="text-align:center">🤝</th>
                                    <th style="text-align:center">🌙</th>
                                    <th style="text-align:right">Rata-rata</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($semuaKebiasaan->take(30) as $item)
                                    @php
                                        $scores = collect($fields)->map(fn($f) => $item->$f)->filter()->values();
                                        $avg = $scores->count() ? $scores->avg() : null;
                                    @endphp
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($item->created_at)->translatedFormat('d M Y') }}</td>
                                        @foreach($fields as $f)
                                            <td style="text-align:center">
                                                @if($item->$f)
                                                    @php
                                                        $skorInfo = match($item->$f) {
                                                            1 => ['label' => '1', 'class' => 'tag-bad'],
                                                            2 => ['label' => '2', 'class' => 'tag-warn'],
                                                            3 => ['label' => '3', 'class' => 'tag-primary'],
                                                            4 => ['label' => '4', 'class' => 'tag-ok'],
                                                            default => ['label' => '—', 'class' => 'tag-mut'],
                                                        };
                                                    @endphp
                                                    <span class="tag {{ $skorInfo['class'] }}">{{ $skorInfo['label'] }}</span>
                                                @else
                                                    <span class="tag tag-mut">—</span>
                                                @endif
                                            </td>
                                        @endforeach
                                        <td style="text-align:right">
                                            @if($avg !== null)
                                                <b style="font-size:13px;{{ $avg >= 3.5 ? 'color:var(--ok)' : ($avg < 2.5 ? 'color:var(--bad)' : '') }}">{{ number_format($avg, 1) }}</b>
                                            @else
                                                <span style="color:var(--muted)">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-body">
                    <div class="empty-state">
                        <div class="icon">📋</div>
                        <h4>Belum ada laporan kebiasaan</h4>
                        <p>Isi laporan 7 kebiasaan untuk melihat rekap di sini.</p>
                    </div>
                </div>
            </div>
        @endif

        @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const fieldLabels = ['Bangun Pagi', 'Beribadah', 'Olahraga', 'Makan Sehat', 'Belajar', 'Bermasyarakat', 'Tidur Cepat'];
                const fieldAvgs = {!! json_encode(array_values($rataKebiasaan)) !!};

                const ctx1 = document.getElementById('chartKebiasaan').getContext('2d');
                new Chart(ctx1, {
                    type: 'bar',
                    data: {
                        labels: fieldLabels,
                        datasets: [{
                            label: 'Rata-rata Skor',
                            data: fieldAvgs,
                            backgroundColor: fieldAvgs.map(v => v >= 3.5 ? 'rgba(18,128,92,0.75)' : (v >= 2.5 ? 'rgba(31,56,100,0.75)' : (v >= 1.5 ? 'rgba(181,71,8,0.75)' : 'rgba(180,35,24,0.75)'))),
                            borderColor: fieldAvgs.map(v => v >= 3.5 ? '#12805C' : (v >= 2.5 ? '#1F3864' : (v >= 1.5 ? '#B54708' : '#B42318'))),
                            borderWidth: 1.5,
                            borderRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { min: 0, max: 4, grid: { color: '#EDF0F5' }, ticks: { stepSize: 1 } },
                            x: { grid: { display: false }, ticks: { font: { size: 10 } } }
                        }
                    }
                });

                @if($semuaKebiasaan->count())
                    const progresiLabels = {!! json_encode($semuaKebiasaan->sortBy('tanggal')->pluck('tanggal')->map(fn($t) => \Carbon\Carbon::parse($t)->format('d/m'))->toArray()) !!};
                    const progresiData = {!! json_encode(
                        $semuaKebiasaan->sortBy('tanggal')->map(function($item) use ($fields) {
                            $scores = collect($fields)->map(fn($f) => $item->$f)->filter()->values();
                            return $scores->count() ? round($scores->avg(), 2) : null;
                        })->filter()->values()->toArray()
                    ) !!};

                    if (progresiLabels.length && progresiData.length) {
                        const ctx2 = document.getElementById('chartProgresi').getContext('2d');
                        new Chart(ctx2, {
                            type: 'line',
                            data: {
                                labels: progresiLabels,
                                datasets: [{
                                    label: 'Rata-rata Harian',
                                    data: progresiData,
                                    borderColor: '#1F3864',
                                    backgroundColor: 'rgba(31,56,100,0.1)',
                                    fill: true,
                                    tension: 0.3,
                                    pointRadius: 3,
                                    pointBackgroundColor: '#1F3864'
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { display: false } },
                                scales: {
                                    y: { min: 0, max: 4, grid: { color: '#EDF0F5' }, ticks: { stepSize: 1 } },
                                    x: { grid: { display: false }, ticks: { font: { size: 10 } } }
                                }
                            }
                        });
                    }
                @endif
            });
        </script>
        @endpush
    @endif
@endsection
