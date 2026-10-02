@extends('layouts.app')

@section('title', 'Cetak Transkrip Nilai - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Cetak Transkrip Nilai')
@section('page_subtitle', 'Pilih siswa untuk mencetak transkrip')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Daftar Siswa</h3>
            <form method="GET" style="display:flex;gap:8px">
                <input type="text" name="q" value="{{ $q }}" placeholder="Cari nama / NISN" style="padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
                <button type="submit" class="btn btn-sm">Cari</button>
            </form>
        </div>
        <div class="card-body tight">
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:14px">
                    <thead>
                        <tr style="text-align:left;border-bottom:2px solid #EAECF0">
                            <th style="padding:10px">Nama</th>
                            <th style="padding:10px">NISN</th>
                            <th style="padding:10px">No. Ijazah</th>
                            <th style="padding:10px">Nilai Terisi</th>
                            <th style="padding:10px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($siswa as $row)
                            <tr style="border-bottom:1px solid #EAECF0">
                                <td style="padding:10px">{{ $row->nama_peserta_didik }}</td>
                                <td style="padding:10px">{{ $row->nisn ?? '-' }}</td>
                                <td style="padding:10px">{{ $row->nomor_ijazah ?? '-' }}</td>
                                <td style="padding:10px">{{ $row->transkrip_nilai_count }} mapel</td>
                                <td style="padding:10px">
                                    <a href="{{ route('admin.transkrip.cetak.show', $row) }}" target="_blank" class="btn btn-sm">Cetak</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="padding:16px;text-align:center;color:#667085">Belum ada data siswa.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="margin-top:12px">{{ $siswa->links() }}</div>
        </div>
    </div>
@endsection
