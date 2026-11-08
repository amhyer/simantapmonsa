@extends('layouts.app')

@section('title', 'Integrasi TKA ke Penilaian & e-Rapor - Guru')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Integrasi Otomatis TKA ↔ Penilaian & e-Rapor')
@section('page_subtitle', 'Salurkan Capaian Kognitif TKA Langsung ke Daftar Nilai Kognitif & Nilai Akhir e-Rapor Beserta Deskripsi Capaian')

@section('content')
@if(session('success'))
    <div class="note note-ok" style="margin-bottom:16px"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="note note-warn" style="margin-bottom:16px"><i class="fas fa-exclamation-circle"></i> {{ session('error') }}</div>
@endif

{{-- Form Sinkronisasi Massal Kelas --}}
<div class="card" style="margin-bottom:20px;border-left:4px solid var(--navy)">
    <div class="card-header">
        <h3><i class="fas fa-sync-alt" style="color:var(--navy);margin-right:8px"></i>Sinkronisasi Massal Hasil TKA Kelas ke Alur Penilaian & e-Rapor</h3>
        <span class="tag tag-ok">Audit Hash-Chain Aktif</span>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('guru.tka.integrasi-erapor.index') }}" style="margin-bottom:16px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <label style="font-size:12.5px;font-weight:700;color:var(--muted)">Pilih Rombel:</label>
            <select name="kelas" onchange="this.form.submit()" class="form-control" style="max-width:180px">
                <option value="">Semua Kelas</option>
                @foreach($daftarKelas as $kls)
                    <option value="{{ $kls }}" @selected($kelasFilter === $kls)>{{ $kls }}</option>
                @endforeach
            </select>
        </form>

        <form method="POST" action="{{ route('guru.tka.integrasi-erapor.sync') }}">
            @csrf
            <input type="hidden" name="kelas" value="{{ $kelasFilter }}">
            <div class="grid grid-4" style="gap:14px;margin-bottom:14px">
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px">Mata Pelajaran Tujuan</label>
                    <select name="mata_pelajaran" class="form-control" required>
                        @foreach($mapelList as $mpl)
                            <option value="{{ $mpl }}" @selected($defaultMapel === $mpl)>{{ $mpl }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px">Komponen Nilai e-Rapor</label>
                    <select name="komponen_target" class="form-control" required>
                        <option value="sumatif">Nilai Sumatif (40%)</option>
                        <option value="formatif">Nilai Formatif (30%)</option>
                        <option value="sumatif_akhir">Sumatif Akhir Semester (30%)</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px">Semester</label>
                    <select name="semester" class="form-control" required>
                        <option value="Ganjil" @selected($defaultSemester === 'Ganjil')>Ganjil</option>
                        <option value="Genap" @selected($defaultSemester === 'Genap')>Genap</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px">Tahun Ajaran</label>
                    <input type="text" name="tahun_ajaran" value="{{ $defaultTahunAjaran }}" class="form-control" required>
                </div>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
                <div style="font-size:12.5px;color:var(--muted)">
                    Menyalin skor kognitif TKA terukur ke <strong>Daftar Nilai Harian (Ulangan)</strong> sekaligus memperbarui komponen, nilai akhir, predikat, dan deskripsi capaian pada <strong>e-Rapor</strong>.
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-cloud-upload-alt"></i> Sinkronkan Seluruh Siswa Terukur
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Daftar kesiapan skor kognitif per siswa --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-header">
        <h3><i class="fas fa-list-ol" style="color:var(--navy);margin-right:8px"></i>Pratinjau Skor Kognitif Siswa Siap Integrasi</h3>
        <span class="tag tag-secondary">Rata-rata Kelompok: {{ number_format((float) $rataKelompok, 1) }}</span>
    </div>
    <div class="card-body" style="padding:0">
        @if(empty($profiles))
            <div style="padding:28px;text-align:center;color:var(--muted)">Belum ada data siswa aktif.</div>
        @else
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nama Siswa</th>
                            <th>Kelas</th>
                            <th style="text-align:center">Rekam Uji</th>
                            <th style="text-align:center">Skor Kognitif TKA</th>
                            <th style="text-align:center">Zona Kemampuan</th>
                            <th style="text-align:right">Sinkronisasi Individu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($profiles as $p)
                            @php $s = $p['siswa']; @endphp
                            <tr>
                                <td style="font-weight:700">{{ $s->nama_peserta_didik }}</td>
                                <td>{{ $s->kelas }}</td>
                                <td style="text-align:center">{{ $p['jumlah_pengujian'] }}x</td>
                                <td style="text-align:center;font-weight:800">
                                    {{ $p['jumlah_pengujian'] > 0 ? number_format((float) $p['skor_kognitif'], 1) : '—' }}
                                </td>
                                <td style="text-align:center">
                                    <span class="tag {{ $p['zona_badge'] }}">{{ $p['zona_label'] }}</span>
                                </td>
                                <td style="text-align:right">
                                    @if($p['jumlah_pengujian'] > 0)
                                        <form method="POST" action="{{ route('guru.tka.integrasi-erapor.sync') }}" style="display:inline">
                                            @csrf
                                            <input type="hidden" name="siswa_uuid" value="{{ $s->uuid }}">
                                            <input type="hidden" name="mata_pelajaran" value="{{ $defaultMapel }}">
                                            <input type="hidden" name="komponen_target" value="sumatif">
                                            <input type="hidden" name="semester" value="{{ $defaultSemester }}">
                                            <input type="hidden" name="tahun_ajaran" value="{{ $defaultTahunAjaran }}">
                                            <button type="submit" class="btn btn-outline btn-sm">
                                                <i class="fas fa-paper-plane"></i> Kirim ke {{ $defaultMapel }}
                                            </button>
                                        </form>
                                    @else
                                        <span style="font-size:12px;color:var(--muted)">Belum ada skor TKA</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

{{-- Riwayat Log Integrasi TKA ke e-Rapor --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-clipboard-check" style="color:var(--navy);margin-right:8px"></i>Riwayat Log Integrasi TKA → Penilaian & e-Rapor</h3>
        <span class="tag tag-secondary">{{ $syncLogs->count() }} Log Terakhir</span>
    </div>
    <div class="card-body" style="padding:0">
        @if($syncLogs->isEmpty())
            <div style="padding:24px;text-align:center;color:var(--muted);font-size:13px">
                Belum ada riwayat sinkronisasi TKA ke e-Rapor.
            </div>
        @else
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Waktu Sinkron</th>
                            <th>Siswa</th>
                            <th>Mata Pelajaran</th>
                            <th style="text-align:center">Komponen</th>
                            <th style="text-align:center">Skor TKA</th>
                            <th style="text-align:center">Nilai Akhir e-Rapor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($syncLogs as $log)
                            <tr>
                                <td style="font-size:12.5px">{{ $log->synced_at?->format('d M Y H:i') ?? '-' }}</td>
                                <td style="font-weight:700">{{ $log->siswa?->nama_peserta_didik ?? '-' }}</td>
                                <td>{{ $log->mata_pelajaran }}</td>
                                <td style="text-align:center"><span class="tag tag-primary">{{ strtoupper($log->komponen_target) }}</span></td>
                                <td style="text-align:center;font-weight:700">{{ number_format((float) $log->skor_tka_konversi, 1) }}</td>
                                <td style="text-align:center;font-weight:800;color:#166534">{{ number_format((float) $log->nilai_akhir_erapor, 1) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
