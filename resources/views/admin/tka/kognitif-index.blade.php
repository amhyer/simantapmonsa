@extends('layouts.admin-with-tabs')

@section('title', 'Analisis Kognitif Siswa TKA - Admin')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Dashboard Analisis Kognitif Per Siswa (Rekap Otomatis)')
@section('page_subtitle', 'Pemetaan Capaian Indikator Kognitif TKA, Tren Perkembangan Antar Waktu & Zona Kemampuan Seluruh Siswa')

@section('header_actions')
    <a href="{{ route('admin.tka.laporan-kelas.index') }}" class="btn btn-gold btn-sm">
        <i class="fas fa-th"></i> Laporan Kelas & Heatmap
    </a>
@endsection

@section('content')
<div class="grid grid-4" style="margin-bottom:20px">
    <div class="stat-card primary">
        <i class="fas fa-brain stat-icon"></i>
        <div class="stat-label">Rata-rata Kognitif</div>
        <div class="stat-value">{{ number_format((float) $rataKelompok, 1) }}</div>
        <div class="stat-change">{{ $totalSiswa }} siswa aktif</div>
    </div>
    <div class="stat-card ok">
        <i class="fas fa-arrow-trend-up stat-icon"></i>
        <div class="stat-label">Di Atas Rata-rata</div>
        <div class="stat-value">{{ $zonaCounts['di_atas_rata_rata'] ?? 0 }}</div>
        <div class="stat-change up">Potensi pengayaan akademik</div>
    </div>
    <div class="stat-card gold">
        <i class="fas fa-equals stat-icon"></i>
        <div class="stat-label">Zona Rata-rata</div>
        <div class="stat-value">{{ $zonaCounts['rata_rata'] ?? 0 }}</div>
        <div class="stat-change">Kesiapan stabil</div>
    </div>
    <div class="stat-card bad">
        <i class="fas fa-exclamation-circle stat-icon"></i>
        <div class="stat-label">Di Bawah Rata-rata</div>
        <div class="stat-value">{{ $zonaCounts['di_bawah_rata_rata'] ?? 0 }}</div>
        <div class="stat-change down">Prioritas pendampingan</div>
    </div>
</div>

<div class="card" style="margin-bottom:20px">
    <div class="card-body" style="padding:16px 20px">
        <form method="GET" action="{{ route('admin.tka.kognitif.index') }}" style="display:flex;flex-wrap:wrap;align-items:flex-end;gap:12px">
            <div>
                <label style="display:block;font-size:12px;font-weight:700;color:var(--muted);margin-bottom:4px">Kelas</label>
                <select name="kelas" class="form-control" style="min-width:150px">
                    <option value="">Semua Kelas</option>
                    @foreach($daftarKelas as $kls)
                        <option value="{{ $kls }}" @selected($kelasFilter === $kls)>{{ $kls }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:700;color:var(--muted);margin-bottom:4px">Zona Kemampuan</label>
                <select name="zona" class="form-control" style="min-width:180px">
                    <option value="">Semua Zona</option>
                    <option value="di_atas_rata_rata" @selected($zonaFilter === 'di_atas_rata_rata')>Di Atas Rata-rata</option>
                    <option value="rata_rata" @selected($zonaFilter === 'rata_rata')>Rata-rata</option>
                    <option value="di_bawah_rata_rata" @selected($zonaFilter === 'di_bawah_rata_rata')>Di Bawah Rata-rata</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Terapkan</button>
            @if($kelasFilter !== '' || $zonaFilter !== '')
                <a href="{{ route('admin.tka.kognitif.index') }}" class="btn btn-secondary btn-sm">Reset</a>
            @endif
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-users" style="color:var(--navy);margin-right:8px"></i>Daftar Rekap Kognitif Siswa</h3>
        <span class="tag tag-secondary">{{ count($profiles) }} Siswa</span>
    </div>
    <div class="card-body" style="padding:0">
        @if(empty($profiles))
            <div style="padding:36px 20px;text-align:center;color:var(--muted)">Belum ada data siswa sesuai filter.</div>
        @else
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nama Siswa</th>
                            <th>Kelas</th>
                            <th style="text-align:center">Skor Kognitif</th>
                            <th style="text-align:center">Zona Kemampuan</th>
                            <th style="text-align:center">Tren</th>
                            <th style="text-align:center">Jumlah Uji</th>
                            <th style="text-align:right">Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($profiles as $p)
                            @php $s = $p['siswa']; @endphp
                            <tr>
                                <td style="font-weight:800;color:var(--navy)">
                                    {{ $s->nama_peserta_didik }}
                                    <div style="font-size:11px;color:var(--muted);font-weight:400">NIS: {{ $s->nis ?? '-' }}</div>
                                </td>
                                <td><span class="tag tag-secondary">{{ $s->kelas }}</span></td>
                                <td style="text-align:center;font-weight:800">{{ $p['jumlah_pengujian'] > 0 ? number_format((float) $p['skor_kognitif'], 1) : '—' }}</td>
                                <td style="text-align:center"><span class="tag {{ $p['zona_badge'] }}">{{ $p['zona_label'] }}</span></td>
                                <td style="text-align:center">
                                    @if($p['tren_status'] === 'meningkat')
                                        <span class="tag tag-ok">↑ +{{ number_format((float) $p['tren_delta'], 1) }}</span>
                                    @elseif($p['tren_status'] === 'menurun')
                                        <span class="tag tag-bad">↓ {{ number_format((float) $p['tren_delta'], 1) }}</span>
                                    @elseif($p['tren_status'] === 'stabil')
                                        <span class="tag tag-primary">→ Stabil</span>
                                    @else
                                        <span style="color:var(--muted);font-size:12px">—</span>
                                    @endif
                                </td>
                                <td style="text-align:center">{{ $p['jumlah_pengujian'] }}x</td>
                                <td style="text-align:right">
                                    <a href="{{ route('admin.tka.kognitif.show', $s->uuid) }}" class="btn btn-primary btn-sm">Lihat Profil</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
