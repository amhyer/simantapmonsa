@extends('layouts.app')

@section('title', '7 Kebiasaan dari Orang Tua - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', '7 Kebiasaan dari Orang Tua')
@section('page_subtitle', 'Laporan pembiasaan siswa dari rumah')

@section('content')
    <div class="grid grid-4" style="margin-bottom:20px">
        <div class="stat-card primary">
            <i class="fas fa-users stat-icon"></i>
            <div class="stat-label">Siswa Dilaporkan</div>
            <div class="stat-value">{{ $totalDilaporkan }}</div>
            <div class="stat-change">minggu ini</div>
        </div>
        <div class="stat-card gold">
            <i class="fas fa-clipboard-check stat-icon"></i>
            <div class="stat-label">Total Laporan</div>
            <div class="stat-value">{{ $totalLaporan }}</div>
            <div class="stat-change">semua tanggal</div>
        </div>
        <div class="stat-card ok">
            <i class="fas fa-star stat-icon"></i>
            <div class="stat-label">Rata-rata Nilai</div>
            <div class="stat-value">{{ number_format($rataRataSkor, 1) }}</div>
            <div class="stat-change">skor kebiasaan</div>
        </div>
        <div class="stat-card bad">
            <i class="fas fa-calendar-times stat-icon"></i>
            <div class="stat-label">Belum Dilaporkan</div>
            <div class="stat-value">{{ $belumDilaporkan }}</div>
            <div class="stat-change">siswa minggu ini</div>
        </div>
    </div>

    <div class="card" style="margin-bottom:20px">
        <div class="card-header">
            <h3><i class="fas fa-chart-bar" style="margin-right:8px;color:var(--navy)"></i>Rata-rata Skor Kebiasaan per Siswa</h3>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="chartKebiasaan"></canvas>
            </div>
        </div>
    </div>

    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px;align-items:end">
        <div>
            <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Filter Tanggal</label>
            <input type="date" id="filterTanggal" value="{{ $tanggalFilter }}" max="{{ date('Y-m-d') }}" onchange="window.location.href='?tanggal='+this.value" style="padding:8px 12px;border:1px solid var(--line);border-radius:8px">
        </div>
    </div>

    @foreach($kebiasaanByDate as $tanggal => $data)
        <div class="card" style="margin-bottom:16px">
            <div class="card-header">
                <h3>
                    <i class="fas fa-calendar-day" style="margin-right:8px;color:var(--gold)"></i>
                    {{ \Carbon\Carbon::parse($tanggal)->translatedFormat('l, d F Y') }}
                </h3>
                <span class="tag tag-primary">{{ count($data) }} laporan</span>
            </div>
            <div class="card-body tight">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Siswa</th>
                                <th style="text-align:center">🌅 Bangun Pagi</th>
                                <th style="text-align:center">🕋 Beribadah</th>
                                <th style="text-align:center">🏃 Olahraga</th>
                                <th style="text-align:center">🍎 Makan Sehat</th>
                                <th style="text-align:center">📖 Belajar</th>
                                <th style="text-align:center">🤝 Bermasyarakat</th>
                                <th style="text-align:center">🌙 Tidur Cepat</th>
                                <th style="text-align:right">Rata-rata</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data as $item)
                                @php
                                    $fields = ['bangun_pagi', 'beribadah', 'berolahraga', 'makan_sehat', 'gemar_belajar', 'bermasyarakat', 'tidur_cepat'];
                                    $filled = collect($fields)->filter(fn($f) => $item->$f)->count();
                                    $scores = collect($fields)->map(fn($f) => $item->$f)->filter()->values();
                                    $avg = $scores->avg();
                                @endphp
                                <tr>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:10px">
                                            <span class="avatar" style="{{ $avg && $avg >= 3.5 ? 'background:var(--ok);color:#fff' : ($avg && $avg < 2.5 ? 'background:var(--bad);color:#fff' : '') }}">{{ substr($item->siswa->nama_peserta_didik, 0, 2) }}</span>
                                            <div>
                                                <b>{{ $item->siswa->nama_peserta_didik }}</b>
                                                <div style="font-size:11px;color:var(--muted)">{{ $item->siswa->nis }} · {{ $filled }}/7 terisi</div>
                                            </div>
                                        </div>
                                    </td>
                                    @foreach($fields as $f)
                                        <td style="text-align:center">
                                            @if($item->$f)
                                                @php
                                                    $skor = $item->$f;
                                                    $skorInfo = match($skor) {
                                                        1 => ['label' => 'Belum', 'class' => 'tag-bad'],
                                                        2 => ['label' => 'Kadang', 'class' => 'tag-warn'],
                                                        3 => ['label' => 'Sering', 'class' => 'tag-primary'],
                                                        4 => ['label' => 'Selalu', 'class' => 'tag-ok'],
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
                                        @if($avg)
                                            <b style="font-size:15px;{{ $avg >= 3.5 ? 'color:var(--ok)' : ($avg < 2.5 ? 'color:var(--bad)' : '') }}">{{ number_format($avg, 1) }}</b>
                                        @else
                                            <span style="color:var(--muted)">—</span>
                                        @endif
                                    </td>
                                    <td style="max-width:180px">
                                        @if($item->catatan_orang_tua)
                                            <span style="font-size:12px;color:var(--muted)">{{ Str::limit($item->catatan_orang_tua, 50) }}</span>
                                        @else
                                            <span style="font-size:12px;color:var(--muted)">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endforeach

    @if(count($kebiasaanByDate) == 0)
        <div class="card">
            <div class="card-body">
                <div class="empty-state">
                    <div class="icon">📋</div>
                    <h4>Belum ada laporan kebiasaan</h4>
                    <p>Laporan dari orang tua akan muncul di sini.</p>
                </div>
            </div>
        </div>
    @endif

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const labels = {!! json_encode($chartLabels ?? []) !!};
            const data = {!! json_encode($chartData ?? []) !!};
            const ctx = document.getElementById('chartKebiasaan').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Rata-rata Skor (maks 4)',
                        data: data,
                        backgroundColor: data.map(v => v >= 3.5 ? 'rgba(18,128,92,0.75)' : (v >= 2.5 ? 'rgba(31,56,100,0.75)' : (v >= 1.5 ? 'rgba(181,71,8,0.75)' : 'rgba(180,35,24,0.75)'))),
                        borderColor: data.map(v => v >= 3.5 ? '#12805C' : (v >= 2.5 ? '#1F3864' : (v >= 1.5 ? '#B54708' : '#B42318'))),
                        borderWidth: 1.5,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { min: 0, max: 4, grid: { color: '#EDF0F5' }, ticks: { stepSize: 1 } },
                        y: { grid: { display: false } }
                    }
                }
            });
        });
    </script>
    @endpush
@endsection
