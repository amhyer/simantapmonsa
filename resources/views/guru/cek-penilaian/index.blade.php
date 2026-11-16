@extends('layouts.app')

@section('title', 'Cek Penilaian & Deskripsi Capaian - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Cek Penilaian & Deskripsi Capaian')
@section('page_subtitle', 'Pratinjau nilai akhir, predikat, dan narasi capaian kompetensi rapor siswa')

@section('content')
    <div class="card" style="margin-bottom:16px">
        <div class="card-body">
            <form method="GET" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
                <div>
                    <label class="form-label">Kelas</label>
                    <select name="kelas" class="form-control" onchange="this.form.submit()">
                        @foreach($kelasList as $k)
                            <option value="{{ $k }}" @selected($k === $kelas)>{{ $k }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Mata Pelajaran</label>
                    <select name="mapel" class="form-control" onchange="this.form.submit()">
                        <option value="">Semua Mapel</option>
                        @foreach($mapelList as $m)
                            <option value="{{ $m }}" @selected($m === $mapelFilter)>{{ $m }}</option>
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
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Rekap Nilai & Narasi Deskripsi Capaian Rapor ({{ $kelas ?? '-' }})</h3>
            <span class="tag tag-mut">{{ $siswa->count() }} siswa</span>
        </div>
        <div class="card-body tight">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width:44px">No</th>
                            <th style="min-width:180px">Nama Siswa</th>
                            <th style="width:150px">Mata Pelajaran</th>
                            <th style="width:90px;text-align:center">Formatif</th>
                            <th style="width:90px;text-align:center">Sumatif</th>
                            <th style="width:90px;text-align:center">Nilai Akhir</th>
                            <th style="width:80px;text-align:center">Predikat</th>
                            <th>Deskripsi Capaian Kompetensi (Tertinggi & Terendah)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($siswa as $i => $s)
                            @php $n = $nilaiFiltered[$s->id] ?? null; @endphp
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>
                                    <strong>{{ $s->nama_peserta_didik }}</strong>
                                    <div style="font-size:11px;color:var(--muted)">NISN: {{ $s->nisn ?? $s->nis ?? '-' }}</div>
                                </td>
                                <td>{{ $n->mata_pelajaran ?? ($mapelFilter ?: '-') }}</td>
                                <td style="text-align:center">{{ $n ? number_format((float) $n->nilai_formatif, 0) : '-' }}</td>
                                <td style="text-align:center">{{ $n ? number_format((float) $n->nilai_sumatif, 0) : '-' }}</td>
                                <td style="text-align:center;font-weight:700">{{ $n && $n->nilai_akhir !== null ? number_format((float) $n->nilai_akhir, 1) : '-' }}</td>
                                <td style="text-align:center">
                                    @if($n && $n->predikat)
                                        <span class="tag {{ in_array($n->predikat, ['A', 'B']) ? 'tag-ok' : ($n->predikat === 'C' ? 'tag-gold' : 'tag-bad') }}">{{ $n->predikat }}</span>
                                    @else
                                        <span class="tag tag-mut">Belum</span>
                                    @endif
                                </td>
                                <td style="font-size:12.5px">
                                    @if($n)
                                        <div>{{ $n->deskripsi_capaian ?: $n->generateDeskripsi() }}</div>
                                    @else
                                        <span style="color:var(--muted)">Belum ada nilai rapor yang diinput untuk siswa ini.</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr class="table-empty">
                                <td colspan="8" style="padding:20px;text-align:center;color:var(--muted)">Belum ada data siswa untuk ditampilkan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
