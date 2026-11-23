@extends('layouts.app')

@section('title', 'Program Semester (Prosem Matriks) - Pembelajaran Mendalam')
@section('page_title', 'Program Semester (Prosem Matriks)')
@section('page_subtitle', 'Distribusi mingguan otomatis dari Program Tahunan dengan penanda minggu libur/tidak efektif Kalender Pendidikan')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('header_actions')
    <a href="{{ route('guru.pm.prosem.pdf', ['tahun_ajaran' => $tahunAjaran, 'tingkat' => $tingkat, 'semester' => $semester, 'mapel' => $mapelAktif]) }}"
       target="_blank" class="btn btn-ghost btn-sm">
        <i class="fas fa-print"></i> Cetak Mapel Ini
    </a>
    <a href="{{ route('guru.pm.prosem.pdf', ['tahun_ajaran' => $tahunAjaran, 'tingkat' => $tingkat, 'semester' => $semester]) }}"
       target="_blank" class="btn btn-ghost btn-sm">
        <i class="fas fa-print"></i> Cetak Semua Mapel
    </a>
@endsection

@section('content')
    @include('guru.pm._nav')

    {{-- Pilihan Kelas, Semester & Mapel --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-body" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between">
            <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center">
                @foreach($pmAllowedTingkat ?? [1,2,3,4,5,6] as $t)
                    <a href="{{ route('guru.pm.prosem.index', ['tahun_ajaran' => $tahunAjaran, 'tingkat' => $t, 'semester' => $semester, 'mapel' => $mapelAktif]) }}"
                       class="btn btn-sm {{ $tingkat === $t ? '' : 'btn-ghost' }}">Kls {{ $t }}</a>
                @endforeach
                <span style="margin:0 4px;color:#D0D5DD">|</span>
                <a href="{{ route('guru.pm.prosem.index', ['tahun_ajaran' => $tahunAjaran, 'tingkat' => $tingkat, 'semester' => 1, 'mapel' => $mapelAktif]) }}"
                   class="btn btn-sm {{ $semester === 1 ? '' : 'btn-ghost' }}">Semester 1</a>
                <a href="{{ route('guru.pm.prosem.index', ['tahun_ajaran' => $tahunAjaran, 'tingkat' => $tingkat, 'semester' => 2, 'mapel' => $mapelAktif]) }}"
                   class="btn btn-sm {{ $semester === 2 ? '' : 'btn-ghost' }}">Semester 2</a>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:6px">
                @foreach($mapelList as $m)
                    <a href="{{ route('guru.pm.prosem.index', ['tahun_ajaran' => $tahunAjaran, 'tingkat' => $tingkat, 'semester' => $semester, 'mapel' => $m->nama]) }}"
                       class="btn btn-sm {{ $mapelAktif === $m->nama ? '' : 'btn-ghost' }}">{{ $m->kode }}</a>
                @endforeach
            </div>
        </div>
    </div>

    {{-- KPI & Generator Otomatis dari Prota --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin-bottom:16px">
        <div class="card">
            <div class="card-body" style="padding:12px">
                <div style="font-size:12px;color:#667085">Minggu Efektif Sem {{ $semester }}</div>
                <div style="font-size:20px;font-weight:700;color:#101828">{{ $ringkasan['total_efektif'] }} Minggu</div>
            </div>
        </div>
        <div class="card">
            <div class="card-body" style="padding:12px">
                <div style="font-size:12px;color:#667085">JP Tersedia ({{ $jpPerMinggu }} JP/mgg)</div>
                <div style="font-size:20px;font-weight:700;color:#101828">{{ $ringkasan['jp_tersedia'] }} JP</div>
            </div>
        </div>
        <div class="card">
            <div class="card-body" style="padding:12px">
                <div style="font-size:12px;color:#667085">JP Direncanakan di Prosem</div>
                <div style="font-size:20px;font-weight:700;color:#027A48">{{ $totalJpProsem }} JP</div>
            </div>
        </div>
        <div class="card">
            <div class="card-body" style="padding:12px">
                <div style="font-size:12px;color:#667085;margin-bottom:6px">Susun Otomatis dari Prota ({{ $jumlahProtaSem }} materi)</div>
                <form method="POST" action="{{ route('guru.pm.prosem.susun') }}" style="display:flex;gap:6px;align-items:center">
                    @csrf
                    <input type="hidden" name="tahun_ajaran" value="{{ $tahunAjaran }}">
                    <input type="hidden" name="tingkat" value="{{ $tingkat }}">
                    <input type="hidden" name="nama_mapel" value="{{ $mapelAktif }}">
                    <input type="hidden" name="semester" value="{{ $semester }}">
                    <input type="number" name="jp_per_minggu" min="1" max="20" value="{{ $jpPerMinggu }}"
                           title="JP per minggu"
                           style="width:58px;padding:5px;border:1px solid #D0D5DD;border-radius:6px;text-align:center">
                    <button type="submit" class="btn btn-sm" {{ $jumlahProtaSem === 0 ? 'disabled' : '' }}>
                        <i class="fas fa-magic"></i> Susun Prosem
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Matriks Program Semester --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <strong>Matriks Program Semester {{ $semester }} — {{ $mapelAktif }} Kelas {{ $tingkat }} ({{ $tahunAjaran }})</strong>
        </div>
        <div class="card-body" style="padding:0">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th rowspan="2" style="width:40px;vertical-align:middle">NO</th>
                            <th rowspan="2" style="width:72px;vertical-align:middle">BAB</th>
                            <th rowspan="2" style="width:76px;vertical-align:middle">NO. ATP</th>
                            <th rowspan="2" style="min-width:210px;vertical-align:middle">Materi / Tujuan Pembelajaran</th>
                            <th rowspan="2" style="width:55px;text-align:center;vertical-align:middle">JML</th>
                            @foreach($ringkasan['rows'] as $kal)
                                <th colspan="{{ $kal->jumlah_minggu }}" style="text-align:center;border-left:1px solid #EAECF0;font-size:11px">
                                    {{ strtoupper($kal->namaBulan()) }} {{ $kal->tahun }}
                                </th>
                            @endforeach
                        </tr>
                        <tr>
                            @foreach($ringkasan['rows'] as $kal)
                                @php $libur = is_array($kal->minggu_libur) ? array_map('intval', $kal->minggu_libur) : []; @endphp
                                @for($m = 1; $m <= $kal->jumlah_minggu; $m++)
                                    @php $isLibur = in_array($m, $libur, true); @endphp
                                    <th style="text-align:center;font-size:11px;padding:4px 6px;border-left:1px solid #EAECF0;{{ $isLibur ? 'background:#FEE4E2;color:var(--bad)' : '' }}"
                                        title="{{ $isLibur ? ($kal->keterangan ?: 'Minggu tidak efektif') : 'Minggu efektif' }}">
                                        {{ $m }}
                                    </th>
                                @endfor
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($matriksRows as $idx => $row)
                            <tr>
                                <td style="text-align:center">{{ $idx + 1 }}</td>
                                <td>{{ $row['bab'] }}</td>
                                <td><code>{{ $row['kode_tp'] }}</code></td>
                                <td style="font-weight:600;font-size:13px">{{ $row['materi'] }}</td>
                                <td style="text-align:center;font-weight:700">{{ $row['total_jp'] }}</td>
                                @foreach($ringkasan['rows'] as $kal)
                                    @php $libur = is_array($kal->minggu_libur) ? array_map('intval', $kal->minggu_libur) : []; @endphp
                                    @for($m = 1; $m <= $kal->jumlah_minggu; $m++)
                                        @php
                                            $isLibur = in_array($m, $libur, true);
                                            $val = $row['sel'][$kal->bulan.'_'.$m] ?? null;
                                        @endphp
                                        <td style="text-align:center;font-size:12px;border-left:1px solid #EAECF0;padding:4px;{{ $isLibur ? 'background:#FEF3F2;color:var(--bad)' : ($val ? 'background:#ECFDF3;color:#027A48;font-weight:700' : '') }}">
                                            {{ $isLibur ? '×' : ($val ?? '') }}
                                        </td>
                                    @endfor
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="40" style="text-align:center;padding:28px;color:#667085">
                                    Belum ada distribusi Program Semester. Klik <strong>Susun Prosem</strong> di atas untuk mengisi otomatis dari Prota, atau tambahkan baris manual di bawah.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Tambah Baris Manual & Daftar Baris --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px">
        <div class="card">
            <div class="card-header"><strong><i class="fas fa-plus-circle"></i> Tambah Baris Prosem Manual</strong></div>
            <div class="card-body">
                <form method="POST" action="{{ route('guru.pm.prosem.store') }}" style="display:grid;gap:10px">
                    @csrf
                    <input type="hidden" name="tahun_ajaran" value="{{ $tahunAjaran }}">
                    <input type="hidden" name="tingkat" value="{{ $tingkat }}">
                    <input type="hidden" name="nama_mapel" value="{{ $mapelAktif }}">
                    <input type="hidden" name="semester" value="{{ $semester }}">
                    <div style="display:grid;grid-template-columns:100px 1fr;gap:8px">
                        <div>
                            <label for="fld-pm-prosem-bab" style="font-size:12px;font-weight:600">Bab</label>
                            <input id="fld-pm-prosem-bab" type="text" name="bab" placeholder="Bab 1"
                                   style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                        <div>
                            <label for="fld-pm-prosem-materi" style="font-size:12px;font-weight:600">Materi / Topik *</label>
                            <input id="fld-pm-prosem-materi" type="text" name="materi" required placeholder="Nama topik pertemuan..."
                                   style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 95px 85px;gap:8px">
                        <div>
                            <label for="fld-pm-prosem-bulan" style="font-size:12px;font-weight:600">Bulan *</label>
                            <select id="fld-pm-prosem-bulan" name="bulan" required style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                                @foreach($ringkasan['rows'] as $kal)
                                    <option value="{{ $kal->bulan }}">{{ $kal->namaBulan() }} {{ $kal->tahun }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="fld-pm-prosem-minggu-ke" style="font-size:12px;font-weight:600">Minggu Ke *</label>
                            <input id="fld-pm-prosem-minggu-ke" type="number" name="minggu_ke" min="1" max="5" value="1" required
                                   style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                        <div>
                            <label for="fld-pm-prosem-alokasi-jp" style="font-size:12px;font-weight:600">JP *</label>
                            <input id="fld-pm-prosem-alokasi-jp" type="number" name="alokasi_jp" min="1" max="40" value="{{ $jpPerMinggu }}" required
                                   style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-sm"><i class="fas fa-save"></i> Tambah Baris</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Rincian Slot Mingguan ({{ $prosemRows->count() }} baris)</strong></div>
            <div class="card-body" style="padding:0">
                @if($prosemRows->isEmpty())
                    <div style="padding:24px;text-align:center;color:#667085;font-size:13px">Belum ada baris mingguan.</div>
                @else
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Bulan / Mgg</th>
                                    <th>Materi</th>
                                    <th style="width:55px;text-align:center">JP</th>
                                    <th style="width:55px;text-align:right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($prosemRows as $ps)
                                    <tr>
                                        <td style="font-size:12px;white-space:nowrap">{{ $bulanNama[$ps->bulan] ?? $ps->bulan }} · M{{ $ps->minggu_ke }}</td>
                                        <td style="font-size:13px">{{ $ps->bab ? $ps->bab.': ' : '' }}{{ $ps->materi }}</td>
                                        <td style="text-align:center;font-weight:600">{{ $ps->alokasi_jp }}</td>
                                        <td style="text-align:right">
                                            <form method="POST" action="{{ route('guru.pm.prosem.destroy', $ps) }}"
                                                  onsubmit="return confirm('Hapus slot ini?');" style="display:inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-ghost" style="color:var(--bad)" aria-label="Hapus"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
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
