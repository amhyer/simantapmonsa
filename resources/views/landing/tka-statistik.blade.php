<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Statistik TKA Nasional — SIMANTAP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root { --navy: #1F3864; --gold: #B8860B; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; }
    </style>
</head>
<body>

@php
    $peserta = $tka['peserta'] ?? null;
    $total = $peserta['total'] ?? [];
    $rekap = $peserta['rekap'] ?? [];
    $tanggal = $peserta['tanggal'] ?? [];
    $capes = $peserta['capes'] ?? [];
    $lastUpdate = $tka['last_update'] ?? '-';
    $fmtNum = function ($val) {
        $clean = is_string($val) ? str_replace('.', '', $val) : $val;
        return (int) $clean;
    };
@endphp

<section id="tka-statistik" class="py-5">
    <div class="container">
        <div class="text-center mb-4">
            <a href="/" style="text-decoration:none;color:var(--navy)"><i class="fas fa-arrow-left"></i> Kembali ke Beranda</a>
            <h3 class="font-weight-bold mt-2" style="color:var(--navy)">Statistik TKA Nasional</h3>
            <p class="text-muted">Data Tes Kemampuan Akademik — Tahun 2026 dari tka.kemendikdasmen.go.id</p>
            @if($lastUpdate !== '-')
                <small class="text-muted">Last Update: {{ $lastUpdate }}</small>
            @endif
        </div>

        @if(isset($tka['error']))
            <div class="alert alert-warning text-center">
                <i class="fas fa-exclamation-triangle"></i>
                Data TKA belum tersedia. Silakan coba lagi nanti.
            </div>
        @else
            <div class="row mb-4">
                <div class="col-sm-3">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body">
                            <div class="bg-primary mx-auto mb-2" style="width:50px;height:50px;border-radius:50%;display:flex;align-items:center;justify-content:center">
                                <i class="fas fa-building text-white"></i>
                            </div>
                            <h4 class="fw-bold mb-0" style="color:var(--navy)">{{ number_format($fmtNum($total['jm_sek_daftar'] ?? 0)) }}</h4>
                            <small class="text-muted">Satuan Pendidikan</small>
                        </div>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body">
                            <div class="bg-info mx-auto mb-2" style="width:50px;height:50px;border-radius:50%;display:flex;align-items:center;justify-content:center">
                                <i class="fas fa-user text-white"></i>
                            </div>
                            <h4 class="fw-bold mb-0" style="color:var(--navy)">{{ number_format($fmtNum($total['jm_pes_t'] ?? 0)) }}</h4>
                            <small class="text-muted">Calon Peserta</small>
                        </div>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body">
                            <div class="bg-success mx-auto mb-2" style="width:50px;height:50px;border-radius:50%;display:flex;align-items:center;justify-content:center">
                                <i class="fas fa-user-check text-white"></i>
                            </div>
                            <h4 class="fw-bold mb-0" style="color:var(--navy)">{{ number_format($fmtNum($total['jm_daftar_t'] ?? 0)) }}</h4>
                            <small class="text-muted">Pendaftar Lengkap</small>
                        </div>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body">
                            <div class="bg-warning mx-auto mb-2" style="width:50px;height:50px;border-radius:50%;display:flex;align-items:center;justify-content:center">
                                <i class="fas fa-user-times text-white"></i>
                            </div>
                            <h4 class="fw-bold mb-0" style="color:var(--navy)">{{ number_format($fmtNum($total['jm_tidak'] ?? 0)) }}</h4>
                            <small class="text-muted">Belum Lengkap</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-sm-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h6 class="text-center fw-bold">Grafik Calon Peserta Harian</h6>
                            <canvas id="tka-chart-capes-harian" height="200"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h6 class="text-center fw-bold">Grafik Pendaftar Harian (Kumulatif)</h6>
                            <canvas id="tka-chart-kumulatif" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Rekap Pendaftaran per Jenjang</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-sm">
                            <thead style="background:var(--navy);color:#fff">
                                <tr>
                                    <th class="text-center" rowspan="2">No</th>
                                    <th class="text-center" rowspan="2">Jenjang</th>
                                    <th class="text-center" colspan="2">Satuan Pendidikan</th>
                                    <th class="text-center" colspan="3">Calon Peserta</th>
                                    <th class="text-center" colspan="3">Pendaftar Lengkap</th>
                                </tr>
                                <tr>
                                    <th class="text-center">Jumlah</th>
                                    <th class="text-center">Mendaftar</th>
                                    <th class="text-center">L</th>
                                    <th class="text-center">P</th>
                                    <th class="text-center">Jumlah</th>
                                    <th class="text-center">L</th>
                                    <th class="text-center">P</th>
                                    <th class="text-center">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($rekap as $i => $row)
                                    <tr>
                                        <td class="text-center">{{ $i + 1 }}</td>
                                        <td>{{ $row['nm_jenjang'] ?? '-' }}</td>
                                        <td class="text-end">{{ number_format($fmtNum($row['jm_sek'] ?? 0)) }}</td>
                                        <td class="text-end">{{ number_format($fmtNum($row['jm_sek_daftar'] ?? 0)) }}</td>
                                        <td class="text-end">{{ number_format($fmtNum($row['jm_pes_l'] ?? 0)) }}</td>
                                        <td class="text-end">{{ number_format($fmtNum($row['jm_pes_p'] ?? 0)) }}</td>
                                        <td class="text-end">{{ number_format($fmtNum($row['jm_pes_t'] ?? 0)) }}</td>
                                        <td class="text-end">{{ number_format($fmtNum($row['jm_daftar_l'] ?? 0)) }}</td>
                                        <td class="text-end">{{ number_format($fmtNum($row['jm_daftar_p'] ?? 0)) }}</td>
                                        <td class="text-end">{{ number_format($fmtNum($row['jm_daftar_t'] ?? 0)) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center text-muted">Data belum tersedia</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var labels = @json($tanggal['label'] ?? []);
    var dataHarian = @json($capes['data'] ?? []);
    var dataKumulatif = @json($tanggal['kumulatif'] ?? []);

    if (labels.length > 0 && typeof Chart !== 'undefined') {
        var canvas1 = document.getElementById('tka-chart-capes-harian');
        if (canvas1) {
            new Chart(canvas1, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Calon Peserta',
                        data: dataHarian,
                        borderColor: '#1F3864',
                        backgroundColor: 'rgba(31,56,100,0.1)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 3
                    }]
                },
                options: { responsive: true, plugins: { legend: { display: false } } }
            });
        }

        var canvas2 = document.getElementById('tka-chart-kumulatif');
        if (canvas2) {
            new Chart(canvas2, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Kumulatif',
                        data: dataKumulatif,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40,167,69,0.1)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 3
                    }]
                },
                options: { responsive: true, plugins: { legend: { display: false } } }
            });
        }
    }
});
</script>

</body>
</html>
