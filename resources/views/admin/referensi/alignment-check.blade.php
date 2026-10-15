<<<<<<< HEAD
@extends('layouts.admin-with-tabs')

@section('title', 'Cek Alignment E-Rapor - SIMANTAP')
=======
@extends('layouts.app')

@section('title', 'Cek Alignment e-Rapor - SIMANTAP')
>>>>>>> origin/main

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

<<<<<<< HEAD
@section('page_title', 'Cek Alignment E-Rapor')
@section('page_subtitle', 'Keterpetakan nilai_erapor ke jadwal_pelajaran (pembelajaran resmi)')

@section('header_actions')
    <a href="{{ route('admin.referensi.pembelajaran') }}" class="btn btn-sm btn-ghost">Data Pembelajaran</a>
=======
@section('page_title', 'Cek Alignment e-Rapor')
@section('page_subtitle', 'Seberapa banyak nilai e-Rapor sudah terhubung ke baris pembelajaran (jadwal_pelajaran)')

@section('header_actions')
    <a href="{{ route('admin.referensi.pembelajaran') }}" class="btn btn-sm">Data Pembelajaran</a>
    <a href="{{ route('admin.dapodik.index') }}" class="btn btn-sm btn-ghost">Import Dapodik</a>
>>>>>>> origin/main
@endsection

@section('content')
    <div class="grid grid-4" style="margin-bottom:16px">
<<<<<<< HEAD
        <div class="stat"><div class="label">Total nilai e-Rapor</div><div class="value">{{ number_format($stats['total']) }}</div></div>
        <div class="stat ok"><div class="label">Terpetakan</div><div class="value">{{ number_format($stats['mapped']) }}</div></div>
        <div class="stat {{ $stats['unmapped'] ? 'bad' : 'ok' }}"><div class="label">Belum terpetakan</div><div class="value">{{ number_format($stats['unmapped']) }}</div></div>
        <div class="stat gold"><div class="label">Persentase</div><div class="value">{{ $stats['persen'] }}%</div></div>
=======
        <div class="stat">
            <div class="label">Total nilai e-Rapor</div>
            <div class="value">{{ number_format($stats['total_nilai'], 0, ',', '.') }}</div>
        </div>
        <div class="stat ok">
            <div class="label">Sudah terpetakan</div>
            <div class="value">{{ number_format($stats['terpetakan'], 0, ',', '.') }}</div>
            <div class="label">{{ $stats['persen_terpetakan'] }}% dari total</div>
        </div>
        <div class="stat {{ $stats['belum_terpetakan'] > 0 ? 'bad' : '' }}">
            <div class="label">Belum terpetakan</div>
            <div class="value">{{ number_format($stats['belum_terpetakan'], 0, ',', '.') }}</div>
        </div>
        <div class="stat gold">
            <div class="label">Baris pembelajaran</div>
            <div class="value">{{ number_format($stats['total_jadwal'], 0, ',', '.') }}</div>
            <div class="label">{{ number_format($stats['jadwal_tanpa_rombel'], 0, ',', '.') }} tanpa rombel</div>
        </div>
>>>>>>> origin/main
    </div>

    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
<<<<<<< HEAD
            <h3>Rincian Belum Terpetakan</h3>
        </div>
        <div class="card-body tight">
            @if($stats['unmapped'] > 0)
=======
            <h3>Yang perlu dilengkapi</h3>
            <span class="tag tag-mut">Mapel × Kelas</span>
        </div>
        <div class="card-body tight">
            @if($stats['belum_list']->count())
>>>>>>> origin/main
                <div class="table-wrapper">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
<<<<<<< HEAD
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Sebab</th>
                                <th style="padding:11px 14px;text-align:right;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $labelKategori = [
                                    'semester_tak_cocok' => 'Semester/tahun ajaran tak cocok',
                                    'guru_tak_punya_jadwal' => 'Guru tak punya jadwal sama sekali',
                                    'mapel_tak_cocok' => 'Nama mapel tak cocok',
                                    'kelas_tak_cocok' => 'Nama kelas tak cocok',
                                ];
                            @endphp
                            @foreach($stats['kategori'] as $kunci => $jumlah)
                                <tr>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">{{ $labelKategori[$kunci] ?? $kunci }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:right;font-weight:700">{{ number_format($jumlah) }}</td>
=======
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Mata Pelajaran</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Kelas</th>
                                <th style="padding:11px 14px;text-align:right;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Jumlah Nilai</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($stats['belum_list'] as $row)
                                <tr>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC"><b>{{ $row->mata_pelajaran ?? '-' }}</b></td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">{{ $row->kelas ?? '-' }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:right">{{ number_format($row->jumlah, 0, ',', '.') }}</td>
>>>>>>> origin/main
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
<<<<<<< HEAD
                <div class="note note-gold" style="margin:16px">
                    Nilai baru yang disimpan guru dipetakan otomatis. Untuk nilai lama, jalankan ulang populate atau perbaiki data master (mapel/kelas/semester) lalu simpan ulang nilai.
=======
                <div style="padding:12px 14px;font-size:12.5px;color:#667085">
                    Tambahkan baris pada <a href="{{ route('admin.referensi.pembelajaran') }}">Data Pembelajaran</a>
                    dengan kombinasi mapel + kelas di atas supaya nilai berikutnya otomatis terpetakan.
>>>>>>> origin/main
                </div>
            @else
                <div class="empty-state">
                    <div class="icon">✅</div>
<<<<<<< HEAD
                    <h4>Semua nilai terpetakan</h4>
                    <p>Tidak ada nilai e-Rapor yang belum punya pasangan pembelajaran.</p>
=======
                    <h4>Semua nilai sudah terpetakan</h4>
                    <p>Tidak ada nilai e-Rapor yang kehilangan tautan ke pembelajaran.</p>
>>>>>>> origin/main
                </div>
            @endif
        </div>
    </div>
<<<<<<< HEAD

    @if($stats['jadwal_ambigu'] > 0)
        <div class="note note-warn">
            <b>{{ number_format($stats['jadwal_ambigu']) }} baris jadwal ambigu</b> (kombinasi guru+mapel+kelas+semester yang sama di beberapa hari).
            Nilai dipetakan ke jadwal ber-ID terkecil; rapikan jadwal ganda bila perlu.
        </div>
    @endif
@endsection

=======
@endsection
>>>>>>> origin/main
