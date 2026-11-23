@extends('layouts.app')

@section('title', 'Tujuan Pembelajaran (TP) - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Tujuan Pembelajaran (TP)')
@section('page_subtitle', 'Kelola butir Tujuan Pembelajaran per mata pelajaran, fase, dan semester sebagai acuan deskripsi capaian rapor')

@section('content')
    <div class="grid grid-3" style="margin-bottom:20px">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-plus-circle" style="margin-right:6px;color:var(--navy)"></i>Tambah Butir TP</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('guru.tp.store') }}">
                    @csrf
                    <div class="form-group" style="margin-bottom:12px">
                        <label for="fld-guru-tp-kode" class="form-label">Kode TP</label>
                        <input id="fld-guru-tp-kode" type="text" name="kode" class="form-control" placeholder="Misal: TP.1.1" maxlength="30" required>
                    </div>
                    <div class="form-group" style="margin-bottom:12px">
                        <label for="fld-guru-tp-mata-pelajaran" class="form-label">Mata Pelajaran</label>
                        <select id="fld-guru-tp-mata-pelajaran" name="mata_pelajaran" class="form-control" required>
                            @foreach($mapelOptions as $m)
                                <option value="{{ $m }}" @selected($m === ($pengaturan->mata_pelajaran ?? ''))>{{ $m }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
                        <div>
                            <label for="fld-guru-tp-fase" class="form-label">Fase</label>
                            <select id="fld-guru-tp-fase" name="fase" class="form-control" required>
                                <option value="Fase A">Fase A (Kelas 1-2)</option>
                                <option value="Fase B">Fase B (Kelas 3-4)</option>
                                <option value="Fase C">Fase C (Kelas 5-6)</option>
                            </select>
                        </div>
                        <div>
                            <label for="fld-guru-tp-semester" class="form-label">Semester</label>
                            <select id="fld-guru-tp-semester" name="semester" class="form-control" required>
                                <option value="Ganjil" @selected(($pengaturan->semester ?? 'Ganjil') === 'Ganjil')>Ganjil</option>
                                <option value="Genap" @selected(($pengaturan->semester ?? '') === 'Genap')>Genap</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom:14px">
                        <label for="fld-guru-tp-deskripsi" class="form-label">Deskripsi Ringkas TP (Maks. 300 karakter)</label>
                        <textarea id="fld-guru-tp-deskripsi" name="deskripsi" rows="3" maxlength="300" class="form-control" placeholder="Contoh: memahami konsep penjumlahan dan pengurangan bilangan cacah sampai 100" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%">
                        <i class="fas fa-save"></i> Simpan Tujuan Pembelajaran
                    </button>
                </form>
            </div>
        </div>

        <div class="card" style="grid-column:span 2">
            <div class="card-header">
                <div>
                    <h3 style="margin:0">Daftar Tujuan Pembelajaran</h3>
                    <small style="color:var(--muted)">Digunakan untuk narasi capaian kompetensi tertinggi & terendah pada e-Rapor</small>
                </div>
                <form method="GET" style="display:flex;gap:8px;align-items:center">
                    <select name="mapel" class="form-control" onchange="this.form.submit()" style="min-width:180px">
                        <option value="">Semua Mata Pelajaran</option>
                        @foreach($mapelOptions as $m)
                            <option value="{{ $m }}" @selected($mapelFilter === $m)>{{ $m }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
            <div class="card-body tight">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:90px">Kode</th>
                                <th style="width:160px">Mata Pelajaran</th>
                                <th style="width:120px">Fase / Smt</th>
                                <th>Deskripsi Tujuan Pembelajaran</th>
                                <th style="width:80px;text-align:center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($tpList as $tp)
                                <tr>
                                    <td><span class="tag tag-primary">{{ $tp['kode'] ?? '-' }}</span></td>
                                    <td><strong>{{ $tp['mata_pelajaran'] ?? '-' }}</strong></td>
                                    <td>{{ $tp['fase'] ?? '-' }} &bull; {{ $tp['semester'] ?? '-' }}</td>
                                    <td>{{ $tp['deskripsi'] ?? '-' }}</td>
                                    <td style="text-align:center">
                                        <form method="POST" action="{{ route('guru.tp.destroy', $tp['id']) }}" onsubmit="return confirm('Hapus butir TP ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-ghost" style="color:var(--bad)" aria-label="Hapus">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr class="table-empty">
                                    <td colspan="5" style="padding:24px;text-align:center;color:var(--muted)">
                                        Belum ada data Tujuan Pembelajaran (TP). Tambahkan melalui formulir di samping.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
