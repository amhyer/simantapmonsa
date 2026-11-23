@extends('layouts.app')

@section('title', 'Radar Mutu Satuan Pendidikan — Pengawas Sekolah')
@section('page_title', 'Radar Mutu & Pendampingan Pengawas Sekolah')
@section('page_subtitle', ($sekolah->nama_sekolah ?? 'Satuan Pendidikan Binaan') . ' · Tahun Ajaran ' . $tahunAjaran . ' · Semester ' . $semester)

@section('sidebar')
    @include('pengawas.partials.sidebar')
@endsection

@section('header_actions')
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <a href="{{ route('pengawas.visitasi.index', ['tahun_ajaran' => $tahunAjaran, 'semester' => $semester]) }}" class="btn btn-sm btn-ghost">
            <i class="fas fa-clipboard-list"></i> + Catat Visitasi Kelas
        </a>
        <a href="{{ route('pengawas.laporan.pdf', ['tahun_ajaran' => $tahunAjaran, 'semester' => $semester]) }}" target="_blank" class="btn btn-sm">
            <i class="fas fa-file-pdf"></i> Cetak Laporan Kepengawasan PDF
        </a>
    </div>
@endsection

@section('content')
    {{-- Filter Konteks & Pemilih Sekolah Binaan --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
            <form method="GET" action="{{ route('pengawas.dashboard') }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <span style="font-size:12px;font-weight:700;color:#344054"><i class="fas fa-calendar-alt"></i> Periode Pemantauan:</span>
                <input type="text" name="tahun_ajaran" value="{{ $tahunAjaran }}" placeholder="2026/2027"
                       style="width:110px;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                <select name="semester" style="padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                    <option value="1" {{ $semester === 1 ? 'selected' : '' }}>Semester 1 (Ganjil)</option>
                    <option value="2" {{ $semester === 2 ? 'selected' : '' }}>Semester 2 (Genap)</option>
                </select>
                <button type="submit" class="btn btn-sm">Terapkan</button>
            </form>

            @if($sekolahBinaanList->count() > 1)
                <form method="POST" action="{{ route('pengawas.sekolah.ganti') }}" style="display:flex;gap:8px;align-items:center">
                    @csrf
                    <span style="font-size:12px;font-weight:700;color:#175CD3"><i class="fas fa-school"></i> Sekolah Binaan:</span>
                    <select name="tenant_id" onchange="this.form.submit()" style="padding:7px 10px;border:1px solid #84CAFF;border-radius:8px;font-size:13px;font-weight:600">
                        @foreach($sekolahBinaanList as $sb)
                            <option value="{{ $sb->id }}" {{ (int) $activeTenantId === (int) $sb->id ? 'selected' : '' }}>
                                {{ $sb->nama_sekolah }} {{ $sb->npsn ? '(' . $sb->npsn . ')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>
    </div>

    {{-- 4 Indikator Utama Radar Mutu Satuan Pendidikan --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:16px">
        <div class="card" style="border-left:4px solid #175CD3">
            <div class="card-body" style="padding:16px">
                <div style="font-size:12px;color:#667085;font-weight:600">Indeks Kesehatan Akademik</div>
                <div style="font-size:26px;font-weight:800;color:#175CD3;margin-top:4px">{{ $kpi['indeks_kesehatan'] }}%</div>
                <div style="font-size:11px;color:#667085;margin-top:4px">Gabungan Kelengkapan PM, SLA Kepsek &amp; Verifikasi</div>
            </div>
        </div>
        <div class="card" style="border-left:4px solid #027A48">
            <div class="card-body" style="padding:16px">
                <div style="font-size:12px;color:#667085;font-weight:600">Kesiapan Pembelajaran Mendalam</div>
                <div style="font-size:26px;font-weight:800;color:#027A48;margin-top:4px">{{ $kpi['rata_kelengkapan_pm'] }}%</div>
                <div style="font-size:11px;color:#667085;margin-top:4px">Rata-rata kelengkapan 9 dokumen ({{ $kpi['total_guru'] }} Guru)</div>
            </div>
        </div>
        <div class="card" style="border-left:4px solid #B54708">
            <div class="card-body" style="padding:16px">
                <div style="font-size:12px;color:#667085;font-weight:600">SLA Supervisi Kepala Sekolah</div>
                <div style="font-size:26px;font-weight:800;color:#B54708;margin-top:4px">{{ $kpi['sla_supervisi_kepsek'] }}%</div>
                <div style="font-size:11px;color:#667085;margin-top:4px">{{ $kpi['disetujui_kepsek'] }} Disetujui · {{ $kpi['revisi_kepsek'] }} Revisi · {{ $kpi['menunggu_kepsek'] }} Menunggu</div>
            </div>
        </div>
        <div class="card" style="border-left:4px solid #6941C6">
            <div class="card-body" style="padding:16px">
                <div style="font-size:12px;color:#667085;font-weight:600">Verifikasi &amp; Visitasi Pengawas</div>
                <div style="font-size:26px;font-weight:800;color:#6941C6;margin-top:4px">{{ $kpi['persen_verifikasi_pengawas'] }}%</div>
                <div style="font-size:11px;color:#667085;margin-top:4px">{{ $kpi['terverifikasi_pengawas'] }} Terverifikasi · {{ $kpi['total_visitasi'] }} Visitasi (Skor {{ $kpi['rata_skor_visitasi'] }}/4)</div>
            </div>
        </div>
    </div>

    {{-- Statistik Operasional Sekolah (Read-Only) --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:16px">
        <div class="card"><div class="card-body" style="padding:12px"><div style="font-size:11px;color:#667085">Guru Aktif</div><div style="font-size:20px;font-weight:700">{{ $kpi['total_guru'] }}</div></div></div>
        <div class="card"><div class="card-body" style="padding:12px"><div style="font-size:11px;color:#667085">Peserta Didik Aktif</div><div style="font-size:20px;font-weight:700">{{ $kpi['total_siswa'] }}</div></div></div>
        <div class="card"><div class="card-body" style="padding:12px"><div style="font-size:11px;color:#667085">Rombongan Belajar</div><div style="font-size:20px;font-weight:700">{{ $kpi['total_rombel'] }}</div></div></div>
        <div class="card"><div class="card-body" style="padding:12px"><div style="font-size:11px;color:#667085">Realisasi Jurnal Mengajar</div><div style="font-size:20px;font-weight:700">{{ $kpi['total_jurnal'] }}</div></div></div>
        <div class="card"><div class="card-body" style="padding:12px"><div style="font-size:11px;color:#667085">Log 7 Kebiasaan Hebat</div><div style="font-size:20px;font-weight:700">{{ $kpi['total_kebiasaan'] }}</div></div></div>
    </div>

    {{-- Ringkasan Status Verifikasi Berjenjang Guru --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
            <strong>Pantauan Verifikasi Berjenjang (Guru &rarr; Kepala Sekolah &rarr; Pengawas)</strong>
            <a href="{{ route('pengawas.supervisi.index', ['tahun_ajaran' => $tahunAjaran, 'semester' => $semester]) }}" class="btn btn-sm btn-ghost">
                Buka Matriks Lengkap &rarr;
            </a>
        </div>
        <div class="card-body" style="padding:0">
            @if($guruRows->isEmpty())
                <div style="padding:24px;text-align:center;color:#667085">Belum ada data guru aktif pada sekolah binaan ini.</div>
            @else
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width:42px">No</th>
                                <th>Nama Guru</th>
                                <th style="text-align:center">Kelengkapan 9 Dokumen PM</th>
                                <th>Status Supervisi Kepsek</th>
                                <th>Status Verifikasi Pengawas</th>
                                <th style="text-align:right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($guruRows->take(10) as $i => $row)
                                @php $sup = $row['supervisi']; @endphp
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>
                                        <div style="font-weight:600;color:#101828">{{ $row['guru']->nama_lengkap }}</div>
                                        @if($row['guru']->kelas_mata_pelajaran)
                                            <div style="font-size:11px;color:#667085">{{ $row['guru']->kelas_mata_pelajaran }}</div>
                                        @endif
                                    </td>
                                    <td style="text-align:center;font-weight:700;color:{{ $row['persen'] >= 80 ? '#027A48' : ($row['persen'] >= 50 ? '#B54708' : 'var(--bad)') }}">
                                        {{ $row['persen'] }}% ({{ $row['terisi'] }}/9)
                                    </td>
                                    <td><span class="badge {{ $row['status_badge'] }}">{{ $row['status_label'] }}</span></td>
                                    <td>
                                        <span class="badge {{ $sup ? $sup->statusPengawasBadge() : 'badge-warning' }}">
                                            {{ $sup ? $sup->statusPengawasLabel() : 'Menunggu Verifikasi Pengawas' }}
                                        </span>
                                    </td>
                                    <td style="text-align:right">
                                        <a href="{{ route('pengawas.supervisi.show', ['guru' => $row['guru']->uuid, 'tahun_ajaran' => $tahunAjaran, 'semester' => $semester]) }}"
                                           class="btn btn-sm btn-ghost">
                                            <i class="fas fa-search"></i> Verifikasi
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

    {{-- Riwayat E-Visitasi & Observasi Kelas Terbaru --}}
    <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
            <strong>Riwayat E-Visitasi &amp; Observasi Kelas Terbaru</strong>
            <a href="{{ route('pengawas.visitasi.index', ['tahun_ajaran' => $tahunAjaran, 'semester' => $semester]) }}" class="btn btn-sm btn-ghost">
                Kelola Visitasi &rarr;
            </a>
        </div>
        <div class="card-body" style="padding:0">
            @if($visitasiTerbaru->isEmpty())
                <div style="padding:24px;text-align:center;color:#667085">Belum ada catatan visitasi / observasi kelas pada semester ini.</div>
            @else
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Jenis Pendampingan</th>
                                <th>Guru Diobservasi</th>
                                <th style="text-align:center">Skor Rubrik PM</th>
                                <th>Predikat</th>
                                <th style="text-align:right">Berita Acara PDF</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($visitasiTerbaru as $v)
                                <tr>
                                    <td style="white-space:nowrap">{{ $v->tanggal_visitasi->format('d/m/Y') }}</td>
                                    <td>{{ $v->jenisLabel() }}</td>
                                    <td>{{ $v->guru?->nama_lengkap ?? 'Seluruh Satuan Pendidikan (Manajerial)' }}</td>
                                    <td style="text-align:center;font-weight:700">{{ $v->rataRataSkor() }} / 4.00</td>
                                    <td><span class="badge badge-info">{{ $v->predikatObservasi() }}</span></td>
                                    <td style="text-align:right">
                                        <a href="{{ route('pengawas.visitasi.pdf', $v->uuid) }}" target="_blank" class="btn btn-sm btn-ghost">
                                            <i class="fas fa-file-pdf"></i> PDF
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
