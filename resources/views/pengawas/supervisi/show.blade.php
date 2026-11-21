@extends('layouts.app')

@section('title', 'Verifikasi Pengawas — ' . $guru->nama_lengkap)
@section('page_title', 'Verifikasi Pengawas: ' . $guru->nama_lengkap)
@section('page_subtitle', 'Tahun Ajaran ' . $tahunAjaran . ' · Semester ' . $semester . ' · Kelengkapan ' . $persen . '% (' . $terisi . '/9 dokumen)')

@section('sidebar')
    @include('pengawas.partials.sidebar')
@endsection

@section('header_actions')
    <a href="{{ route('pengawas.supervisi.index', ['tahun_ajaran' => $tahunAjaran, 'semester' => $semester]) }}" class="btn btn-ghost btn-sm">
        &larr; Kembali ke Matriks
    </a>
@endsection

@section('content')
    {{-- Status Supervisi Kepala Sekolah (Tier 1) --}}
    <div class="card" style="margin-bottom:16px;border-left:4px solid #175CD3">
        <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
            <div>
                <div style="font-size:12px;font-weight:700;color:#175CD3">TAHAP 1 — HASIL SUPERVISI KEPALA SEKOLAH</div>
                <div style="font-size:13px;color:#344054;margin-top:4px">
                    Catatan Kepala Sekolah: <strong>{{ $supervisi?->catatan_kepsek ?: 'Belum ada catatan dari Kepala Sekolah.' }}</strong>
                </div>
            </div>
            <div>
                <span class="badge {{ $supervisi ? $supervisi->statusBadge() : 'badge-warning' }}">
                    {{ $supervisi ? $supervisi->statusLabel() : 'Menunggu Supervisi Kepsek' }}
                    @if($supervisi?->disetujui_at) · {{ $supervisi->disetujui_at->format('d/m/Y H:i') }} @endif
                </span>
            </div>
        </div>
    </div>

    {{-- Form Verifikasi Berjenjang & Catatan Pembinaan Pengawas (Tier 2) --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
            <strong><i class="fas fa-shield-alt"></i> TAHAP 2 — Verifikasi Pengawas &amp; Rekomendasi Tindak Lanjut (RTL)</strong>
            @if($supervisi)
                <span class="badge {{ $supervisi->statusPengawasBadge() }}">
                    {{ $supervisi->statusPengawasLabel() }}
                    @if($supervisi->diverifikasi_pengawas_at) · {{ $supervisi->diverifikasi_pengawas_at->format('d/m/Y H:i') }} @endif
                </span>
            @endif
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('pengawas.supervisi.verifikasi', $guru->uuid) }}" style="display:grid;gap:12px">
                @csrf
                <input type="hidden" name="tahun_ajaran" value="{{ $tahunAjaran }}">
                <input type="hidden" name="semester" value="{{ $semester }}">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px">
                    <div>
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Status Verifikasi Pengawas *</label>
                        <select name="status_pengawas" required style="width:100%;padding:8px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                            @foreach($statusPengawasLabels as $kode => $label)
                                <option value="{{ $kode }}" {{ ($supervisi?->status_pengawas ?? 'menunggu') === $kode ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Catatan Pembinaan Pengawas</label>
                        <textarea name="catatan_pengawas" rows="2" placeholder="Tuliskan umpan balik kualitas CP/TP/ATP/KKTP/RPM/Jurnal..."
                                  style="width:100%;padding:8px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">{{ old('catatan_pengawas', $supervisi?->catatan_pengawas) }}</textarea>
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Rekomendasi Tindak Lanjut (RTL)</label>
                        <textarea name="rekomendasi_rtl" rows="2" placeholder="Rencana pendampingan atau tindak lanjut bagi Guru & Kepala Sekolah..."
                                  style="width:100%;padding:8px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">{{ old('rekomendasi_rtl', $supervisi?->rekomendasi_rtl) }}</textarea>
                    </div>
                </div>
                <div style="display:flex;justify-content:flex-end">
                    <button type="submit" class="btn btn-sm"><i class="fas fa-check-double"></i> Simpan Verifikasi Pengawas</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Ringkasan 9 Dokumen --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:8px;margin-bottom:16px">
        @foreach([
            'cp' => 'CP', 'tp' => 'TP', 'atp' => 'ATP', 'kktp' => 'KKTP',
            'prota' => 'Prota', 'prosem' => 'Prosem', 'rpm' => 'RPM / Modul', 'jurnal' => 'Jurnal', 'kokurikuler' => 'Kokurikuler'
        ] as $k => $lbl)
            <div class="card">
                <div class="card-body" style="padding:10px;text-align:center">
                    <div style="font-size:11px;color:#667085;font-weight:600">{{ $lbl }}</div>
                    <div style="font-size:18px;font-weight:700;color:{{ $counts[$k] > 0 ? '#027A48' : '#B42318' }}">{{ $counts[$k] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Rincian Read-Only 9 Dokumen Guru --}}
    <div style="display:grid;gap:12px">
        <details class="card" open>
            <summary class="card-header" style="cursor:pointer;font-weight:600">1. Modul Ajar / RPM ({{ $rpm->count() }})</summary>
            <div class="card-body" style="padding:0">
                @if($rpm->isEmpty())
                    <div style="padding:18px;color:#667085;font-size:13px">Belum ada Modul Ajar / RPM.</div>
                @else
                    <div class="table-wrapper">
                        <table class="table">
                            <thead><tr><th>Judul</th><th>Mapel / Kelas</th><th>Alokasi</th><th>Ringkasan</th></tr></thead>
                            <tbody>
                                @foreach($rpm as $item)
                                    <tr>
                                        <td style="font-weight:600">{{ $item->judul }}</td>
                                        <td>{{ $item->mata_pelajaran }} · {{ $item->kelas }}</td>
                                        <td>{{ $item->alokasi_waktu ?: '-' }}</td>
                                        <td style="font-size:12px">{{ $item->ringkasan ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </details>

        <details class="card">
            <summary class="card-header" style="cursor:pointer;font-weight:600">2. Jurnal Mengajar Harian ({{ $jurnal->count() }})</summary>
            <div class="card-body" style="padding:0">
                @if($jurnal->isEmpty())
                    <div style="padding:18px;color:#667085;font-size:13px">Belum ada catatan Jurnal Mengajar.</div>
                @else
                    <div class="table-wrapper">
                        <table class="table">
                            <thead><tr><th>Tanggal</th><th>Kelas / Mapel</th><th>Topik</th><th>Praktik &amp; Pengalaman PM</th></tr></thead>
                            <tbody>
                                @foreach($jurnal as $j)
                                    <tr>
                                        <td style="white-space:nowrap">{{ $j->tanggal->format('d/m/Y') }}</td>
                                        <td>{{ $j->kelas }} · {{ $j->mata_pelajaran }}</td>
                                        <td style="font-weight:600">{{ $j->topik }}</td>
                                        <td style="font-size:12px">{{ $j->praktik_pedagogis ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </details>

        <details class="card">
            <summary class="card-header" style="cursor:pointer;font-weight:600">3. Capaian Pembelajaran ({{ $cp->count() }}) &amp; Tujuan Pembelajaran ({{ $tp->count() }})</summary>
            <div class="card-body" style="padding:0">
                @if($tp->isEmpty())
                    <div style="padding:18px;color:#667085;font-size:13px">Belum ada Tujuan Pembelajaran.</div>
                @else
                    <div class="table-wrapper">
                        <table class="table">
                            <thead><tr><th>Mapel</th><th>Fase</th><th>Kode TP</th><th>Deskripsi TP</th><th>Target Kelas/Sem</th><th>JP</th></tr></thead>
                            <tbody>
                                @foreach($tp as $item)
                                    <tr>
                                        <td>{{ $item->nama_mapel }}</td>
                                        <td>Fase {{ $item->fase }}</td>
                                        <td style="font-weight:600">{{ $item->kode_tp ?: '-' }}</td>
                                        <td style="font-size:12px">{{ $item->deskripsi }}</td>
                                        <td>{{ $item->kelas_target ? 'Kls '.$item->kelas_target.' / Sem '.($item->semester_target ?: '-') : '-' }}</td>
                                        <td>{{ $item->alokasi_jp ?: 0 }} JP</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </details>

        <details class="card">
            <summary class="card-header" style="cursor:pointer;font-weight:600">4. Program Tahunan ({{ $prota->count() }}) &amp; Program Semester ({{ $prosem->count() }})</summary>
            <div class="card-body" style="padding:0">
                @if($prota->isEmpty())
                    <div style="padding:18px;color:#667085;font-size:13px">Belum ada Program Tahunan.</div>
                @else
                    <div class="table-wrapper">
                        <table class="table">
                            <thead><tr><th>Mapel</th><th>Semester</th><th>Materi Pokok</th><th>Alokasi JP</th></tr></thead>
                            <tbody>
                                @foreach($prota as $p)
                                    <tr>
                                        <td>{{ $p->nama_mapel }}</td>
                                        <td>Semester {{ $p->semester }}</td>
                                        <td>{{ $p->materi }}</td>
                                        <td style="font-weight:600">{{ $p->alokasi_jp }} JP</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </details>

        <details class="card">
            <summary class="card-header" style="cursor:pointer;font-weight:600">5. Rencana Kokurikuler ({{ $kokurikuler->count() }})</summary>
            <div class="card-body" style="padding:0">
                @if($kokurikuler->isEmpty())
                    <div style="padding:18px;color:#667085;font-size:13px">Belum ada Rencana Kokurikuler.</div>
                @else
                    <div class="table-wrapper">
                        <table class="table">
                            <thead><tr><th>Kelas</th><th>Tema / Judul</th><th>Bentuk Kegiatan</th><th>Tujuan</th></tr></thead>
                            <tbody>
                                @foreach($kokurikuler as $k)
                                    <tr>
                                        <td>Kelas {{ $k->tingkat }}</td>
                                        <td style="font-weight:600">{{ $k->tema_judul }}</td>
                                        <td>{{ $k->bentukLabel() }}</td>
                                        <td style="font-size:12px">{{ $k->tujuan ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </details>
    </div>
@endsection
