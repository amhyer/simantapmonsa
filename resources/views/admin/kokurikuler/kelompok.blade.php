@extends('layouts.app')

@section('title', 'Kelompok Kokurikuler - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Kelompok Kokurikuler')
@section('page_subtitle', 'Kelompok siswa per kegiatan beserta anggotanya')

@section('content')
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Tambah Kelompok</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.kokurikuler.kelompok.store') }}" method="POST" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
                @csrf
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Kegiatan</label>
                    <select name="kegiatan_id" required style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
                        <option value="">— Pilih kegiatan —</option>
                        @foreach($kegiatan as $k)
                            <option value="{{ $k->id }}" {{ old('kegiatan_id') == $k->id ? 'selected' : '' }}>{{ $k->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Nama Kelompok</label>
                    <input type="text" name="nama" value="{{ old('nama') }}" placeholder="Kelompok 1" maxlength="255" required style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Keterangan</label>
                    <input type="text" name="keterangan" value="{{ old('keterangan') }}" maxlength="500" style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div style="grid-column:1/-1">
                    <button type="submit" class="btn btn-sm">Tambah Kelompok</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Daftar Kelompok</h3>
            <span class="tag tag-mut">{{ $kelompok->count() }} kelompok</span>
        </div>
        <div class="card-body tight">
            @if($kelompok->count())
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Kelompok</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Kegiatan</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Anggota</th>
                                <th style="padding:11px 14px;text-align:right;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($kelompok as $k)
                                <tr>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC"><b>{{ $k->nama }}</b></td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">{{ $k->kegiatan?->nama ?? '-' }}<div style="font-size:12px;color:#667085">{{ $k->kegiatan?->tema?->nama ?? '' }}</div></td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">{{ $k->anggota_count }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:right">
                                        <a href="{{ route('admin.kokurikuler.kelompok.show', $k) }}" class="btn btn-sm btn-ghost">Kelola Anggota</a>
                                        <form action="{{ route('admin.kokurikuler.kelompok.destroy', $k) }}" method="POST" style="display:inline" onsubmit="return confirm('Hapus kelompok ini?')">
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
                    <div class="icon">👥</div>
                    <h4>Belum ada kelompok</h4>
                    <p>Buat kegiatan dulu, lalu tambah kelompok.</p>
                </div>
            @endif
        </div>
    </div>
@endsection
