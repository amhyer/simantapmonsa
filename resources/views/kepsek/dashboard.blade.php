@extends('layouts.app')

@section('title', 'Dashboard Kepala Sekolah - SIMANTAP')

@section('sidebar')
    @include('kepsek.partials.sidebar')
@endsection

@section('page_title', 'Dashboard Kepala Sekolah')
@section('page_subtitle', 'Monitoring aktivitas sekolah secara real-time')

@section('content')
<div class="grid grid-4" style="margin-bottom:16px">
    <div class="stat-card primary"><div class="stat-value">{{ number_format($stats['total_siswa']) }}</div><div class="stat-label">Total Siswa</div></div>
    <div class="stat-card gold"><div class="stat-value">{{ $stats['total_guru'] }}</div><div class="stat-label">Total Guru</div></div>
    <div class="stat-card {{ $predikatSekolah['kelas'] }}"><div class="stat-value">{{ number_format($rataNilai, 1) }}</div><div class="stat-label">Rata-rata Nilai</div></div>
    <div class="stat-card ok"><div class="stat-value">{{ $persenHadir }}%</div><div class="stat-label">Kehadiran Rata-rata</div></div>
</div>

<div class="grid grid-2" style="margin-bottom:16px">
    <div class="card">
        <div class="card-header"><h3>📊 Aktivitas Guru 7 Hari</h3></div>
        <div class="card-body"><div style="height:280px"><canvas id="chartAktivitas"></canvas></div></div>
    </div>
    <div class="card">
        <div class="card-header"><h3>🎯 Distribusi Predikat</h3></div>
        <div class="card-body"><div style="height:280px"><canvas id="chartPredikat"></canvas></div></div>
    </div>
</div>

<div class="card" style="margin-bottom:16px">
    <div class="card-header"><h3>👨‍👩‍👧 Partisipasi Orang Tua</h3><span class="tag tag-{{ $partisipasiOrtu >= 70 ? 'ok' : ($partisipasiOrtu >= 40 ? 'warn' : 'bad') }}">{{ $partisipasiOrtu }}%</span></div>
    <div class="card-body">
        <div style="background:#F2F4F7;border-radius:8px;height:14px;overflow:hidden"><div style="height:100%;background:{{ $partisipasiOrtu >= 70 ? '#059669' : ($partisipasiOrtu >= 40 ? '#D97706' : '#DC2626') }};width:{{ $partisipasiOrtu }}%;border-radius:8px"></div></div>
        <div style="margin-top:12px;display:flex;justify-content:space-between;font-size:13px">
            <span><b>{{ $stats['total_orang_tua'] }}</b> akun orang tua</span>
            <span><b>{{ round($stats['total_siswa'] * $partisipasiOrtu / 100) }}</b> dari {{ $stats['total_siswa'] }} siswa dilaporkan</span>
        </div>
    </div>
</div>

