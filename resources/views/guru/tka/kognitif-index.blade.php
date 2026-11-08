@extends('layouts.app')

@section('title', 'Rekap Analisis Kognitif & Rekomendasi TKA - Guru')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Dashboard Analisis Kognitif Per Siswa & Rekomendasi')
@section('page_subtitle', 'Rekap Otomatis Capaian Indikator Kognitif TKA, Tren Perkembangan, Zona Kemampuan & Paket Personalisasi')

@section('content')
@if(session('success'))
    <div class="note note-ok" style="margin-bottom:16px"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="note note-warn" style="margin-bottom:16px"><i class="fas fa-exclamation-circle"></i> {{ session('error') }}</div>
@endif

{{-- Ringkasan Zona Kemampuan Kelompok --}}
<div class="grid grid-4" style="margin-bottom:20px">
    <div class="stat-card primary">
        <i class="fas fa-users stat-icon"></i>
        <div class="stat-label">Rata-rata Kognitif Kelas</div>
        <div class="stat-value">{{ number_format((float) $rataKelompok, 1) }}</div>
        <div class="stat-change">{{ $totalSiswa }} siswa aktif dipantau</div>
    </div>
    <div class="stat-card ok">
        <i class="fas fa-arrow-up stat-icon"></i>
        <div class="stat-label">Di Atas Rata-rata</div>
        <div class="stat-value">{{ $zonaCounts['di_atas_rata_rata'] ?? 0 }}</div>
        <div class="stat-change up">Siap paket pengayaan HOTS</div>
    </div>
    <div class="stat-card gold">
        <i class="fas fa-grip-lines stat-icon"></i>
        <div class="stat-label">Zona Rata-rata</div>
        <div class="stat-value">{{ $zonaCounts['rata_rata'] ?? 0 }}</div>
        <div class="stat-change">Penguatan terfokus indikator</div>
    </div>
    <div class="stat-card bad">
        <i class="fas fa-arrow-down stat-icon"></i>
        <div class="stat-label">Di Bawah Rata-rata</div>
        <div class="stat-value">{{ $zonaCounts['di_bawah_rata_rata'] ?? 0 }}</div>
        <div class="stat-change down">Butuh paket klinik/remedial</div>
    </div>
</div>

