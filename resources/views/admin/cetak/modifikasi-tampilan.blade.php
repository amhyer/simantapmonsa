@extends('layouts.admin-with-tabs')

@section('title', 'Modifikasi Tampilan Rapor - SIMANTAP')
@section('page_title', 'Modifikasi Tampilan Rapor')
@section('page_subtitle', 'Kustomisasi tipografi, warna tabel, watermark, kop surat, dan format PTS dengan pratinjau langsung')

@push('styles')
<style>
    .modif-grid {
        display: grid;
        grid-template-columns: minmax(340px, 42%) 1fr;
        gap: 20px;
        align-items: start;
    }
    @media (max-width: 1024px) {
        .modif-grid { grid-template-columns: 1fr; }
    }
    .modif-panel { display: none; }
    .modif-panel.active { display: block; }
    .kop-row-editor {
        display: grid;
        grid-template-columns: 36px 68px 42px 1fr;
        gap: 6px;
        align-items: center;
        margin-bottom: 8px;
    }
    .bold-toggle-btn {
        height: 36px;
        border: 1px solid #CBD5E1;
        border-radius: 6px;
        background: #F8FAFC;
        font-weight: 800;
        cursor: pointer;
    }
    .bold-toggle-btn.is-bold {
        background: var(--navy, #1F3864);
        color: #fff;
        border-color: var(--navy, #1F3864);
    }
    .preview-paper {
        position: relative;
        background: #fff;
        border: 1px solid #CBD5E1;
        border-radius: 8px;
        padding: 24px 26px;
        box-shadow: 0 4px 18px rgba(15, 23, 42, 0.08);
        overflow: hidden;
        color: #000;
        min-height: 540px;
    }
    .preview-watermark {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        pointer-events: none;
        z-index: 0;
    }
    .preview-content { position: relative; z-index: 1; }
    .preview-kop {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        border-bottom: 3px double #000;
        padding-bottom: 10px;
        margin-bottom: 14px;
    }
    .preview-kop img.kop-logo { width: 58px; height: 58px; object-fit: contain; flex-shrink: 0; }
    .preview-kop .kop-text { flex: 1; text-align: center; line-height: 1.3; }
    .preview-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .preview-table th, .preview-table td { border: 1px solid #000; padding: 6px 8px; }
</style>
@endpush

@section('content')
@php
    $logoPemdaUrl = !empty($pengaturan['logo_pemda']) ? asset('storage/' . $pengaturan['logo_pemda']) : null;
    $logoSekolahUrl = !empty($pengaturan['logo_sekolah']) ? asset('storage/' . $pengaturan['logo_sekolah']) : null;
@endphp

<div class="modif-grid">
    {{-- Kolom Kiri: Form Pengaturan --}}
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-paint-brush" style="margin-right:8px;color:var(--navy)"></i>Pengaturan Tampilan</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.cetak.modifikasi-tampilan.simpan') }}" enctype="multipart/form-data" id="modifikasiTampilanForm">
                @csrf

                <div style="margin-bottom:16px;padding:12px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px">
                    <div style="font-weight:700;font-size:13px;margin-bottom:8px">Terapkan Pada</div>
                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;margin-bottom:6px;cursor:pointer">
                        <input type="checkbox" name="terapkan_semester" value="1" {{ ($tampilan['terapkan_semester'] || empty($pengaturan['tampilan_rapor'])) ? 'checked' : '' }}>
                        <span>Rapor Akhir Semester</span>
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
                        <input type="checkbox" name="terapkan_pts" value="1" {{ $tampilan['terapkan_pts'] ? 'checked' : '' }}>
                        <span>Rapor Tengah Semester (PTS)</span>
                    </label>
                </div>

                <div class="form-group" style="margin-bottom:16px">
                    <label style="font-weight:700" for="select_menu_pengaturan">Pilih Modul Pengaturan</label>
                    <select id="select_menu_pengaturan" class="form-control">
                        <option value="menu_pengaturan_tulisan_rapor">1. Pengaturan Tulisan Rapor</option>
                        <option value="menu_pengaturan_warna_rapor">2. Pengaturan Warna Rapor</option>
                        <option value="menu_pengaturan_watermark_rapor">3. Pengaturan Watermark Rapor</option>
                        <option value="menu_pengaturan_kop_surat">4. Pengaturan Kop Surat</option>
                        <option value="menu_pengaturan_rapor_tengah_semester">5. Pengaturan Rapor Tengah Semester</option>
                    </select>
                </div>

                {{-- Modul 1: Tulisan Rapor --}}
                <div id="menu_pengaturan_tulisan_rapor" class="modif-panel active">
                    <div class="form-group" style="margin-bottom:12px">
                        <label for="font_family_rapor">Jenis Tulisan Rapor</label>
                        <select name="font_family" id="font_family_rapor" class="form-control live-input">
                            @foreach(['Arial', 'Calibri', 'Helvetica', 'Tahoma', 'Times New Roman', 'Verdana', 'Bookman Old Style'] as $font)
                                <option value="{{ $font }}" {{ $tampilan['font_family'] === $font ? 'selected' : '' }}>{{ $font }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px">
                        <div class="form-group">
                            <label for="size_judul_rapor">Judul Rapor (px)</label>
                            <input type="number" step="0.5" min="10" max="36" name="size_judul_rapor" id="size_judul_rapor" class="form-control live-input" value="{{ $tampilan['size_judul_rapor'] }}">
                        </div>
                        <div class="form-group">
                            <label for="size_judul_tabel">Judul Tabel (px)</label>
                            <input type="number" step="0.5" min="8" max="24" name="size_judul_tabel" id="size_judul_tabel" class="form-control live-input" value="{{ $tampilan['size_judul_tabel'] }}">
                        </div>
                        <div class="form-group">
                            <label for="size_isi_tabel">Isi Tabel (px)</label>
                            <input type="number" step="0.5" min="8" max="22" name="size_isi_tabel" id="size_isi_tabel" class="form-control live-input" value="{{ $tampilan['size_isi_tabel'] }}">
                        </div>
                    </div>
                </div>

                {{-- Modul 2: Warna Rapor --}}
                <div id="menu_pengaturan_warna_rapor" class="modif-panel">
                    <div class="form-group" style="margin-bottom:12px">
                        <label for="select_template">Pilih Set Template Warna</label>
                        <select name="warna_template" id="select_template" class="form-control">
                            <option value="custom" {{ $tampilan['warna_template'] === 'custom' ? 'selected' : '' }}>Kustom</option>
                            <option value="default" {{ $tampilan['warna_template'] === 'default' ? 'selected' : '' }}>Setelan Awal (Abu-abu Netral)</option>
                            <option value="oranye" {{ $tampilan['warna_template'] === 'oranye' ? 'selected' : '' }}>Oranye Hangat</option>
                            <option value="biru" {{ $tampilan['warna_template'] === 'biru' ? 'selected' : '' }}>Biru Resmi</option>
                            <option value="hijau" {{ $tampilan['warna_template'] === 'hijau' ? 'selected' : '' }}>Hijau Segar</option>
                        </select>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
                        <div class="form-group">
                            <label for="warna_bg_table_header">Latar Kepala Tabel</label>
                            <input type="color" name="warna_bg_header" id="warna_bg_table_header" class="form-control live-input" style="height:38px;padding:2px" value="{{ $tampilan['warna_bg_header'] }}">
                        </div>
                        <div class="form-group">
                            <label for="warna_bg_table_cell">Latar Badan Tabel</label>
                            <input type="color" name="warna_bg_cell" id="warna_bg_table_cell" class="form-control live-input" style="height:38px;padding:2px" value="{{ $tampilan['warna_bg_cell'] }}">
                        </div>
                        <div class="form-group">
                            <label for="warna_text_table_header">Teks Kepala Tabel</label>
                            <input type="color" name="warna_text_header" id="warna_text_table_header" class="form-control live-input" style="height:38px;padding:2px" value="{{ $tampilan['warna_text_header'] }}">
                        </div>
                        <div class="form-group">
                            <label for="warna_text_table_cell">Teks Badan Tabel</label>
                            <input type="color" name="warna_text_cell" id="warna_text_table_cell" class="form-control live-input" style="height:38px;padding:2px" value="{{ $tampilan['warna_text_cell'] }}">
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom:12px">
                        <label for="warna_border_table">Warna Garis (Border) Tabel</label>
                        <input type="color" name="warna_border" id="warna_border_table" class="form-control live-input" style="height:38px;padding:2px;width:120px" value="{{ $tampilan['warna_border'] }}">
                    </div>
                    <div class="form-group">
                        <label for="table_opacity">Transparansi Tabel: <strong id="tableOpacityLabel">{{ $tampilan['table_opacity'] }}%</strong></label>
                        <input type="range" name="table_opacity" id="table_opacity" min="0" max="100" value="{{ $tampilan['table_opacity'] }}" class="live-input" style="width:100%">
                    </div>
                </div>

                {{-- Modul 3: Watermark Rapor --}}
                <div id="menu_pengaturan_watermark_rapor" class="modif-panel">
                    <div class="form-group" style="margin-bottom:12px">
                        <label for="show_watermark">Tampilkan Gambar Watermark</label>
                        <select name="show_watermark" id="show_watermark" class="form-control live-input">
                            <option value="0" {{ !$tampilan['show_watermark'] ? 'selected' : '' }}>Tidak</option>
                            <option value="1" {{ $tampilan['show_watermark'] ? 'selected' : '' }}>Ya</option>
                        </select>
                    </div>
                    @if(!empty($tampilan['watermark_data_uri']))
                        <div style="margin-bottom:10px;padding:10px;border:1px solid #E2E8F0;border-radius:8px;display:flex;align-items:center;gap:12px">
                            <img src="{{ $tampilan['watermark_data_uri'] }}" alt="Watermark" style="max-height:54px;max-width:54px;object-fit:contain">
                            <label style="font-size:12.5px;color:var(--bad);cursor:pointer" for="hapus_watermark">
                                <input type="checkbox" name="hapus_watermark" id="hapus_watermark" value="1" class="live-input"> Hapus watermark tersimpan
                            </label>
                        </div>
                    @endif
                    <div class="form-group" style="margin-bottom:12px">
                        <label for="file_watermark">Upload Gambar Watermark (PNG/JPG, maks 1 MB)</label>
                        <input type="file" name="file_watermark" id="file_watermark" accept="image/png,image/jpeg,image/webp" class="form-control">
                    </div>
                    <div class="form-group" style="margin-bottom:12px">
                        <label for="watermark_opacity">Transparansi Gambar: <strong id="watermarkOpacityLabel">{{ $tampilan['watermark_opacity'] }}%</strong></label>
                        <input type="range" name="watermark_opacity" id="watermark_opacity" min="0" max="100" value="{{ $tampilan['watermark_opacity'] }}" class="live-input" style="width:100%">
                    </div>
                    <div class="form-group">
                        <label for="watermark_size">Ukuran Watermark (%)</label>
                        <input type="number" step="0.5" min="10" max="150" name="watermark_size" id="watermark_size" class="form-control live-input" value="{{ $tampilan['watermark_size'] }}">
                    </div>
                </div>

                {{-- Modul 4: Kop Surat --}}
                <div id="menu_pengaturan_kop_surat" class="modif-panel">
                    <div class="form-group" style="margin-bottom:12px">
                        <label for="kop_preset">Set Tampilan Kop Surat</label>
                        <select name="kop_preset" id="kop_preset" class="form-control live-input">
                            <option value="tanpa_kop" {{ $tampilan['kop_preset'] === 'tanpa_kop' ? 'selected' : '' }}>Tanpa Kop</option>
                            <option value="set_1" {{ $tampilan['kop_preset'] === 'set_1' ? 'selected' : '' }}>Set Tampilan 1 (Logo Kanan &amp; Kiri)</option>
                            <option value="set_2" {{ $tampilan['kop_preset'] === 'set_2' ? 'selected' : '' }}>Set Tampilan 2 (Logo Kiri)</option>
                            <option value="set_3" {{ $tampilan['kop_preset'] === 'set_3' ? 'selected' : '' }}>Set Tampilan 3 (Logo Kanan)</option>
                            <option value="set_4" {{ $tampilan['kop_preset'] === 'set_4' ? 'selected' : '' }}>Set Tampilan 4 (Tanpa Logo)</option>
                        </select>
                    </div>
                    <div style="font-size:12px;color:var(--muted);margin-bottom:8px">
                        Atur 6 baris kop surat (Bold, Ukuran px, Warna, Teks). Logo diambil dari menu <em>Logo dan TTD</em>.
                    </div>
                    @for($i = 1; $i <= 6; $i++)
                        @php $b = $tampilan['kop_baris'][$i] ?? ['bold' => false, 'size' => 12, 'color' => '#000000', 'text' => '']; @endphp
                        <div class="kop-row-editor">
                            <input type="hidden" name="kop_baris[{{ $i }}][bold]" value="{{ !empty($b['bold']) ? 1 : 0 }}" class="kop-bold-val" data-row="{{ $i }}">
                            <button type="button" class="bold-toggle-btn {{ !empty($b['bold']) ? 'is-bold' : '' }}" data-row="{{ $i }}" title="Tebal (Bold)">B</button>
                            <input type="number" min="8" max="32" name="kop_baris[{{ $i }}][size]" class="form-control kop-size live-input" data-row="{{ $i }}" value="{{ $b['size'] ?? 12 }}" title="Ukuran Font (px)">
                            <input type="color" name="kop_baris[{{ $i }}][color]" class="form-control kop-color live-input" data-row="{{ $i }}" style="height:36px;padding:2px" value="{{ $b['color'] ?? '#000000' }}" title="Warna Teks">
                            <input type="text" name="kop_baris[{{ $i }}][text]" class="form-control kop-text live-input" data-row="{{ $i }}" value="{{ $b['text'] ?? '' }}" placeholder="Baris {{ $i }}...">
                        </div>
                    @endfor
                </div>

                {{-- Modul 5: Rapor Tengah Semester (PTS) --}}
                <div id="menu_pengaturan_rapor_tengah_semester" class="modif-panel">
                    <div style="font-weight:700;font-size:13px;margin-bottom:8px">Jumlah Kolom Rincian Penilaian per Tingkat</div>
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:14px">
                        @for($k = 1; $k <= 6; $k++)
                            <div class="form-group">
                                <label for="pts_kolom_kelas_{{ $k }}">Kelas {{ $k }}</label>
                                <input type="number" id="pts_kolom_kelas_{{ $k }}" min="1" max="12" name="pts_kolom_kelas[{{ $k }}]" class="form-control live-input" value="{{ $tampilan['pts_kolom_kelas'][(string)$k] ?? 5 }}">
                            </div>
                        @endfor
                    </div>
                    <div class="form-group" style="margin-bottom:10px">
                        <label for="pts_formatif_masuk">Penilaian Formatif Masuk ke Rapor Tengah Semester</label>
                        <select name="pts_formatif_masuk" id="pts_formatif_masuk" class="form-control live-input">
                            <option value="0" {{ !$tampilan['pts_formatif_masuk'] ? 'selected' : '' }}>Tidak</option>
                            <option value="1" {{ $tampilan['pts_formatif_masuk'] ? 'selected' : '' }}>Ya</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:10px">
                        <label for="pts_ttd_kepsek">Tanda Tangan Kepsek Pada Rapor Tengah Semester</label>
                        <select name="pts_ttd_kepsek" id="pts_ttd_kepsek" class="form-control live-input">
                            <option value="1" {{ $tampilan['pts_ttd_kepsek'] ? 'selected' : '' }}>Ya</option>
                            <option value="0" {{ !$tampilan['pts_ttd_kepsek'] ? 'selected' : '' }}>Tidak</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="pts_tabel_ekskul">Tabel Ekskul Pada Rapor Tengah Semester</label>
                        <select name="pts_tabel_ekskul" id="pts_tabel_ekskul" class="form-control live-input">
                            <option value="1" {{ $tampilan['pts_tabel_ekskul'] ? 'selected' : '' }}>Ya</option>
                            <option value="0" {{ !$tampilan['pts_tabel_ekskul'] ? 'selected' : '' }}>Tidak</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top:18px;display:flex;gap:10px">
                    <button type="submit" class="btn btn-primary" style="flex:1">
                        <i class="fas fa-save"></i> Simpan Data
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Kolom Kanan: Pratinjau Rapor Live --}}
    <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
            <h3><i class="fas fa-eye" style="margin-right:8px;color:var(--navy)"></i>Pratinjau Rapor</h3>
            <div style="display:flex;gap:8px;align-items:center">
                <select id="previewModeSelect" class="form-control" style="width:auto;height:32px;padding:4px 10px;font-size:12.5px">
                    <option value="semester">Mode: Akhir Semester</option>
                    <option value="pts">Mode: Tengah Semester (PTS)</option>
                </select>
                <a href="{{ route('admin.cetak.modifikasi-tampilan.preview') }}" target="_blank" class="btn btn-sm btn-primary">
                    <i class="fas fa-file-pdf"></i> Pratinjau PDF
                </a>
            </div>
        </div>
        <div class="card-body" style="background:#F1F5F9;padding:18px">
            <div class="preview-paper" id="previewPaper">
                <img src="{{ $tampilan['watermark_data_uri'] ?? '' }}" id="previewWatermark" class="preview-watermark" alt="" style="{{ ($tampilan['show_watermark'] && !empty($tampilan['watermark_data_uri'])) ? '' : 'display:none;' }}">
                <div class="preview-content">
                    <div class="preview-kop" id="previewKop">
                        @if($logoPemdaUrl)
                            <img src="{{ $logoPemdaUrl }}" id="previewLogoKiri" class="kop-logo" alt="Logo Pemda">
                        @else
                            <div id="previewLogoKiri" style="width:58px;height:58px;border:1px dashed #CBD5E1;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:10px;color:#94A3B8;flex-shrink:0">Logo 1</div>
                        @endif
                        <div class="kop-text" id="previewKopText"></div>
                        @if($logoSekolahUrl)
                            <img src="{{ $logoSekolahUrl }}" id="previewLogoKanan" class="kop-logo" alt="Logo Sekolah">
                        @else
                            <div id="previewLogoKanan" style="width:58px;height:58px;border:1px dashed #CBD5E1;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:10px;color:#94A3B8;flex-shrink:0">Logo 2</div>
                        @endif
                    </div>

                    <div style="text-align:center;margin:10px 0">
                        <h3 id="previewJudulRapor" style="margin:0;text-decoration:underline">LAPORAN HASIL BELAJAR PESERTA DIDIK</h3>
                        <div style="font-size:12px;margin-top:2px">Semester Ganjil Tahun Ajaran {{ date('Y') }}/{{ date('Y') + 1 }}</div>
                    </div>

                    <table class="preview-table" id="previewTable">
                        <thead>
                            <tr>
                                <th style="width:36px">No</th>
                                <th>Muatan Pelajaran</th>
                                <th style="width:70px">Nilai</th>
                                <th style="width:65px">Predikat</th>
                                <th>Capaian Kompetensi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td style="text-align:center">1</td><td>Pendidikan Agama &amp; Budi Pekerti</td><td style="text-align:center">90</td><td style="text-align:center">A</td><td>Sangat baik dalam praktik ibadah dan akhlak.</td></tr>
                            <tr><td style="text-align:center">2</td><td>Bahasa Indonesia</td><td style="text-align:center">86</td><td style="text-align:center">B</td><td>Baik dalam membaca pemahaman dan menulis narasi.</td></tr>
                            <tr><td style="text-align:center">3</td><td>Matematika</td><td style="text-align:center">84</td><td style="text-align:center">B</td><td>Cakap dalam operasi hitung pecahan dan geometri.</td></tr>
                        </tbody>
                    </table>

                    <div id="previewEkskulWrap" style="margin-top:10px">
                        <table class="preview-table" id="previewEkskulTable">
                            <thead>
                                <tr><th style="width:36px">No</th><th>Ekstrakurikuler</th><th style="width:100px">Predikat</th></tr>
                            </thead>
                            <tbody>
                                <tr><td style="text-align:center">1</td><td>Pramuka</td><td style="text-align:center">Sangat Baik</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div id="previewTtdWrap" style="display:flex;justify-content:space-between;margin-top:24px;font-size:12.5px">
                        <div id="previewTtdKepsek" style="width:45%;text-align:center">
                            <div>Mengetahui,<br>Kepala Sekolah</div>
                            <br><br>
                            <div><strong><u>{{ $sekolah->kepala_sekolah ?? 'Nama Kepala Sekolah' }}</u></strong></div>
                        </div>
                        <div style="width:45%;text-align:center">
                            <div>Wali Kelas</div>
                            <br><br>
                            <div><strong><u>Nama Wali Kelas</u></strong></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const moduleSelect = document.getElementById('select_menu_pengaturan');
    const panels = document.querySelectorAll('.modif-panel');
    moduleSelect.addEventListener('change', function () {
        panels.forEach(p => p.classList.toggle('active', p.id === this.value));
    });

    const templates = {
        default: { bgH: '#f0f0f0', bgC: '#ffffff', txtH: '#000000', txtC: '#000000', border: '#000000' },
        oranye:  { bgH: '#F97316', bgC: '#FFF7ED', txtH: '#FFFFFF', txtC: '#1E293B', border: '#EA580C' },
        biru:    { bgH: '#1E40AF', bgC: '#EFF6FF', txtH: '#FFFFFF', txtC: '#0F172A', border: '#1D4ED8' },
        hijau:   { bgH: '#27D332', bgC: '#FFFFFF', txtH: '#000000', txtC: '#000000', border: '#000000' },
    };

    const tplSelect = document.getElementById('select_template');
    tplSelect.addEventListener('change', function () {
        const t = templates[this.value];
        if (!t) return;
        document.getElementById('warna_bg_table_header').value = t.bgH;
        document.getElementById('warna_bg_table_cell').value = t.bgC;
        document.getElementById('warna_text_table_header').value = t.txtH;
        document.getElementById('warna_text_table_cell').value = t.txtC;
        document.getElementById('warna_border_table').value = t.border;
        renderPreview();
    });

    document.querySelectorAll('.bold-toggle-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const row = this.dataset.row;
            const input = document.querySelector(`.kop-bold-val[data-row="${row}"]`);
            const next = input.value === '1' ? '0' : '1';
            input.value = next;
            this.classList.toggle('is-bold', next === '1');
            renderPreview();
        });
    });

    const watermarkFile = document.getElementById('file_watermark');
    let uploadedWatermarkSrc = document.getElementById('previewWatermark').getAttribute('src') || '';
    watermarkFile.addEventListener('change', function () {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                uploadedWatermarkSrc = e.target.result;
                renderPreview();
            };
            reader.readAsDataURL(this.files[0]);
        }
    });

    function hexToRgba(hex, transparencyPct) {
        const clean = hex.replace('#', '');
        const r = parseInt(clean.substring(0, 2), 16) || 255;
        const g = parseInt(clean.substring(2, 4), 16) || 255;
        const b = parseInt(clean.substring(4, 6), 16) || 255;
        const alpha = Math.max(0, Math.min(1, (100 - transparencyPct) / 100));
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    function renderPreview() {
        const paper = document.getElementById('previewPaper');
        const font = document.getElementById('font_family_rapor').value;
        const sizeJudul = document.getElementById('size_judul_rapor').value || 18;
        const sizeTh = document.getElementById('size_judul_tabel').value || 13;
        const sizeTd = document.getElementById('size_isi_tabel').value || 12;

        paper.style.fontFamily = `"${font}", Arial, sans-serif`;
        const judulEl = document.getElementById('previewJudulRapor');
        judulEl.style.fontSize = sizeJudul + 'px';

        const mode = document.getElementById('previewModeSelect').value;
        judulEl.textContent = mode === 'pts'
            ? 'LAPORAN HASIL BELAJAR TENGAH SEMESTER (PTS)'
            : 'LAPORAN HASIL BELAJAR PESERTA DIDIK';

        const transp = parseInt(document.getElementById('table_opacity').value || '0', 10);
        document.getElementById('tableOpacityLabel').textContent = transp + '%';

        const bgH = hexToRgba(document.getElementById('warna_bg_table_header').value, transp);
        const bgC = hexToRgba(document.getElementById('warna_bg_table_cell').value, transp);
        const txtH = document.getElementById('warna_text_table_header').value;
        const txtC = document.getElementById('warna_text_table_cell').value;
        const border = document.getElementById('warna_border_table').value;

        paper.querySelectorAll('.preview-table th').forEach(th => {
            th.style.background = bgH;
            th.style.color = txtH;
            th.style.fontSize = sizeTh + 'px';
            th.style.borderColor = border;
        });
        paper.querySelectorAll('.preview-table td').forEach(td => {
            td.style.background = bgC;
            td.style.color = txtC;
            td.style.fontSize = sizeTd + 'px';
            td.style.borderColor = border;
        });

        // Watermark
        const showWm = document.getElementById('show_watermark').value === '1';
        const hapusWm = document.getElementById('hapus_watermark')?.checked || false;
        const wmOpacity = parseInt(document.getElementById('watermark_opacity').value || '75', 10);
        const wmSize = parseFloat(document.getElementById('watermark_size').value || '65');
        document.getElementById('watermarkOpacityLabel').textContent = wmOpacity + '%';
        const wmEl = document.getElementById('previewWatermark');
        if (showWm && !hapusWm && uploadedWatermarkSrc) {
            wmEl.src = uploadedWatermarkSrc;
            wmEl.style.display = 'block';
            wmEl.style.width = wmSize + '%';
            wmEl.style.opacity = Math.max(0.02, Math.min(1, (100 - wmOpacity) / 100));
        } else {
            wmEl.style.display = 'none';
        }

        // Kop Surat
        const kopPreset = document.getElementById('kop_preset').value;
        const kopBox = document.getElementById('previewKop');
        const logoL = document.getElementById('previewLogoKiri');
        const logoR = document.getElementById('previewLogoKanan');
        if (kopPreset === 'tanpa_kop') {
            kopBox.style.display = 'none';
        } else {
            kopBox.style.display = 'flex';
            logoL.style.display = (kopPreset === 'set_1' || kopPreset === 'set_2') ? 'flex' : 'none';
            logoR.style.display = (kopPreset === 'set_1' || kopPreset === 'set_3') ? 'flex' : 'none';
            const kopText = document.getElementById('previewKopText');
            kopText.innerHTML = '';
            for (let i = 1; i <= 6; i++) {
                const txt = document.querySelector(`.kop-text[data-row="${i}"]`).value.trim();
                if (!txt) continue;
                const div = document.createElement('div');
                div.textContent = txt;
                div.style.fontWeight = document.querySelector(`.kop-bold-val[data-row="${i}"]`).value === '1' ? 'bold' : 'normal';
                div.style.fontSize = (document.querySelector(`.kop-size[data-row="${i}"]`).value || 12) + 'px';
                div.style.color = document.querySelector(`.kop-color[data-row="${i}"]`).value || '#000000';
                kopText.appendChild(div);
            }
        }

        // PTS Toggles
        const showEkskul = mode === 'semester' || document.getElementById('pts_tabel_ekskul').value === '1';
        const showKepsek = mode === 'semester' || document.getElementById('pts_ttd_kepsek').value === '1';
        document.getElementById('previewEkskulWrap').style.display = showEkskul ? 'block' : 'none';
        document.getElementById('previewTtdKepsek').style.display = showKepsek ? 'block' : 'none';
        document.getElementById('previewTtdWrap').style.justifyContent = showKepsek ? 'space-between' : 'flex-end';
    }

    document.querySelectorAll('.live-input, #previewModeSelect').forEach(el => {
        el.addEventListener('input', renderPreview);
        el.addEventListener('change', renderPreview);
    });
    renderPreview();
});
</script>
@endpush
