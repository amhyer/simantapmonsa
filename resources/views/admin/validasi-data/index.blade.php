@extends('layouts.admin-with-tabs')

@section('title', 'Validasi & Kelengkapan Data - SIMANTAP')
@section('page_title', 'Validasi & Kelengkapan Data Sekolah')
@section('page_subtitle', 'Pemeriksaan menyeluruh kesiapan data rombel, wali kelas, jadwal, akun, dan profil sekolah')

@section('content')
    <div class="card" style="margin-bottom:16px">
        <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
            <div>
                <div style="font-size:13px;color:#667085">Status Kesiapan Operasional Sekolah</div>
                <div style="font-size:20px;font-weight:700;color:{{ $ringkasan['total_temuan'] === 0 ? '#067647' : '#B54708' }}">
                    {{ $ringkasan['total_temuan'] === 0 ? 'Data Lengkap & Siap Operasional' : $ringkasan['total_temuan'].' Temuan Perlu Ditindaklanjuti' }}
                </div>
            </div>
            <div style="font-size:13px;color:#475467">
                Total Siswa Aktif: <strong>{{ $ringkasan['total_siswa_aktif'] }}</strong>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:18px">
        <div class="card-header"><strong>Daftar Pemeriksaan Kelengkapan Data</strong></div>
        <div class="card-body" style="padding:0">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Indikator Pemeriksaan</th>
                            <th>Status</th>
                            <th>Temuan</th>
                            <th>Keterangan</th>
                            <th style="text-align:right">Tautan Perbaikan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ringkasan['items'] as $item)
                            <tr>
                                <td><strong>{{ $item['judul'] }}</strong></td>
                                <td>
                                    @if($item['status'] === 'ok')
                                        <span class="badge badge-ok">Lengkap</span>
                                    @elseif($item['status'] === 'warn')
                                        <span class="badge badge-warn">Perlu Perbaikan</span>
                                    @else
                                        <span class="badge badge-info">Perhatian</span>
                                    @endif
                                </td>
                                <td><strong>{{ $item['jumlah'] }}</strong></td>
                                <td style="font-size:13px;color:#475467">{{ $item['keterangan'] }}</td>
                                <td style="text-align:right;white-space:nowrap">
                                    @if(\Illuminate\Support\Facades\Route::has($item['route']))
                                        <a href="{{ route($item['route']) }}" class="btn btn-ghost btn-sm">
                                            {{ $item['aksi_label'] }} →
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if(!empty($ringkasan['siswa_tanpa_rombel']))
        <div class="card" style="margin-bottom:18px">
            <div class="card-header"><strong>Daftar Siswa Aktif Belum Masuk Rombel (maks. 25 teratas)</strong></div>
            <div class="card-body" style="padding:0">
                <div class="table-wrapper">
                    <table class="table">
                        <thead><tr><th>NIS / NISN</th><th>Nama Siswa</th><th>Label Kelas</th></tr></thead>
                        <tbody>
                            @foreach($ringkasan['siswa_tanpa_rombel'] as $s)
                                <tr><td>{{ $s['nis'] }}</td><td>{{ $s['nama'] }}</td><td>{{ $s['kelas'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if(!empty($ringkasan['rombel_tanpa_wali']))
        <div class="card">
            <div class="card-header"><strong>Daftar Rombel Belum Memiliki Wali Kelas</strong></div>
            <div class="card-body" style="padding:0">
                <div class="table-wrapper">
                    <table class="table">
                        <thead><tr><th>Nama Rombel</th><th>Tingkat</th></tr></thead>
                        <tbody>
                            @foreach($ringkasan['rombel_tanpa_wali'] as $r)
                                <tr><td><strong>{{ $r['nama_rombel'] }}</strong></td><td>{{ $r['tingkat'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
@endsection
