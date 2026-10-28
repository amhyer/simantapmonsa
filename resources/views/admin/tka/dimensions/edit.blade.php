@extends('layouts.admin-with-tabs')

@section('title', 'Edit Dimensi TKA - Admin')

@section('page_title', 'Edit Dimensi TKA')
@section('page_subtitle', $dimension->nama_dimensi)

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Form Dimensi</h3>
            <a href="{{ route('admin.tka.dimensions.show', $dimension) }}" class="btn btn-sm">Kembali</a>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.tka.dimensions.update', $dimension) }}" method="POST" style="display:grid;gap:12px;max-width:640px">
                @csrf
                @method('PUT')
                <div style="display:grid;grid-template-columns:1fr 2fr;gap:12px">
                    <div>
                        <label class="form-label">Kode Dimensi</label>
                        <input type="text" name="kode_dimensi" value="{{ old('kode_dimensi', $dimension->kode_dimensi) }}" maxlength="20" required class="form-control">
                        @error('kode_dimensi')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <label class="form-label">Nama Dimensi</label>
                        <input type="text" name="nama_dimensi" value="{{ old('nama_dimensi', $dimension->nama_dimensi) }}" maxlength="255" required class="form-control">
                        @error('nama_dimensi')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div>
                    <label class="form-label">Kategori</label>
                    <select name="kategori" required class="form-control">
                        @foreach(['akhlak_beragama' => 'Akhlak Beragama', 'kebinekaan_global' => 'Kebinekaan Global', 'berkebhinekaan' => 'Berkebinekaan', 'mandiri' => 'Mandiri', 'bernalar_kritis' => 'Bernalar Kritis', 'kreatif' => 'Kreatif'] as $val => $label)
                            <option value="{{ $val }}" @selected(old('kategori', $dimension->kategori) === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('kategori')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div>
                    <label class="form-label">Deskripsi</label>
                    <textarea name="deskripsi" rows="3" class="form-control">{{ old('deskripsi', $dimension->deskripsi) }}</textarea>
                    @error('deskripsi')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div>
                    <button type="submit" class="btn btn-sm">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card" style="margin-top:16px">
        <div class="card-header">
            <h3>Informasi Tambahan</h3>
        </div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:150px 1fr;gap:8px 16px;font-size:13px">
                <div style="color:var(--muted)">Semester</div>
                <div>{{ $dimension->semester->label ?? '-' }}</div>

                <div style="color:var(--muted)">Bobot</div>
                <div>{{ $dimension->bobot ?? '-' }}</div>

                <div style="color:var(--muted)">Threshold Siap</div>
                <div>{{ $dimension->threshold_siap ?? '-' }}</div>

                <div style="color:var(--muted)">Status</div>
                <div>
                    <span class="tag {{ $dimension->aktif ? 'tag-ok' : 'tag-mut' }}">
                        {{ $dimension->aktif ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </div>
            </div>
        </div>
    </div>
@endsection
