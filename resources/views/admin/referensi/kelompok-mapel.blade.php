@extends('layouts.app')

@section('title', 'Kelompok Mapel - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Data Kelompok Mapel')
@section('page_subtitle', 'Kelompokkan mata pelajaran (mis. A, B, C) untuk pengelompokan di rapor')

@section('content')
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Sebaran Kelompok</h3>
        </div>
        <div class="card-body" style="display:flex;gap:8px;flex-wrap:wrap">
            @forelse($kelompokList as $k)
                <span class="tag tag-primary">{{ $k }} ({{ $mapel->where('kelompok', $k)->count() }})</span>
            @empty
                <span style="font-size:13px;color:#667085">Belum ada kelompok. Isi kolom kelompok di bawah lalu simpan.</span>
            @endforelse
            @if($mapel->whereNull('kelompok')->count())
                <span class="tag tag-mut">Tanpa kelompok ({{ $mapel->whereNull('kelompok')->count() }})</span>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Atur Kelompok per Mapel</h3>
        </div>
        <div class="card-body tight">
            @if($mapel->count())
                <form action="{{ route('admin.referensi.mapel-meta.update') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="table-wrapper">
                        <table style="width:100%;border-collapse:collapse">
                            <thead>
                                <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                    <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Mata Pelajaran</th>
                                    <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Kode</th>
                                    <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Kelompok</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($mapel as $m)
                                    <tr>
                                        <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC"><b>{{ $m->nama }}</b></td>
                                        <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">{{ $m->kode ?? '-' }}</td>
                                        <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                            <input type="text" name="mapel[{{ $m->id }}][kelompok]" value="{{ $m->kelompok }}" placeholder="A / B / C" maxlength="50" style="width:140px;padding:6px 10px;border:1px solid #E4E7EC;border-radius:8px">
                                            <input type="hidden" name="mapel[{{ $m->id }}][urutan]" value="{{ $m->urutan ?? 0 }}">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top:12px">
                        <button type="submit" class="btn btn-sm">Simpan Kelompok</button>
                    </div>
                </form>
            @else
                <div class="empty-state">
                    <div class="icon">📚</div>
                    <h4>Belum ada mata pelajaran aktif</h4>
                    <p>Tambah dulu di menu Data Mapel.</p>
                </div>
            @endif
        </div>
    </div>
@endsection
