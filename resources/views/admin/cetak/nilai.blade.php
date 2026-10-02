@extends('layouts.app')

@section('title', 'Nilai Rapor - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Nilai Rapor')
@section('page_subtitle', 'Cetak hasil belajar per peserta didik')

@section('content')
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Filter</h3>
        </div>
        <div class="card-body">
            <form method="GET" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Kelas</label>
                    <select name="kelas" style="padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
                        @foreach($kelasList as $k)
                            <option value="{{ $k }}" @selected($k === $kelas)>{{ $k }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Semester</label>
                    <select name="semester" style="padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
                        <option value="Ganjil" @selected($semester === 'Ganjil')>Ganjil</option>
                        <option value="Genap" @selected($semester === 'Genap')>Genap</option>
                    </select>
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Tahun Ajaran</label>
                    <input type="text" name="tahun_ajaran" value="{{ $tahunAjaran }}" maxlength="9" style="width:120px;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div>
                    <button type="submit" class="btn btn-sm">Tampilkan</button>
                </div>
            </form>
        </div>
    </div>

    @if($kelas)
        <div class="card">
            <div class="card-header">
                <h3>Kelas {{ $kelas }} — {{ $semester }} {{ $tahunAjaran }}</h3>
                <span class="tag tag-mut">{{ $siswa->count() }} siswa</span>
            </div>
            <div class="card-body tight">
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse;font-size:14px">
                        <thead>
                            <tr style="text-align:left;border-bottom:2px solid #EAECF0">
                                <th style="padding:10px">Nama</th>
                                <th style="padding:10px">NISN</th>
                                <th style="padding:10px;text-align:center">Nilai Terisi</th>
                                <th style="padding:10px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($siswa as $row)
                                <tr style="border-bottom:1px solid #EAECF0">
                                    <td style="padding:10px">{{ $row->nama_peserta_didik }}</td>
                                    <td style="padding:10px">{{ $row->nisn ?? '-' }}</td>
                                    <td style="padding:10px;text-align:center">{{ $row->nilai_erapot_count }} mapel</td>
                                    <td style="padding:10px">
                                        <a href="{{ route('admin.cetak.nilai.show', ['siswa' => $row->id, 'semester' => $semester, 'tahun_ajaran' => $tahunAjaran]) }}" target="_blank" class="btn btn-sm">Cetak</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" style="padding:16px;text-align:center;color:#667085">Belum ada data siswa di kelas ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
@endsection
