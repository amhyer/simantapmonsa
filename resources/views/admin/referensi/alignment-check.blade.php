@extends('layouts.app')

@section('title', 'Cek Alignment e-Rapor - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Cek Alignment e-Rapor')
@section('page_subtitle', 'Seberapa banyak nilai e-Rapor sudah terhubung ke baris pembelajaran (jadwal_pelajaran)')

@section('header_actions')
    <a href="{{ route('admin.referensi.pembelajaran') }}" class="btn btn-sm">Data Pembelajaran</a>
    <a href="{{ route('admin.dapodik.index') }}" class="btn btn-sm btn-ghost">Import Dapodik</a>
@endsection

@section('content')
    <div class="grid grid-4" style="margin-bottom:16px">
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
    </div>

    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Yang perlu dilengkapi</h3>
            <span class="tag tag-mut">Mapel × Kelas</span>
        </div>
        <div class="card-body tight">
            @if($stats['belum_list']->count())
                <div class="table-wrapper">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
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
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div style="padding:12px 14px;font-size:12.5px;color:#667085">
                    Tambahkan baris pada <a href="{{ route('admin.referensi.pembelajaran') }}">Data Pembelajaran</a>
                    dengan kombinasi mapel + kelas di atas supaya nilai berikutnya otomatis terpetakan.
                </div>
            @else
                <div class="empty-state">
                    <div class="icon">✅</div>
                    <h4>Semua nilai sudah terpetakan</h4>
                    <p>Tidak ada nilai e-Rapor yang kehilangan tautan ke pembelajaran.</p>
                </div>
            @endif
        </div>
    </div>
@endsection
