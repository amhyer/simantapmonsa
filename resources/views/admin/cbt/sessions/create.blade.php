@extends('layouts.admin-with-tabs')

@section('title', 'Buat Sesi Ujian - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Buat Sesi Ujian')
@section('page_subtitle', 'Sesi ujian CBT baru')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Form Sesi Ujian</h3>
            <a href="{{ route('admin.cbt.sessions.index') }}" class="btn btn-sm">Kembali</a>
        </div>
        <div class="card-body">
            @if($banks->isEmpty())
                <p style="color:#667085">Belum ada bank soal aktif yang berisi pertanyaan. Buat bank soal dan isi pertanyaannya dulu.</p>
            @else
                <form action="{{ route('admin.cbt.sessions.store') }}" method="POST" style="display:grid;gap:12px;max-width:640px">
                    @csrf
                    <div>
                        <label class="form-label">Bank Soal</label>
                        <select name="question_bank_id" required class="form-control">
                            <option value="">— Pilih —</option>
                            @foreach($banks as $b)
                                <option value="{{ $b->id }}" @selected(old('question_bank_id') == $b->id)>{{ $b->nama_bank }}</option>
                            @endforeach
                        </select>
                        @error('question_bank_id')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                        <div>
                            <label class="form-label">Nama Sesi</label>
                            <input type="text" name="nama_sesi" value="{{ old('nama_sesi') }}" maxlength="255" required class="form-control">
                            @error('nama_sesi')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div>
                            <label class="form-label">Kelas</label>
                            <input type="text" name="kelas" value="{{ old('kelas') }}" maxlength="50" class="form-control">
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                        <div>
                            <label class="form-label">Rombel</label>
                            <select name="rombel_id" class="form-control">
                                <option value="">— Umum —</option>
                                @foreach($rombels as $r)
                                    <option value="{{ $r->id }}" @selected(old('rombel_id') == $r->id)>{{ $r->nama_rombel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Guru Pengawas</label>
                            <select name="guru_id" required class="form-control">
                                <option value="">— Pilih —</option>
                                @foreach($gurus as $g)
                                    <option value="{{ $g->id }}" @selected(old('guru_id') == $g->id)>{{ $g->nama_lengkap }}</option>
                                @endforeach
                            </select>
                            @error('guru_id')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
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
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
                        <div>
                            <label class="form-label">Durasi (menit)</label>
                            <input type="number" name="durasi_menit" value="{{ old('durasi_menit', 60) }}" min="1" required class="form-control">
                        </div>
                        <div>
                            <label class="form-label">Jumlah Soal</label>
                            <input type="number" name="jumlah_soal_tampil" value="{{ old('jumlah_soal_tampil', 20) }}" min="1" required class="form-control">
                        </div>
                        <div>
                            <label class="form-label">Passing Grade</label>
                            <input type="number" name="passing_grade" value="{{ old('passing_grade', 70) }}" min="0" max="100" step="0.01" required class="form-control">
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                        <div>
                            <label class="form-label">Maksimal Percobaan</label>
                            <input type="number" name="maksimal_percobaan" value="{{ old('maksimal_percobaan', 1) }}" min="1" class="form-control">
                        </div>
                        <div style="display:flex;gap:16px;align-items:end;padding-bottom:8px;font-size:13px">
                            <label><input type="checkbox" name="acak_soal" value="1" @checked(old('acak_soal', true))> Acak soal</label>
                            <label><input type="checkbox" name="acak_opsi" value="1" @checked(old('acak_opsi', true))> Acak opsi</label>
                        </div>
                    </div>
                    <div style="display:flex;gap:16px;font-size:13px">
                        <label><input type="checkbox" name="tampilkan_nilai" value="1" @checked(old('tampilkan_nilai', true))> Tampilkan nilai</label>
                        <label><input type="checkbox" name="boleh_ulang" value="1" @checked(old('boleh_ulang'))> Boleh mengulang</label>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-sm">Simpan Sesi</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endsection

