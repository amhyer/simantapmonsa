@extends('layouts.app')

@section('title', 'Rekap Capaian & Deskripsi Rapor - SIMANTAP')

@section('sidebar')
    @include('siswa.partials.sidebar')
@endsection

@section('page_title', 'Rekap Capaian & Deskripsi Rapor')
@section('page_subtitle', 'Rincian nilai mata pelajaran, capaian kompetensi tertinggi & terendah, serta projek P5')

@section('header_actions')
    <a href="{{ route('siswa.rapor.unduh') }}" class="btn btn-primary btn-sm" style="background:var(--violet,#6D28D9);border-color:var(--violet,#6D28D9)">
        <i class="fas fa-file-download"></i> Download Rapor
    </a>
@endsection

@section('content')
    @if(!$siswa)
        <div class="note note-warn">
            <i class="fas fa-exclamation-triangle"></i> Akun Anda belum terhubung dengan data siswa. Hubungi guru atau admin sekolah.
        </div>
    @else
        <div class="card" style="margin-bottom:16px">
            <div class="card-body">
                <form method="GET" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
                    <div>
                        <label for="fld-rapor-capaian-semester" class="form-label">Semester</label>
                        <select id="fld-rapor-capaian-semester" name="semester" class="form-control">
                            <option value="Ganjil" @selected($semester === 'Ganjil')>Ganjil</option>
                            <option value="Genap" @selected($semester === 'Genap')>Genap</option>
                        </select>
                    </div>
                    <div>
                        <label for="fld-rapor-capaian-tahun-ajaran" class="form-label">Tahun Ajaran</label>
                        <input id="fld-rapor-capaian-tahun-ajaran" type="text" name="tahun_ajaran" value="{{ $tahunAjaran }}" maxlength="9" class="form-control" style="width:120px">
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                    <a href="{{ route('siswa.rapor.capaian') }}" class="btn btn-sm btn-ghost">Semua</a>
                </form>
            </div>
        </div>

        {{-- Tabel Capaian Kompetensi Intrakurikuler --}}
        <div class="card" style="margin-bottom:20px">
            <div class="card-header">
                <h3><i class="fas fa-book-open" style="margin-right:8px;color:var(--violet)"></i>Nilai Rapor & Deskripsi Capaian Kompetensi</h3>
                <span class="tag tag-violet">{{ $nilaiRapor->count() }} mata pelajaran</span>
            </div>
            <div class="card-body tight">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:44px">No</th>
                                <th style="width:200px">Mata Pelajaran</th>
                                <th style="width:90px;text-align:center">Nilai Akhir</th>
                                <th style="width:80px;text-align:center">Predikat</th>
                                <th>Capaian Kompetensi (Tertinggi & Terendah)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($nilaiRapor as $i => $n)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>
                                        <strong>{{ $n->mata_pelajaran }}</strong>
                                        <div style="font-size:11px;color:var(--muted)">{{ $n->semester }} {{ $n->tahun_ajaran }}</div>
                                    </td>
                                    <td style="text-align:center;font-weight:800;font-size:15px;color:var(--violet)">
                                        {{ $n->nilai_akhir !== null ? number_format((float) $n->nilai_akhir, 0) : '-' }}
                                    </td>
                                    <td style="text-align:center">
                                        <span class="tag {{ in_array($n->predikat, ['A', 'B']) ? 'tag-violet' : ($n->predikat === 'C' ? 'tag-gold' : 'tag-bad') }}">
                                            {{ $n->predikat ?? '-' }}
                                        </span>
                                    </td>
                                    <td style="font-size:13px;line-height:1.5">
                                        <div>{{ $n->deskripsi_capaian ?: $n->generateDeskripsi() }}</div>
                                        @if($n->capaian_tertinggi || $n->capaian_terendah)
                                            <div style="margin-top:6px;display:flex;gap:8px;flex-wrap:wrap;font-size:11.5px">
                                                @if($n->capaian_tertinggi)
                                                    <span style="background:#ECFDF5;color:#065F46;padding:2px 8px;border-radius:4px">
                                                        <b>Optimal:</b> {{ $n->capaian_tertinggi }}
                                                    </span>
                                                @endif
                                                @if($n->capaian_terendah)
                                                    <span style="background:#FEF3C7;color:#92400E;padding:2px 8px;border-radius:4px">
                                                        <b>Perlu Bimbingan:</b> {{ $n->capaian_terendah }}
                                                    </span>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr class="table-empty">
                                    <td colspan="5" style="padding:22px;text-align:center;color:var(--muted)">
                                        Belum ada nilai rapor akhir semester yang diterbitkan untuk periode ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Tabel Capaian Projek P5 (Kokurikuler) --}}
        <div class="card" style="margin-bottom:20px">
            <div class="card-header">
                <h3><i class="fas fa-seedling" style="margin-right:8px;color:var(--ok)"></i>Capaian Projek Penguatan Profil Pelajar Pancasila (P5)</h3>
                <span class="tag tag-ok">{{ $nilaiP5->count() }} dimensi</span>
            </div>
            <div class="card-body tight">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:44px">No</th>
                                <th style="width:200px">Tema Projek</th>
                                <th style="width:220px">Dimensi Profil Pelajar</th>
                                <th style="width:120px;text-align:center">Capaian</th>
                                <th>Catatan Proses</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($nilaiP5 as $i => $p)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td><strong>{{ $p->tema }}</strong></td>
                                    <td>{{ $p->dimensi }}</td>
                                    <td style="text-align:center">
                                        <span class="tag {{ in_array($p->predikat, ['SB', 'BSH']) ? 'tag-ok' : 'tag-gold' }}">{{ $p->predikat }}</span>
                                    </td>
                                    <td>{{ $p->catatan ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr class="table-empty">
                                    <td colspan="5" style="padding:20px;text-align:center;color:var(--muted)">Belum ada data penilaian projek P5.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Info Ekstrakurikuler & Kenaikan Kelas --}}
        <div class="grid grid-2">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-running" style="margin-right:8px;color:var(--gold)"></i>Kegiatan Ekstrakurikuler</h3>
                </div>
                <div class="card-body">
                    @if($ekskul && !empty($ekskul['nama']))
                        <div style="font-size:15px;font-weight:700">{{ $ekskul['nama'] }}</div>
                        <div style="margin-top:6px">Predikat: <span class="tag tag-ok">{{ $ekskul['predikat'] ?? 'Baik' }}</span></div>
                        <div style="margin-top:8px;font-size:13px;color:var(--muted)">{{ $ekskul['keterangan'] ?? '-' }}</div>
                    @else
                        <div style="color:var(--muted);font-size:13px">Belum ada catatan kegiatan ekstrakurikuler pada semester ini.</div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-graduation-cap" style="margin-right:8px;color:var(--violet)"></i>Keputusan Akhir Tahun / Kenaikan Kelas</h3>
                </div>
                <div class="card-body">
                    @if($naikKelas)
                        <div style="font-size:15px;font-weight:800;color:{{ $naikKelas->naik_kelas ? 'var(--ok)' : 'var(--bad)' }}">
                            {{ $naikKelas->naik_kelas ? 'NAIK KELAS / LULUS' : 'TINGGAL DI KELAS' }}
                        </div>
                        <div style="margin-top:6px;font-size:13px;color:var(--muted)">
                            {{ $naikKelas->catatan ?: 'Keputusan ditetapkan oleh Dewan Guru dan Wali Kelas.' }}
                        </div>
                    @else
                        <div style="color:var(--muted);font-size:13px">Keputusan kenaikan kelas ditampilkan pada akhir semester genap.</div>
                    @endif
                </div>
            </div>
        </div>
    @endif
@endsection
