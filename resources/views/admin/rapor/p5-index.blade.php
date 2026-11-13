@extends('layouts.admin-with-tabs')
@section('title', 'Penilaian P5BK - SIMANTAP')
@section('page_title', 'Penilaian P5BK (Projek Penguatan Profil Pelajar Pancasila)')
@section('page_subtitle', 'Input capaian dimensi projek per siswa dan cetak rapor P5BK')

@section('content')
    <div class="card" style="margin-bottom:16px">
        <div class="card-body">
            <form method="GET" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;align-items:end">
                <div>
                    <label class="form-label">Kelas</label>
                    <select name="kelas" class="form-control">
                        @foreach($kelasList as $k)
                            <option value="{{ $k }}" @selected($k === $kelas)>{{ $k }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Tema P5BK</label>
                    <select name="tema" class="form-control">
                        @foreach($temaList as $t)
                            <option value="{{ $t }}" @selected($t === $tema)>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Dimensi</label>
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

    @if($kelas)
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 style="margin:0">{{ $tema }}</h3>
                    <small style="color:var(--muted)">Dimensi: {{ $dimensi }} &bull; {{ $kelas }} ({{ $semester }} {{ $tahunAjaran }})</small>
                </div>
                <span class="tag tag-mut">{{ $siswa->count() }} siswa</span>
            </div>
            <div class="card-body tight">
                <form method="POST" action="{{ route('admin.rapor.p5.simpan') }}">
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
                                    <th style="min-width:190px">Nama Siswa</th>
                                    <th style="width:180px">Capaian (Predikat)</th>
                                    <th>Catatan Proses</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($siswa as $i => $s)
                                    @php $rec = $penilaian[$s->id] ?? null; $pred = $rec->predikat ?? 'BSH'; @endphp
                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td><strong>{{ $s->nama_peserta_didik }}</strong></td>
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
                                                   class="form-control" maxlength="500" placeholder="Catatan perkembangan projek...">
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="table-empty"><td colspan="4" style="padding:18px;text-align:center;color:var(--muted)">Belum ada siswa aktif di kelas ini.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($siswa->isNotEmpty())
                        <div style="padding:16px;border-top:1px solid #E4E7EC;display:flex;justify-content:flex-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Simpan Penilaian P5BK
                            </button>
                        </div>
                    @endif
                </form>
            </div>
        </div>
    @endif
@endsection
