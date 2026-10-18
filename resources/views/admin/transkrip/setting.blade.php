@extends('layouts.admin-with-tabs')

@section('title', 'Setting Transkrip - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Setting Transkrip')
@section('page_subtitle', 'Kop penerbitan transkrip nilai ijazah')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Setting Transkrip</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.transkrip.setting.update') }}" method="POST" style="display:grid;gap:12px;max-width:560px">
                @csrf
                @method('PUT')
                <div>
                    <label class="form-label">Tempat Penerbitan</label>
                    <input type="text" name="tempat" value="{{ old('tempat', $setting['tempat'] ?? '') }}" maxlength="100" placeholder="Cirebon" class="form-control">
                </div>
                <div>
                    <label class="form-label">Tahun Pelajaran Lulus</label>
                    <input type="text" name="tahun_lulus" value="{{ old('tahun_lulus', $setting['tahun_lulus'] ?? '') }}" maxlength="9" placeholder="2025/2026" class="form-control">
                </div>
                <div>
                    <label class="form-label">NIP Kepala Sekolah</label>
                    <input type="text" name="nip_kepala_sekolah" value="{{ old('nip_kepala_sekolah', $setting['nip_kepala_sekolah'] ?? '') }}" maxlength="50" class="form-control">
                    <small style="color:#667085">Nama kepala sekolah diambil dari Identitas Sekolah.</small>
                </div>
                <div>
                    <label class="form-label">Keterangan Kaki Transkrip</label>
                    <textarea name="keterangan" rows="3" maxlength="500" class="form-control">{{ old('keterangan', $setting['keterangan'] ?? '') }}</textarea>
                </div>
                <div>
                    <button type="submit" class="btn btn-sm">Simpan Setting</button>
                </div>
            </form>
        </div>
    </div>
@endsection

