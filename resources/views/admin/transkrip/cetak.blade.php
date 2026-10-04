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
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>NISN</th>
                            <th>No. Ijazah</th>
                            <th>Nilai Terisi</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($siswa as $row)
                            <tr>
                                <td>{{ $row->nama_peserta_didik }}</td>
                                <td>{{ $row->nisn ?? '-' }}</td>
                                <td>{{ $row->nomor_ijazah ?? '-' }}</td>
                                <td>{{ $row->transkrip_nilai_count }} mapel</td>
                                <td>
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
