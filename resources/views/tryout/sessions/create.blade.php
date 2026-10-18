@extends('layouts.app')

@section('title', 'Buat Sesi Tryout - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Buat Sesi Tryout')
@section('page_subtitle', 'Jadwal pelaksanaan tryout')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Form Sesi Tryout</h3>
            <a href="{{ route('tryout.sessions.index') }}" class="btn btn-sm">Kembali</a>
        </div>
        <div class="card-body">
            @if($packages->isEmpty())
                <p style="color:#667085">Belum ada paket tryout aktif. Buat paket tryout dulu.</p>
            @else
                <form action="{{ route('tryout.sessions.store') }}" method="POST" style="display:grid;gap:12px;max-width:640px">
                    @csrf
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                        <div>
                            <label class="form-label">Paket</label>
                            <select name="package_id" required class="form-control">
                                <option value="">— Pilih —</option>
                                @foreach($packages as $p)
                                    <option value="{{ $p->id }}" @selected(old('package_id') == $p->id)>{{ $p->nama_paket }}</option>
                                @endforeach
                            </select>
                            @error('package_id')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
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
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 2fr;gap:12px">
                        <div>
                            <label class="form-label">Kelas</label>
                            <input type="text" name="kelas" value="{{ old('kelas') }}" maxlength="50" placeholder="6.A" class="form-control">
                        </div>
                        <div>
                            <label class="form-label">Nama Sesi</label>
                            <input type="text" name="nama_sesi" value="{{ old('nama_sesi') }}" maxlength="255" required class="form-control">
                            @error('nama_sesi')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Rombel</label>
                        <select name="rombel_id" class="form-control">
                            <option value="">— Umum —</option>
                            @foreach($rombels as $r)
                                <option value="{{ $r->id }}" @selected(old('rombel_id') == $r->id)>{{ $r->nama_rombel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                        <div>
                            <label class="form-label">Mulai</label>
                            <input type="datetime-local" name="tanggal_mulai" value="{{ old('tanggal_mulai') }}" required class="form-control">
                            @error('tanggal_mulai')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div>
                            <label class="form-label">Selesai</label>
                            <input type="datetime-local" name="tanggal_selesai" value="{{ old('tanggal_selesai') }}" required class="form-control">
                            @error('tanggal_selesai')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-sm">Simpan Sesi</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endsection