<div class="grid grid-2" style="margin-bottom:16px">
    <div class="card">
        <div class="card-header"><h3>🏆 Top 5 Guru Aktif</h3></div>
        <div class="card-body tight">
            @if($topGuru->count())
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse">
                        <thead><tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC"><th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Guru</th><th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Nilai</th><th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Materi</th><th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Kuis</th><th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Siswa</th></tr></thead>
                        <tbody>
                            @foreach($topGuru as $item)
                                <tr style="border-bottom:1px solid #F2F4F7">
                                    <td style="padding:8px 12px"><div style="display:flex;align-items:center;gap:8px"><span style="width:28px;height:28px;border-radius:50%;background:#EEF2FF;color:#4338CA;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700">{{ substr($item['guru']->nama_lengkap, 0, 2) }}</span><div><div style="font-weight:600;font-size:12px">{{ $item['guru']->nama_lengkap }}</div><div style="font-size:11px;color:#667085">{{ $item['guru']->kelas_mata_pelajaran ?? '-' }}</div></div></div></td>
                                    <td style="padding:8px 12px;text-align:center"><span style="background:#D1FAE5;color:#065F46;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600">{{ $item['total_nilai'] }}</span></td>
                                    <td style="padding:8px 12px;text-align:center"><span style="background:#DBEAFE;color:#1E40AF;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600">{{ $item['total_materi'] }}</span></td>
                                    <td style="padding:8px 12px;text-align:center"><span style="background:#FEF3C7;color:#92400E;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600">{{ $item['total_kuis'] }}</span></td>
                                    <td style="padding:8px 12px;text-align:center;font-weight:700">{{ $item['total_siswa'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div style="text-align:center;padding:30px;color:#888">Belum ada aktivitas guru</div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>🏫 Ringkasan Per Kelas</h3></div>
        <div class="card-body tight">
            @if($perKelas->count())
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse">
                        <thead><tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC"><th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Kelas</th><th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Siswa</th><th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Rata Nilai</th><th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Hadir</th><th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Partisipasi</th></tr></thead>
                        <tbody>
                            @foreach($perKelas as $k)
                                <tr style="border-bottom:1px solid #F2F4F7">
                                    <td style="padding:8px 12px;font-weight:600">{{ $k['kelas'] }}</td>
                                    <td style="padding:8px 12px;text-align:center">{{ $k['jumlah'] }}</td>
                                    <td style="padding:8px 12px;text-align:center"><span style="background:{{ $k['rata_nilai'] >= 80 ? '#D1FAE5' : ($k['rata_nilai'] >= 70 ? '#FEF3C7' : '#FEE2E2') }};color:{{ $k['rata_nilai'] >= 80 ? '#065F46' : ($k['rata_nilai'] >= 70 ? '#92400E' : '#991B1B') }};padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600">{{ $k['rata_nilai'] }}</span></td>
                                    <td style="padding:8px 12px;text-align:center">{{ $k['persen_hadir'] }}%</td>
                                    <td style="padding:8px 12px;text-align:center"><span style="background:{{ $k['partisipasi_ortu'] >= 70 ? '#D1FAE5' : ($k['partisipasi_ortu'] >= 40 ? '#FEF3C7' : '#FEE2E2') }};color:{{ $k['partisipasi_ortu'] >= 70 ? '#065F46' : ($k['partisipasi_ortu'] >= 40 ? '#92400E' : '#991B1B') }};padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600">{{ $k['partisipasi_ortu'] }}%</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div style="text-align:center;padding:30px;color:#888">Belum ada data kelas</div>
            @endif
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <a href="{{ route('kepsek.rekap.index') }}" class="btn btn-sm" style="background:#1F3864;color:#fff">📊 Rekap Lengkap</a>
            <a href="{{ route('kepsek.hasil-belajar.index') }}" class="btn btn-ghost btn-sm">🎓 Hasil Belajar</a>
            <a href="{{ route('kepsek.pantau.index') }}" class="btn btn-ghost btn-sm">📡 Pantau Aktivitas</a>
            <a href="{{ route('kepsek.peta-kelas.index') }}" class="btn btn-ghost btn-sm">🏫 Peta Kelas</a>
        </div>
    </div>
</div>


@if(config('simantap.cbt_enabled', false))
{{-- CBT Analytics Section (kartu disembunyikan selama modul CBT mati) --}}
<div class="card" style="margin-bottom:16px">
    <div class="card-header">
        <h3><i class="fas fa-clipboard-check" style="margin-right:8px;color:var(--navy)"></i>Analisis CBT Sekolah</h3>
    </div>
    <div class="card-body">
        @php
            $cbtTotalSessions = $cbtRingkasan['total_sessions'] ?? 0;
            $cbtOngoing = $cbtRingkasan['ongoing'] ?? 0;
            $cbtCompleted = $cbtRingkasan['completed'] ?? 0;
            $cbtTotalParticipants = $cbtRingkasan['total_participants'] ?? 0;
            $cbtAvgScore = $cbtRingkasan['avg_score'] ?? 0;
            $cbtPassRate = $cbtRingkasan['pass_rate'] ?? 0;
        @endphp
        <div class="grid grid-5" style="margin-bottom:16px">
            <div class="stat" style="text-align:center;padding:12px;background:#F8F6FC;border-radius:8px">
                <div class="value" style="font-size:24px;color:var(--navy)">{{ $cbtTotalSessions }}</div>
                <div class="label" style="font-size:11px">Total Sesi</div>
            </div>
            <div class="stat" style="text-align:center;padding:12px;background:#FEF3C7;border-radius:8px">
                <div class="value" style="font-size:24px;color:#92400E">{{ $cbtOngoing }}</div>
                <div class="label" style="font-size:11px">Sedang Berlangsung</div>
            </div>
            <div class="stat" style="text-align:center;padding:12px;background:#D1FAE5;border-radius:8px">
                <div class="value" style="font-size:24px;color:#065F46">{{ $cbtCompleted }}</div>
                <div class="label" style="font-size:11px">Selesai</div>
            </div>
            <div class="stat" style="text-align:center;padding:12px;background:#DBEAFE;border-radius:8px">
                <div class="value" style="font-size:24px;color:#1E40AF">{{ $cbtTotalParticipants }}</div>
                <div class="label" style="font-size:11px">Peserta</div>
            </div>
            <div class="stat" style="text-align:center;padding:12px;background:#EEF2FF;border-radius:8px">
                <div class="value" style="font-size:24px;color:#4338CA">{{ number_format($cbtAvgScore, 1) }}</div>
                <div class="label" style="font-size:11px">Rata-rata Nilai</div>
            </div>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center">
            <div>
                <span style="font-size:12px;color:var(--muted)">Tingkat Kelulusan:</span>
                <span class="tag tag-{{ $cbtPassRate >= 80 ? 'ok' : ($cbtPassRate >= 60 ? 'warn' : 'bad') }}">{{ number_format($cbtPassRate, 1) }}%</span>
            </div>
            <div style="display:flex;gap:8px">
                <a href="{{ route('admin.cbt.sessions.index') }}" class="btn btn-ghost btn-sm">Detail Ujian</a>
            </div>
        </div>
    </div>
@endif
</div>
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctxAktivitas = document.getElementById('chartAktivitas').getContext('2d');
    new Chart(ctxAktivitas, {
        type: 'line',
        data: {
            labels: @json(array_column($aktivitasGuru, 'label')),
            datasets: [{
                label: 'Nilai Input', data: @json(array_column($aktivitasGuru, 'nilai_input')),
                borderColor: '#1F3864', backgroundColor: 'rgba(31,56,100,0.1)', fill: true, tension: 0.35, borderWidth: 2.5,
            }, {
                label: 'Materi', data: @json(array_column($aktivitasGuru, 'materi')),
                borderColor: '#B8860B', backgroundColor: 'rgba(184,134,11,0.1)', fill: true, tension: 0.35, borderWidth: 2.5,
            }, {
                label: 'Kuis', data: @json(array_column($aktivitasGuru, 'kuis')),
                borderColor: '#059669', backgroundColor: 'rgba(5,150,105,0.1)', fill: true, tension: 0.35, borderWidth: 2.5,
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12 } } }, scales: { y: { beginAtZero: true, grid: { color: '#EDF0F5' } }, x: { grid: { display: false } } } }
    });

    const ctxPredikat = document.getElementById('chartPredikat').getContext('2d');
    new Chart(ctxPredikat, {
        type: 'doughnut',
        data: {
            labels: ['A · Sangat Baik', 'B · Baik', 'C · Cukup', 'D · Perlu Bimbingan'],
            datasets: [{ data: [{{ $distribusiPredikat['A'] }}, {{ $distribusiPredikat['B'] }}, {{ $distribusiPredikat['C'] }}, {{ $distribusiPredikat['D'] }}], backgroundColor: ['#059669', '#1F3864', '#B8860B', '#DC2626'], borderWidth: 2, borderColor: '#fff' }]
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: '58%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12 } } } }
    });
});
</script>
@endpush
@endsection

