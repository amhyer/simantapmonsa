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
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>NISN</th>
                            <th>Progres</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($siswa as $row)
                            <tr>
                                <td>{{ $row->nama_peserta_didik }}</td>
                                <td>{{ $row->nisn ?? '-' }}</td>
                                <td>
                                    <span class="tag {{ $jumlahMapel > 0 && $row->transkrip_nilai_count >= $jumlahMapel ? 'tag-ok' : 'tag-mut' }}">
                                        {{ $row->transkrip_nilai_count }}/{{ $jumlahMapel }} mapel
                                    </span>
                                </td>
                                <td>
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
