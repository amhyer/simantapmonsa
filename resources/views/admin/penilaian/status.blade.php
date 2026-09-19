@extends('layouts.app')

@section('title', 'Status Penilaian - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Status Penilaian')
@section('page_subtitle', 'Pantau kelengkapan input setiap guru (siswa, materi, kuis, nilai, e-rapor)')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Status per Guru</h3>
            <span class="tag tag-mut">{{ $rows->count() }} guru</span>
        </div>
        <div class="card-body tight">
            @if($rows->count())
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Guru</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Siswa</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Materi</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Kuis</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Nilai</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">e-Rapor</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Aktivitas Terakhir</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $r)
                                <tr>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC"><b>{{ $r['guru']->nama_lengkap }}</b></td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">{{ $r['siswa'] }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">{{ $r['materi'] }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">{{ $r['kuis'] }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">{{ $r['nilai'] }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">{{ $r['erapor'] }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">{{ $r['terakhir'] ? $r['terakhir']->diffForHumans() : '-' }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">
                                        @if($r['lengkap'])
                                            <span class="tag tag-ok">Lengkap</span>
                                        @elseif($r['mulai'])
                                            <span class="tag tag-primary">Sebagian</span>
                                        @else
                                            <span class="tag tag-mut">Belum mulai</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">
                    <div class="icon">✅</div>
                    <h4>Belum ada guru aktif</h4>
                    <p>Tambah akun guru melalui Kelola Pengguna.</p>
                </div>
            @endif
        </div>
    </div>
@endsection
