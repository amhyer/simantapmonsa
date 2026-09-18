@extends('layouts.app')

@section('title', 'Semester - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Manajemen Semester')
@section('page_subtitle', 'Kelola semester aktif dan tahun ajaran')

@section('content')
@if(session('success'))
    <div class="toast toast-success" style="position:static;margin-bottom:16px">{{ session('success') }}</div>
@endif

<div class="grid grid-2" style="margin-bottom:16px">
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-plus-circle"></i> Tambah Semester</h3></div>
        <div class="card-body">
            <form action="{{ route('admin.semester.store') }}" method="POST">
                @csrf
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px">
                    <div>
                        <label style="font-size:12px;color:#667085;display:block;margin-bottom:4px">Semester ID</label>
                        <input type="text" name="semester_id" placeholder="20261" required
                            style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px;font-size:13px"
                            value="{{ old('semester_id') }}">
                        @error('semester_id') <span style="color:#DC2626;font-size:11px">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label style="font-size:12px;color:#667085;display:block;margin-bottom:4px">Tahun Ajaran</label>
                        <input type="text" name="tahun_ajaran" placeholder="2025/2026" required
                            style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px;font-size:13px"
                            value="{{ old('tahun_ajaran') }}">
                        @error('tahun_ajaran') <span style="color:#DC2626;font-size:11px">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label style="font-size:12px;color:#667085;display:block;margin-bottom:4px">Semester</label>
                        <select name="nama_semester" required
                            style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px;font-size:13px">
                            <option value="ganjil" {{ old('nama_semester') == 'ganjil' ? 'selected' : '' }}>Ganjil</option>
                            <option value="genap" {{ old('nama_semester') == 'genap' ? 'selected' : '' }}>Genap</option>
                        </select>
                        @error('nama_semester') <span style="color:#DC2626;font-size:11px">{{ $message }}</span> @enderror
                    </div>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3><i class="fas fa-info-circle"></i> Panduan</h3></div>
        <div class="card-body" style="font-size:13px;color:#667085;line-height:1.8">
            <p><b>Semester ID</b>: Format 5 digit. Contoh: <code>20261</code> (Ganjil), <code>20262</code> (Genap)</p>
            <p><b>Semester Aktif</b>: Data siswa/guru yang di-sync akan terkait ke semester ini.</p>
            <p><b>Cara pakai:</b></p>
            <ol style="margin:0;padding-left:20px">
                <li>Tambah semester baru</li>
                <li>Klik <b>Aktifkan</b> pada semester yang dipakai</li>
                <li>Bridge Dapodik akan otomatis pakai semester aktif</li>
            </ol>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-calendar-alt"></i> Daftar Semester</h3><span class="tag tag-secondary">{{ $semesters->count() }} semester</span></div>
    <div class="card-body tight">
        @if($semesters->count())
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse">
                    <thead>
                        <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Semester ID</th>
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Tahun Ajaran</th>
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Semester</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Status</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($semesters as $s)
                        <tr style="border-bottom:1px solid #F2F4F7;{{ $activeId == $s->id ? 'background:#F0FDF4;' : '' }}">
                            <td style="padding:10px 12px;font-size:13px;font-weight:600">{{ $s->semester_id }}</td>
                            <td style="padding:10px 12px;font-size:13px">{{ $s->tahun_ajaran }}</td>
                            <td style="padding:10px 12px"><span class="tag {{ $s->nama_semester == 'ganjil' ? 'tag-primary' : 'tag-gold' }}">{{ ucfirst($s->nama_semester) }}</span></td>
                            <td style="padding:10px 12px;text-align:center">
                                @if($activeId == $s->id)
                                    <span class="tag tag-ok"><i class="fas fa-check"></i> Aktif</span>
                                @else
                                    <span class="tag tag-secondary">Non-aktif</span>
                                @endif
                            </td>
                            <td style="padding:10px 12px;text-align:center">
                                @if($activeId != $s->id)
                                    <form action="{{ route('admin.semester.activate', $s->id) }}" method="POST" style="display:inline">
                                        @csrf
                                        <button class="btn btn-ghost btn-sm" style="color:#059669"><i class="fas fa-check-circle"></i> Aktifkan</button>
                                    </form>
                                @endif
                                <form action="{{ route('admin.semester.destroy', $s->id) }}" method="POST" style="display:inline" onsubmit="return confirm('Hapus semester ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-ghost btn-sm" style="color:#DC2626"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div style="text-align:center;padding:40px;color:#888">
                <div style="font-size:48px;margin-bottom:12px"><i class="fas fa-calendar-plus"></i></div>
                <h4>Belum ada semester</h4>
                <p style="font-size:13px">Tambahkan semester baru untuk mulai mengelola data per-semester.</p>
            </div>
        @endif
    </div>
</div>
@endsection
