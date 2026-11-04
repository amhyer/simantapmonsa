@extends('layouts.app')
@section('title', 'Buat Sesi Ujian Baru - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Buat Sesi Ujian Baru')
@section('page_subtitle', 'Tentukan bank soal, rombel kelas, jadwal, serta durasi pengerjaan siswa')

@section('content')
@include('cbt.feedback')

<div style="max-width:800px;margin:0 auto">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-plus" style="margin-right:8px;color:var(--navy)"></i>Formulir Sesi Ujian Baru</h3>
            <a href="{{ route('cbt.sessions.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Kembali ke Daftar
            </a>
        </div>

        <div class="card-body">
            <form method="POST" action="{{ route('cbt.sessions.store') }}">
                @csrf

                <div style="margin-bottom:20px">
                    <label for="title" class="form-label">
                        Judul Sesi Ujian <span style="color:var(--bad)">*</span>
                    </label>
                    <input id="title" 
                           type="text" 
                           name="title" 
                           class="form-control" 
                           maxlength="200" 
                           required 
                           placeholder="Contoh: Penilaian Akhir Semester (PAS) Ganjil Matematika Kelas 7A" 
                           value="{{ old('title') }}">
                    <small style="color:var(--muted);font-size:12px;margin-top:4px;display:block">
                        Judul ini akan tampil di layar dasbor siswa saat ujian berlangsung.
                    </small>
                </div>

                <div class="grid grid-2" style="margin-bottom:20px">
                    <div>
                        <label for="bank" class="form-label">
                            Bank Soal Pilihan Ganda <span style="color:var(--bad)">*</span>
                        </label>
                        <select id="bank" name="bank_id" class="form-control" required style="width:100%">
                            <option value="">— Pilih Bank Soal —</option>
                            @foreach($banks as $bank)
                                <option value="{{ $bank->id }}" @selected(old('bank_id') == $bank->id)>
                                    {{ $bank->title }} ({{ $bank->subject }})
                                </option>
                            @endforeach
                        </select>
                        <small style="color:var(--muted);font-size:12px;margin-top:4px;display:block">
                            Hanya bank soal dengan minimal 1 soal yang dapat dipilih.
                        </small>
                    </div>

                    <div>
                        <label for="kelas" class="form-label">
                            Target Kelas / Rombel <span style="color:var(--bad)">*</span>
                        </label>
                        <select id="kelas" name="kelas" class="form-control" required style="width:100%">
                            <option value="">— Pilih Kelas Peserta —</option>
                            @foreach($classes as $kelas)
                                <option value="{{ $kelas }}" @selected(old('kelas') === $kelas)>
                                    Kelas {{ $kelas }}
                                </option>
                            @endforeach
                        </select>
                        <small style="color:var(--muted);font-size:12px;margin-top:4px;display:block">
                            Seluruh siswa aktif di kelas ini akan menjadi peserta ujian.
                        </small>
                    </div>
                </div>

                <div class="grid grid-2" style="margin-bottom:20px">
                    <div>
                        <label for="opens" class="form-label">
                            Jadwal Buka (Mulai Sesi) <span style="color:var(--bad)">*</span>
                        </label>
                        <input id="opens" 
                               type="datetime-local" 
                               name="opens_at" 
                               class="form-control" 
                               required 
                               value="{{ old('opens_at') }}">
                        <small style="color:var(--muted);font-size:12px;margin-top:4px;display:block">
                            Zona waktu: {{ config('app.timezone') }}
                        </small>
                    </div>

                    <div>
                        <label for="closes" class="form-label">
                            Jadwal Tutup (Selesai Sesi) <span style="color:var(--bad)">*</span>
                        </label>
                        <input id="closes" 
                               type="datetime-local" 
                               name="closes_at" 
                               class="form-control" 
                               required 
                               value="{{ old('closes_at') }}">
                        <small style="color:var(--muted);font-size:12px;margin-top:4px;display:block">
                            Setelah waktu ini, seluruh pengerjaan otomatis tertutup.
                        </small>
                    </div>
                </div>

                <div style="margin-bottom:24px">
                    <label for="duration" class="form-label">
                        Alokasi Durasi Pengerjaan Siswa (Menit) <span style="color:var(--bad)">*</span>
                    </label>
                    <div style="display:flex;align-items:center;gap:12px">
                        <input id="duration" 
                               type="number" 
                               name="duration_minutes" 
                               class="form-control" 
                               min="1" 
                               max="300" 
                               style="max-width:180px" 
                               required 
                               value="{{ old('duration_minutes', 60) }}">
                        <span style="font-size:13px;font-weight:600;color:var(--text)">Menit</span>
                    </div>
                    <small style="color:var(--muted);font-size:12px;margin-top:4px;display:block">
                        Hitung mundur timer setiap siswa dihitung sejak menekan tombol "Mulai Ujian".
                    </small>
                </div>

                <div class="note note-ok" style="margin-bottom:24px;display:flex;align-items:flex-start;gap:10px">
                    <i class="fas fa-info-circle" style="color:var(--ok);margin-top:2px"></i>
                    <div style="font-size:12.5px;line-height:1.5">
                        <strong>Catatan Alur CBT:</strong> Sesi baru disimpan sebagai <em>Draf</em>. Anda dapat meninjau data sebelum mengklik <strong>Publikasikan</strong> untuk membekukan daftar soal & peserta.
                    </div>
                </div>

                <div style="display:flex;align-items:center;justify-content:space-between;padding-top:18px;border-top:1px solid var(--line);gap:12px">
                    <a href="{{ route('cbt.sessions.index') }}" class="btn btn-secondary">
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
