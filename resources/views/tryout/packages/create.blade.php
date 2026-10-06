@extends('layouts.app')

@section('title', 'Tambah Paket Tryout - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Tambah Paket Tryout')
@section('page_subtitle', 'Paket soal beserta mata pelajarannya')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Form Paket Tryout</h3>
            <a href="{{ route('tryout.packages.index') }}" class="btn btn-sm">Kembali</a>
        </div>
        <div class="card-body">
            <form action="{{ route('tryout.packages.store') }}" method="POST" style="display:grid;gap:12px;max-width:720px">
                @csrf
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <div>
                        <label class="form-label">Kategori</label>
                        <select name="category_id" required class="form-control">
                            <option value="">— Pilih —</option>
                            @foreach($categories as $c)
                                <option value="{{ $c->id }}" @selected(old('category_id') == $c->id)>{{ $c->nama_kategori }}</option>
                            @endforeach
                        </select>
                        @error('category_id')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <label class="form-label">Jenjang</label>
                        <select name="jenjang" required class="form-control">
                            @foreach(['SD', 'SMP', 'SMA', 'SMK', 'UMUM'] as $j)
                                <option value="{{ $j }}" @selected(old('jenjang', 'SD') === $j)>{{ $j }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 2fr;gap:12px">
                    <div>
                        <label class="form-label">Kode Paket</label>
                        <input type="text" name="kode_paket" value="{{ old('kode_paket') }}" maxlength="50" required class="form-control">
                        @error('kode_paket')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <label class="form-label">Nama Paket</label>
                        <input type="text" name="nama_paket" value="{{ old('nama_paket') }}" maxlength="255" required class="form-control">
                        @error('nama_paket')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
                    <div>
                        <label class="form-label">Tahun Paket</label>
                        <input type="number" name="tahun_paket" value="{{ old('tahun_paket', date('Y')) }}" min="2020" max="2030" required class="form-control">
                    </div>
                    <div>
                        <label class="form-label">Durasi Total (menit)</label>
                        <input type="number" name="durasi_total_menit" value="{{ old('durasi_total_menit', 120) }}" min="1" required class="form-control">
                    </div>
                    <div>
                        <label class="form-label">Passing Grade</label>
                        <input type="number" name="passing_grade" value="{{ old('passing_grade', 70) }}" min="0" max="100" step="0.01" required class="form-control">
                    </div>
                </div>
                <div>
                    <label class="form-label">Deskripsi</label>
                    <textarea name="deskripsi" rows="2" class="form-control">{{ old('deskripsi') }}</textarea>
                </div>
                <div>
                    <label class="form-label">Mata Pelajaran dalam Paket (minimal 1)</label>
                    <div id="subjectRows" style="display:grid;gap:8px">
                        <div class="subject-row" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr auto;gap:8px;align-items:end">
                            <div>
                                <select name="subjects[0][mata_pelajaran_id]" required class="form-control">
                                    <option value="">— Mapel —</option>
                                    @foreach($mataPelajarans as $m)
                                        <option value="{{ $m->id }}">{{ $m->nama }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div><input type="number" name="subjects[0][bobot]" placeholder="Bobot" min="0" max="10" step="0.1" required class="form-control"></div>
                            <div><input type="number" name="subjects[0][jumlah_soal]" placeholder="Jml soal" min="1" required class="form-control"></div>
                            <div><input type="number" name="subjects[0][durasi_menit]" placeholder="Menit" min="1" required class="form-control"></div>
                            <div><input type="number" name="subjects[0][passing_grade_mapel]" placeholder="KKM" min="0" max="100" step="0.01" required class="form-control"></div>
                            <div><button type="button" class="btn btn-sm" onclick="this.closest('.subject-row').remove()">Hapus</button></div>
                        </div>
                    </div>
                    <div style="margin-top:8px">
                        <button type="button" class="btn btn-sm" id="addSubject">+ Tambah Mapel</button>
                    </div>
                    @error('subjects')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div>
                    <button type="submit" class="btn btn-sm">Simpan Paket</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function() {
    let idx = 1;
    const mapelOptions = `@foreach($mataPelajarans as $m)<option value="{{ $m->id }}">{{ $m->nama }}</option>@endforeach`;
    document.getElementById('addSubject').addEventListener('click', function() {
        const row = document.createElement('div');
        row.className = 'subject-row';
        row.style.cssText = 'display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr auto;gap:8px;align-items:end';
        row.innerHTML =
            '<div><select name="subjects[' + idx + '][mata_pelajaran_id]" required class="form-control"><option value="">— Mapel —</option>' + mapelOptions + '</select></div>' +
            '<div><input type="number" name="subjects[' + idx + '][bobot]" placeholder="Bobot" min="0" max="10" step="0.1" required class="form-control"></div>' +
            '<div><input type="number" name="subjects[' + idx + '][jumlah_soal]" placeholder="Jml soal" min="1" required class="form-control"></div>' +
            '<div><input type="number" name="subjects[' + idx + '][durasi_menit]" placeholder="Menit" min="1" required class="form-control"></div>' +
            '<div><input type="number" name="subjects[' + idx + '][passing_grade_mapel]" placeholder="KKM" min="0" max="100" step="0.01" required class="form-control"></div>' +
            '<div><button type="button" class="btn btn-sm" onclick="this.closest(\'.subject-row\').remove()">Hapus</button></div>';
        document.getElementById('subjectRows').appendChild(row);
        idx++;
    });
})();
</script>
@endpush
