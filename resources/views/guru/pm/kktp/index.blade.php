@extends('layouts.app')

@section('title', 'KKTP - Pembelajaran Mendalam')
@section('page_title', 'Kriteria Ketercapaian Tujuan Pembelajaran (KKTP)')
@section('page_subtitle', 'Interval 4 predikat ketuntasan & kriteria/rubrik ketercapaian per Tujuan Pembelajaran')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('header_actions')
    <a href="{{ route('guru.pm.kktp.excel', ['tingkat' => $tingkat, 'mapel' => $mapelAktif]) }}" class="btn btn-ghost btn-sm">
        <i class="fas fa-file-excel"></i> Export Excel
    </a>
    <a href="{{ route('guru.pm.kktp.pdf', ['tingkat' => $tingkat, 'mapel' => $mapelAktif]) }}" target="_blank" class="btn btn-ghost btn-sm">
        <i class="fas fa-print"></i> Cetak Mapel Ini
    </a>
    <a href="{{ route('guru.pm.kktp.pdf', ['tingkat' => $tingkat]) }}" target="_blank" class="btn btn-ghost btn-sm">
        <i class="fas fa-print"></i> Cetak Semua Mapel
    </a>
@endsection

@section('content')
    @include('guru.pm._nav')

    {{-- Pilihan Kelas & Mapel --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-body" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between">
            <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center">
                <span style="font-size:12px;font-weight:700;color:#667085;margin-right:4px">KELAS:</span>
                @foreach(($pmAllowedTingkat ?? \App\Support\SchoolLevel::allowedTingkatForUser(auth()->user())) as $t)
                    <a href="{{ route('guru.pm.kktp.index', ['tingkat' => $t, 'mapel' => $mapelAktif]) }}"
                       class="btn btn-sm {{ $tingkat === $t ? '' : 'btn-ghost' }}">Kelas {{ $t }}</a>
                @endforeach
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:6px">
                @foreach($mapelList as $m)
                    <a href="{{ route('guru.pm.kktp.index', ['tingkat' => $tingkat, 'mapel' => $m->nama]) }}"
                       class="btn btn-sm {{ $mapelAktif === $m->nama ? '' : 'btn-ghost' }}">
                        {{ $m->kode }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Interval Nilai 4 Predikat --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <strong>Interval Nilai KKTP — {{ $mapelAktif }} (Kelas {{ $tingkat }} · Fase {{ $fase }})</strong>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('guru.pm.kktp.interval') }}" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;margin-bottom:14px">
                @csrf
                <input type="hidden" name="nama_mapel" value="{{ $mapelAktif }}">
                <input type="hidden" name="tingkat" value="{{ $tingkat }}">
                <div>
                    <label for="fld-pm-kktp-batas-bawah" style="font-size:12px;font-weight:600;display:block">Batas Bawah (Layak)</label>
                    <input id="fld-pm-kktp-batas-bawah" type="number" name="batas_bawah" min="1" max="90" value="{{ $interval->batas_bawah }}"
                           style="width:110px;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                </div>
                <div>
                    <label for="fld-pm-kktp-nilai-kktp" style="font-size:12px;font-weight:600;display:block">Nilai KKTP (Cakap)</label>
                    <input id="fld-pm-kktp-nilai-kktp" type="number" name="nilai_kktp" min="2" max="95" value="{{ $interval->nilai_kktp }}"
                           style="width:110px;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                </div>
                <div>
                    <label for="fld-pm-kktp-batas-atas" style="font-size:12px;font-weight:600;display:block">Batas Atas (Mahir)</label>
                    <input id="fld-pm-kktp-batas-atas" type="number" name="batas_atas" min="3" max="99" value="{{ $interval->batas_atas }}"
                           style="width:110px;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                </div>
                <button type="submit" class="btn btn-sm"><i class="fas fa-save"></i> Simpan Interval</button>
            </form>

            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:120px">Interval</th>
                            <th style="width:170px">Predikat</th>
                            <th>Keterangan / Tindak Lanjut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rentangPredikat as $r)
                            <tr>
                                <td style="font-weight:700">{{ $r['interval'] }}</td>
                                <td><span class="badge {{ $r['warna'] }}">{{ $r['predikat'] }}</span></td>
                                <td style="font-size:13px">{{ $r['keterangan'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- KKTP per TP --}}
    <div class="card">
        <div class="card-header"><strong>Kriteria Ketercapaian per Tujuan Pembelajaran</strong></div>
        @if($tpList->isEmpty())
            <div class="card-body" style="text-align:center;color:#667085;padding:32px">
                Belum ada TP untuk <strong>{{ $mapelAktif }}</strong> Fase {{ $fase }}.
                <div style="margin-top:10px">
                    <a href="{{ route('guru.pm.tp.index', ['mapel' => $mapelAktif, 'fase' => $fase]) }}" class="btn btn-sm">
                        <i class="fas fa-plus"></i> Tambah TP Terlebih Dahulu
                    </a>
                </div>
            </div>
        @else
            <form method="POST" action="{{ route('guru.pm.kktp.item') }}">
                @csrf
                <div class="card-body" style="padding:0">
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="min-width:240px">Tujuan Pembelajaran</th>
                                    <th style="width:170px">Pendekatan</th>
                                    <th style="min-width:240px">Kriteria Ketercapaian (satu per baris)</th>
                                    <th style="min-width:220px">Rubrik (untuk pendekatan rubrik)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tpList as $tp)
                                    @php $item = $tp->kktpItem; @endphp
                                    <tr>
                                        <td style="font-size:13px;line-height:1.4">
                                            @if($tp->kode_tp)<code>{{ $tp->kode_tp }}</code> @endif
                                            <strong>{{ $tp->elemen }}</strong>
                                            <div style="margin-top:3px;color:#344054">{{ $tp->deskripsi }}</div>
                                        </td>
                                        <td>
                                            <select name="items[{{ $tp->id }}][pendekatan]"
                                                    style="width:100%;padding:6px;border:1px solid #D0D5DD;border-radius:6px;font-size:13px">
                                                @foreach($pendekatanLabels as $kode => $label)
                                                    <option value="{{ $kode }}" {{ ($item?->pendekatan ?? 'deskripsi') === $kode ? 'selected' : '' }}>
                                                        {{ $label }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <textarea name="items[{{ $tp->id }}][kriteria]" rows="3"
                                                      placeholder="Tulis kriteria ketercapaian (1 baris = 1 kriteria)..."
                                                      style="width:100%;padding:6px 8px;border:1px solid #D0D5DD;border-radius:6px;font-size:12px">{{ $item?->kriteria }}</textarea>
                                        </td>
                                        <td>
                                            <textarea name="items[{{ $tp->id }}][rubrik]" rows="3"
                                                      placeholder="Opsional: uraian level Baru Berkembang s.d. Mahir..."
                                                      style="width:100%;padding:6px 8px;border:1px solid #D0D5DD;border-radius:6px;font-size:12px">{{ $item?->rubrik }}</textarea>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-body" style="border-top:1px solid #EAECF0;display:flex;justify-content:flex-end">
                    <button type="submit" class="btn"><i class="fas fa-save"></i> Simpan KKTP per TP</button>
                </div>
            </form>
        @endif
    </div>
@endsection
