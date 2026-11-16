@extends('layouts.app')

@section('title', 'Input Projek P5 - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Input Capaian & Catatan Proses P5')
@section('page_subtitle', 'Penilaian Projek Penguatan Profil Pelajar Pancasila (Kokurikuler)')

@section('content')
    <div class="card" style="margin-bottom:16px">
        <div class="card-body">
            <form method="GET" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;align-items:end">
                <div>
                    <label class="form-label">Kelas</label>
                    <select name="kelas" class="form-control">
                        @foreach($kelasList as $k)
                            <option value="{{ $k }}" @selected($k === $kelas)>{{ $k }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Tema Kokurikuler P5</label>
                    <select name="tema" class="form-control">
                        @foreach($temaList as $t)
                            <option value="{{ $t }}" @selected($t === $tema)>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Dimensi Profil Pelajar</label>
                    <select name="dimensi" class="form-control">
                        @foreach($dimensiList as $d)
                            <option value="{{ $d }}" @selected($d === $dimensi)>{{ $d }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Semester</label>
                    <select name="semester" class="form-control">
                        <option value="Ganjil" @selected($semester === 'Ganjil')>Ganjil</option>
                        <option value="Genap" @selected($semester === 'Genap')>Genap</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Tahun Ajaran</label>
                    <input type="text" name="tahun_ajaran" value="{{ $tahunAjaran }}" maxlength="9" class="form-control">
                </div>
                <div>
                    <button type="submit" class="btn btn-primary btn-sm">Tampilkan</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h3 style="margin:0">{{ $tema }}</h3>
                <small style="color:var(--muted)">Dimensi: {{ $dimensi }} &bull; Kelas {{ $kelas ?? '-' }} ({{ $semester }} {{ $tahunAjaran }})</small>
            </div>
            <span class="tag tag-mut">{{ $siswa->count() }} siswa</span>
        </div>
        <div class="card-body tight">
            <form method="POST" action="{{ route('guru.p5.simpan') }}">
                @csrf
                <input type="hidden" name="kelas" value="{{ $kelas }}">
                <input type="hidden" name="semester" value="{{ $semester }}">
                <input type="hidden" name="tahun_ajaran" value="{{ $tahunAjaran }}">
                <input type="hidden" name="tema" value="{{ $tema }}">
                <input type="hidden" name="dimensi" value="{{ $dimensi }}">

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:44px">No</th>
                                <th style="min-width:190px">Nama Peserta Didik</th>
                                <th style="width:220px">Capaian Indikator P5</th>
                                <th>Catatan Proses Perkembangan</th>
                                <th style="width:110px;text-align:center">Cetak P5</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($siswa as $i => $s)
                                @php
                                    $rec = $penilaian[$s->id] ?? null;
                                    $pred = $rec->predikat ?? 'BSH';
                                @endphp
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>
                                        <strong>{{ $s->nama_peserta_didik }}</strong>
                                        <div style="font-size:11px;color:var(--muted)">NISN: {{ $s->nisn ?? $s->nis ?? '-' }}</div>
                                    </td>
                                    <td>
                                        <select name="nilai[{{ $s->id }}][predikat]" class="form-control">
                                            <option value="BB" @selected($pred === 'BB')>BB (Belum Berkembang)</option>
                                            <option value="MB" @selected($pred === 'MB')>MB (Mulai Berkembang)</option>
                                            <option value="BSH" @selected($pred === 'BSH')>BSH (Berkembang Sesuai Harapan)</option>
                                            <option value="SB" @selected($pred === 'SB')>SB (Sangat Berkembang)</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" name="nilai[{{ $s->id }}][catatan]" value="{{ $rec->catatan ?? '' }}"
                                               class="form-control" maxlength="500" placeholder="Catatan proses perkembangan karakter siswa...">
                                    </td>
                                    <td style="text-align:center">
                                        <a href="{{ route('guru.walas.rapor-p5', ['siswa' => $s->id, 'semester' => $semester, 'tahun_ajaran' => $tahunAjaran]) }}"
                                           target="_blank" class="btn btn-sm btn-ghost" title="Cetak Rapor P5 Siswa">
                                            <i class="fas fa-print"></i> PDF
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr class="table-empty">
                                    <td colspan="5" style="padding:20px;text-align:center;color:var(--muted)">Belum ada data siswa pada kelas ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($siswa->isNotEmpty())
                    <div style="padding:16px;border-top:1px solid #E4E7EC;display:flex;justify-content:flex-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan Capaian & Catatan P5
                        </button>
                    </div>
                @endif
            </form>
        </div>
    </div>
@endsection
