@extends('layouts.app')

@section('title', 'Input Kenaikan Kelas (Wali Kelas) - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Input Kenaikan Kelas (Wali Kelas)')
@section('page_subtitle', 'Tentukan keputusan naik kelas, tinggal di kelas, atau kelulusan peserta didik')

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
                        <option value="Genap" @selected($semester === 'Genap')>Genap</option>
                        <option value="Ganjil" @selected($semester === 'Ganjil')>Ganjil</option>
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
            <h3>Daftar Keputusan Kenaikan Kelas — {{ $kelas ?? '-' }}</h3>
            <span class="tag tag-mut">{{ $siswa->count() }} siswa</span>
        </div>
        <div class="card-body tight">
            <form method="POST" action="{{ route('guru.walas.naik-kelas.simpan') }}">
                @csrf
                <input type="hidden" name="kelas" value="{{ $kelas }}">
                <input type="hidden" name="semester" value="{{ $semester }}">
                <input type="hidden" name="tahun_ajaran" value="{{ $tahunAjaran }}">

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:44px">No</th>
                                <th>Nama Peserta Didik</th>
                                <th>NISN</th>
                                <th style="text-align:center">Keputusan</th>
                                <th>Keterangan / Catatan Rapor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($siswa as $i => $s)
                                @php
                                    $rec = $keputusan[$s->id] ?? null;
                                    $naik = $rec ? (bool) $rec->naik_kelas : true;
                                @endphp
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td><strong>{{ $s->nama_peserta_didik }}</strong></td>
                                    <td>{{ $s->nisn ?? $s->nis ?? '-' }}</td>
                                    <td style="text-align:center;white-space:nowrap">
                                        <label style="margin-right:12px;cursor:pointer;font-weight:600;color:#059669">
                                            <input type="radio" name="keputusan[{{ $s->id }}][naik_kelas]" value="1" @checked($naik)> Naik Kelas / Lulus
                                        </label>
                                        <label style="cursor:pointer;font-weight:600;color:#DC2626">
                                            <input type="radio" name="keputusan[{{ $s->id }}][naik_kelas]" value="0" @checked(!$naik)> Tinggal Kelas
                                        </label>
                                    </td>
                                    <td>
                                        <input type="text" name="keputusan[{{ $s->id }}][catatan]" value="{{ $rec->catatan ?? '' }}"
                                               class="form-control" maxlength="255" placeholder="Misal: Naik ke kelas berikutnya">
                                    </td>
                                </tr>
                            @empty
                                <tr class="table-empty"><td colspan="5" style="padding:18px;text-align:center;color:var(--muted)">Belum ada siswa aktif di kelas ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($siswa->isNotEmpty())
                    <div style="padding:16px;border-top:1px solid #E4E7EC;display:flex;justify-content:flex-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan Keputusan Kenaikan Kelas
                        </button>
                    </div>
                @endif
            </form>
        </div>
    </div>
@endsection
