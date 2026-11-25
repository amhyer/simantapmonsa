@extends('layouts.app')
@section('title', 'Pengaturan & Kustomisasi Cetak Rapor - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Pengaturan & Kustomisasi Cetak Rapor')
@section('page_subtitle', 'Atur kop kementerian, ukuran kertas, format nilai, tanda tangan digital, dan pratinjau langsung')

@section('content')
<div class="grid grid-2" style="gap:20px;align-items:start">
    {{-- Form Pengaturan --}}
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-sliders-h" style="margin-right:8px;color:var(--navy)"></i>Konfigurasi Cetak Rapor</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.cetak.pengaturan-rapor.simpan') }}" enctype="multipart/form-data" id="raporSettingsForm">
                @csrf

                <div style="margin-bottom:16px">
                    <label class="form-label" for="kementerian">Kop Nama Kementerian</label>
                    <select id="kementerian" name="kementerian" class="form-control" onchange="syncRaporPreview()">
                        <option value="Kemendikdasmen" @selected(($raporConfig['kementerian'] ?? '') === 'Kemendikdasmen')>Kementerian Pendidikan Dasar dan Menengah (Kemendikdasmen)</option>
                        <option value="Kemendikbudristek" @selected(($raporConfig['kementerian'] ?? '') === 'Kemendikbudristek')>Kementerian Pendidikan, Kebudayaan, Riset, dan Teknologi (Kemendikbudristek)</option>
                    </select>
                </div>

                <div class="grid grid-2" style="gap:12px;margin-bottom:16px">
                    <div>
                        <label class="form-label" for="ukuran_kertas">Ukuran Kertas</label>
                        <select id="ukuran_kertas" name="ukuran_kertas" class="form-control" onchange="syncRaporPreview()">
                            <option value="A4" @selected(($raporConfig['ukuran_kertas'] ?? 'A4') === 'A4')>A4 (210 x 297 mm)</option>
                            <option value="F4" @selected(($raporConfig['ukuran_kertas'] ?? '') === 'F4')>F4 / Folio (215 x 330 mm)</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="margin_atas_cm">Margin Atas (cm)</label>
                        <input id="margin_atas_cm" type="number" name="margin_atas_cm" class="form-control" step="0.1" min="0.5" max="5"
                               value="{{ old('margin_atas_cm', $raporConfig['margin_atas_cm'] ?? 1.5) }}" oninput="syncRaporPreview()">
                    </div>
                </div>

                <div class="grid grid-2" style="gap:12px;margin-bottom:16px">
                    <div>
                        <label class="form-label" for="kapitalisasi_nama">Kapitalisasi Nama Siswa</label>
                        <select id="kapitalisasi_nama" name="kapitalisasi_nama" class="form-control" onchange="syncRaporPreview()">
                            <option value="asli" @selected(($raporConfig['kapitalisasi_nama'] ?? 'asli') === 'asli')>Sesuai Data Asli</option>
                            <option value="upper" @selected(($raporConfig['kapitalisasi_nama'] ?? '') === 'upper')>HURUF BESAR SEMUA</option>
                            <option value="title" @selected(($raporConfig['kapitalisasi_nama'] ?? '') === 'title')>Title Case (Huruf Awal Besar)</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="format_desimal">Format Desimal Nilai</label>
                        <select id="format_desimal" name="format_desimal" class="form-control" onchange="syncRaporPreview()">
                            <option value="0" @selected(($raporConfig['format_desimal'] ?? '0') === '0')>Bilangan Bulat (88)</option>
                            <option value="2" @selected(($raporConfig['format_desimal'] ?? '') === '2')>Dua Desimal (88,50)</option>
                            <option value="auto" @selected(($raporConfig['format_desimal'] ?? '') === 'auto')>Otomatis (88,5)</option>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom:16px">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:600">
                        <input type="checkbox" name="page_break_catatan" value="1" @checked(!empty($raporConfig['page_break_catatan']))>
                        <span>Mulai bagian Kehadiran, Catatan &amp; Tanda Tangan di halaman baru (Page Break)</span>
                    </label>
                </div>

                <div style="margin-bottom:18px;padding:14px;background:#F8FAFC;border:1px solid #E4E7EC;border-radius:10px">
                    <label class="form-label" for="ttd_kepsek">Tanda Tangan Digital Kepala Sekolah (PNG/JPG, maks 512 KB)</label>
                    @if(!empty($raporConfig['ttd_kepsek_data_uri']))
                        <div style="margin-bottom:10px;display:flex;align-items:center;gap:12px">
                            <img src="{{ $raporConfig['ttd_kepsek_data_uri'] }}" alt="TTD Kepsek" style="max-height:56px;border:1px solid #CBD5E1;border-radius:6px;padding:4px;background:#fff">
                            <label style="font-size:12px;color:var(--bad);display:flex;align-items:center;gap:6px;cursor:pointer">
                                <input type="checkbox" name="hapus_ttd_kepsek" value="1"> Hapus tanda tangan tersimpan
                            </label>
                        </div>
                    @endif
                    <input id="ttd_kepsek" type="file" name="ttd_kepsek" accept="image/png,image/jpeg" class="form-control">
                </div>

                <div style="margin-bottom:18px;padding:14px;background:#F8FAFC;border:1px solid #E4E7EC;border-radius:10px">
                    <div style="font-weight:700;font-size:13px;margin-bottom:8px">
                        <i class="fas fa-signature" style="margin-right:6px;color:var(--navy)"></i>Tanda Tangan Digital per Wali Kelas (Grid Massal)
                    </div>
                    <div class="table-wrapper" style="max-height:260px;overflow-y:auto">
                        <table style="width:100%;font-size:12px">
                            <thead>
                                <tr>
                                    <th>Wali Kelas / Guru</th>
                                    <th>Pratinjau TTD</th>
                                    <th>Unggah Baru (PNG/JPG)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse(($waliKelasList ?? collect()) as $wk)
                                    @php $wkUri = ($ttdWaliMap ?? [])[(string) $wk->id] ?? null; @endphp
                                    <tr>
                                        <td><strong>{{ $wk->nama_lengkap }}</strong></td>
                                        <td>
                                            @if($wkUri)
                                                <img src="{{ $wkUri }}" alt="TTD {{ $wk->nama_lengkap }}" style="max-height:36px;border:1px solid #CBD5E1;border-radius:4px;background:#fff;padding:2px">
                                                <label style="display:block;font-size:11px;color:var(--bad);cursor:pointer;margin-top:2px">
                                                    <input type="checkbox" name="hapus_ttd_wali[{{ $wk->id }}]" value="1"> Hapus
                                                </label>
                                            @else
                                                <span style="color:var(--muted)">Belum ada</span>
                                            @endif
                                        </td>
                                        <td>
                                            <input type="file" name="ttd_wali[{{ $wk->id }}]" accept="image/png,image/jpeg" class="form-control" style="font-size:11.5px;padding:4px">
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" style="text-align:center;color:var(--muted)">Belum ada data guru aktif.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Pengaturan
                    </button>
                    <a href="{{ route('admin.cetak.nilai') }}" class="btn btn-secondary">
                        <i class="fas fa-print"></i> Buka Cetak Nilai Rapor
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Side-by-Side Live Preview --}}
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-eye" style="margin-right:8px;color:var(--navy)"></i>Pratinjau Langsung Rapor</h3>
            <span id="previewBadge" class="tag tag-info">{{ $raporConfig['ukuran_kertas'] ?? 'A4' }}</span>
        </div>
        <div class="card-body" style="background:#EEF2F6">
            <div id="raporLivePreview" style="background:#fff;border:1px solid #CBD5E1;box-shadow:0 8px 24px rgba(15,23,42,0.08);padding:20px 24px;font-family:'Times New Roman',serif;color:#000;transition:padding-top 0.15s">
                <div style="text-align:center;border-bottom:3px double #000;padding-bottom:8px;margin-bottom:12px">
                    <div id="pvKementerian" style="font-size:10px;font-weight:700;letter-spacing:0.3px">
                        KEMENTERIAN PENDIDIKAN DASAR DAN MENENGAH
                    </div>
                    <div style="font-size:15px;font-weight:700;text-transform:uppercase">
                        {{ $sekolah->nama_sekolah ?? 'UPT SPF SD NEGERI' }}
                    </div>
                    <div style="font-size:10px">{{ $sekolah->alamat ?? 'Alamat Sekolah' }} &bull; NPSN: {{ $sekolah->npsn ?? '-' }}</div>
                </div>

                <div style="text-align:center;margin-bottom:10px">
                    <div style="font-size:12px;font-weight:700;text-decoration:underline">LAPORAN HASIL BELAJAR PESERTA DIDIK</div>
                    <div style="font-size:10.5px">Semester Ganjil Tahun Ajaran {{ date('Y') }}/{{ date('Y') + 1 }}</div>
                </div>

                <div class="table-wrapper">
                    <table style="width:100%;font-size:11px;margin-bottom:10px">
                        <tr>
                            <td style="width:120px">Nama Peserta Didik</td>
                            <td style="width:10px">:</td>
                            <td><strong id="pvNamaSiswa">Alya Putri Pratama</strong></td>
                        </tr>
                        <tr>
                            <td>Kelas</td>
                            <td>:</td>
                            <td>6A</td>
                        </tr>
                    </table>
                </div>

                <div class="table-wrapper">
                    <table style="width:100%;border-collapse:collapse;font-size:11px">
                        <thead>
                            <tr style="background:#F1F5F9">
                                <th style="border:1px solid #000;padding:4px 6px;width:28px">No</th>
                                <th style="border:1px solid #000;padding:4px 6px;text-align:left">Muatan Pelajaran</th>
                                <th style="border:1px solid #000;padding:4px 6px;width:60px">Nilai</th>
                                <th style="border:1px solid #000;padding:4px 6px;width:55px">Predikat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="border:1px solid #000;padding:4px 6px;text-align:center">1</td>
                                <td style="border:1px solid #000;padding:4px 6px">Matematika</td>
                                <td id="pvNilai" style="border:1px solid #000;padding:4px 6px;text-align:center">88</td>
                                <td style="border:1px solid #000;padding:4px 6px;text-align:center">B</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div style="display:flex;justify-content:space-between;margin-top:20px;font-size:10.5px;text-align:center">
                    <div style="width:45%">
                        <div>Mengetahui,<br>Kepala Sekolah</div>
                        @if(!empty($raporConfig['ttd_kepsek_data_uri']))
                            <img src="{{ $raporConfig['ttd_kepsek_data_uri'] }}" alt="TTD" style="max-height:36px;margin:4px auto;display:block">
                        @else
                            <div style="height:36px"></div>
                        @endif
                        <div><strong><u>{{ $sekolah->kepala_sekolah ?? 'Kepala Sekolah' }}</u></strong></div>
                    </div>
                    <div style="width:45%">
                        <div>Wali Kelas</div>
                        <div style="height:48px"></div>
                        <div><strong><u>Wali Kelas 6A</u></strong></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function syncRaporPreview() {
    const kem = document.getElementById('kementerian')?.value;
    const kertas = document.getElementById('ukuran_kertas')?.value;
    const margin = parseFloat(document.getElementById('margin_atas_cm')?.value || '1.5');
    const kap = document.getElementById('kapitalisasi_nama')?.value;
    const des = document.getElementById('format_desimal')?.value;

    const pvKem = document.getElementById('pvKementerian');
    if (pvKem) {
        pvKem.textContent = kem === 'Kemendikbudristek'
            ? 'KEMENTERIAN PENDIDIKAN, KEBUDAYAAN, RISET, DAN TEKNOLOGI'
            : 'KEMENTERIAN PENDIDIKAN DASAR DAN MENENGAH';
    }
    const pvBadge = document.getElementById('previewBadge');
    if (pvBadge) pvBadge.textContent = kertas || 'A4';

    const pvBox = document.getElementById('raporLivePreview');
    if (pvBox && !Number.isNaN(margin)) {
        pvBox.style.paddingTop = Math.max(10, Math.round(margin * 14)) + 'px';
    }

    const pvNama = document.getElementById('pvNamaSiswa');
    if (pvNama) {
        const sample = 'Alya Putri Pratama';
        pvNama.textContent = kap === 'upper' ? sample.toUpperCase() : sample;
    }

    const pvNilai = document.getElementById('pvNilai');
    if (pvNilai) {
        pvNilai.textContent = des === '2' ? '88,50' : (des === 'auto' ? '88,5' : '89');
    }
}
syncRaporPreview();
</script>
@endsection
