@extends('layouts.app')
@section('title', 'Kelengkapan Nilai Akhir - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Matriks Kelengkapan Nilai Akhir')
@section('page_subtitle', 'Pantau progres pengisian nilai akhir per kelas dan kunci input rapor per rombel')

@section('content')
    <div class="card" style="margin-bottom:20px">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.cetak.kelengkapan') }}" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end">
                <div style="min-width:260px;flex:1">
                    <label class="form-label" style="font-size:12px;font-weight:600">Pilih Kelas / Rombel</label>
                    <select name="kelas" class="form-control" onchange="this.form.submit()">
                        @foreach($kelasList as $k)
                            @php $st = $kelasStats[$k] ?? ['persen' => 0, 'terkunci' => false]; @endphp
                            <option value="{{ $k }}" @selected($kelas === $k)>
                                {{ $k }} — [{{ number_format($st['persen'], 1) }}%] {{ $st['terkunci'] ? '🔒 Terkunci' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label" style="font-size:12px;font-weight:600">Semester</label>
                    <select name="semester" class="form-control" onchange="this.form.submit()">
                        <option value="Ganjil" @selected($semester === 'Ganjil')>Ganjil</option>
                        <option value="Genap" @selected($semester === 'Genap')>Genap</option>
                    </select>
                </div>

                <div>
                    <label class="form-label" style="font-size:12px;font-weight:600">Tahun Ajaran</label>
                    <input type="text" name="tahun_ajaran" value="{{ $tahunAjaran }}" class="form-control" style="width:120px" maxlength="9">
                </div>

                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-filter"></i> Tampilkan
                </button>
            </form>
        </div>
    </div>

    @if($kelas)
        @php $activeStat = $kelasStats[$kelas] ?? ['siswa' => 0, 'mapel' => 0, 'filled' => 0, 'expected' => 0, 'persen' => 0, 'terkunci' => false]; @endphp

        <div class="stats-grid" style="margin-bottom:20px">
            <div class="stat-card">
                <div class="stat-value">{{ $activeStat['siswa'] }}</div>
                <div class="stat-label">Siswa Aktif ({{ $kelas }})</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{{ $activeStat['mapel'] }}</div>
                <div class="stat-label">Mapel Terdata</div>
            </div>
            <div class="stat-card ok">
                <div class="stat-value">{{ number_format($activeStat['persen'], 1) }}%</div>
                <div class="stat-label">Kelengkapan ({{ $activeStat['filled'] }}/{{ $activeStat['expected'] }})</div>
            </div>
            <div class="stat-card {{ $activeStat['terkunci'] ? 'gold' : '' }}">
                <div class="stat-value" style="font-size:18px">
                    {{ $activeStat['terkunci'] ? '🔒 Terkunci' : '🔓 Terbuka' }}
                </div>
                <div class="stat-label">Status Input Rapor</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
                <div>
                    <h3 style="margin:0">Matriks Kelengkapan — {{ $kelas }}</h3>
                    <small style="color:var(--muted)">Semester {{ $semester }} • {{ $tahunAjaran }}</small>
                </div>
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                    @if($rombelAktif)
                        <form method="POST" action="{{ route('admin.peta-kelas.toggle-kunci', $rombelAktif) }}" style="margin:0">
                            @csrf
                            <button type="submit" class="btn btn-sm {{ $rombelAktif->kunci_input_rapor ? 'btn-secondary' : 'btn-primary' }}"
                                    style="{{ $rombelAktif->kunci_input_rapor ? '' : 'background:#B54708;border-color:#B54708' }}">
                                <i class="fas {{ $rombelAktif->kunci_input_rapor ? 'fa-unlock' : 'fa-lock' }}"></i>
                                {{ $rombelAktif->kunci_input_rapor ? 'Buka Kunci Input Rapor' : 'Kunci Input Rapor' }}
                            </button>
                        </form>
                    @endif
                    <a href="{{ route('admin.cetak.leger', ['kelas' => $kelas, 'semester' => $semester, 'tahun_ajaran' => $tahunAjaran]) }}" class="btn btn-sm btn-secondary">
                        <i class="fas fa-table"></i> Lihat Leger
                    </a>
                </div>
            </div>

            <div class="card-body tight">
                @if($siswa->isEmpty() || $mapel->isEmpty())
                    <div class="empty-state" style="padding:36px">
                        <i class="fas fa-clipboard-check" style="font-size:32px;color:var(--muted);margin-bottom:8px"></i>
                        <h4>Belum ada data nilai untuk kelas {{ $kelas }}</h4>
                        <p style="font-size:13px;color:var(--muted)">Matriks akan muncul otomatis setelah guru mata pelajaran mulai menginput nilai e-Rapor.</p>
                    </div>
                @else
                    <div class="table-wrapper">
                        <table style="width:100%;border-collapse:collapse;font-size:13px">
                            <thead>
                                <tr style="background:#F8FAFC;border-bottom:1px solid #E4E7EC">
                                    <th style="padding:10px 12px;text-align:left;width:44px">No</th>
                                    <th style="padding:10px 12px;text-align:left;min-width:200px">Nama Siswa</th>
                                    @foreach($mapel as $m)
                                        <th style="padding:10px 10px;text-align:center;min-width:95px">{{ $m }}</th>
                                    @endforeach
                                    <th style="padding:10px 12px;text-align:center;min-width:85px">Progres</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($siswa as $i => $s)
                                    @php
                                        $adaCount = 0;
                                        foreach ($mapel as $m) {
                                            if (!empty($matriks[$s->id][$m])) $adaCount++;
                                        }
                                        $rowPct = $mapel->count() > 0 ? round(($adaCount / $mapel->count()) * 100) : 0;
                                    @endphp
                                    <tr style="border-bottom:1px solid #F1F5F9">
                                        <td style="padding:9px 12px;color:var(--muted)">{{ $i + 1 }}</td>
                                        <td style="padding:9px 12px">
                                            <strong>{{ $s->nama_peserta_didik }}</strong>
                                            <div style="font-size:11px;color:var(--muted)">NISN: {{ $s->nisn ?? '-' }}</div>
                                        </td>
                                        @foreach($mapel as $m)
                                            @php $ok = !empty($matriks[$s->id][$m]); @endphp
                                            <td style="padding:9px 10px;text-align:center">
                                                @if($ok)
                                                    <span title="Nilai lengkap" style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:6px;background:#ECFDF5;color:#059669;font-weight:700">✓</span>
                                                @else
                                                    <span title="Belum ada nilai" style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:6px;background:#FEF2F2;color:#DC2626;font-weight:700">✕</span>
                                                @endif
                                            </td>
                                        @endforeach
                                        <td style="padding:9px 12px;text-align:center;font-weight:700;color:{{ $rowPct === 100 ? '#059669' : '#B54708' }}">
                                            {{ $adaCount }}/{{ $mapel->count() }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endif
@endsection
