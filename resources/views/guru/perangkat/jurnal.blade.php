@extends('layouts.app')

@section('title', 'Jurnal Mengajar Harian - SIMANTAP')
@section('page_title', 'Jurnal Mengajar Harian')
@section('page_subtitle', 'Pencatatan pelaksanaan pembelajaran, kehadiran kelas, tindak lanjut & cetak rekap PDF')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('header_actions')
    <a href="{{ route('guru.perangkat.index') }}" class="btn btn-ghost btn-sm">← Perangkat Ajar</a>
    <a href="{{ route('guru.perangkat.jurnal.pdf', ['dari' => $dari, 'sampai' => $sampai]) }}" target="_blank" class="btn btn-sm">
        <i class="fas fa-file-pdf"></i> Cetak Rekap PDF
    </a>
@endsection

@section('content')
    @if(session('success'))
        <div class="alert alert-ok" style="margin-bottom:14px">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-bad" style="margin-bottom:14px">{{ $errors->first() }}</div>
    @endif

    {{-- Form Catat Jurnal Baru --}}
    <div class="card" style="margin-bottom:18px">
        <div class="card-header"><strong>Catat Jurnal Mengajar Baru</strong></div>
        <div class="card-body">
            <form method="POST" action="{{ route('guru.perangkat.jurnal.store') }}">
                @csrf
                <div class="grid grid-4" style="gap:12px;margin-bottom:12px">
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Tanggal *</label>
                        <input type="date" name="tanggal" value="{{ old('tanggal', today()->format('Y-m-d')) }}" required
                               style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Jam Ke-</label>
                        <input type="text" name="jam_ke" value="{{ old('jam_ke') }}" placeholder="Mis. 1-2"
                               style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Rombel</label>
                        <select name="rombel_id" style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                            <option value="">— Pilih Rombel —</option>
                            @foreach($rombelList as $r)
                                <option value="{{ $r->id }}" {{ (string) old('rombel_id') === (string) $r->id ? 'selected' : '' }}>{{ $r->nama_rombel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Label Kelas</label>
                        <input type="text" name="kelas" value="{{ old('kelas') }}" placeholder="Mis. 6A"
                               style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                </div>

                <div class="grid grid-3" style="gap:12px;margin-bottom:12px">
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Mata Pelajaran *</label>
                        <input type="text" name="mata_pelajaran" value="{{ old('mata_pelajaran') }}" required placeholder="Contoh: Matematika"
                               style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Topik / Materi Pokok *</label>
                        <input type="text" name="topik" value="{{ old('topik') }}" required placeholder="Contoh: Operasi Hitung Pecahan"
                               style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Rujukan Perangkat Ajar</label>
                        <select name="perangkat_id" style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                            <option value="">— Opsional —</option>
                            @foreach($perangkatOptions as $p)
                                <option value="{{ $p->id }}">[{{ strtoupper($p->jenis) }}] {{ $p->judul }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-3" style="gap:12px;margin-bottom:12px">
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Pertemuan Ke-</label>
                        <input type="number" min="1" name="pertemuan_ke" value="{{ old('pertemuan_ke') }}"
                               style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Siswa Hadir</label>
                        <input type="number" min="0" name="jumlah_hadir" value="{{ old('jumlah_hadir') }}"
                               style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Tidak Hadir</label>
                        <input type="number" min="0" name="jumlah_tidak_hadir" value="{{ old('jumlah_tidak_hadir', 0) }}"
                               style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                </div>

                <div style="margin-bottom:12px">
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Ringkasan Kegiatan Pembelajaran *</label>
                    <textarea name="kegiatan" rows="3" required placeholder="Uraikan pelaksanaan pembelajaran hari ini..."
                              style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">{{ old('kegiatan') }}</textarea>
                </div>

                <div class="grid grid-2" style="gap:12px;margin-bottom:14px">
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Catatan / Kendala Kelas</label>
                        <input type="text" name="catatan" value="{{ old('catatan') }}" style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Tindak Lanjut / Remedial</label>
                        <input type="text" name="tindak_lanjut" value="{{ old('tindak_lanjut') }}" style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                </div>

                <button type="submit" class="btn"><i class="fas fa-save"></i> Simpan Jurnal</button>
            </form>
        </div>
    </div>

    {{-- Filter & Daftar Jurnal --}}
    <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
            <strong>Riwayat Jurnal Mengajar</strong>
            <form method="GET" action="{{ route('guru.perangkat.jurnal.index') }}" style="display:flex;flex-wrap:wrap;gap:6px;align-items:center">
                <input type="date" name="dari" value="{{ $dari }}" style="padding:6px 8px;border:1px solid #E4E7EC;border-radius:6px;font-size:12px">
                <span style="font-size:12px;color:#667085">s/d</span>
                <input type="date" name="sampai" value="{{ $sampai }}" style="padding:6px 8px;border:1px solid #E4E7EC;border-radius:6px;font-size:12px">
                <input type="text" name="kelas" value="{{ $kelasFilter }}" placeholder="Kelas..." style="padding:6px 8px;border:1px solid #E4E7EC;border-radius:6px;font-size:12px;width:90px">
                <button type="submit" class="btn btn-ghost btn-sm">Filter</button>
            </form>
        </div>
        <div class="card-body" style="padding:0">
            @if($jurnalList->isEmpty())
                <div style="padding:28px;text-align:center;color:#667085;font-size:13px">Belum ada catatan jurnal mengajar.</div>
            @else
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tanggal &amp; Jam</th>
                                <th>Kelas / Mapel</th>
                                <th>Topik &amp; Kegiatan</th>
                                <th>Kehadiran</th>
                                <th style="text-align:right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($jurnalList as $j)
                                <tr>
                                    <td style="white-space:nowrap">
                                        <strong>{{ $j->tanggal->format('d/m/Y') }}</strong>
                                        @if($j->jam_ke)
                                            <div style="font-size:11px;color:#667085">Jam ke-{{ $j->jam_ke }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div><strong>{{ $j->rombel?->nama_rombel ?? $j->kelas }}</strong></div>
                                        <small style="color:#667085">{{ $j->mata_pelajaran }}</small>
                                    </td>
                                    <td>
                                        <div><strong>{{ $j->topik }}</strong></div>
                                        <div style="font-size:12px;color:#475467">{{ \Illuminate\Support\Str::limit($j->kegiatan, 120) }}</div>
                                        @if($j->tindak_lanjut)
                                            <div style="font-size:11px;color:#0F766E">Tindak lanjut: {{ $j->tindak_lanjut }}</div>
                                        @endif
                                    </td>
                                    <td style="white-space:nowrap">
                                        @if($j->jumlah_hadir !== null)
                                            Hadir: {{ $j->jumlah_hadir }}<br>
                                            <small style="color:#667085">Absen: {{ $j->jumlah_tidak_hadir ?? 0 }}</small>
                                        @else
                                            <span style="color:#98A2B3">-</span>
                                        @endif
                                    </td>
                                    <td style="text-align:right">
                                        <form method="POST" action="{{ route('guru.perangkat.jurnal.destroy', $j) }}" onsubmit="return confirm('Hapus jurnal ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-ghost btn-sm" style="color:#D92D20"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($jurnalList->hasPages())
                    <div style="padding:12px 16px">{{ $jurnalList->links() }}</div>
                @endif
            @endif
        </div>
    </div>
@endsection
