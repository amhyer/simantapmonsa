@extends('layouts.app')

@section('title', 'Kegiatan Kokurikuler - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Kegiatan Kokurikuler')
@section('page_subtitle', 'Kegiatan per tema beserta koordinator dan jadwalnya')

@section('content')
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Tambah Kegiatan</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.kokurikuler.kegiatan.store') }}" method="POST" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
                @csrf
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Tema</label>
                    <select name="tema_id" required style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
                        <option value="">— Pilih tema —</option>
                        @foreach($tema as $t)
                            <option value="{{ $t->id }}" {{ old('tema_id') == $t->id ? 'selected' : '' }}>{{ $t->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Nama Kegiatan</label>
                    <input type="text" name="nama" value="{{ old('nama') }}" maxlength="255" required style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Kelas</label>
                    <input type="text" name="kelas" value="{{ old('kelas') }}" maxlength="50" style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Koordinator</label>
                    <input type="text" name="koordinator" value="{{ old('koordinator') }}" maxlength="255" style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Tanggal Mulai</label>
                    <input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai') }}" style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Tanggal Selesai</label>
                    <input type="date" name="tanggal_selesai" value="{{ old('tanggal_selesai') }}" style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div style="grid-column:1/-1">
                    <button type="submit" class="btn btn-sm">Tambah Kegiatan</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Daftar Kegiatan</h3>
            <span class="tag tag-mut">{{ $kegiatan->count() }} kegiatan</span>
        </div>
        <div class="card-body tight">
            @if($kegiatan->count())
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Kegiatan</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Tema</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Koordinator</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Kelompok</th>
                                <th style="padding:11px 14px;text-align:right;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($kegiatan as $k)
                                <tr>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC"><b>{{ $k->nama }}</b><div style="font-size:12px;color:#667085">{{ $k->kelas ?? '-' }}</div></td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">{{ $k->tema?->nama ?? '-' }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">{{ $k->koordinator ?? '-' }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">{{ $k->kelompok->count() }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:right">
                                        <form action="{{ route('admin.kokurikuler.kegiatan.destroy', $k) }}" method="POST" style="display:inline" onsubmit="return confirm('Hapus kegiatan beserta kelompoknya?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-ghost" style="color:#B42318">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">
                    <div class="icon">📋</div>
                    <h4>Belum ada kegiatan</h4>
                    <p>Buat tema dulu, lalu tambah kegiatan.</p>
                </div>
            @endif
        </div>
    </div>
@endsection
