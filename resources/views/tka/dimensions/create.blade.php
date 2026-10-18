@extends('layouts.app')

@section('title', 'Tambah Dimensi TKA - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Tambah Dimensi TKA')
@section('page_subtitle', 'Dimensi penilaian beserta rubriknya')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Form Dimensi</h3>
            <a href="{{ route('tka.dimensions.index') }}" class="btn btn-sm">Kembali</a>
        </div>
        <div class="card-body">
            <form action="{{ route('tka.dimensions.store') }}" method="POST" style="display:grid;gap:12px;max-width:640px">
                @csrf
                <div>
                    <label class="form-label">Semester</label>
                    <select name="semester_id" required class="form-control">
                        <option value="">— Pilih —</option>
                        @foreach($semesters as $s)
                            <option value="{{ $s->id }}" @selected(old('semester_id') == $s->id)>{{ $s->label }}</option>
                        @endforeach
                    </select>
                    @error('semester_id')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div style="display:grid;grid-template-columns:1fr 2fr;gap:12px">
                    <div>
                        <label class="form-label">Kode Dimensi</label>
                        <input type="text" name="kode_dimensi" value="{{ old('kode_dimensi') }}" maxlength="50" required class="form-control">
                        @error('kode_dimensi')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <label class="form-label">Nama Dimensi</label>
                        <input type="text" name="nama_dimensi" value="{{ old('nama_dimensi') }}" maxlength="255" required class="form-control">
                        @error('nama_dimensi')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
                    <div>
                        <label class="form-label">Kategori</label>
                        <select name="kategori" required class="form-control">
                            @foreach(['religius' => 'Religius', 'nasionalis' => 'Nasionalis', 'mandiri' => 'Mandiri', 'gotong_royong' => 'Gotong Royong', 'integritas' => 'Integritas', 'kreatif' => 'Kreatif', 'berdaya_saing' => 'Berdaya Saing'] as $val => $label)
                                <option value="{{ $val }}" @selected(old('kategori') === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Bobot (0-10)</label>
                        <input type="number" name="bobot" value="{{ old('bobot', 5) }}" min="0" max="10" step="0.1" required class="form-control">
                    </div>
                    <div>
                        <label class="form-label">Threshold Siap (1-4)</label>
                        <input type="number" name="threshold_siap" value="{{ old('threshold_siap', 3) }}" min="1" max="4" step="0.1" required class="form-control">
                    </div>
                </div>
                <div>
                    <label class="form-label">Deskripsi</label>
                    <textarea name="deskripsi" rows="2" class="form-control">{{ old('deskripsi') }}</textarea>
                </div>
                <div>
                    <label class="form-label">Rubrik Penilaian (minimal 1 baris)</label>
                    <div id="rubrikRows" style="display:grid;gap:8px">
                        <div class="rubrik-row" style="display:grid;grid-template-columns:1fr 2fr auto;gap:8px;align-items:end">
                            <div><input type="text" name="rubrik_penilaian[0][level]" placeholder="Level, mis. 4" required class="form-control"></div>
                            <div><input type="text" name="rubrik_penilaian[0][deskripsi]" placeholder="Deskripsi capaian" required class="form-control"></div>
                            <div><button type="button" class="btn btn-sm" onclick="this.closest('.rubrik-row').remove()">Hapus</button></div>
                        </div>
                    </div>
                    <div style="margin-top:8px">
                        <button type="button" class="btn btn-sm" id="addRubrik">+ Tambah Baris</button>
                    </div>
                    @error('rubrik_penilaian')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div>
                    <button type="submit" class="btn btn-sm">Simpan Dimensi</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function() {
    let idx = 1;
    document.getElementById('addRubrik').addEventListener('click', function() {
        const row = document.createElement('div');
        row.className = 'rubrik-row';
        row.style.cssText = 'display:grid;grid-template-columns:1fr 2fr auto;gap:8px;align-items:end';
        row.innerHTML =
            '<div><input type="text" name="rubrik_penilaian[' + idx + '][level]" placeholder="Level, mis. 4" required class="form-control"></div>' +
            '<div><input type="text" name="rubrik_penilaian[' + idx + '][deskripsi]" placeholder="Deskripsi capaian" required class="form-control"></div>' +
            '<div><button type="button" class="btn btn-sm" onclick="this.closest(\'.rubrik-row\').remove()">Hapus</button></div>';
        document.getElementById('rubrikRows').appendChild(row);
        idx++;
    });
})();
</script>
@endpush
