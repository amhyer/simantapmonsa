@extends('layouts.app')

@section('title', 'Push Peserta Didik ke Dapodik - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Push Peserta Didik ke Dapodik')
@section('page_subtitle', 'Kirim data peserta didik ke server Dapodik berdasarkan semester')

@section('content')
    <div class="card" style="margin-bottom:24px">
        <div class="card-header">
            <h3><x-lucide-upload class="w-5 h-5 mr-2" /> Push Peserta Didik ke Dapodik</h3>
        </div>
        <div class="card-body">
            <div class="alert alert-info" style="margin-bottom:16px">
                <x-lucide-info class="w-5 h-5 mr-2" />
                <strong>Informasi:</strong> Push ini akan mengirim data peserta didik (siswa) yang aktif dan memiliki dapodik_id ke server Dapodik pusat. Pastikan semester sudah dipilih dengan benar.
            </div>

            <form action="{{ route('admin.dapodik.push.peserta-didik') }}" method="POST">
                @csrf
                <div class="card">
                    <div class="card-header">
                        <h4>Pilih Semester</h4>
                    </div>
                    <div class="card-body">
                        <div style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;margin-bottom:16px">
                            <div style="flex:1;min-width:200px">
                                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Pilih Semester <span class="text-danger">*</span></label>
                                <select name="semester_id" required class="form-control" style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
                                    <option value="">-- Pilih Semester --</option>
                                    @foreach($semesters as $s)
                                        <option value="{{ $s->semester_id }}" {{ old('semester_id') == $s->semester_id ? 'selected' : '' }}>
                                            {{ $s->tahun_ajaran }} - {{ ucfirst($s->semester) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('semester_id')
                                    <span class="text-danger text-sm mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                            <div style="flex:0 0 auto">
                                <button type="submit" class="btn" style="height:40px">
                                    <x-lucide-upload class="w-4 h-4 mr-2" /> Push Peserta Didik
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            @if(session('success'))
                <div class="alert alert-success" style="margin-top:16px">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger" style="margin-top:16px">{{ session('error') }}</div>
            @endif

            @if(isset($result))
                <div class="card" style="margin-top:24px">
                    <div class="card-header">
                        <h4>Hasil Push</h4>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-4" style="margin-bottom:16px">
                            <div class="stat-card"><div class="stat-value">{{ $result['berhasil'] ?? 0 }}</div><div class="stat-label">Berhasil</div></div>
                            <div class="stat-card"><div class="stat-value">{{ $result['gagal'] ?? 0 }}</div><div class="stat-label">Gagal</div></div>
                        </div>
                        @if(!empty($result['errors']))
                            <div class="alert alert-danger" style="margin-top:16px">
                                <strong>Error:</strong>
                                <ul style="margin:8px 0 0 16px">
                                    @foreach($result['errors'] as $err)
                                        <li>{{ $err }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</section>