@extends('layouts.app')

@section('title', 'Input Nilai Ekstrakurikuler - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Input Nilai Ekstrakurikuler (Wali Kelas)')
@section('page_subtitle', 'Penilaian predikat dan keterangan kegiatan ekstrakurikuler siswa kelas binaan')

@section('content')
    <div class="card" style="margin-bottom:16px">
        <div class="card-body">
            <form method="GET" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
                <div>
                    <label for="fld-walas-ekskul-kelas" class="form-label">Kelas Binaan</label>
                    <select id="fld-walas-ekskul-kelas" name="kelas" class="form-control" onchange="this.form.submit()">
                        @foreach($kelasList as $k)
                            <option value="{{ $k }}" @selected($k === $kelas)>{{ $k }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Tampilkan</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Daftar Penilaian Ekstrakurikuler — Kelas {{ $kelas ?? '-' }}</h3>
            <span class="tag tag-mut">{{ $siswa->count() }} siswa</span>
        </div>
        <div class="card-body tight">
            <form method="POST" action="{{ route('guru.walas.ekskul.simpan') }}">
                @csrf
                <input type="hidden" name="kelas" value="{{ $kelas }}">

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:44px">No</th>
                                <th style="min-width:190px">Nama Peserta Didik</th>
                                <th style="width:210px">Kegiatan Ekstrakurikuler</th>
                                <th style="width:160px">Predikat</th>
                                <th>Deskripsi / Keterangan Capaian</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($siswa as $i => $s)
                                @php
                                    $eks = ($s->rekaman ?? [])['ekskul'] ?? [];
                                    $namaEks = $eks['nama'] ?? '';
                                    $predEks = $eks['predikat'] ?? 'Baik';
                                    $ketEks = $eks['keterangan'] ?? '';
                                @endphp
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>
                                        <strong>{{ $s->nama_peserta_didik }}</strong>
                                        <div style="font-size:11px;color:var(--muted)">NISN: {{ $s->nisn ?? $s->nis ?? '-' }}</div>
                                    </td>
                                    <td>
                                        <select name="ekskul[{{ $s->id }}][nama]" class="form-control">
                                            <option value="">-- Tidak Mengikuti --</option>
                                            @foreach($ekskulList as $nama)
                                                <option value="{{ $nama }}" @selected($namaEks === $nama)>{{ $nama }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="ekskul[{{ $s->id }}][predikat]" class="form-control">
                                            @foreach(['Sangat Baik', 'Baik', 'Cukup', 'Kurang'] as $p)
                                                <option value="{{ $p }}" @selected($predEks === $p)>{{ $p }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" name="ekskul[{{ $s->id }}][keterangan]" value="{{ $ketEks }}"
                                               class="form-control" maxlength="300" placeholder="Misal: Aktif mengikuti kegiatan kepramukaan dengan disiplin">
                                    </td>
                                </tr>
                            @empty
                                <tr class="table-empty">
                                    <td colspan="5" style="padding:20px;text-align:center;color:var(--muted)">Belum ada data siswa di kelas ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($siswa->isNotEmpty())
                    <div style="padding:16px;border-top:1px solid #E4E7EC;display:flex;justify-content:flex-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan Nilai Ekstrakurikuler
                        </button>
                    </div>
                @endif
            </form>
        </div>
    </div>
@endsection
