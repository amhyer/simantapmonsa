@extends('layouts.admin-with-tabs')

@section('title', 'Laporan Presensi - SIMANTAP_MONSA')
@section('page_title', 'Laporan Presensi')
@section('page_subtitle', 'Rekapitulasi kehadiran harian, bulanan, dan tahunan untuk Guru, Tenaga Kependidikan, dan Siswa')

@section('content')
<div class="presensi-laporan-container" x-data="{
    periode: @js($periode),
    kategori: @js($kategori),
    tanggalCetak: @js($tanggalCetak),
    tampilkanWaktu: @js((bool) $tampilkanWaktu),
    cetakBaseUrl: @js(route('admin.presensi.laporan.cetak', collect(request()->query())->except(['tanggal_cetak', 'tampilkan_waktu'])->all())),
    get cetakUrl() {
        const u = new URL(this.cetakBaseUrl, window.location.origin);
        if (this.tanggalCetak) u.searchParams.set('tanggal_cetak', this.tanggalCetak);
        if (this.tampilkanWaktu) u.searchParams.set('tampilkan_waktu', '1');
        return u.toString();
    }
}">
    {{-- Header Kop untuk Mode Cetak / Print --}}
    <div class="print-only" style="display:none;margin-bottom:20px;border-bottom:2px solid #000;padding-bottom:12px">
        <div style="display:flex;align-items:center;gap:16px;text-align:center">
            <img src="{{ asset('images/logo-128.png') }}" alt="Logo Sekolah" style="width:70px;height:70px;object-fit:cover">
            <div style="flex:1">
                <h2 style="margin:0;font-size:18px;text-transform:uppercase;font-weight:700">PEMERINTAH KOTA MAKASSAR</h2>
                <h3 style="margin:2px 0;font-size:16px;font-weight:800;text-transform:uppercase">{{ $sekolah->nama_sekolah ?? 'UPTD SD NEGERI UNGGULAN MONGISIDI 1' }}</h3>
                <p style="margin:0;font-size:12px;color:#333">{{ $sekolah->alamat ?? 'Jl. Mongisidi No. 1' }}, Kec. {{ $sekolah->kecamatan ?? 'Makassar' }}, Kota {{ $sekolah->kota ?? 'Makassar' }} | NPSN: {{ $sekolah->npsn ?? '-' }}</p>
            </div>
        </div>
        <div style="margin-top:14px;text-align:center">
            <h4 style="margin:0;font-size:14px;font-weight:700;text-decoration:underline">
                LAPORAN PRESENSI {{ strtoupper($kategori === 'siswa' ? 'SISWA' : ($kategori === 'tendik' ? 'TENAGA KEPENDIDIKAN' : ($kategori === 'guru' ? 'GURU' : 'PEGAWAI'))) }}
            </h4>
            <div style="font-size:12px;margin-top:4px">
                Periode: 
                @if($periode === 'harian')
                    {{ \Carbon\Carbon::parse($tanggal)->locale('id')->translatedFormat('l, d F Y') }}
                @elseif($periode === 'bulanan')
                    {{ \Carbon\Carbon::create($tahun, $bulan, 1)->locale('id')->translatedFormat('F Y') }}
                @else
                    Tahun {{ $tahun }}
                @endif
                @if($kategori === 'siswa' && $kelas)
                    | Kelas: {{ $kelas }}
                @endif
            </div>
        </div>
    </div>

    {{-- Kategori Tabs (Guru, Tendik, Semua Pegawai, Siswa) --}}
    <div class="no-print" style="margin-bottom:16px">
        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;border-bottom:1px solid var(--border, #e5e7eb);padding-bottom:12px">
            <span style="font-size:13px;font-weight:600;color:#4b5563;margin-right:8px">Kategori:</span>
            <a href="{{ route('admin.presensi.laporan', array_merge(request()->query(), ['kategori' => 'guru'])) }}"
               class="btn btn-sm {{ $kategori === 'guru' ? 'btn-primary' : 'btn-ghost' }}"
               style="border-radius:20px;padding:6px 14px;display:inline-flex;align-items:center;gap:6px">
                <i class="fas fa-chalkboard-teacher"></i> Guru
            </a>
            <a href="{{ route('admin.presensi.laporan', array_merge(request()->query(), ['kategori' => 'tendik'])) }}"
               class="btn btn-sm {{ $kategori === 'tendik' ? 'btn-primary' : 'btn-ghost' }}"
               style="border-radius:20px;padding:6px 14px;display:inline-flex;align-items:center;gap:6px">
                <i class="fas fa-user-tie"></i> Tenaga Kependidikan
            </a>
            <a href="{{ route('admin.presensi.laporan', array_merge(request()->query(), ['kategori' => 'pegawai'])) }}"
               class="btn btn-sm {{ $kategori === 'pegawai' ? 'btn-primary' : 'btn-ghost' }}"
               style="border-radius:20px;padding:6px 14px;display:inline-flex;align-items:center;gap:6px">
                <i class="fas fa-users"></i> Semua Pegawai
            </a>
            <a href="{{ route('admin.presensi.laporan', array_merge(request()->query(), ['kategori' => 'siswa'])) }}"
               class="btn btn-sm {{ $kategori === 'siswa' ? 'btn-primary' : 'btn-ghost' }}"
               style="border-radius:20px;padding:6px 14px;display:inline-flex;align-items:center;gap:6px">
                <i class="fas fa-user-graduate"></i> Siswa
            </a>
        </div>
    </div>

    {{-- Filter Panel & Periode Selector --}}
    <div class="card no-print" style="margin-bottom:20px">
        <div class="card-header" style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:12px">
            {{-- Segmented Periode Selector --}}
            <div style="display:flex;gap:4px;background:#f3f4f6;padding:4px;border-radius:10px">
                <a href="{{ route('admin.presensi.laporan', array_merge(request()->query(), ['periode' => 'harian'])) }}"
                   class="btn btn-sm {{ $periode === 'harian' ? 'btn-white' : 'btn-ghost' }}"
                   style="border-radius:8px;font-size:12px;font-weight:600;padding:5px 14px;{{ $periode === 'harian' ? 'background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.1)' : '' }}">
                    📅 Harian
                </a>
                <a href="{{ route('admin.presensi.laporan', array_merge(request()->query(), ['periode' => 'bulanan'])) }}"
                   class="btn btn-sm {{ $periode === 'bulanan' ? 'btn-white' : 'btn-ghost' }}"
                   style="border-radius:8px;font-size:12px;font-weight:600;padding:5px 14px;{{ $periode === 'bulanan' ? 'background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.1)' : '' }}">
                    🗓️ Bulanan
                </a>
                <a href="{{ route('admin.presensi.laporan', array_merge(request()->query(), ['periode' => 'tahunan'])) }}"
                   class="btn btn-sm {{ $periode === 'tahunan' ? 'btn-white' : 'btn-ghost' }}"
                   style="border-radius:8px;font-size:12px;font-weight:600;padding:5px 14px;{{ $periode === 'tahunan' ? 'background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.1)' : '' }}">
                    📆 Tahunan
                </a>
            </div>

            {{-- Action Buttons (Export & Cetak) --}}
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <label style="display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:#374151;cursor:pointer;background:#f9fafb;border:1px solid #d1d5db;padding:5px 10px;border-radius:8px">
                    <input type="checkbox" x-model="tampilkanWaktu" style="accent-color:#059669;cursor:pointer">
                    <span>{{ $kategori === 'siswa' ? 'Tampilkan Jam Masuk saat Cetak' : 'Tampilkan Jam Masuk & Pulang saat Cetak' }}</span>
                </label>
                <a href="{{ route('admin.presensi.laporan.export', request()->query()) }}" class="btn btn-sm btn-ghost" style="display:inline-flex;align-items:center;gap:6px">
                    <i class="fas fa-file-excel" style="color:#059669"></i> Export CSV / Excel
                </a>
                <a href="{{ route('admin.presensi.laporan.cetak', array_merge(request()->query(), ['tanggal_cetak' => $tanggalCetak])) }}" :href="cetakUrl" target="_blank" class="btn btn-sm btn-primary" style="display:inline-flex;align-items:center;gap:6px">
                    <i class="fas fa-print"></i> Cetak Daftar Presensi Saja
                </a>
            </div>
        </div>

        <div class="card-body">
            <form action="{{ route('admin.presensi.laporan') }}" method="GET" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:12px;align-items:end">
                <input type="hidden" name="kategori" value="{{ $kategori }}">
                <input type="hidden" name="periode" value="{{ $periode }}">

                @if($periode === 'harian')
                    <div>
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Tanggal</label>
                        <input type="date" name="tanggal" value="{{ $tanggal }}" class="form-control" style="width:100%">
                    </div>
                @elseif($periode === 'bulanan')
                    <div>
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Bulan</label>
                        <select name="bulan" class="form-control" style="width:100%">
                            @for($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}" {{ (int)$bulan === $m ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::create(2026, $m, 1)->locale('id')->translatedFormat('F') }}
                                </option>
                            @endfor
                        </select>
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Tahun</label>
                        <select name="tahun" class="form-control" style="width:100%">
                            @for($y = (int)date('Y') - 3; $y <= (int)date('Y') + 1; $y++)
                                <option value="{{ $y }}" {{ (int)$tahun === $y ? 'selected' : '' }}>{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                @else
                    <div>
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Tahun</label>
                        <select name="tahun" class="form-control" style="width:100%">
                            @for($y = (int)date('Y') - 3; $y <= (int)date('Y') + 1; $y++)
                                <option value="{{ $y }}" {{ (int)$tahun === $y ? 'selected' : '' }}>{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                @endif

                @if($kategori !== 'siswa')
                    <div>
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Pegawai</label>
                        <select name="user_id" class="form-control" style="width:100%">
                            <option value="">Semua Pegawai</option>
                            @foreach($pegawaiFilterList as $p)
                                <option value="{{ $p->id }}" {{ (string)$userId === (string)$p->id ? 'selected' : '' }}>
                                    {{ $p->nama_lengkap }} ({{ ucfirst($p->peran) }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div>
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Rombel / Kelas</label>
                        <select name="kelas" class="form-control" style="width:100%">
                            <option value="">Semua Kelas</option>
                            @foreach($kelasList as $k)
                                <option value="{{ $k }}" {{ $kelas === $k ? 'selected' : '' }}>{{ $k }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div>
                    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Pencarian</label>
                    <input type="text" name="search" value="{{ $search }}" placeholder="{{ $kategori === 'siswa' ? 'Nama / NISN / NIS' : 'Nama / NIP' }}" class="form-control" style="width:100%">
                </div>

                <div>
                    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Tanggal Cetak</label>
                    <input type="date" name="tanggal_cetak" x-model="tanggalCetak" value="{{ $tanggalCetak }}" class="form-control" style="width:100%">
                </div>

                <input type="hidden" name="tampilkan_waktu" :value="tampilkanWaktu ? 1 : 0" value="{{ $tampilkanWaktu ? 1 : 0 }}">

                <div>
                    <button type="submit" class="btn btn-sm btn-primary" style="width:100%;padding:7px 12px">
                        <i class="fas fa-filter"></i> Terapkan Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Ringkasan Statistik / KPI --}}
    <div class="grid grid-4 no-print" style="margin-bottom:20px">
        <div class="stat">
            <div class="label">Total {{ $kategori === 'siswa' ? 'Siswa' : 'Personel' }}</div>
            <div class="value">{{ $kpi['total_personel'] }}</div>
        </div>
        <div class="stat ok">
            <div class="label">Hadir {{ $periode === 'harian' ? 'Hari Ini' : ($periode === 'bulanan' ? 'Bulan Ini' : 'Tahun Ini') }}</div>
            <div class="value">
                {{ $kpi['total_hadir'] }}
                @if($periode === 'harian' && $kpi['total_personel'] > 0)
                    <span style="font-size:13px;font-weight:normal;color:#10b981">({{ $kpi['persen_hadir'] }}%)</span>
                @endif
            </div>
        </div>
        <div class="stat gold">
            <div class="label">Izin & Sakit</div>
            <div class="value">{{ $kpi['total_izin'] + $kpi['total_sakit'] }} <span style="font-size:12px;font-weight:normal;color:#6b7280">(I: {{ $kpi['total_izin'] }}, S: {{ $kpi['total_sakit'] }})</span></div>
        </div>
        @if($kategori !== 'siswa')
            <div class="stat primary">
                <div class="label">Rata-rata Jam Kerja</div>
                <div class="value">{{ $kpi['rata_rata_durasi_label'] }}</div>
            </div>
        @else
            <div class="stat bad">
                <div class="label">Alpa / Tanpa Keterangan</div>
                <div class="value">{{ $kpi['total_alpa'] }}</div>
            </div>
        @endif
    </div>

    {{-- Tabel Laporan Presensi --}}
    <div class="card">
        <div class="card-header no-print" style="display:flex;justify-content:space-between;align-items:center">
            <h3 style="margin:0;font-size:16px;font-weight:700">
                Daftar Presensi {{ ucfirst($kategori === 'siswa' ? 'Siswa' : ($kategori === 'tendik' ? 'Tenaga Kependidikan' : ($kategori === 'guru' ? 'Guru' : 'Pegawai'))) }}
                <span style="font-size:13px;font-weight:400;color:#6b7280">
                    ({{ $periode === 'harian' ? \Carbon\Carbon::parse($tanggal)->locale('id')->translatedFormat('d F Y') : ($periode === 'bulanan' ? \Carbon\Carbon::create($tahun, $bulan, 1)->locale('id')->translatedFormat('F Y') : 'Tahun ' . $tahun) }})
                </span>
            </h3>
            <span style="font-size:12px;color:#6b7280">Ditemukan {{ count($rows) }} data</span>
        </div>

        @if($kategori !== 'siswa')
            <div class="table-wrapper">
                {{-- TABEL PEGAWAI (GURU & TENDIK) --}}
                <table class="table" style="width:100%;border-collapse:collapse">
                    <thead>
                        <tr>
                            <th style="width:40px;text-align:center">No</th>
                            <th>Nama Pegawai & NIP</th>
                            <th>Peran</th>
                            @if($periode === 'harian')
                                <th style="text-align:center">Status</th>
                                <th style="text-align:center">Jam Masuk</th>
                                <th style="text-align:center">Jam Pulang</th>
                                <th style="text-align:center;background:#f0fdf4">Jam Hari Ini</th>
                                <th style="text-align:center;background:#eff6ff">Jam Minggu Ini</th>
                                <th style="text-align:center;background:#fefce8">Jam Tahun Ini</th>
                                <th style="text-align:center">Metode</th>
                            @elseif($periode === 'bulanan')
                                <th style="text-align:center">Hadir</th>
                                <th style="text-align:center">Terlambat</th>
                                <th style="text-align:center">Izin/Sakit</th>
                                <th style="text-align:center">Alpa</th>
                                <th style="text-align:center;background:#f0fdf4">Total Jam Bulan Ini</th>
                                <th style="text-align:center">Rata-rata/Hari</th>
                                <th style="text-align:center;background:#eff6ff">Jam Minggu Ini</th>
                                <th style="text-align:center;background:#fefce8">Jam Tahun Ini</th>
                            @else
                                <th style="text-align:center">Hadir</th>
                                <th style="text-align:center">Terlambat</th>
                                <th style="text-align:center">Izin/Sakit</th>
                                <th style="text-align:center">Alpa</th>
                                <th style="text-align:center;background:#f0fdf4">Total Jam Tahun Ini</th>
                                <th style="text-align:center">Rata-rata/Minggu</th>
                                <th style="text-align:center">Rata-rata/Hari</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $index => $row)
                            <tr>
                                <td style="text-align:center">{{ $index + 1 }}</td>
                                <td>
                                    <div style="font-weight:600;color:#111827">{{ $row['user']->nama_lengkap }}</div>
                                    <div style="font-size:12px;color:#6b7280">NIP: {{ $row['user']->nip ?: '-' }}</div>
                                </td>
                                <td>
                                    <span class="badge {{ $row['user']->peran === 'guru' ? 'badge-info' : 'badge-neutral' }}" style="font-size:11px">
                                        {{ $row['peran_label'] }}
                                    </span>
                                </td>

                                @if($periode === 'harian')
                                    <td style="text-align:center">
                                        @if(in_array($row['status'], ['hadir', 'terlambat'], true))
                                            <span class="badge {{ $row['status'] === 'hadir' ? 'badge-success' : 'badge-warning' }}">
                                                {{ ucfirst($row['status']) }}
                                            </span>
                                        @elseif($row['status'] === 'izin')
                                            <span class="badge badge-info">Izin</span>
                                        @elseif($row['status'] === 'sakit')
                                            <span class="badge badge-warning">Sakit</span>
                                        @elseif($row['status'] === 'alpa')
                                            <span class="badge badge-danger">Alpa</span>
                                        @else
                                            <span class="badge" style="background:#e5e7eb;color:#4b5563">Belum Absen</span>
                                        @endif
                                    </td>
                                    <td style="text-align:center;font-weight:600">{{ $row['jam_masuk'] }}</td>
                                    <td style="text-align:center;font-weight:600">{{ $row['jam_pulang'] }}</td>
                                    <td style="text-align:center;font-weight:700;color:#059669;background:#f0fdf4">
                                        {{ $row['durasi_hari'] }}
                                        @if($row['durasi_hari_jam'] > 0)
                                            <span style="font-size:11px;font-weight:normal;color:#047857;display:block">({{ $row['durasi_hari_jam'] }} jam)</span>
                                        @endif
                                    </td>
                                    <td style="text-align:center;font-weight:700;color:#2563eb;background:#eff6ff">
                                        {{ $row['durasi_minggu'] }}
                                        @if($row['durasi_minggu_jam'] > 0)
                                            <span style="font-size:11px;font-weight:normal;color:#1d4ed8;display:block">({{ $row['durasi_minggu_jam'] }} jam)</span>
                                        @endif
                                    </td>
                                    <td style="text-align:center;font-weight:700;color:#d97706;background:#fefce8">
                                        {{ $row['durasi_tahun'] }}
                                        @if($row['durasi_tahun_jam'] > 0)
                                            <span style="font-size:11px;font-weight:normal;color:#b45309;display:block">({{ $row['durasi_tahun_jam'] }} jam)</span>
                                        @endif
                                    </td>
                                    <td style="text-align:center;font-size:12px">{{ $row['metode'] }}</td>

                                @elseif($periode === 'bulanan')
                                    <td style="text-align:center;font-weight:600;color:#059669">{{ $row['hadir_bulan'] }}</td>
                                    <td style="text-align:center;font-weight:600;color:#d97706">{{ $row['terlambat_bulan'] }}</td>
                                    <td style="text-align:center">{{ $row['izin_bulan'] + $row['sakit_bulan'] }}</td>
                                    <td style="text-align:center;color:#dc2626">{{ $row['alpa_bulan'] }}</td>
                                    <td style="text-align:center;font-weight:700;color:#059669;background:#f0fdf4">
                                        {{ $row['durasi_bulan'] }}
                                        <span style="font-size:11px;font-weight:normal;color:#047857;display:block">({{ $row['durasi_bulan_jam'] }} jam)</span>
                                    </td>
                                    <td style="text-align:center">{{ $row['rata_rata_hari_bulan'] }}</td>
                                    <td style="text-align:center;font-weight:700;color:#2563eb;background:#eff6ff">{{ $row['durasi_minggu'] }}</td>
                                    <td style="text-align:center;font-weight:700;color:#d97706;background:#fefce8">{{ $row['durasi_tahun'] }}</td>

                                @else
                                    <td style="text-align:center;font-weight:600;color:#059669">{{ $row['hadir_tahun'] }}</td>
                                    <td style="text-align:center;font-weight:600;color:#d97706">{{ $row['terlambat_tahun'] }}</td>
                                    <td style="text-align:center">{{ $row['izin_tahun'] + $row['sakit_tahun'] }}</td>
                                    <td style="text-align:center;color:#dc2626">{{ $row['alpa_tahun'] }}</td>
                                    <td style="text-align:center;font-weight:700;color:#059669;background:#f0fdf4">
                                        {{ $row['durasi_tahun'] }}
                                        <span style="font-size:11px;font-weight:normal;color:#047857;display:block">({{ $row['durasi_tahun_jam'] }} jam)</span>
                                    </td>
                                    <td style="text-align:center">{{ $row['rata_rata_minggu_tahun'] }}</td>
                                    <td style="text-align:center">{{ $row['rata_rata_hari_tahun'] }}</td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" style="text-align:center;padding:30px;color:#6b7280">
                                    <i class="fas fa-inbox" style="font-size:24px;margin-bottom:8px;display:block"></i>
                                    Tidak ada data presensi yang ditemukan untuk kriteria filter ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @else
            <div class="table-wrapper">
                {{-- TABEL SISWA --}}
                <table class="table" style="width:100%;border-collapse:collapse">
                    <thead>
                        <tr>
                            <th style="width:40px;text-align:center">No</th>
                            <th>NISN / NIS</th>
                            <th>Nama Siswa</th>
                            <th style="text-align:center">Kelas</th>
                            @if($periode === 'harian')
                                <th style="text-align:center">Status Kehadiran</th>
                                <th style="text-align:center">Jam Masuk</th>
                                <th style="text-align:center">Metode</th>
                                <th>Keterangan</th>
                            @elseif($periode === 'bulanan')
                                <th style="text-align:center">Hadir (H)</th>
                                <th style="text-align:center">Sakit (S)</th>
                                <th style="text-align:center">Izin (I)</th>
                                <th style="text-align:center">Alpa (A)</th>
                                <th style="text-align:center;background:#f0fdf4">% Kehadiran</th>
                            @else
                                <th style="text-align:center">Hadir (H)</th>
                                <th style="text-align:center">Sakit (S)</th>
                                <th style="text-align:center">Izin (I)</th>
                                <th style="text-align:center">Alpa (A)</th>
                                <th style="text-align:center;background:#f0fdf4">% Kehadiran Tahunan</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $index => $row)
                            <tr>
                                <td style="text-align:center">{{ $index + 1 }}</td>
                                <td style="font-size:12px;font-family:monospace">
                                    <div>{{ $row['siswa']->nisn ?: '-' }}</div>
                                    <div style="color:#6b7280">{{ $row['siswa']->nis ?: '' }}</div>
                                </td>
                                <td style="font-weight:600;color:#111827">{{ $row['siswa']->nama_peserta_didik }}</td>
                                <td style="text-align:center">
                                    <span class="badge badge-info" style="font-size:11px">{{ $row['kelas'] }}</span>
                                </td>

                                @if($periode === 'harian')
                                    <td style="text-align:center">
                                        @if($row['status'] === 'Hadir')
                                            <span class="badge badge-success">Hadir</span>
                                        @elseif($row['status'] === 'Sakit')
                                            <span class="badge badge-warning">Sakit</span>
                                        @elseif($row['status'] === 'Izin')
                                            <span class="badge badge-info">Izin</span>
                                        @elseif($row['status'] === 'Alpa')
                                            <span class="badge badge-danger">Alpa</span>
                                        @else
                                            <span class="badge" style="background:#e5e7eb;color:#4b5563">Belum Absen</span>
                                        @endif
                                    </td>
                                    <td style="text-align:center;font-weight:600">{{ $row['jam_masuk'] }}</td>
                                    <td style="text-align:center;font-size:12px">{{ $row['metode'] }}</td>
                                    <td style="font-size:12px;color:#4b5563">{{ $row['keterangan'] }}</td>

                                @elseif($periode === 'bulanan')
                                    <td style="text-align:center;font-weight:600;color:#059669">{{ $row['hadir_bulan'] }}</td>
                                    <td style="text-align:center">{{ $row['sakit_bulan'] }}</td>
                                    <td style="text-align:center">{{ $row['izin_bulan'] }}</td>
                                    <td style="text-align:center;color:#dc2626">{{ $row['alpa_bulan'] }}</td>
                                    <td style="text-align:center;font-weight:700;color:#059669;background:#f0fdf4">
                                        {{ $row['persen_bulan'] }}%
                                    </td>

                                @else
                                    <td style="text-align:center;font-weight:600;color:#059669">{{ $row['hadir_tahun'] }}</td>
                                    <td style="text-align:center">{{ $row['sakit_tahun'] }}</td>
                                    <td style="text-align:center">{{ $row['izin_tahun'] }}</td>
                                    <td style="text-align:center;color:#dc2626">{{ $row['alpa_tahun'] }}</td>
                                    <td style="text-align:center;font-weight:700;color:#059669;background:#f0fdf4">
                                        {{ $row['persen_tahun'] }}%
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" style="text-align:center;padding:30px;color:#6b7280">
                                    <i class="fas fa-inbox" style="font-size:24px;margin-bottom:8px;display:block"></i>
                                    Tidak ada data kehadiran siswa yang ditemukan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Footer Tanda Tangan Cetak --}}
    <div class="print-only" style="display:none;margin-top:30px;page-break-inside:avoid">
        <div style="display:flex;justify-content:space-between;text-align:center">
            <div style="width:200px">
                <p style="margin:0 0 50px">Petugas Presensi,</p>
                <p style="margin:0;font-weight:700;text-decoration:underline">{{ auth()->user()->nama_lengkap ?? 'Administrator' }}</p>
                <p style="margin:2px 0 0;font-size:11px">NIP. {{ auth()->user()->nip ?? '-' }}</p>
            </div>
            <div style="width:220px">
                <p style="margin:0">{{ trim((string) preg_replace('/^kota\s+/i', '', $sekolah->kota ?? 'Makassar')) ?: 'Makassar' }}, {{ \Carbon\Carbon::parse($tanggalCetak)->locale('id')->translatedFormat('d F Y') }}</p>
                <p style="margin:0 0 50px">Mengetahui,<br>Kepala Sekolah</p>
                <p style="margin:0;font-weight:700;text-decoration:underline">{{ $sekolah->kepala_sekolah ?? 'Kepala Sekolah' }}</p>
                <p style="margin:2px 0 0;font-size:11px">NIP. {{ $sekolah->nip_kepala_sekolah ?? '-' }}</p>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .sidebar, .topbar, .admin-tabs, .mobile-bottom-nav, .toast-container, .no-print, .sidebar-overlay, .topbar-academic-pill {
        display: none !important;
    }
    .main-content, .page-content {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }
    .print-only {
        display: block !important;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
    }
    .table {
        width: 100% !important;
        border: 1px solid #000 !important;
    }
    .table th, .table td {
        border: 1px solid #000 !important;
        padding: 6px 8px !important;
        font-size: 11px !important;
    }
    .table th {
        background: #f3f4f6 !important;
        color: #000 !important;
    }
    .stat {
        border: 1px solid #ddd !important;
    }
}
</style>
@endsection
