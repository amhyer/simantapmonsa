@extends('layouts.app')

@section('title', 'Mapping Rapor - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Mapping Rapor')
@section('page_subtitle', 'Atur urutan tampil mata pelajaran di rapor (angka kecil tampil lebih dulu)')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Urutan Mapel di Rapor</h3>
        </div>
        <div class="card-body tight">
            @if($mapel->count())
                @if($errors->any())
                    <div class="alert alert-danger" style="margin-bottom:16px">
                        <ul style="margin:0;padding-left:18px">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <form action="{{ route('admin.referensi.mapel-meta.update') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="table-wrapper">
                        <table style="width:100%;border-collapse:collapse">
                            <thead>
                                <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                    <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085;width:90px">Urutan</th>
                                    <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Mata Pelajaran</th>
                                    <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Kelompok</th>
                                    <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Mapel Transkrip</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($mapel as $m)
                                    <tr>
                                        <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">
                                            <input type="number" name="mapel[{{ $m->id }}][urutan]" value="{{ $m->urutan ?? 0 }}" min="0" max="999" style="width:80px;padding:6px 10px;border:1px solid #E4E7EC;border-radius:8px;text-align:center">
                                            <input type="hidden" name="mapel[{{ $m->id }}][kelompok]" value="{{ $m->kelompok }}">
                                        </td>
                                        <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC"><b>{{ $m->nama }}</b> <span style="font-size:12px;color:#667085">{{ $m->kode ?? '' }}</span></td>
                                        <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">{{ $m->kelompok ?? '-' }}</td>
                                        <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">
                                            <select name="mapel[{{ $m->id }}][masuk_transkrip]" style="padding:6px 10px;border:1px solid #E4E7EC;border-radius:8px">
                                                <option value="1" {{ $m->masuk_transkrip ? 'selected' : '' }}>Ya</option>
                                                <option value="0" {{ $m->masuk_transkrip ? '' : 'selected' }}>Tidak</option>
                                            </select>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top:12px">
                        <button type="submit" class="btn btn-sm">Simpan Urutan</button>
                    </div>
                </form>
            @else
                <div class="empty-state">
                    <div class="icon">🔀</div>
                    <h4>Belum ada mata pelajaran aktif</h4>
                    <p>Tambah dulu di menu Data Mapel.</p>
                </div>
            @endif
        </div>
    </div>
@endsection
