@extends('layouts.app')

@section('title', 'Input Nilai Transkrip - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Input Nilai Transkrip')
@section('page_subtitle', 'Nilai akhir per mapel untuk ijazah')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Daftar Siswa</h3>
            <span class="tag tag-mut">{{ $jumlahMapel }} mapel masuk transkrip</span>
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
                            <th style="padding:10px">Progres</th>
                            <th style="padding:10px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($siswa as $row)
                            <tr style="border-bottom:1px solid #EAECF0">
                                <td style="padding:10px">{{ $row->nama_peserta_didik }}</td>
                                <td style="padding:10px">{{ $row->nisn ?? '-' }}</td>
                                <td style="padding:10px">
                                    <span class="tag {{ $jumlahMapel > 0 && $row->transkrip_nilai_count >= $jumlahMapel ? 'tag-ok' : 'tag-mut' }}">
                                        {{ $row->transkrip_nilai_count }}/{{ $jumlahMapel }} mapel
                                    </span>
                                </td>
                                <td style="padding:10px">
                                    <a href="{{ route('admin.transkrip.input.form', $row) }}" class="btn btn-sm">Input Nilai</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="padding:16px;text-align:center;color:#667085">Belum ada data siswa.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="margin-top:12px">{{ $siswa->links() }}</div>
        </div>
    </div>
@endsection
