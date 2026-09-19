@extends('layouts.app')

@section('title', 'Tema Kokurikuler - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Daftar Tema')
@section('page_subtitle', 'Tema kegiatan kokurikuler / projek')

@section('content')
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Tambah Tema</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.kokurikuler.tema.store') }}" method="POST" style="display:grid;grid-template-columns:1fr 2fr auto;gap:12px;align-items:end">
                @csrf
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Nama Tema</label>
                    <input type="text" name="nama" value="{{ old('nama') }}" placeholder="Gaya Hidup Berkelanjutan" maxlength="255" required style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Deskripsi</label>
                    <input type="text" name="deskripsi" value="{{ old('deskripsi') }}" maxlength="500" style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div>
                    <button type="submit" class="btn btn-sm">Tambah</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Daftar Tema</h3>
            <span class="tag tag-mut">{{ $tema->count() }} tema</span>
        </div>
        <div class="card-body tight">
            @if($tema->count())
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Tema</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Kegiatan</th>
                                <th style="padding:11px 14px;text-align:right;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tema as $t)
                                <tr>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC"><b>{{ $t->nama }}</b><div style="font-size:12px;color:#667085">{{ $t->deskripsi ?? '-' }}</div></td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">{{ $t->kegiatan_count }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:right">
                                        <form action="{{ route('admin.kokurikuler.tema.destroy', $t) }}" method="POST" style="display:inline" onsubmit="return confirm('Hapus tema beserta kegiatannya?')">
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
                    <div class="icon">🎯</div>
                    <h4>Belum ada tema</h4>
                    <p>Tambah tema melalui form di atas.</p>
                </div>
            @endif
        </div>
    </div>
@endsection
