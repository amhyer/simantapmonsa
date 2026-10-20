@extends('layouts.admin-with-tabs')

@section('title', 'Edit Sesi Tryout - Admin')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Edit Sesi Tryout')
@section('page_subtitle', $session->nama_sesi)

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Form Sesi Tryout</h3>
            <a href="{{ route('admin.tryout.sessions.show', $session) }}" class="btn btn-sm">Kembali</a>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.tryout.sessions.update', $session) }}" method="POST" style="display:grid;gap:12px;max-width:640px">
                @csrf
                @method('PUT')
                <div style="display:grid;grid-template-columns:1fr 2fr;gap:12px">
                    <div>
                        <label class="form-label">Kode Sesi</label>
                        <input type="text" name="kode_sesi" value="{{ old('kode_sesi', $session->kode_sesi) }}" maxlength="50" required class="form-control">
                        @error('kode_sesi')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <label class="form-label">Nama Sesi</label>
                        <input type="text" name="nama_sesi" value="{{ old('nama_sesi', $session->nama_sesi) }}" maxlength="255" required class="form-control">
                        @error('nama_sesi')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <div>
                        <label class="form-label">Paket</label>
                        <select name="package_id" required class="form-control">
                            <option value="">— Pilih —</option>
                            @foreach($packages as $p)
                                <option value="{{ $p->id }}" @selected(old('package_id', $session->package_id) == $p->id)>{{ $p->nama_paket }}</option>
                            @endforeach
                        </select>
                        @error('package_id')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <label class="form-label">Semester</label>
                        <select name="semester_id" class="form-control">
                            <option value="">— Umum —</option>
                            @foreach($semesters as $s)
                                <option value="{{ $s->id }}" @selected(old('semester_id', $session->semester_id) == $s->id)>{{ $s->label }}</option>
                            @endforeach
                        </select>
                        @error('semester_id')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
                    <div>
                        <label class="form-label">Mulai</label>
                        <input type="datetime-local" name="tanggal_mulai" value="{{ old('tanggal_mulai', $session->tanggal_mulai?->format('Y-m-d\TH:i')) }}" required class="form-control">
                        @error('tanggal_mulai')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <label class="form-label">Selesai</label>
                        <input type="datetime-local" name="tanggal_selesai" value="{{ old('tanggal_selesai', $session->tanggal_selesai?->format('Y-m-d\TH:i')) }}" required class="form-control">
                        @error('tanggal_selesai')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <label class="form-label">Durasi (menit)</label>
                        <input type="number" name="durasi_menit" value="{{ old('durasi_menit', $session->durasi_menit ?? 120) }}" min="1" required class="form-control">
                        @error('durasi_menit')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div>
                    <label class="form-label">Instruksi</label>
                    <textarea name="instruksi" rows="3" class="form-control">{{ old('instruksi', $session->instruksi) }}</textarea>
                    @error('instruksi')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div>
                    <button type="submit" class="btn btn-sm">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
@endsection