{{-- Baris Filter & Tombol Aksi Otomatis --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-body" style="padding:16px 20px;display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:14px">
        <form method="GET" action="{{ route('guru.tka.kognitif.index') }}" style="display:flex;flex-wrap:wrap;align-items:flex-end;gap:12px">
            <div>
                <label style="display:block;font-size:12px;font-weight:700;color:var(--muted);margin-bottom:4px">Kelas</label>
                <select name="kelas" class="form-control" style="min-width:140px">
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
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            @if($kelasFilter !== '' || $zonaFilter !== '')
                <a href="{{ route('guru.tka.kognitif.index') }}" class="btn btn-secondary btn-sm">Reset</a>
            @endif
        </form>

        <div style="display:flex;flex-wrap:wrap;gap:8px">
            <form method="POST" action="{{ route('guru.tka.rekomendasi.generate') }}">
                @csrf
                <input type="hidden" name="kelas" value="{{ $kelasFilter }}">
                <button type="submit" class="btn btn-gold btn-sm">
                    <i class="fas fa-magic"></i> Susun Paket Rekomendasi Otomatis
                </button>
            </form>
            <a href="{{ route('guru.tka.integrasi-erapor.index', ['kelas' => $kelasFilter]) }}" class="btn btn-outline btn-sm">
                <i class="fas fa-sync-alt"></i> Integrasi ke e-Rapor
            </a>
            <a href="{{ route('guru.tka.laporan-kelas.index') }}" class="btn btn-outline btn-sm">
                <i class="fas fa-th"></i> Heatmap Kelas
            </a>
        </div>
    </div>
</div>

{{-- Tabel Rekap Kognitif Per Siswa --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-user-graduate" style="color:var(--navy);margin-right:8px"></i>Rekap Hasil TKA per Indikator Kognitif Siswa</h3>
        <span class="tag tag-secondary">{{ count($profiles) }} Siswa</span>
    </div>
    <div class="card-body" style="padding:0">
        @if(empty($profiles))
            <div style="padding:36px 20px;text-align:center;color:var(--muted)">
                Tidak ada data siswa yang sesuai dengan filter saat ini.
            </div>
        @else
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nama Siswa</th>
                            <th>Kelas</th>
                            <th style="text-align:center">Skor Kognitif</th>
                            <th style="text-align:center">Zona Kemampuan</th>
                            <th style="text-align:center">Tren Antar Waktu</th>
                            <th>Ringkasan Indikator (Lemah / Kuat)</th>
                            <th style="text-align:center">Paket Tindak Lanjut</th>
                            <th style="text-align:right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($profiles as $p)
                            @php
                                $s = $p['siswa'];
                                $rek = $p['rekomendasi'];
                            @endphp
                            <tr>
                                <td>
                                    <div style="font-weight:800;color:var(--navy)">{{ $s->nama_peserta_didik }}</div>
                                    <small style="color:var(--muted)">NIS: {{ $s->nis ?? '-' }} • {{ $p['jumlah_pengujian'] }}x uji</small>
                                </td>
                                <td><span class="tag tag-secondary">{{ $s->kelas }}</span></td>
                                <td style="text-align:center;font-weight:800;font-size:14.5px">
                                    {{ $p['jumlah_pengujian'] > 0 ? number_format((float) $p['skor_kognitif'], 1) : '—' }}
                                </td>
                                <td style="text-align:center">
                                    <span class="tag {{ $p['zona_badge'] }}">{{ $p['zona_label'] }}</span>
                                </td>
                                <td style="text-align:center">
                                    @if($p['tren_status'] === 'meningkat')
                                        <span class="tag tag-ok">↑ +{{ number_format((float) $p['tren_delta'], 1) }}</span>
                                    @elseif($p['tren_status'] === 'menurun')
                                        <span class="tag tag-bad">↓ {{ number_format((float) $p['tren_delta'], 1) }}</span>
                                    @elseif($p['tren_status'] === 'stabil')
                                        <span class="tag tag-primary">→ Stabil</span>
                                    @elseif($p['tren_status'] === 'awal')
                                        <span class="tag tag-secondary">Uji #1</span>
                                    @else
                                        <span style="color:var(--muted);font-size:12px">Belum diuji</span>
                                    @endif
                                </td>
                                <td style="font-size:12px">
                                    <div style="display:flex;flex-wrap:wrap;gap:4px">
                                        @foreach($indikatorStandar as $indName)
                                            @php $d = $p['indikator_scores'][$indName] ?? null; @endphp
                                            @if($d && $d['terukur'])
                                                <span class="tag {{ $d['skor'] >= 80 ? 'tag-ok' : ($d['skor'] >= 70 ? 'tag-primary' : 'tag-bad') }}" style="font-size:10.5px">
                                                    {{ \Illuminate\Support\Str::words($indName, 1, '') }}: {{ number_format($d['skor'], 0) }}
                                                </span>
                                            @endif
                                        @endforeach
                                    </div>
                                </td>
                                <td style="text-align:center">
                                    @if($rek)
                                        <span class="tag {{ $rek->status_tindak_lanjut === 'tuntas' ? 'tag-ok' : 'tag-gold' }}">
                                            {{ count($rek->paket_tindak_lanjut ?? []) }} Paket ({{ ucfirst($rek->status_tindak_lanjut) }})
                                        </span>
                                    @else
                                        <span style="font-size:12px;color:var(--muted)">Belum disusun</span>
                                    @endif
                                </td>
                                <td style="text-align:right">
                                    <a href="{{ route('guru.tka.kognitif.show', $s->uuid) }}" class="btn btn-primary btn-sm">
                                        Detail Profil
                                    </a>
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
