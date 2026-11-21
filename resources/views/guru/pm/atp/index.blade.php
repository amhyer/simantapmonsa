@extends('layouts.app')

@section('title', 'Alur Tujuan Pembelajaran (ATP) - Pembelajaran Mendalam')
@section('page_title', 'Alur Tujuan Pembelajaran (ATP)')
@section('page_subtitle', 'Urutkan TP dalam satu fase, tetapkan Kelas, Semester, Materi Pokok, dan Alokasi JP untuk Program Tahunan')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('header_actions')
    <a href="{{ route('guru.pm.atp.pdf', ['mapel' => $mapelAktif, 'fase' => $faseAktif]) }}" target="_blank" class="btn btn-ghost btn-sm">
        <i class="fas fa-print"></i> Cetak ATP Mapel Ini
    </a>
    <a href="{{ route('guru.pm.atp.pdf', ['fase' => $faseAktif]) }}" target="_blank" class="btn btn-ghost btn-sm">
        <i class="fas fa-print"></i> Semua Mapel Fase {{ $faseAktif }}
    </a>
@endsection

@section('content')
    @include('guru.pm._nav')

    {{-- Filter Mapel & Fase + Tombol AI --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-body" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between">
            <form method="GET" action="{{ route('guru.pm.atp.index') }}" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
                <select name="mapel" onchange="this.form.submit()" style="padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                    @foreach($mapelList as $m)
                        <option value="{{ $m->nama }}" {{ $mapelAktif === $m->nama ? 'selected' : '' }}>{{ $m->nama }}</option>
                    @endforeach
                </select>
                <select name="fase" onchange="this.form.submit()" style="padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                    @foreach($faseLabels as $kode => $label)
                        <option value="{{ $kode }}" {{ $faseAktif === $kode ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-sm btn-ghost">Tampilkan</button>
            </form>

            <form method="POST" action="{{ route('guru.pm.atp.ai') }}" style="display:inline">
                @csrf
                <input type="hidden" name="nama_mapel" value="{{ $mapelAktif }}">
                <input type="hidden" name="fase" value="{{ $faseAktif }}">
                <button type="submit" class="btn btn-sm" {{ (!$aiQuota['configured'] || $tpList->isEmpty()) ? 'disabled' : '' }}>
                    <i class="fas fa-magic"></i> Susun Alur dengan AI
                </button>
            </form>
        </div>
    </div>

    {{-- Ringkasan TP & JP per Kelas/Semester --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px;margin-bottom:16px">
        @foreach($ringkasan as $r)
            <div class="card">
                <div class="card-body" style="padding:12px">
                    <div style="font-size:12px;color:#667085;font-weight:600">Kelas {{ $r['kelas'] }} · Semester {{ $r['semester'] }}</div>
                    <div style="font-size:18px;font-weight:700;color:#101828;margin-top:4px">
                        {{ $r['jumlah_tp'] }} TP · {{ $r['total_jp'] }} JP
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Panel Acuan CP (Collapsible) --}}
    @if($cpAcuan->isNotEmpty())
        <details class="card" style="margin-bottom:16px">
            <summary class="card-header" style="cursor:pointer;font-weight:600">
                <i class="fas fa-book-open"></i> Capaian Pembelajaran — Acuan ({{ $cpAcuan->count() }} elemen)
            </summary>
            <div class="card-body" style="display:grid;gap:8px">
                @foreach($cpAcuan as $cp)
                    <div style="font-size:13px;line-height:1.5;border-left:3px solid #2E90FA;padding-left:10px">
                        <strong>{{ $cp->elemen }}:</strong> {{ $cp->deskripsi }}
                    </div>
                @endforeach
            </div>
        </details>
    @endif

    {{-- Tabel Editor ATP --}}
    <div class="card">
        <div class="card-header">
            <strong>Editor Alur Tujuan Pembelajaran — {{ $mapelAktif }} (Fase {{ $faseAktif }})</strong>
        </div>
        @if($tpList->isEmpty())
            <div class="card-body" style="text-align:center;color:#667085;padding:32px">
                Belum ada Tujuan Pembelajaran untuk <strong>{{ $mapelAktif }}</strong> Fase {{ $faseAktif }}.
                <div style="margin-top:10px">
                    <a href="{{ route('guru.pm.tp.index', ['mapel' => $mapelAktif, 'fase' => $faseAktif]) }}" class="btn btn-sm">
                        <i class="fas fa-plus"></i> Tambah TP Terlebih Dahulu
                    </a>
                </div>
            </div>
        @else
            <form method="POST" action="{{ route('guru.pm.atp.simpan') }}">
                @csrf
                <div class="card-body" style="padding:0">
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width:70px">Urutan</th>
                                    <th>Tujuan Pembelajaran</th>
                                    <th style="width:110px">Kelas</th>
                                    <th style="width:130px">Semester</th>
                                    <th style="width:210px">Materi Pokok</th>
                                    <th style="width:85px">JP</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tpList as $tp)
                                    <tr>
                                        <td>
                                            <input type="number" min="1" name="items[{{ $tp->id }}][urutan]" value="{{ $tp->urutan }}"
                                                   style="width:56px;padding:5px;border:1px solid #D0D5DD;border-radius:6px;text-align:center">
                                        </td>
                                        <td style="font-size:13px;line-height:1.4">
                                            <div>
                                                @if($tp->kode_tp)<code style="margin-right:4px">{{ $tp->kode_tp }}</code>@endif
                                                <strong>{{ $tp->elemen }}</strong>
                                            </div>
                                            <div style="color:#344054;margin-top:2px">{{ $tp->deskripsi }}</div>
                                        </td>
                                        <td>
                                            <select name="items[{{ $tp->id }}][kelas_target]" style="width:100%;padding:6px;border:1px solid #D0D5DD;border-radius:6px;font-size:13px">
                                                <option value="">—</option>
                                                @foreach($kelasFase as $k)
                                                    <option value="{{ $k }}" {{ (int) $tp->kelas_target === $k ? 'selected' : '' }}>Kelas {{ $k }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <select name="items[{{ $tp->id }}][semester_target]" style="width:100%;padding:6px;border:1px solid #D0D5DD;border-radius:6px;font-size:13px">
                                                <option value="">—</option>
                                                <option value="1" {{ (int) $tp->semester_target === 1 ? 'selected' : '' }}>1 (Ganjil)</option>
                                                <option value="2" {{ (int) $tp->semester_target === 2 ? 'selected' : '' }}>2 (Genap)</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="items[{{ $tp->id }}][materi_pokok]" value="{{ $tp->materi_pokok }}"
                                                   placeholder="Topik utama..."
                                                   style="width:100%;padding:6px 8px;border:1px solid #D0D5DD;border-radius:6px;font-size:13px">
                                        </td>
                                        <td>
                                            <input type="number" min="0" max="100" name="items[{{ $tp->id }}][alokasi_jp]" value="{{ $tp->alokasi_jp }}"
                                                   style="width:68px;padding:6px;border:1px solid #D0D5DD;border-radius:6px;text-align:center;font-size:13px">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-body" style="border-top:1px solid #EAECF0;display:flex;justify-content:flex-end">
                    <button type="submit" class="btn"><i class="fas fa-save"></i> Simpan Alur ATP</button>
                </div>
            </form>
        @endif
    </div>
@endsection
