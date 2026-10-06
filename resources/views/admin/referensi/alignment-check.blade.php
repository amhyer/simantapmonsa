@extends('layouts.admin-with-tabs')

@section('title', 'Cek Alignment E-Rapor - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Cek Alignment E-Rapor')
@section('page_subtitle', 'Keterpetakan nilai_erapor ke jadwal_pelajaran (pembelajaran resmi)')

@section('header_actions')
    <a href="{{ route('admin.referensi.pembelajaran') }}" class="btn btn-sm btn-ghost">Data Pembelajaran</a>
@endsection

@section('content')
    <div class="grid grid-4" style="margin-bottom:16px">
        <div class="stat"><div class="label">Total nilai e-Rapor</div><div class="value">{{ number_format($stats['total']) }}</div></div>
        <div class="stat ok"><div class="label">Terpetakan</div><div class="value">{{ number_format($stats['mapped']) }}</div></div>
        <div class="stat {{ $stats['unmapped'] ? 'bad' : 'ok' }}"><div class="label">Belum terpetakan</div><div class="value">{{ number_format($stats['unmapped']) }}</div></div>
        <div class="stat gold"><div class="label">Persentase</div><div class="value">{{ $stats['persen'] }}%</div></div>
    </div>

    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Rincian Belum Terpetakan</h3>
        </div>
        <div class="card-body tight">
            @if($stats['unmapped'] > 0)
                <div class="table-wrapper">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
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
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="note note-gold" style="margin:16px">
                    Nilai baru yang disimpan guru dipetakan otomatis. Untuk nilai lama, jalankan ulang populate atau perbaiki data master (mapel/kelas/semester) lalu simpan ulang nilai.
                </div>
            @else
                <div class="empty-state">
                    <div class="icon">✅</div>
                    <h4>Semua nilai terpetakan</h4>
                    <p>Tidak ada nilai e-Rapor yang belum punya pasangan pembelajaran.</p>
                </div>
            @endif
        </div>
    </div>

    @if($stats['jadwal_ambigu'] > 0)
        <div class="note note-warn">
            <b>{{ number_format($stats['jadwal_ambigu']) }} baris jadwal ambigu</b> (kombinasi guru+mapel+kelas+semester yang sama di beberapa hari).
            Nilai dipetakan ke jadwal ber-ID terkecil; rapikan jadwal ganda bila perlu.
        </div>
    @endif
@endsection

