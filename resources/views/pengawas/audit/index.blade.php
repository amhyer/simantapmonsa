@extends('layouts.app')

@section('title', 'Audit Keselarasan Kurikulum & e-Rapor — Pengawas Sekolah')
@section('page_title', 'Audit Keselarasan Kurikulum, KKTP & e-Rapor')
@section('page_subtitle', 'Deteksi otomatis keselarasan Bank TP Pembelajaran Mendalam, KKTP, KKM & Peta Hasil Belajar Kelas 1–6')

@section('sidebar')
    @include('pengawas.partials.sidebar')
@endsection

@section('content')
    {{-- Ringkasan KPI Audit --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:16px">
        <div class="card">
            <div class="card-body" style="padding:14px">
                <div style="font-size:12px;color:#667085;font-weight:600">Total Guru Diaudit</div>
                <div style="font-size:22px;font-weight:700;color:#101828;margin-top:4px">{{ $ringkasan['total_guru'] }}</div>
            </div>
        </div>
        <div class="card">
            <div class="card-body" style="padding:14px">
                <div style="font-size:12px;color:#667085;font-weight:600">Guru Sinkron TP PM &rarr; e-Rapor</div>
                <div style="font-size:22px;font-weight:700;color:#027A48;margin-top:4px">{{ $ringkasan['guru_sinkron_tp'] }} / {{ $ringkasan['total_guru'] }}</div>
            </div>
        </div>
        <div class="card">
            <div class="card-body" style="padding:14px">
                <div style="font-size:12px;color:#667085;font-weight:600">Guru Selaras Nilai KKTP &harr; KKM</div>
                <div style="font-size:22px;font-weight:700;color:#175CD3;margin-top:4px">{{ $ringkasan['guru_selaras_kktp'] }} / {{ $ringkasan['total_guru'] }}</div>
            </div>
        </div>
        <div class="card">
            <div class="card-body" style="padding:14px">
                <div style="font-size:12px;color:#667085;font-weight:600">Guru Aktif Menginput Nilai</div>
                <div style="font-size:22px;font-weight:700;color:#6941C6;margin-top:4px">{{ $ringkasan['guru_aktif_menilai'] }} / {{ $ringkasan['total_guru'] }}</div>
            </div>
        </div>
    </div>

    {{-- Peta Ketuntasan KKTP & Rata-rata Hasil Belajar Kelas 1 s.d. 6 --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <strong>Peta Ketuntasan KKTP &amp; Rata-rata Hasil Belajar per Tingkat Kelas (Kelas 1 s.d. Kelas 6)</strong>
        </div>
        <div class="card-body" style="padding:0">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tingkat Kelas</th>
                            <th>Fase Kurikulum</th>
                            <th style="text-align:center">Jumlah Siswa Aktif</th>
                            <th style="text-align:center">Batas KKTP Rata-rata</th>
                            <th style="text-align:center">Rata-rata Nilai Siswa</th>
                            <th>Status Ketuntasan Kelas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($petaKelas as $pk)
                            <tr>
                                <td style="font-weight:700">Kelas {{ $pk['tingkat'] }}</td>
                                <td>Fase {{ $pk['fase'] }}</td>
                                <td style="text-align:center">{{ $pk['jumlah_siswa'] }} Siswa</td>
                                <td style="text-align:center;font-weight:600">{{ $pk['kktp_rata'] }}</td>
                                <td style="text-align:center;font-weight:700;color:{{ $pk['rata_nilai'] >= $pk['kktp_rata'] ? '#027A48' : ($pk['rata_nilai'] > 0 ? 'var(--bad)' : '#667085') }}">
                                    {{ $pk['rata_nilai'] > 0 ? $pk['rata_nilai'] : '—' }}
                                </td>
                                <td>
                                    <span class="badge {{ $pk['rata_nilai'] >= $pk['kktp_rata'] ? 'badge-success' : ($pk['rata_nilai'] > 0 ? 'badge-danger' : 'badge-warning') }}">
                                        {{ $pk['status_ketuntasan'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Tabel Audit Keselarasan per Guru --}}
    <div class="card">
        <div class="card-header">
            <strong>Rincian Audit Keselarasan Kurikulum (TP &amp; KKTP Pembelajaran Mendalam &harr; e-Rapor) per Guru</strong>
        </div>
        <div class="card-body" style="padding:0">
            @if($guruAudit->isEmpty())
                <div style="padding:32px;text-align:center;color:#667085">Belum ada data guru aktif pada sekolah binaan ini.</div>
            @else
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width:42px">No</th>
                                <th>Nama Guru</th>
                                <th style="text-align:center">Bank TP PM</th>
                                <th style="text-align:center">TP di e-Rapor</th>
                                <th style="text-align:center">KKTP &harr; KKM</th>
                                <th style="text-align:center">Entri Nilai</th>
                                <th>Temuan Audit / Rekomendasi Otomatis</th>
                                <th style="text-align:right">Inspeksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($guruAudit as $i => $row)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>
                                        <div style="font-weight:600">{{ $row['guru']->nama_lengkap }}</div>
                                        @if($row['guru']->kelas_mata_pelajaran)
                                            <div style="font-size:11px;color:#667085">{{ $row['guru']->kelas_mata_pelajaran }}</div>
                                        @endif
                                    </td>
                                    <td style="text-align:center;font-weight:700">{{ $row['tp_pm'] }} TP</td>
                                    <td style="text-align:center;font-weight:700;color:{{ $row['sinkron_tp'] ? '#027A48' : 'var(--bad)' }}">
                                        {{ $row['tp_erapor'] }} TP
                                    </td>
                                    <td style="text-align:center">
                                        <span class="badge {{ $row['selaras_kktp'] ? 'badge-success' : 'badge-warning' }}">
                                            {{ $row['nilai_kktp'] }} / {{ $row['kkm_erapor'] }}
                                        </span>
                                    </td>
                                    <td style="text-align:center;font-weight:600">{{ $row['total_entri_nilai'] }}</td>
                                    <td style="font-size:12px">
                                        @if(empty($row['temuan']))
                                            <span style="color:#027A48;font-weight:600">✓ Kurikulum, KKTP &amp; e-Rapor Selaras</span>
                                        @else
                                            <ul style="margin:0;padding-left:16px;color:var(--bad)">
                                                @foreach($row['temuan'] as $t)
                                                    <li>{{ $t }}</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </td>
                                    <td style="text-align:right">
                                        <a href="{{ route('pengawas.supervisi.show', ['guru' => $row['guru']->uuid, 'tahun_ajaran' => $tahunAjaran, 'semester' => $semester]) }}"
                                           class="btn btn-sm btn-ghost">
                                            Periksa
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
