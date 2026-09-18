@extends('layouts.app')

@section('title', 'Kehadiran Anak - SIMANTAP')

@section('sidebar')
    @include('ortu.partials.sidebar')
@endsection

@section('page_title', 'Kehadiran Anak')
@section('page_subtitle', 'Rekap kehadiran di sekolah')

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
                    <div style="font-size:11.5px;font-weight:800;letter-spacing:.7px;text-transform:uppercase;color:#667085">Kehadiran</div>
                    <div style="font-size:19px;font-weight:800;letter-spacing:-.4px">{{ $anak->nama_peserta_didik }}</div>
                    <div style="font-size:13px;color:#667085">{{ $anak->kelas }}</div>
                </div>
                <div style="text-align:right">
                    <div style="font-size:11.5px;font-weight:800;letter-spacing:.7px;text-transform:uppercase;color:#667085">Persentase Hadir</div>
                    <div style="font-size:34px;font-weight:800;letter-spacing:-1.5px;line-height:1.1;color:{{ $persentase >= 90 ? '#12805C' : ($persentase >= 75 ? '#B8860B' : '#B42318') }}">{{ $persentase }}%</div>
                </div>
            </div>
        </div>

        @if(isset($anakList) && $anakList->count() > 1)
            <div style="margin-bottom:16px">
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Pilih Anak</label>
                <select onchange="window.location.href='{{ route('ortu.kehadiran.index') }}?siswa_id='+this.value" style="padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px;min-width:180px">
                    @foreach($anakList as $a)
                        <option value="{{ $a->id }}" {{ $a->id == $anak->id ? 'selected' : '' }}>{{ $a->nama_peserta_didik }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        @php
            $totalSakit = $kehadiran->where('status', 'S')->count();
            $totalIzin = $kehadiran->where('status', 'I')->count();
            $totalAlpha = $kehadiran->where('status', 'A')->count();
        @endphp

        <div class="grid grid-4" style="margin-bottom:20px">
            <div class="stat-card ok">
                <i class="fas fa-check-circle stat-icon"></i>
                <div class="stat-label">Hadir</div>
                <div class="stat-value">{{ $totalHadir }}</div>
                <div class="stat-change">dari {{ $total }} hari</div>
            </div>
            <div class="stat-card gold">
                <i class="fas fa-procedures stat-icon"></i>
                <div class="stat-label">Sakit</div>
                <div class="stat-value">{{ $totalSakit }}</div>
                <div class="stat-change">hari</div>
            </div>
            <div class="stat-card primary">
                <i class="fas fa-file-medical stat-icon"></i>
                <div class="stat-label">Izin</div>
                <div class="stat-value">{{ $totalIzin }}</div>
                <div class="stat-change">hari</div>
            </div>
            <div class="stat-card bad">
                <i class="fas fa-times-circle stat-icon"></i>
                <div class="stat-label">Alpha</div>
                <div class="stat-value">{{ $totalAlpha }}</div>
                <div class="stat-change">hari</div>
            </div>
        </div>

        <div class="grid grid-2" style="margin-bottom:20px">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-pie" style="margin-right:8px;color:var(--navy)"></i>Distribusi Kehadiran</h3>
                </div>
                <div class="card-body">
                    <div class="chart-container small">
                        <canvas id="chartKehadiran"></canvas>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-bar" style="margin-right:8px;color:var(--gold)"></i>Kehadiran per Bulan</h3>
                </div>
                <div class="card-body">
                    <div class="chart-container small">
                        <canvas id="chartBulanan"></canvas>
                    </div>
                </div>
            </div>
        </div>

        @if($kehadiran->count())
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list" style="margin-right:8px;color:var(--navy)"></i>Riwayat Kehadiran</h3>
                    <span class="tag tag-primary">{{ $total }} hari tercatat</span>
                </div>
                <div class="card-body tight">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Hari</th>
                                    <th style="text-align:center">Status</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($kehadiran->take(30) as $item)
                                    @php
                                        $statusInfo = match($item->status) {
                                            'H' => ['label' => 'Hadir', 'class' => 'tag-ok'],
                                            'S' => ['label' => 'Sakit', 'class' => 'tag-warn'],
                                            'I' => ['label' => 'Izin', 'class' => 'tag-primary'],
                                            'A' => ['label' => 'Alpha', 'class' => 'tag-bad'],
                                            default => ['label' => $item->status, 'class' => 'tag-mut'],
                                        };
                                        $hari = \Carbon\Carbon::parse($item->tanggal)->translatedFormat('l');
                                    @endphp
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($item->tanggal)->translatedFormat('d M Y') }}</td>
                                        <td>{{ $hari }}</td>
                                        <td style="text-align:center">
                                            <span class="tag {{ $statusInfo['class'] }}">{{ $statusInfo['label'] }}</span>
                                        </td>
                                        <td>{{ $item->keterangan ?? '—' }}</td>
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
                        <div class="icon">🗓️</div>
                        <h4>Belum ada data kehadiran</h4>
                        <p>Data kehadiran dari guru akan muncul di sini.</p>
                    </div>
                </div>
            </div>
        @endif

        @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const ctx1 = document.getElementById('chartKehadiran').getContext('2d');
                new Chart(ctx1, {
                    type: 'doughnut',
                    data: {
                        labels: ['Hadir', 'Sakit', 'Izin', 'Alpha'],
                        datasets: [{
                            data: [{{ $totalHadir }}, {{ $totalSakit }}, {{ $totalIzin }}, {{ $totalAlpha }}],
                            backgroundColor: ['#12805C', '#B8860B', '#1F3864', '#B42318'],
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

                @php
                    $bulanData = $kehadiran->groupBy(function($item) {
                        return \Carbon\Carbon::parse($item->tanggal)->format('Y-m');
                    })->map(function($items) {
                        $total = $items->count();
                        $hadir = $items->where('status', 'H')->count();
                        return $total > 0 ? round($hadir / $total * 100, 1) : 0;
                    })->sortKeys()->toArray();
                @endphp

                if (Object.keys({!! json_encode($bulanData) !!}).length) {
                    const ctx2 = document.getElementById('chartBulanan').getContext('2d');
                    const bulanLabels = {!! json_encode(array_keys($bulanData)) !!};
                    const bulanValues = {!! json_encode(array_values($bulanData)) !!};
                    new Chart(ctx2, {
                        type: 'bar',
                        data: {
                            labels: bulanLabels.map(b => {
                                const [y, m] = b.split('-');
                                const bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                                return bulan[parseInt(m)] + ' ' + y;
                            }),
                            datasets: [{
                                label: '% Hadir',
                                data: bulanValues,
                                backgroundColor: bulanValues.map(v => v >= 90 ? 'rgba(18,128,92,0.75)' : (v >= 75 ? 'rgba(184,134,11,0.75)' : 'rgba(180,35,24,0.75)')),
                                borderColor: bulanValues.map(v => v >= 90 ? '#12805C' : (v >= 75 ? '#B8860B' : '#B42318')),
                                borderWidth: 1.5,
                                borderRadius: 6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                y: { min: 0, max: 100, grid: { color: '#EDF0F5' }, ticks: { callback: v => v + '%' } },
                                x: { grid: { display: false } }
                            }
                        }
                    });
                }
            });
        </script>
        @endpush
    @endif
@endsection
