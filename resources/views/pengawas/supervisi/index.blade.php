@extends('layouts.app')

@section('title', 'Verifikasi Berjenjang Pembelajaran Mendalam — Pengawas Sekolah')
@section('page_title', 'Verifikasi Berjenjang Perangkat Pembelajaran Mendalam')
@section('page_subtitle', 'Inspeksi read-only 9 dokumen guru, status supervisi Kepala Sekolah & pengesahan Pengawas Pembina')

@section('sidebar')
    @include('pengawas.partials.sidebar')
@endsection

@section('header_actions')
    <a href="{{ route('pengawas.laporan.pdf', ['tahun_ajaran' => $tahunAjaran, 'semester' => $semester]) }}"
       target="_blank" class="btn btn-sm">
        <i class="fas fa-file-pdf"></i> Cetak Laporan Kepengawasan PDF
    </a>
@endsection

@section('content')
    {{-- Filter --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-body">
            <form method="GET" action="{{ route('pengawas.supervisi.index') }}" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
                <input type="text" name="tahun_ajaran" value="{{ $tahunAjaran }}" placeholder="2026/2027"
                       style="width:110px;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                <select name="semester" style="padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                    <option value="1" {{ $semester === 1 ? 'selected' : '' }}>Semester 1 (Ganjil)</option>
                    <option value="2" {{ $semester === 2 ? 'selected' : '' }}>Semester 2 (Genap)</option>
                </select>
                <select name="status_kepsek" style="padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                    <option value="">Semua Status Kepsek</option>
                    @foreach($statusKepsekLabels as $kode => $label)
                        <option value="{{ $kode }}" {{ $statusKepsek === $kode ? 'selected' : '' }}>Kepsek: {{ $label }}</option>
                    @endforeach
                </select>
                <select name="status_pengawas" style="padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                    <option value="">Semua Status Pengawas</option>
                    @foreach($statusPengawasLabels as $kode => $label)
                        <option value="{{ $kode }}" {{ $statusPengawas === $kode ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <input type="text" name="q" value="{{ $cari }}" placeholder="Cari nama guru..."
                       style="min-width:180px;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                <button type="submit" class="btn btn-sm">Terapkan</button>
                @if($cari || $statusKepsek || $statusPengawas)
                    <a href="{{ route('pengawas.supervisi.index', ['tahun_ajaran' => $tahunAjaran, 'semester' => $semester]) }}" class="btn btn-sm btn-ghost">Reset</a>
                @endif
            </form>
        </div>
    </div>

    {{-- Tabel Matriks Verifikasi Berjenjang --}}
    <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
            <strong>Matriks 9 Dokumen Pembelajaran Mendalam — {{ $tahunAjaran }} Semester {{ $semester }}</strong>
            <span style="font-size:12px;color:#667085">Mode Pengawas: <strong>100% Read-Only</strong> pada dokumen guru + Form Verifikasi &amp; RTL</span>
        </div>
        <div class="card-body" style="padding:0">
            @if($rows->isEmpty())
                <div style="padding:32px;text-align:center;color:#667085">Belum ada data guru yang sesuai filter.</div>
            @else
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width:42px">No</th>
                                <th style="min-width:180px">Nama Guru</th>
                                <th style="text-align:center">CP</th>
                                <th style="text-align:center">TP</th>
                                <th style="text-align:center">ATP</th>
                                <th style="text-align:center">KKTP</th>
                                <th style="text-align:center">Prota</th>
                                <th style="text-align:center">Prosem</th>
                                <th style="text-align:center">RPM</th>
                                <th style="text-align:center">Jurnal</th>
                                <th style="text-align:center">Koku</th>
                                <th style="text-align:center">Skor</th>
                                <th>Supervisi Kepsek</th>
                                <th>Verifikasi Pengawas</th>
                                <th style="text-align:right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $i => $row)
                                @php
                                    $c = $row['counts'];
                                    $sup = $row['supervisi'];
                                @endphp
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>
                                        <div style="font-weight:600;color:#101828">{{ $row['guru']->nama_lengkap }}</div>
                                        @if($row['guru']->kelas_mata_pelajaran)
                                            <div style="font-size:11px;color:#667085">{{ $row['guru']->kelas_mata_pelajaran }}</div>
                                        @endif
                                    </td>
                                    @foreach(['cp','tp','atp','kktp','prota','prosem','rpm','jurnal','kokurikuler'] as $k)
                                        <td style="text-align:center;font-size:12px">
                                            @if($c[$k] > 0)
                                                <span style="color:#027A48;font-weight:700">✓ {{ $c[$k] }}</span>
                                            @else
                                                <span style="color:var(--bad);opacity:.65">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td style="text-align:center;font-weight:700">{{ $row['persen'] }}%</td>
                                    <td><span class="badge {{ $row['status_badge'] }}">{{ $row['status_label'] }}</span></td>
                                    <td>
                                        <span class="badge {{ $sup ? $sup->statusPengawasBadge() : 'badge-warning' }}">
                                            {{ $sup ? $sup->statusPengawasLabel() : 'Menunggu Verifikasi Pengawas' }}
                                        </span>
                                    </td>
                                    <td style="text-align:right">
                                        <a href="{{ route('pengawas.supervisi.show', ['guru' => $row['guru']->uuid, 'tahun_ajaran' => $tahunAjaran, 'semester' => $semester]) }}"
                                           class="btn btn-sm btn-ghost">
                                            <i class="fas fa-clipboard-check"></i> Periksa
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
