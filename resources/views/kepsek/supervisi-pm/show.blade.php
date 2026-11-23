@extends('layouts.app')

@section('title', 'Detail Supervisi PM — ' . $guru->nama_lengkap)
@section('page_title', 'Supervisi Pembelajaran Mendalam: ' . $guru->nama_lengkap)
@section('page_subtitle', 'Tahun Ajaran ' . $tahunAjaran . ' · Semester ' . $semester . ' · Kelengkapan ' . $persen . '% (' . $terisi . '/9 dokumen)')

@section('sidebar')
    @include('kepsek.partials.sidebar')
@endsection

@section('header_actions')
    <a href="{{ route('kepsek.supervisi-pm.index', ['tahun_ajaran' => $tahunAjaran, 'semester' => $semester]) }}" class="btn btn-ghost btn-sm">
        ← Kembali ke Matriks
    </a>
@endsection

@section('content')
    {{-- Form Keputusan & Persetujuan Digital Kepala Sekolah --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
            <strong><i class="fas fa-stamp"></i> Keputusan &amp; Catatan Supervisi Akademik</strong>
            @if($supervisi)
                <span class="badge {{ $supervisi->statusBadge() }}">
                    {{ $supervisi->statusLabel() }}
                    @if($supervisi->disetujui_at) · {{ $supervisi->disetujui_at->format('d/m/Y H:i') }} @endif
                </span>
            @endif
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('kepsek.supervisi-pm.keputusan', $guru) }}" style="display:grid;gap:12px">
                @csrf
                <input type="hidden" name="tahun_ajaran" value="{{ $tahunAjaran }}">
                <input type="hidden" name="semester" value="{{ $semester }}">
                <div style="display:grid;grid-template-columns:220px 1fr;gap:12px;align-items:start">
                    <div>
                        <label for="fld-supervisi-pm-show-status" style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Status Supervisi *</label>
                        <select id="fld-supervisi-pm-show-status" name="status" required style="width:100%;padding:8px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                            @foreach($statusLabels as $kode => $label)
                                <option value="{{ $kode }}" {{ ($supervisi?->status ?? 'menunggu') === $kode ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="fld-supervisi-pm-show-catatan-kepsek" style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Catatan / Rekomendasi Kepala Sekolah</label>
                        <textarea id="fld-supervisi-pm-show-catatan-kepsek" name="catatan_kepsek" rows="2" placeholder="Tuliskan apresiasi atau poin perbaikan untuk guru..."
                                  style="width:100%;padding:8px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">{{ old('catatan_kepsek', $supervisi?->catatan_kepsek) }}</textarea>
                    </div>
                </div>
                <div style="display:flex;justify-content:flex-end">
                    <button type="submit" class="btn btn-sm"><i class="fas fa-check-circle"></i> Simpan Keputusan Supervisi</button>
                </div>
            </form>
            @if($supervisi && ($supervisi->status_pengawas !== 'menunggu' || $supervisi->catatan_pengawas || $supervisi->rekomendasi_rtl))
                <div style="margin-top:12px;padding:10px 12px;background:#F4F3FF;border:1px solid #D9D6FE;border-radius:8px;font-size:12.5px;color:#5925DC">
                    <strong><i class="fas fa-shield-alt"></i> Verifikasi Pengawas Sekolah: {{ $supervisi->statusPengawasLabel() }}</strong>
                    @if($supervisi->catatan_pengawas)
                        <div style="margin-top:4px"><strong>Catatan Pembinaan:</strong> {{ $supervisi->catatan_pengawas }}</div>
                    @endif
                    @if($supervisi->rekomendasi_rtl)
                        <div style="margin-top:2px"><strong>Rekomendasi Tindak Lanjut (RTL):</strong> {{ $supervisi->rekomendasi_rtl }}</div>
                    @endif
                </div>
            @endif
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
                    <div style="font-size:18px;font-weight:700;color:{{ $counts[$k] > 0 ? '#027A48' : 'var(--bad)' }}">{{ $counts[$k] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Accordion Rincian Dokumen Guru --}}
    <div style="display:grid;gap:12px">
        <details class="card" open>
            <summary class="card-header" style="cursor:pointer;font-weight:600">1. Modul Ajar / RPM ({{ $rpm->count() }})</summary>
            <div class="card-body" style="padding:0">
                @if($rpm->isEmpty())
                    <div style="padding:18px;color:#667085;font-size:13px">Belum ada Modul Ajar / RPM.</div>
                @else
                    <div class="table-wrapper">
                        <table class="table">
                            <thead><tr><th>Judul</th><th>Mapel / Kelas</th><th>Alokasi</th><th style="text-align:right">PDF</th></tr></thead>
                            <tbody>
                                @foreach($rpm as $item)
                                    <tr>
                                        <td style="font-weight:600">{{ $item->judul }}</td>
                                        <td>{{ $item->mata_pelajaran }} · {{ $item->kelas }}</td>
                                        <td>{{ $item->alokasi_waktu ?: '-' }}</td>
                                        <td style="text-align:right">
                                            <a href="{{ route('kepsek.perangkat.pdf', $item) }}" target="_blank" class="btn btn-sm btn-ghost">
                                                <i class="fas fa-file-pdf"></i> PDF
                                            </a>
                                        </td>
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
                                        <td style="font-size:12px">
                                            {{ $j->praktik_pedagogis ?: '-' }}
                                            @if(!empty($j->pengalaman_belajar))
                                                · {{ implode(', ', $j->pengalaman_belajar) }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </details>

        <details class="card">
            <summary class="card-header" style="cursor:pointer;font-weight:600">3. Capaian Pembelajaran ({{ $cp->count() }}) &amp; Tujuan Pembelajaran / ATP ({{ $tp->count() }})</summary>
            <div class="card-body" style="padding:0">
                <div class="table-wrapper">
                    <table class="table">
                        <thead><tr><th>Mapel</th><th>Fase</th><th>Kode TP</th><th>Rumusan TP</th><th>Target ATP</th></tr></thead>
                        <tbody>
                            @forelse($tp as $t)
                                <tr>
                                    <td style="font-weight:600">{{ $t->nama_mapel }}</td>
                                    <td>Fase {{ $t->fase }}</td>
                                    <td><code>{{ $t->kode_tp ?: '-' }}</code></td>
                                    <td style="font-size:13px">{{ $t->deskripsi }}</td>
                                    <td style="font-size:12px">{{ $t->kelas_target ? "Kls {$t->kelas_target} S{$t->semester_target} ({$t->alokasi_jp} JP)" : 'Belum di-ATP' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" style="text-align:center;color:#667085;padding:16px">Belum ada TP/ATP.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </details>

        <details class="card">
            <summary class="card-header" style="cursor:pointer;font-weight:600">4. Program Tahunan ({{ $prota->count() }}) &amp; Program Semester ({{ $prosem->count() }})</summary>
            <div class="card-body" style="padding:0">
                <div class="table-wrapper">
                    <table class="table">
                        <thead><tr><th>Mapel</th><th>Kelas</th><th>Sem</th><th>Materi Prota</th><th>JP</th></tr></thead>
                        <tbody>
                            @forelse($prota as $pr)
                                <tr>
                                    <td style="font-weight:600">{{ $pr->nama_mapel }}</td>
                                    <td>Kelas {{ $pr->tingkat }}</td>
                                    <td>S{{ $pr->semester }}</td>
                                    <td>{{ $pr->materi }}</td>
                                    <td>{{ $pr->alokasi_jp }} JP</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" style="text-align:center;color:#667085;padding:16px">Belum ada data Prota.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </details>

        <details class="card">
            <summary class="card-header" style="cursor:pointer;font-weight:600">5. KKTP ({{ $kktp->count() }}) &amp; Kokurikuler ({{ $kokurikuler->count() }})</summary>
            <div class="card-body" style="padding:0">
                <div class="table-wrapper">
                    <table class="table">
                        <thead><tr><th>Tema Kokurikuler</th><th>Kelas</th><th>Bentuk</th><th>Dimensi Profil</th></tr></thead>
                        <tbody>
                            @forelse($kokurikuler as $k)
                                <tr>
                                    <td style="font-weight:600">{{ $k->tema_judul }}</td>
                                    <td>Kelas {{ $k->tingkat }}</td>
                                    <td style="font-size:12px">{{ $k->bentukLabel() }}</td>
                                    <td style="font-size:12px">{{ implode(', ', is_array($k->dimensi_profil) ? $k->dimensi_profil : []) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" style="text-align:center;color:#667085;padding:16px">Belum ada kegiatan Kokurikuler.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </details>
    </div>
@endsection
