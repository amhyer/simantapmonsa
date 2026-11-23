@extends('layouts.app')

@section('title', 'Supervisi Pembelajaran Mendalam - Kepala Sekolah')
@section('page_title', 'Supervisi Pembelajaran Mendalam')
@section('page_subtitle', 'Matriks kelengkapan 9 dokumen administrasi guru (CP, TP, ATP, KKTP, Prota, Prosem, RPM, Jurnal & Kokurikuler) & Persetujuan Digital')

@section('sidebar')
    @include('kepsek.partials.sidebar')
@endsection

@section('header_actions')
    <a href="{{ route('kepsek.supervisi-pm.pdf', ['tahun_ajaran' => $tahunAjaran, 'semester' => $semester]) }}"
       target="_blank" class="btn btn-sm">
        <i class="fas fa-file-pdf"></i> Cetak Rekap Supervisi PDF
    </a>
@endsection

@section('content')
    {{-- KPI Cards --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:16px">
        <div class="card">
            <div class="card-body" style="padding:14px">
                <div style="font-size:12px;color:#667085;font-weight:600">Total Guru Aktif</div>
                <div style="font-size:22px;font-weight:700;color:#101828;margin-top:4px">{{ $kpi['total_guru'] }}</div>
            </div>
        </div>
        <div class="card">
            <div class="card-body" style="padding:14px">
                <div style="font-size:12px;color:#667085;font-weight:600">Rata-rata Kelengkapan</div>
                <div style="font-size:22px;font-weight:700;color:#175CD3;margin-top:4px">{{ $kpi['rata_kelengkapan'] }}%</div>
            </div>
        </div>
        <div class="card">
            <div class="card-body" style="padding:14px">
                <div style="font-size:12px;color:#667085;font-weight:600">Disetujui Kepsek</div>
                <div style="font-size:22px;font-weight:700;color:#027A48;margin-top:4px">{{ $kpi['disetujui'] }}</div>
            </div>
        </div>
        <div class="card">
            <div class="card-body" style="padding:14px">
                <div style="font-size:12px;color:#667085;font-weight:600">Perlu Revisi</div>
                <div style="font-size:22px;font-weight:700;color:var(--bad);margin-top:4px">{{ $kpi['revisi'] }}</div>
            </div>
        </div>
        <div class="card">
            <div class="card-body" style="padding:14px">
                <div style="font-size:12px;color:#667085;font-weight:600">Menunggu / Review</div>
                <div style="font-size:22px;font-weight:700;color:#B54708;margin-top:4px">{{ $kpi['menunggu'] }}</div>
            </div>
        </div>
    </div>

    {{-- Filter --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-body">
            <form method="GET" action="{{ route('kepsek.supervisi-pm.index') }}" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
                <input type="text" name="tahun_ajaran" value="{{ $tahunAjaran }}" placeholder="2026/2027"
                       style="width:110px;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                <select name="semester" style="padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                    <option value="1" {{ $semester === 1 ? 'selected' : '' }}>Semester 1 (Ganjil)</option>
                    <option value="2" {{ $semester === 2 ? 'selected' : '' }}>Semester 2 (Genap)</option>
                </select>
                <select name="status" style="padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                    <option value="">Semua Status</option>
                    @foreach($statusLabels as $kode => $label)
                        <option value="{{ $kode }}" {{ $statusFilter === $kode ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <input type="text" name="q" value="{{ $cari }}" placeholder="Cari nama guru..."
                       style="min-width:200px;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                <button type="submit" class="btn btn-sm">Terapkan</button>
                @if($cari || $statusFilter)
                    <a href="{{ route('kepsek.supervisi-pm.index', ['tahun_ajaran' => $tahunAjaran, 'semester' => $semester]) }}" class="btn btn-sm btn-ghost">Reset</a>
                @endif
            </form>
        </div>
    </div>

    {{-- Matriks Kelengkapan 9 Dokumen per Guru --}}
    <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
            <strong>Matriks Kelengkapan Pembelajaran Mendalam — {{ $tahunAjaran }} Semester {{ $semester }}</strong>
            <span style="font-size:12px;color:#667085">Klik <strong>Periksa &amp; Setujui</strong> untuk membuka seluruh dokumen guru</span>
        </div>
        <div class="card-body" style="padding:0">
            @if($rows->isEmpty())
                <div style="padding:32px;text-align:center;color:#667085">Belum ada data guru yang sesuai filter.</div>
            @else
                <div class="table-wrapper">
                    <table class="table table-compact">
                        <thead>
                            <tr>
                                <th style="width:36px">No</th>
                                <th style="min-width:160px">Nama Guru</th>
                                <th style="text-align:center">CP</th>
                                <th style="text-align:center">TP</th>
                                <th style="text-align:center">ATP</th>
                                <th style="text-align:center">KKTP</th>
                                <th style="text-align:center">Prota</th>
                                <th style="text-align:center">Prosem</th>
                                <th style="text-align:center">RPM</th>
                                <th style="text-align:center">Jurnal</th>
                                <th style="text-align:center">Koku</th>
                                <th style="text-align:center;width:60px">Skor</th>
                                <th style="white-space:nowrap">Status</th>
                                <th style="text-align:right;white-space:nowrap">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $i => $row)
                                @php $c = $row['counts']; @endphp
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>
                                        <div style="font-weight:600;color:#101828">{{ $row['guru']->nama_lengkap }}</div>
                                        @if($row['guru']->kelas_mata_pelajaran)
                                            <div style="font-size:11px;color:#475467">{{ $row['guru']->kelas_mata_pelajaran }}</div>
                                        @endif
                                    </td>
                                    @foreach(['cp','tp','atp','kktp','prota','prosem','rpm','jurnal','kokurikuler'] as $k)
                                        <td style="text-align:center;font-size:12px;white-space:nowrap">
                                            @if($c[$k] > 0)
                                                <span style="color:#027A48;font-weight:700" title="{{ $c[$k] }} dokumen">✓ {{ $c[$k] }}</span>
                                            @else
                                                <span style="color:#667085;font-weight:600">0</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td style="text-align:center;font-weight:700;white-space:nowrap;color:{{ $row['persen'] >= 80 ? '#027A48' : ($row['persen'] >= 50 ? '#B54708' : 'var(--bad)') }}">
                                        {{ $row['persen'] }}%
                                    </td>
                                    <td style="white-space:nowrap">
                                        <span class="badge {{ $row['status_badge'] }}">{{ $row['status_label'] }}</span>
                                    </td>
                                    <td style="text-align:right;white-space:nowrap">
                                        <a href="{{ route('kepsek.supervisi-pm.show', ['guru' => $row['guru'], 'tahun_ajaran' => $tahunAjaran, 'semester' => $semester]) }}"
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
