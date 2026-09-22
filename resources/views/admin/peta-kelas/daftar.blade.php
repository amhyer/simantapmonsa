@extends('layouts.app')

@section('title', 'Data Kelas - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Data Kelas')
@section('page_subtitle', 'Daftar rombongan belajar dari Dapodik')

@section('header_actions')
    <a href="{{ route('admin.peta-kelas.index') }}" class="btn btn-sm btn-ghost">Papan Pemetaan</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Daftar Rombel</h3>
            <span class="tag tag-mut">{{ $rombel->count() }} rombel</span>
        </div>
        <div class="card-body tight">
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin:0 16px 12px">
                <a href="{{ route('admin.peta-kelas.daftar') }}" class="btn btn-sm {{ !$jenis ? '' : 'btn-ghost' }}">Semua</a>
                @foreach($jenisList as $j)
                    <a href="{{ route('admin.peta-kelas.daftar', ['jenis' => $j]) }}" class="btn btn-sm {{ $jenis === $j ? '' : 'btn-ghost' }}">{{ $j }}</a>
                @endforeach
            </div>
            @if($rombel->count())
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085;width:50px">No</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Kurikulum</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Nama Kelas</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Jenis Rombel</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Tingkat</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Wali Kelas</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Jml Siswa</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Cek Anggota</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rombel as $i => $r)
                                <tr>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">{{ $i + 1 }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">{{ $r->kurikulum ?? '-' }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC"><b>{{ $r->nama_rombel }}</b></td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">{{ $r->jenis_rombel ?? '-' }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">{{ $r->tingkat ?? '-' }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">{{ $r->guru?->nama_lengkap ?? '-' }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">{{ $r->siswa_count }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">
                                        <a href="{{ route('admin.peta-kelas.detail', $r) }}" class="btn btn-sm btn-ghost">Anggota</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">
                    <div class="icon">🏫</div>
                    <h4>Belum ada rombel{{ $jenis ? ' jenis ' . $jenis : '' }}</h4>
                    <p>Import dari Dapodik untuk mengisi data kelas.</p>
                </div>
            @endif
        </div>
    </div>
@endsection
