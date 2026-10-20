@extends('layouts.app')

@section('title', 'Edit Paket Tryout - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Edit Paket Tryout')
@section('page_subtitle', $package->nama_paket)

@section('content')
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Form Paket Tryout</h3>
            <a href="{{ route('tryout.packages.show', $package) }}" class="btn btn-sm">Kembali</a>
        </div>
        <div class="card-body">
            <form action="{{ route('tryout.packages.update', $package) }}" method="POST" style="display:grid;gap:12px;max-width:720px">
                @csrf
                @method('PUT')
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <div>
                        <label class="form-label">Kategori</label>
                        <select name="category_id" required class="form-control">
                            <option value="">— Pilih —</option>
                            @foreach($categories as $c)
                                <option value="{{ $c->id }}" @selected(old('category_id', $package->category_id) == $c->id)>{{ $c->nama_kategori }}</option>
                            @endforeach
                        </select>
                        @error('category_id')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <label class="form-label">Jenjang</label>
                        <select name="jenjang" required class="form-control">
                            @foreach(['SD', 'SMP', 'SMA', 'SMK', 'UMUM'] as $j)
                                <option value="{{ $j }}" @selected(old('jenjang', $package->jenjang) === $j)>{{ $j }}</option>
                            @endforeach
                        </select>
                        @error('jenjang')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 2fr;gap:12px">
                    <div>
                        <label class="form-label">Kode Paket</label>
                        <input type="text" name="kode_paket" value="{{ old('kode_paket', $package->kode_paket) }}" maxlength="50" required class="form-control">
                        @error('kode_paket')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <label class="form-label">Nama Paket</label>
                        <input type="text" name="nama_paket" value="{{ old('nama_paket', $package->nama_paket) }}" maxlength="255" required class="form-control">
                        @error('nama_paket')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
                    <div>
                        <label class="form-label">Tahun Paket</label>
                        <input type="number" name="tahun_paket" value="{{ old('tahun_paket', $package->tahun_paket) }}" min="2020" max="2030" required class="form-control">
                        @error('tahun_paket')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <label class="form-label">Durasi Total (menit)</label>
                        <input type="number" name="durasi_total_menit" value="{{ old('durasi_total_menit', $package->durasi_total_menit) }}" min="1" required class="form-control">
                        @error('durasi_total_menit')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <label class="form-label">Passing Grade</label>
                        <input type="number" name="passing_grade" value="{{ old('passing_grade', $package->passing_grade) }}" min="0" max="100" step="0.01" required class="form-control">
                        @error('passing_grade')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div>
                    <label class="form-label">Deskripsi</label>
                    <textarea name="deskripsi" rows="2" class="form-control">{{ old('deskripsi', $package->deskripsi) }}</textarea>
                    @error('deskripsi')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div>
                    <label class="form-label" style="display:flex;align-items:center;gap:8px">
                        <input type="hidden" name="aktif" value="0">
                        <input type="checkbox" name="aktif" value="1" @checked(old('aktif', $package->aktif))>
                        Paket aktif
                    </label>
                    @error('aktif')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div>
                    <button type="submit" class="btn btn-sm">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Mata Pelajaran</h3>
        </div>
        <div class="card-body tight">
            <div class="card-body" style="font-size:12.5px;color:var(--muted)">
                Mata pelajaran pada paket tidak diubah dari form ini.
            </div>
            @if($package->subjects->isEmpty())
                <div class="empty-state">
                    <div class="icon">📚</div>
                    <h4>Belum ada mata pelajaran</h4>
                    <p>Paket ini belum memiliki mata pelajaran.</p>
                </div>
            @else
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Mata Pelajaran</th>
                                <th>Bobot</th>
                                <th>Jumlah Soal</th>
                                <th>Durasi</th>
                                <th>KKM Mapel</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($package->subjects as $index => $subject)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $subject->mataPelajaran->nama ?? '-' }}</td>
                                    <td>{{ $subject->bobot ?? '-' }}</td>
                                    <td>{{ $subject->jumlah_soal ?? '-' }}</td>
                                    <td>{{ $subject->durasi_menit ?? '-' }} menit</td>
                                    <td>{{ $subject->passing_grade_mapel ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
