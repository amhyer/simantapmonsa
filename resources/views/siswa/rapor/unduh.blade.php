@extends('layouts.app')

@section('title', 'Download Rapor Mandiri - SIMANTAP')

@section('sidebar')
    @include('siswa.partials.sidebar')
@endsection

@section('page_title', 'Download Rapor Mandiri')
@section('page_subtitle', 'Unduh dan cetak dokumen resmi Rapor Akademik, Rapor Projek P5, serta Pelengkap Rapor')

@section('content')
    @if(!$siswa)
        <div class="note note-warn">
            <i class="fas fa-exclamation-triangle"></i> Akun Anda belum terhubung dengan data siswa. Hubungi guru atau admin sekolah.
        </div>
    @else
        <div class="card" style="margin-bottom:20px">
            <div class="card-body">
                <form method="GET" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
                    <div>
                        <label for="fld-rapor-unduh-semester" class="form-label">Semester Rapor</label>
                        <select id="fld-rapor-unduh-semester" name="semester" class="form-control">
                            <option value="Ganjil" @selected($semester === 'Ganjil')>Ganjil</option>
                            <option value="Genap" @selected($semester === 'Genap')>Genap</option>
                        </select>
                    </div>
                    <div>
                        <label for="fld-rapor-unduh-tahun-ajaran" class="form-label">Tahun Ajaran</label>
                        <input id="fld-rapor-unduh-tahun-ajaran" type="text" name="tahun_ajaran" value="{{ $tahunAjaran }}" maxlength="9" class="form-control" style="width:130px">
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary">Terapkan Periode</button>
                </form>
            </div>
        </div>

        <div class="grid grid-3">
            {{-- 1. Lembar Rapor Akademik / Intrakurikuler --}}
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-file-signature" style="margin-right:8px;color:var(--violet)"></i>1. Rapor Akademik (Intrakurikuler)</h3>
                </div>
                <div class="card-body">
                    <p style="font-size:13px;color:var(--muted);margin-bottom:14px">
                        Lembar resmi laporan hasil belajar peserta didik mencakup nilai akhir mata pelajaran, capaian kompetensi, kehadiran, dan tanda tangan wali kelas.
                    </p>
                    <div style="margin-bottom:14px;font-size:12px">
                        <span class="tag tag-violet">{{ $jmlMapel }} mapel tercatat</span>
                    </div>
                    <a href="{{ route('siswa.rapor.cetak.akademik', ['semester' => $semester, 'tahun_ajaran' => $tahunAjaran]) }}"
                       target="_blank" class="btn btn-primary" style="width:100%;justify-content:center;background:var(--violet,#6D28D9);border-color:var(--violet,#6D28D9)">
                        <i class="fas fa-print"></i> Buka & Download Rapor Akademik
                    </a>
                </div>
            </div>

            {{-- 2. Lembar Rapor Projek Kokurikuler (P5) --}}
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-seedling" style="margin-right:8px;color:var(--ok)"></i>2. Rapor Projek P5 (Kokurikuler)</h3>
                </div>
                <div class="card-body">
                    <p style="font-size:13px;color:var(--muted);margin-bottom:14px">
                        Lembar rapor Projek Penguatan Profil Pelajar Pancasila (P5) lengkap dengan predikat dimensi karakter dan catatan proses perkembangan siswa.
                    </p>
                    <div style="margin-bottom:14px;font-size:12px">
                        <span class="tag tag-ok">{{ $jmlP5 }} capaian P5</span>
                    </div>
                    <a href="{{ route('siswa.rapor.cetak.p5', ['semester' => $semester, 'tahun_ajaran' => $tahunAjaran]) }}"
                       target="_blank" class="btn btn-primary" style="width:100%;justify-content:center">
                        <i class="fas fa-print"></i> Buka & Download Rapor P5
                    </a>
                </div>
            </div>

            {{-- 3. Lembar Pelengkap Rapor (Cover & Identitas) --}}
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-id-card" style="margin-right:8px;color:var(--gold)"></i>3. Pelengkap Rapor (Cover & Biodata)</h3>
                </div>
                <div class="card-body">
                    <p style="font-size:13px;color:var(--muted);margin-bottom:14px">
                        Lembar sampul (cover) buku rapor, identitas sekolah, dan lembar keterangan diri peserta didik sesuai data induk Dapodik.
                    </p>
                    <div style="margin-bottom:14px;font-size:12px">
                        <span class="tag tag-gold">NISN: {{ $siswa->nisn ?? $siswa->nis ?? '-' }}</span>
                    </div>
                    <a href="{{ route('siswa.rapor.cetak.pelengkap') }}"
                       target="_blank" class="btn btn-ghost" style="width:100%;justify-content:center;border:1px solid var(--line)">
                        <i class="fas fa-file-alt"></i> Buka & Cetak Pelengkap Rapor
                    </a>
                </div>
            </div>
        </div>
    @endif
@endsection
