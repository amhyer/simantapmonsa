@extends($layout ?? 'layouts.app')

@section('title', 'Kalender Pendidikan & Hari Libur - Pembelajaran Mendalam')
@section('page_title', 'Kalender Pendidikan & Hari Libur')
@section('page_subtitle', 'Atur minggu efektif, minggu libur, dan hari libur sekolah sebagai dasar otomatis Prota, Prosem & Generator Modul')

@if(!($canEdit ?? false))
    @section('sidebar')
        @include('guru.partials.sidebar')
    @endsection
@endif

@section('header_actions')
    @if($canEdit ?? false)
        <form method="POST" action="{{ route('admin.pm.kalender.default') }}" style="display:inline"
              onsubmit="return confirm('Muat ulang kalender pendidikan standar untuk tahun ajaran {{ $tahunAjaran }}?');">
            @csrf
            <input type="hidden" name="tahun_ajaran" value="{{ $tahunAjaran }}">
            <button type="submit" class="btn btn-ghost btn-sm">
                <i class="fas fa-sync-alt"></i> Muat Kalender Standar
            </button>
        </form>
    @endif
@endsection

@section('content')
    @if(!($canEdit ?? false))
        @include('guru.pm._nav')
        <div style="margin-bottom:12px;padding:10px 14px;background:#EFF8FF;border:1px solid #B2DDFF;border-radius:8px;font-size:12px;color:#175CD3">
            <i class="fas fa-info-circle"></i> <strong>Mode Baca (Read-Only):</strong> Kalender Pendidikan &amp; Hari Libur sekolah dikelola oleh Admin/Operator Sekolah.
        </div>
    @endif

    {{-- Ringkasan Efektif --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:16px">
        <div class="card">
            <div class="card-body" style="padding:14px">
                <div style="font-size:12px;color:#667085;font-weight:600">Semester 1 (Ganjil) · {{ $tahunAjaran }}</div>
                <div style="font-size:20px;font-weight:700;color:#101828;margin-top:4px">{{ $sem1['total_efektif'] }} Minggu Efektif</div>
                <div style="font-size:12px;color:#475467;margin-top:2px">
                    Total {{ $sem1['total_minggu'] }} mgg · Tidak efektif {{ $sem1['total_tidak_efektif'] }} mgg · {{ $sem1['total_hari_efektif'] }} hari efektif
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-body" style="padding:14px">
                <div style="font-size:12px;color:#667085;font-weight:600">Semester 2 (Genap) · {{ $tahunAjaran }}</div>
                <div style="font-size:20px;font-weight:700;color:#101828;margin-top:4px">{{ $sem2['total_efektif'] }} Minggu Efektif</div>
                <div style="font-size:12px;color:#475467;margin-top:2px">
                    Total {{ $sem2['total_minggu'] }} mgg · Tidak efektif {{ $sem2['total_tidak_efektif'] }} mgg · {{ $sem2['total_hari_efektif'] }} hari efektif
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-body" style="padding:14px">
                <div style="font-size:12px;color:#667085;font-weight:600">Total 1 Tahun Ajaran</div>
                <div style="font-size:20px;font-weight:700;color:#101828;margin-top:4px">{{ $sem1['total_efektif'] + $sem2['total_efektif'] }} Minggu Efektif</div>
                <div style="font-size:12px;color:#475467;margin-top:2px">
                    {{ $sem1['total_hari_efektif'] + $sem2['total_hari_efektif'] }} hari efektif · {{ $hariLibur->count() }} agenda libur tercatat
                </div>
            </div>
        </div>
    </div>

    {{-- Form Kalender Pendidikan --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <strong>Rincian Minggu Efektif — Tahun Ajaran {{ $tahunAjaran }}</strong>
        </div>
        <form method="POST" action="{{ route(($routePrefix ?? 'guru.pm').'.kalender.simpan') }}">
            @csrf
            <div class="card-body" style="padding:0">
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width:70px">Sem</th>
                                <th style="width:140px">Bulan</th>
                                <th style="width:90px">Jml Minggu</th>
                                <th style="width:110px">Tdk Efektif</th>
                                <th style="width:95px">Efektif</th>
                                <th style="width:100px">Hari Efektif</th>
                                <th style="width:150px">Minggu Libur (mis: 1,2)</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sem1['rows']->concat($sem2['rows']) as $kal)
                                <tr>
                                    <td><span class="badge {{ $kal->semester === 1 ? 'badge-info' : 'badge-success' }}">S{{ $kal->semester }}</span></td>
                                    <td style="font-weight:600">{{ $kal->namaBulan() }} {{ $kal->tahun }}</td>
                                    <td>
                                        @if($canEdit ?? false)
                                            <input type="number" min="1" max="6" name="kalender[{{ $kal->id }}][jumlah_minggu]"
                                                   value="{{ $kal->jumlah_minggu }}"
                                                   style="width:64px;padding:5px;border:1px solid #D0D5DD;border-radius:6px;text-align:center">
                                        @else
                                            {{ $kal->jumlah_minggu }}
                                        @endif
                                    </td>
                                    <td>
                                        @if($canEdit ?? false)
                                            <input type="number" min="0" max="6" name="kalender[{{ $kal->id }}][minggu_tidak_efektif]"
                                                   value="{{ $kal->minggu_tidak_efektif }}"
                                                   style="width:64px;padding:5px;border:1px solid #D0D5DD;border-radius:6px;text-align:center">
                                        @else
                                            {{ $kal->minggu_tidak_efektif }}
                                        @endif
                                    </td>
                                    <td style="font-weight:700;color:#027A48">{{ $kal->mingguEfektif() }} mgg</td>
                                    <td>
                                        @if($canEdit ?? false)
                                            <input type="number" min="0" max="31" name="kalender[{{ $kal->id }}][hari_efektif]"
                                                   value="{{ $kal->hari_efektif }}"
                                                   style="width:68px;padding:5px;border:1px solid #D0D5DD;border-radius:6px;text-align:center">
                                        @else
                                            {{ $kal->hari_efektif }}
                                        @endif
                                    </td>
                                    <td>
                                        @if($canEdit ?? false)
                                            <input type="text" name="kalender[{{ $kal->id }}][minggu_libur]"
                                                   value="{{ is_array($kal->minggu_libur) ? implode(',', $kal->minggu_libur) : '' }}"
                                                   placeholder="mis: 1,2"
                                                   style="width:110px;padding:5px 8px;border:1px solid #D0D5DD;border-radius:6px;font-size:12px">
                                        @else
                                            {{ is_array($kal->minggu_libur) && count($kal->minggu_libur) ? implode(',', $kal->minggu_libur) : '-' }}
                                        @endif
                                    </td>
                                    <td>
                                        @if($canEdit ?? false)
                                            <input type="text" name="kalender[{{ $kal->id }}][keterangan]"
                                                   value="{{ $kal->keterangan }}"
                                                   style="width:100%;padding:5px 8px;border:1px solid #D0D5DD;border-radius:6px;font-size:12px">
                                        @else
                                            {{ $kal->keterangan ?: '-' }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @if($canEdit ?? false)
                <div class="card-body" style="border-top:1px solid #EAECF0;display:flex;justify-content:flex-end">
                    <button type="submit" class="btn"><i class="fas fa-save"></i> Simpan Kalender Pendidikan</button>
                </div>
            @endif
        </form>
    </div>

    {{-- Hari Libur & Kegiatan Sekolah --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px">
        @if($canEdit ?? false)
        <div class="card">
            <div class="card-header"><strong><i class="fas fa-calendar-times"></i> Tambah Hari Libur / Non-Efektif</strong></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.pm.kalender.libur.store') }}" style="display:grid;gap:10px">
                    @csrf
                    <input type="hidden" name="tahun_ajaran" value="{{ $tahunAjaran }}">
                    <div>
                        <label style="font-size:12px;font-weight:600">Nama Hari Libur / Kegiatan *</label>
                        <input type="text" name="nama" required placeholder="Misal: Libur Semester 1 / Hari Kemerdekaan RI"
                               style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                        <div>
                            <label style="font-size:12px;font-weight:600">Tanggal Mulai *</label>
                            <input type="date" name="tanggal_mulai" required
                                   style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                        <div>
                            <label style="font-size:12px;font-weight:600">Tanggal Selesai</label>
                            <input type="date" name="tanggal_selesai"
                                   style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600">Jenis *</label>
                        <select name="jenis" required style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                            @foreach($jenisLiburLabels as $kode => $label)
                                <option value="{{ $kode }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600">Keterangan (opsional)</label>
                        <input type="text" name="keterangan"
                               style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-sm"><i class="fas fa-plus"></i> Tambah Hari Libur</button>
                    </div>
                </form>
            </div>
        </div>
        @endif

        <div class="card">
            <div class="card-header"><strong>Daftar Hari Libur ({{ $hariLibur->count() }})</strong></div>
            <div class="card-body" style="padding:0">
                @if($hariLibur->isEmpty())
                    <div style="padding:28px;text-align:center;color:#667085;font-size:13px">
                        Belum ada hari libur khusus yang dicatat untuk tahun ajaran {{ $tahunAjaran }}.
                    </div>
                @else
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Nama</th>
                                    <th>Jenis</th>
                                    @if($canEdit ?? false)<th style="width:60px;text-align:right">Aksi</th>@endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($hariLibur as $hl)
                                    <tr>
                                        <td style="font-size:12px;white-space:nowrap">
                                            {{ $hl->tanggal_mulai->format('d/m/Y') }}
                                            @if($hl->tanggal_selesai && $hl->tanggal_selesai->ne($hl->tanggal_mulai))
                                                – {{ $hl->tanggal_selesai->format('d/m/Y') }}
                                            @endif
                                        </td>
                                        <td style="font-weight:600;font-size:13px">{{ $hl->nama }}</td>
                                        <td><span class="badge badge-warning">{{ $jenisLiburLabels[$hl->jenis] ?? $hl->jenis }}</span></td>
                                        @if($canEdit ?? false)
                                            <td style="text-align:right">
                                                <form method="POST" action="{{ route('admin.pm.kalender.libur.destroy', $hl) }}"
                                                      onsubmit="return confirm('Hapus hari libur ini?');" style="display:inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-ghost" style="color:#D92D20"><i class="fas fa-trash"></i></button>
                                                </form>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
