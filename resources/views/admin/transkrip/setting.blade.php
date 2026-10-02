@extends('layouts.app')

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
            @if($errors->any())
                <div style="background:#FDECEC;border:1px solid #F5C6C6;color:#B42318;padding:10px 14px;border-radius:8px;margin-bottom:12px">
                    <ul style="margin:0;padding-left:18px">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <form action="{{ route('admin.transkrip.setting.update') }}" method="POST" style="display:grid;gap:12px;max-width:560px">
                @csrf
                @method('PUT')
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Tempat Penerbitan</label>
                    <input type="text" name="tempat" value="{{ old('tempat', $setting['tempat'] ?? '') }}" maxlength="100" placeholder="Cirebon" style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Tahun Pelajaran Lulus</label>
                    <input type="text" name="tahun_lulus" value="{{ old('tahun_lulus', $setting['tahun_lulus'] ?? '') }}" maxlength="9" placeholder="2025/2026" style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">NIP Kepala Sekolah</label>
                    <input type="text" name="nip_kepala_sekolah" value="{{ old('nip_kepala_sekolah', $setting['nip_kepala_sekolah'] ?? '') }}" maxlength="50" style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
                    <small style="color:#667085">Nama kepala sekolah diambil dari Identitas Sekolah.</small>
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Keterangan Kaki Transkrip</label>
                    <textarea name="keterangan" rows="3" maxlength="500" style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">{{ old('keterangan', $setting['keterangan'] ?? '') }}</textarea>
                </div>
                <div>
                    <button type="submit" class="btn btn-sm">Simpan Setting</button>
                </div>
            </form>
        </div>
    </div>
@endsection
