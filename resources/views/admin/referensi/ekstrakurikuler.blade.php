@extends('layouts.app')

@section('title', 'Ekstrakurikuler - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Data Ekstrakurikuler')
@section('page_subtitle', 'Kelola kegiatan ekstrakurikuler sekolah')

@section('content')
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Tambah Ekstrakurikuler</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.referensi.ekstrakurikuler.store') }}" method="POST" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                @csrf
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Nama Kegiatan</label>
                    <input type="text" name="nama" value="{{ old('nama') }}" placeholder="Pramuka" maxlength="100" required style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Pembina</label>
                    <select name="pembina_id" style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
                        <option value="">— Tanpa pembina —</option>
                        @foreach($guru as $g)
                            <option value="{{ $g->id }}" {{ old('pembina_id') == $g->id ? 'selected' : '' }}>{{ $g->nama_lengkap }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="grid-column:1/-1">
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Deskripsi</label>
                    <input type="text" name="deskripsi" value="{{ old('deskripsi') }}" style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div style="grid-column:1/-1">
                    <button type="submit" class="btn btn-sm">Tambah Ekstrakurikuler</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Daftar Ekstrakurikuler</h3>
            <span class="tag tag-mut">{{ $ekskul->count() }} kegiatan</span>
        </div>
        <div class="card-body tight">
            @if($ekskul->count())
                <div class="table-wrapper">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Kegiatan</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Pembina</th>
                                <th style="padding:11px 14px;text-align:right;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($ekskul as $e)
                                <tr>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC"><b>{{ $e->nama }}</b><div style="font-size:12px;color:#667085">{{ $e->deskripsi ?? '-' }}</div></td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">{{ $e->pembina?->nama_lengkap ?? '-' }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:right">
                                        <form action="{{ route('admin.referensi.ekstrakurikuler.destroy', $e) }}" method="POST" style="display:inline" onsubmit="return confirm('Hapus ekstrakurikuler ini?')">
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
                    <div class="icon">⚽</div>
                    <h4>Belum ada ekstrakurikuler</h4>
                    <p>Tambah kegiatan melalui form di atas.</p>
                </div>
            @endif
        </div>
    </div>
@endsection
