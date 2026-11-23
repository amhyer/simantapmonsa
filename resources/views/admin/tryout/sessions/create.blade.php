@extends('layouts.admin-with-tabs')

@section('title', 'Buat Sesi Tryout - SIMANTAP')

@section('page_title', 'Buat Sesi Tryout')
@section('page_subtitle', 'Jadwal pelaksanaan tryout')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Form Sesi Tryout</h3>
            <a href="{{ route('admin.tryout.sessions.index') }}" class="btn btn-sm">Kembali</a>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.tryout.sessions.store') }}" method="POST" style="display:grid;gap:12px;max-width:640px">
                @csrf
                <div style="display:grid;grid-template-columns:1fr 2fr;gap:12px">
                    <div>
                        <label for="fld-sessions-create-kode-sesi" class="form-label">Kode Sesi</label>
                        <input id="fld-sessions-create-kode-sesi" type="text" name="kode_sesi" value="{{ old('kode_sesi') }}" maxlength="50" required class="form-control">
                        @error('kode_sesi')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <label for="fld-sessions-create-nama-sesi" class="form-label">Nama Sesi</label>
                        <input id="fld-sessions-create-nama-sesi" type="text" name="nama_sesi" value="{{ old('nama_sesi') }}" maxlength="255" required class="form-control">
                        @error('nama_sesi')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <div>
                        <label for="fld-sessions-create-package-id" class="form-label">Paket</label>
                        <select id="fld-sessions-create-package-id" name="package_id" required class="form-control">
                            <option value="">— Pilih —</option>
                            @foreach($packages as $p)
                                <option value="{{ $p->id }}" @selected(old('package_id') == $p->id)>{{ $p->nama_paket }}</option>
                            @endforeach
                        </select>
                        @error('package_id')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <label for="fld-sessions-create-semester-id" class="form-label">Semester</label>
                        <select id="fld-sessions-create-semester-id" name="semester_id" class="form-control">
                            <option value="">— Umum —</option>
                            @foreach($semesters as $s)
                                <option value="{{ $s->id }}" @selected(old('semester_id') == $s->id)>{{ $s->label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
                    <div>
                        <label for="fld-sessions-create-tanggal-mulai" class="form-label">Mulai</label>
                        <input id="fld-sessions-create-tanggal-mulai" type="datetime-local" name="tanggal_mulai" value="{{ old('tanggal_mulai') }}" required class="form-control">
                        @error('tanggal_mulai')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <label for="fld-sessions-create-tanggal-selesai" class="form-label">Selesai</label>
                        <input id="fld-sessions-create-tanggal-selesai" type="datetime-local" name="tanggal_selesai" value="{{ old('tanggal_selesai') }}" required class="form-control">
                        @error('tanggal_selesai')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <label for="fld-sessions-create-durasi-menit" class="form-label">Durasi (menit)</label>
                        <input id="fld-sessions-create-durasi-menit" type="number" name="durasi_menit" value="{{ old('durasi_menit', 120) }}" min="1" required class="form-control">
                    </div>
                </div>
                <div>
                    <label for="fld-sessions-create-instruksi" class="form-label">Instruksi</label>
                    <textarea id="fld-sessions-create-instruksi" name="instruksi" rows="3" class="form-control">{{ old('instruksi') }}</textarea>
                </div>
                <div>
                    <button type="submit" class="btn btn-sm">Simpan Sesi</button>
                </div>
            </form>
        </div>
    </div>
@endsection

