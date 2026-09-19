@extends('layouts.app')

@section('title', 'Tanggal Rapor - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Tanggal Rapor')
@section('page_subtitle', 'Atur tanggal pembagian rapor per tahun ajaran dan semester')

@section('content')
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Tambah / Perbarui Tanggal</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.referensi.tanggal-rapor.store') }}" method="POST" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                @csrf
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Tahun Ajaran</label>
                    <input type="text" name="tahun_ajaran" value="{{ old('tahun_ajaran') }}" placeholder="2025/2026" maxlength="20" required style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Semester</label>
                    <select name="semester" required style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
                        <option value="ganjil" {{ old('semester') === 'ganjil' ? 'selected' : '' }}>Ganjil</option>
                        <option value="genap" {{ old('semester') === 'genap' ? 'selected' : '' }}>Genap</option>
                    </select>
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Tanggal Pembagian</label>
                    <input type="date" name="tanggal" value="{{ old('tanggal') }}" style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Tempat</label>
                    <input type="text" name="tempat" value="{{ old('tempat') }}" placeholder="Aula sekolah" maxlength="255" style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div style="grid-column:1/-1">
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Keterangan</label>
                    <input type="text" name="keterangan" value="{{ old('keterangan') }}" maxlength="500" style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div style="grid-column:1/-1">
                    <button type="submit" class="btn btn-sm">Simpan Tanggal Rapor</button>
                    <span style="font-size:12px;color:#667085;margin-left:8px">Menyimpan tahun ajaran + semester yang sama akan memperbarui data lama.</span>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Daftar Tanggal Rapor</h3>
            <span class="tag tag-mut">{{ $daftar->count() }} entri</span>
        </div>
        <div class="card-body tight">
            @if($daftar->count())
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Tahun Ajaran</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Semester</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Tanggal</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Tempat</th>
                                <th style="padding:11px 14px;text-align:right;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($daftar as $d)
                                <tr>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC"><b>{{ $d->tahun_ajaran }}</b></td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">{{ ucfirst($d->semester) }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">{{ $d->tanggal ? $d->tanggal->translatedFormat('d M Y') : '-' }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">{{ $d->tempat ?? '-' }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:right">
                                        <form action="{{ route('admin.referensi.tanggal-rapor.destroy', $d) }}" method="POST" style="display:inline" onsubmit="return confirm('Hapus tanggal rapor ini?')">
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
                    <div class="icon">📅</div>
                    <h4>Belum ada tanggal rapor</h4>
                    <p>Isi form di atas untuk mengatur tanggal pembagian rapor.</p>
                </div>
            @endif
        </div>
    </div>
@endsection
