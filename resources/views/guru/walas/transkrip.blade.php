@extends('layouts.app')

@section('title', 'Transkrip Ijazah (Wali Kelas) - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Transkrip Nilai Ijazah (Wali Kelas)')
@section('page_subtitle', 'Input nomor ijazah, nilai transkrip kelulusan, dan cetak surat transkrip nilai siswa tingkat akhir')

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
                <button type="submit" class="btn btn-sm btn-primary">Tampilkan</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Daftar Transkrip Ijazah Siswa — Kelas {{ $kelas ?? '-' }}</h3>
            <span class="tag tag-mut">{{ $siswa->count() }} siswa</span>
        </div>
        <div class="card-body tight">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width:44px">No</th>
                            <th style="min-width:180px">Nama Siswa & NISN</th>
                            <th style="width:170px">Nomor Ijazah</th>
                            @foreach($mapel as $m)
                                <th style="width:95px;text-align:center" title="{{ $m->nama }}">{{ $m->kode ?: \Illuminate\Support\Str::limit($m->nama, 10) }}</th>
                            @endforeach
                            <th style="width:150px;text-align:center">Simpan / Cetak</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($siswa as $i => $s)
                            @php
                                $nilaiSiswa = ($transkripMap[$s->id] ?? collect())->keyBy('mata_pelajaran_id');
                                $formId = 'form-transkrip-' . $s->id;
                            @endphp
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>
                                    <strong>{{ $s->nama_peserta_didik }}</strong>
                                    <div style="font-size:11px;color:var(--muted)">NISN: {{ $s->nisn ?? $s->nis ?? '-' }}</div>
                                    <form id="{{ $formId }}" method="POST" action="{{ route('guru.walas.transkrip.simpan', $s->id) }}" style="display:none">
                                        @csrf
                                    </form>
                                </td>
                                <td>
                                    <input type="text" name="nomor_ijazah" form="{{ $formId }}" value="{{ $s->nomor_ijazah ?? '' }}"
                                           class="form-control" placeholder="DN-xx/SD/..." style="font-size:12px">
                                </td>
                                @foreach($mapel as $m)
                                    @php $rec = $nilaiSiswa[$m->id] ?? null; @endphp
                                    <td style="text-align:center">
                                        <input type="number" step="0.01" min="0" max="100"
                                               name="nilai[{{ $m->id }}]" form="{{ $formId }}"
                                               value="{{ $rec ? $rec->nilai_akhir : '' }}"
                                               class="form-control" style="width:76px;text-align:center;margin:0 auto;font-size:12px">
                                    </td>
                                @endforeach
                                <td style="text-align:center;white-space:nowrap">
                                    <button type="submit" form="{{ $formId }}" class="btn btn-xs btn-primary" title="Simpan Transkrip">
                                        <i class="fas fa-save"></i> Simpan
                                    </button>
                                    <a href="{{ route('guru.walas.transkrip.cetak', $s->id) }}" target="_blank" class="btn btn-xs btn-ghost" title="Cetak Transkrip">
                                        <i class="fas fa-print"></i> Cetak
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr class="table-empty">
                                <td colspan="{{ 4 + $mapel->count() }}" style="padding:20px;text-align:center;color:var(--muted)">Belum ada data siswa di kelas ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
