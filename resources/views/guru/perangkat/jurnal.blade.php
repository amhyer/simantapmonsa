@extends('layouts.app')

@section('title', 'Jurnal Mengajar Harian - SIMANTAP')
@section('page_title', 'Jurnal Mengajar Harian')
@section('page_subtitle', 'Pencatatan pelaksanaan pembelajaran, kehadiran kelas, tindak lanjut & cetak rekap PDF')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('header_actions')
    <a href="{{ route('guru.pm.generate.index') }}" class="btn btn-ghost btn-sm">
        <i class="fas fa-magic"></i> Isi Jurnal dengan AI
    </a>
    <a href="{{ route('guru.perangkat.index') }}" class="btn btn-ghost btn-sm">← Modul Ajar / RPM</a>
    <a href="{{ route('guru.perangkat.jurnal.pdf', ['dari' => $dari, 'sampai' => $sampai]) }}" target="_blank" class="btn btn-sm">
        <i class="fas fa-file-pdf"></i> Cetak Rekap PDF
    </a>
@endsection

@section('content')
    @include('guru.pm._nav')

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

                <div class="grid grid-3" style="gap:12px;margin-bottom:12px">
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Tujuan Pembelajaran (Bank TP)</label>
                        <select name="tp_id" style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px;font-size:13px">
                            <option value="">— Opsional: Pilih dari Bank TP —</option>
                            @foreach($tpOptions ?? [] as $tp)
                                <option value="{{ $tp->id }}">
                                    [{{ $tp->nama_mapel }}] {{ $tp->kode_tp ? $tp->kode_tp.' — ' : '' }}{{ \Illuminate\Support\Str::limit($tp->deskripsi, 60) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Praktik Pedagogis</label>
                        <select name="praktik_pedagogis" style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px;font-size:13px">
                            <option value="">— Pilih Praktik Pedagogis —</option>
                            @foreach($modelList ?? [] as $mdl)
                                <option value="{{ $mdl }}" {{ old('praktik_pedagogis') === $mdl ? 'selected' : '' }}>{{ $mdl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Asesmen</label>
                        <input type="text" name="asesmen" value="{{ old('asesmen') }}" placeholder="Mis. Observasi, LKPD, kuis singkat"
                               style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                </div>

                <div class="grid grid-2" style="gap:12px;margin-bottom:12px;padding:10px 12px;background:#F9FAFB;border:1px solid #EAECF0;border-radius:8px">
                    <div>
                        <label style="font-weight:600;font-size:12px;display:block;margin-bottom:6px;color:#344054">Prinsip Pembelajaran Mendalam</label>
                        <div style="display:flex;flex-wrap:wrap;gap:10px;font-size:13px">
                            @foreach(['Berkesadaran (Mindful)', 'Bermakna (Meaningful)', 'Menggembirakan (Joyful)'] as $prinsip)
                                <label style="display:flex;align-items:center;gap:5px">
                                    <input type="checkbox" name="prinsip_pm[]" value="{{ $prinsip }}"> {{ $prinsip }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:12px;display:block;margin-bottom:6px;color:#344054">Pengalaman Belajar</label>
                        <div style="display:flex;flex-wrap:wrap;gap:10px;font-size:13px">
                            @foreach(['Memahami', 'Mengaplikasi', 'Merefleksi'] as $peng)
                                <label style="display:flex;align-items:center;gap:5px">
                                    <input type="checkbox" name="pengalaman_belajar[]" value="{{ $peng }}"> {{ $peng }}
                                </label>
                            @endforeach
                        </div>
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
                                        @if($j->praktik_pedagogis || !empty($j->prinsip_pm) || !empty($j->pengalaman_belajar))
                                            <div style="display:flex;flex-wrap:wrap;gap:4px;margin:3px 0">
                                                @if($j->praktik_pedagogis)
                                                    <span class="badge badge-info" style="font-size:10px">{{ $j->praktik_pedagogis }}</span>
                                                @endif
                                                @foreach(is_array($j->pengalaman_belajar) ? $j->pengalaman_belajar : [] as $pb)
                                                    <span class="badge badge-success" style="font-size:10px">{{ $pb }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                        <div style="font-size:12px;color:#475467">{{ \Illuminate\Support\Str::limit($j->kegiatan, 120) }}</div>
                                        @if($j->asesmen)
                                            <div style="font-size:11px;color:#475467">Asesmen: {{ $j->asesmen }}</div>
                                        @endif
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
                                            <button type="submit" class="btn btn-ghost btn-sm" style="color:#D92D20" aria-label="Hapus"><i class="fas fa-trash"></i></button>
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
