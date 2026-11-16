@extends('layouts.app')

@section('title', 'Leger & Cetak Rapor Kelas - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Leger & Cetak Rapor Kelas (Wali Kelas)')
@section('page_subtitle', 'Rekapitulasi leger nilai kelas, cetak rapor akademik, dan cetak rapor projek P5')

@section('header_actions')
    <a href="{{ route('guru.erapor.excel', ['kelas' => $kelas]) }}" class="btn btn-ghost btn-sm">
        <i class="fas fa-file-excel"></i> Download Leger Excel
    </a>
    <a href="{{ route('guru.erapor.index', ['kelas' => $kelas]) }}" class="btn btn-primary btn-sm">
        <i class="fas fa-print"></i> Cetak Rapor Akademik
    </a>
@endsection

@section('content')
    <div class="card" style="margin-bottom:16px">
        <div class="card-body">
            <form method="GET" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
                <div>
                    <label class="form-label">Kelas Binaan</label>
                    <select name="kelas" class="form-control" onchange="this.form.submit()">
                        @foreach($kelasList as $k)
                            <option value="{{ $k }}" @selected($k === $kelas)>{{ $k }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Semester</label>
                    <select name="semester" class="form-control" onchange="this.form.submit()">
                        <option value="Ganjil" @selected($semester === 'Ganjil')>Ganjil</option>
                        <option value="Genap" @selected($semester === 'Genap')>Genap</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Tahun Ajaran</label>
                    <input type="text" name="tahun_ajaran" value="{{ $tahunAjaran }}" maxlength="9" class="form-control" style="width:120px">
                </div>
                <button type="submit" class="btn btn-sm btn-primary">Tampilkan</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Leger Nilai Rapor — Kelas {{ $kelas ?? '-' }} ({{ $semester }} {{ $tahunAjaran }})</h3>
            <span class="tag tag-mut">{{ $siswa->count() }} siswa</span>
        </div>
        <div class="card-body tight">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width:44px">No</th>
                            <th>Nama Siswa</th>
                            <th>NISN</th>
                            @foreach($mapel as $m)
                                <th style="text-align:center">{{ $m }}</th>
                            @endforeach
                            <th style="text-align:center">Rata-rata</th>
                            <th style="text-align:center;width:180px">Aksi Cetak</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($siswa as $i => $s)
                            @php
                                $nilaiSiswa = collect($matriks[$s->id] ?? []);
                                $rataSiswa = $nilaiSiswa->count() ? round($nilaiSiswa->avg(), 1) : null;
                            @endphp
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td><strong>{{ $s->nama_peserta_didik }}</strong></td>
                                <td>{{ $s->nisn ?? $s->nis ?? '-' }}</td>
                                @foreach($mapel as $m)
                                    <td style="text-align:center">{{ isset($matriks[$s->id][$m]) ? number_format((float) $matriks[$s->id][$m], 0) : '-' }}</td>
                                @endforeach
                                <td style="text-align:center;font-weight:800;color:var(--navy)">{{ $rataSiswa !== null ? $rataSiswa : '-' }}</td>
                                <td style="text-align:center;white-space:nowrap">
                                    <a href="{{ route('guru.erapor.pdf', $s->id) }}" class="btn btn-xs btn-primary" title="Unduh PDF Rapor Intrakurikuler">
                                        <i class="fas fa-file-pdf"></i> Rapor
                                    </a>
                                    <a href="{{ route('guru.walas.rapor-p5', ['siswa' => $s->id, 'semester' => $semester, 'tahun_ajaran' => $tahunAjaran]) }}"
                                       target="_blank" class="btn btn-xs btn-ghost" title="Cetak Rapor P5">
                                        <i class="fas fa-seedling"></i> P5
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr class="table-empty">
                                <td colspan="{{ 5 + $mapel->count() }}" style="padding:20px;text-align:center;color:var(--muted)">Belum ada data leger nilai pada kelas ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
