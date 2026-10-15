@extends('layouts.app')

@section('title', 'Edit Bank Soal - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Edit Bank Soal')
@section('page_subtitle', $bank->nama_bank)

@section('content')
<div class="card" style="max-width:800px">
    <div class="card-header">
        <h3>Ubah Informasi Bank Soal</h3>
    </div>
    
    <div class="card-body">
        <form method="PUT" action="{{ route('cbt.question-banks.update', $bank) }}">
            @csrf
            @method('PUT')
            
            <div style="margin-bottom:20px">
                <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:#667085">
                    Nama Bank Soal <span style="color:var(--bad)">*</span>
                </label>
                <input 
                    type="text" 
                    name="nama_bank" 
                    value="{{ old('nama_bank', $bank->nama_bank) }}"
                    style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:6px;font-size:13px"
                    required
                    placeholder="Contoh: Bank Soal Matematika Semester 1"
                />
                @error('nama_bank')
                    <span style="color:var(--bad);font-size:12px;margin-top:4px;display:block">{{ $message }}</span>
                @enderror
            </div>

            <div style="margin-bottom:20px">
                <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:#667085">
                    Deskripsi
                </label>
                <textarea 
                    name="deskripsi"
                    style="width:100%;min-height:80px;padding:10px;border:1px solid #E4E7EC;border-radius:6px;font-family:inherit;font-size:13px"
                    placeholder="Deskripsi singkat bank soal ini..."
                >{{ old('deskripsi', $bank->deskripsi) }}</textarea>
            </div>

            <div class="grid grid-2" style="gap:16px;margin-bottom:20px">
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:#667085">
                        Mata Pelajaran
                    </label>
                    <input 
                        type="text"
                        value="{{ $bank->mataPelajaran->nama ?? '-' }}"
                        disabled
                        style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:6px;font-size:13px;background:#F9FAFB;color:#667085"
                    />
                    <small style="display:block;margin-top:4px;color:#888">Tidak dapat diubah setelah pembuatan</small>
                </div>

                <div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:#667085">
                        Semester
                    </label>
                    <input 
                        type="text"
                        value="{{ $bank->semester->nama ?? '-' }}"
                        disabled
                        style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:6px;font-size:13px;background:#F9FAFB;color:#667085"
                    />
                    <small style="display:block;margin-top:4px;color:#888">Tidak dapat diubah setelah pembuatan</small>
                </div>
            </div>

            <div class="grid grid-2" style="gap:16px;margin-bottom:20px">
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:#667085">
                        Tingkat Kesulitan
                    </label>
                    <select 
                        name="tingkat_kesulitan"
                        style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:6px;font-size:13px"
                    >
                        <option value="mudah" {{ old('tingkat_kesulitan', $bank->tingkat_kesulitan) === 'mudah' ? 'selected' : '' }}>
                            Mudah
                        </option>
                        <option value="sedang" {{ old('tingkat_kesulitan', $bank->tingkat_kesulitan) === 'sedang' ? 'selected' : '' }}>
                            Sedang
                        </option>
                        <option value="sulit" {{ old('tingkat_kesulitan', $bank->tingkat_kesulitan) === 'sulit' ? 'selected' : '' }}>
                            Sulit
                        </option>
                    </select>
                </div>

                <div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:#667085">
                        Status
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;padding:8px;border:1px solid #E4E7EC;border-radius:6px;background:#F9FAFB;cursor:pointer">
                        <input 
                            type="checkbox" 
                            name="aktif"
                            value="1"
                            {{ old('aktif', $bank->aktif) ? 'checked' : '' }}
                        />
                        <span style="font-size:13px">Bank Soal Aktif</span>
                    </label>
                </div>
            </div>

            <div style="margin-bottom:20px">
                <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:#667085">
                    Kategori Soal
                </label>
                <input 
                    type="text" 
                    name="kategori_soal"
                    value="{{ old('kategori_soal', $bank->kategori_soal) }}"
                    style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:6px;font-size:13px"
                    placeholder="Contoh: UTS, UAS, Formatif"
                />
            </div>

            {{-- Info Box --}}
            <div style="padding:12px;background:#E3F2FD;border-left:3px solid #1976D2;border-radius:6px;margin-bottom:20px;font-size:12px;color:#1565C0">
                <i class="fas fa-info-circle" style="margin-right:6px"></i>
                Mata pelajaran dan semester tidak dapat diubah setelah pembuatan bank soal. Jika ingin mengubah, silakan buat bank soal baru.
            </div>

            {{-- Form Actions --}}
            <div style="display:flex;gap:12px;justify-content:center">
                <a href="{{ route('cbt.question-banks.show', $bank) }}" class="btn" style="background:#E4E7EC;color:#101828">
                    <i class="fas fa-times" style="margin-right:6px"></i>Batal
                </a>
                <button type="submit" class="btn" style="background:#12805C;color:#fff">
                    <i class="fas fa-save" style="margin-right:6px"></i>Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

@endsection
