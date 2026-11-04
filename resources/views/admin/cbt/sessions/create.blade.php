@extends('layouts.admin-with-tabs')

@section('title', 'Buat Sesi Ujian CBT - Admin')

@section('page_title', 'Buat Sesi Ujian CBT')
@section('page_subtitle', 'Sesi baru (status awal draf)')

@section('content')
<div style="max-width:800px;margin:0 auto">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-plus" style="margin-right:8px;color:var(--navy)"></i>Formulir Sesi Ujian Baru</h3>
            <a href="{{ route('admin.cbt.sessions.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Kembali ke Daftar
            </a>
        </div>

        <div class="card-body">
            <form method="POST" action="{{ route('admin.cbt.sessions.store') }}">
                @csrf

                <div class="grid grid-2" style="margin-bottom:20px">
                    <div>
                        <label class="form-label">
                            Guru Penyelenggara <span style="color:var(--bad)">*</span>
                        </label>
                        <select name="guru_id" class="form-control" required style="width:100%">
                            <option value="">— Pilih Guru —</option>
                            @foreach($gurus as $guru)
                                <option value="{{ $guru->id }}" @selected(old('guru_id') == $guru->id)>
                                    {{ $guru->nama_lengkap }} (NIP: {{ $guru->nip ?? '-' }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="form-label">
                            Bank Soal Terkait <span style="color:var(--bad)">*</span>
                        </label>
                        <select name="bank_id" class="form-control" required style="width:100%">
                            <option value="">— Pilih Bank Soal —</option>
                            @foreach($banks as $bank)
                                <option value="{{ $bank->id }}" @selected(old('bank_id') == $bank->id)>
                                    {{ $bank->title }} ({{ $bank->subject }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-2" style="margin-bottom:20px">
                    <div>
                        <label class="form-label">
                            Judul Sesi Ujian <span style="color:var(--bad)">*</span>
                        </label>
                        <input type="text" 
                               name="title" 
                               class="form-control" 
                               maxlength="200" 
                               placeholder="Contoh: Penilaian Akhir Semester Ganjil" 
                               value="{{ old('title') }}" 
                               required>
                    </div>

                    <div>
                        <label class="form-label">
                            Target Kelas / Rombel (Dapodik) <span style="color:var(--bad)">*</span>
                        </label>
                        @if(isset($classes) && count($classes) > 0)
                            <select name="kelas" class="form-control" required style="width:100%">
                                <option value="">— Pilih Kelas Peserta —</option>
                                @foreach($classes as $c)
                                    <option value="{{ $c }}" @selected(old('kelas') === $c)>Kelas {{ $c }}</option>
                                @endforeach
                            </select>
                        @else
                            <input type="text" 
                                   name="kelas" 
                                   class="form-control" 
                                   placeholder="Contoh: 7A, 8B, IX-1" 
                                   value="{{ old('kelas') }}" 
                                   required>
                        @endif
                    </div>
                </div>

                <div class="grid grid-2" style="margin-bottom:20px">
                    <div>
                        <label class="form-label">
                            Jadwal Buka Sesi <span style="color:var(--bad)">*</span>
                        </label>
                        <input type="datetime-local" 
                               name="opens_at" 
                               class="form-control" 
                               value="{{ old('opens_at') }}" 
                               required>
                    </div>

                    <div>
                        <label class="form-label">
                            Jadwal Tutup Sesi <span style="color:var(--bad)">*</span>
                        </label>
                        <input type="datetime-local" 
                               name="closes_at" 
                               class="form-control" 
                               value="{{ old('closes_at') }}" 
                               required>
                    </div>
                </div>

                <div style="margin-bottom:24px">
                    <label class="form-label">
                        Durasi Pengerjaan per Siswa (Menit) <span style="color:var(--bad)">*</span>
                    </label>
                    <div style="display:flex;align-items:center;gap:10px">
                        <input type="number" 
                               name="duration_minutes" 
                               class="form-control" 
                               min="1" 
                               max="300" 
                               style="max-width:180px" 
                               value="{{ old('duration_minutes', 60) }}" 
                               required>
                        <span style="font-size:13px;font-weight:600;color:var(--text)">Menit</span>
                    </div>
                </div>

                <div style="display:flex;align-items:center;justify-content:space-between;padding-top:18px;border-top:1px solid var(--line);gap:12px">
                    <a href="{{ route('admin.cbt.sessions.index') }}" class="btn btn-secondary">
                        Batal
                    </a>
                    <button type="submit" class="btn btn-primary" style="padding:10px 24px">
                        <i class="fas fa-save"></i> Buat Draf Sesi Ujian
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
