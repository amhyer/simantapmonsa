@extends('layouts.app')

@section('title', 'Kuis & Soal - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Kuis & Soal')
@section('page_subtitle', 'Kelola asesmen pembelajaran')

@section('header_actions')
    <a href="{{ route('guru.kuis.create') }}" class="btn btn-sm">+ Buat kuis</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Daftar Kuis</h3>
            <span class="tag tag-mut">{{ $kuis->count() }} total</span>
        </div>
        <div class="card-body tight">
            @if($kuis->count())
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Judul</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Jenis</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Soal</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Status</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Dikerjakan</th>
                                <th style="padding:11px 14px;text-align:right;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($kuis as $k)
                                <tr>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                        <b>{{ $k->judul }}</b>
                                        <div style="font-size:12px;color:#667085">{{ \Carbon\Carbon::parse($k->tanggal)->translatedFormat('d M Y') }}</div>
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC"><span class="tag tag-gold">{{ $k->jenis }}</span></td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">{{ $k->jumlah_soal }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">
                                        @if($k->aktif)
                                            <span class="tag tag-ok">Aktif</span>
                                        @else
                                            <span class="tag tag-mut">Tutup</span>
                                        @endif
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">{{ $k->hasilKuis->count() }}/{{ $siswaCount }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:right;white-space:nowrap">
                                        <a href="{{ route('guru.kuis.hasil', $k) }}" class="btn btn-ghost btn-sm">Hasil</a>
                                        <a href="{{ route('guru.kuis.edit', $k) }}" class="btn btn-ghost btn-sm">Ubah</a>
                                        <form action="{{ route('guru.kuis.toggle', $k) }}" method="POST" style="display:inline">
                                            @csrf
                                            <button type="submit" class="btn btn-ghost btn-sm">{{ $k->aktif ? 'Tutup' : 'Buka' }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty">
                    <div class="icon">📝</div>
                    <b>Belum ada kuis</b>
                    <div>Buat kuis untuk mengasesh siswa Anda.</div>
                </div>
            @endif
        </div>
    </div>
@endsection
