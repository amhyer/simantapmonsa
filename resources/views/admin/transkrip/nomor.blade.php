@extends('layouts.app')

@section('title', 'Nomor Ijazah - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Nomor Ijazah')
@section('page_subtitle', 'Kelola nomor ijazah per peserta didik')

@section('content')
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Import Nomor Ijazah (CSV)</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.transkrip.nomor.import') }}" method="POST" enctype="multipart/form-data" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
                @csrf
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">File CSV (header: nisn,nomor_ijazah)</label>
                    <input type="file" name="file" accept=".csv,.txt" required>
                </div>
                <div>
                    <button type="submit" class="btn btn-sm">Import</button>
                </div>
            </form>
            @error('file')
                <div style="color:#B42318;font-size:13px;margin-top:8px">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Daftar Siswa</h3>
            <form method="GET" style="display:flex;gap:8px">
                <input type="text" name="q" value="{{ $q }}" placeholder="Cari nama / NISN / NIS" style="padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
                <button type="submit" class="btn btn-sm">Cari</button>
            </form>
        </div>
        <div class="card-body tight">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>NISN / NIS</th>
                            <th>Nomor Ijazah</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($siswa as $row)
                            <tr>
                                <td>{{ $row->nama_peserta_didik }}</td>
                                <td>{{ $row->nisn ?? '-' }} / {{ $row->nis ?? '-' }}</td>
                                <td>
                                    <form action="{{ route('admin.transkrip.nomor.update', $row) }}" method="POST" style="display:flex;gap:8px">
                                        @csrf
                                        @method('PUT')
                                        <input type="text" name="nomor_ijazah" value="{{ old('nomor_ijazah', $row->nomor_ijazah) }}" maxlength="100" placeholder="DN-..." style="padding:6px 10px;border:1px solid #E4E7EC;border-radius:8px;min-width:180px">
                                        <button type="submit" class="btn btn-sm">Simpan</button>
                                    </form>
                                    @error('nomor_ijazah')
                                        <div style="color:#B42318;font-size:12px;margin-top:4px">{{ $message }}</div>
                                    @enderror
                                </td>
                                <td></td>
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
