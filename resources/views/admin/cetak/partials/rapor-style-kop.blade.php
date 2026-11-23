@php
    $t = $tampilanRapor ?? [];
    $jenisRapor = $jenis ?? 'semester';
    $applyTampilan = !empty($forceApplyTampilan)
        || ($jenisRapor === 'pts' && !empty($t['terapkan_pts']))
        || ($jenisRapor === 'semester' && ($t['terapkan_semester'] ?? true));

    $hexToRgba = function (string $hex, int $transparencyPct): string {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            $hex = 'ffffff';
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $alpha = max(0, min(1, (100 - $transparencyPct) / 100));

        return "rgba({$r}, {$g}, {$b}, {$alpha})";
    };

    $tableTransparency = (int) ($t['table_opacity'] ?? 0);
    $bgHeaderCss = $applyTampilan ? $hexToRgba((string) ($t['warna_bg_header'] ?? '#f0f0f0'), $tableTransparency) : '#f0f0f0';
    $bgCellCss = $applyTampilan ? $hexToRgba((string) ($t['warna_bg_cell'] ?? '#ffffff'), $tableTransparency) : '#ffffff';
    $textHeaderCss = $applyTampilan ? (string) ($t['warna_text_header'] ?? '#000000') : '#000000';
    $textCellCss = $applyTampilan ? (string) ($t['warna_text_cell'] ?? '#000000') : '#000000';
    $borderCss = $applyTampilan ? (string) ($t['warna_border'] ?? '#000000') : '#000000';
    $allowedFonts = ['Times New Roman', 'Arial', 'Calibri', 'Cambria', 'Georgia', 'Tahoma', 'Verdana', 'Helvetica', 'DejaVu Sans', 'DejaVu Serif'];
    $rawFont = trim((string) ($t['font_family'] ?? 'Times New Roman'));
    $fontFamilySafe = in_array($rawFont, $allowedFonts, true) ? $rawFont : 'Times New Roman';
    $fontFallback = $applyTampilan ? 'Arial, sans-serif' : 'Times, serif';
    $sizeJudulRapor = $applyTampilan ? (float) ($t['size_judul_rapor'] ?? 17) : 17;
    $sizeJudulTabel = $applyTampilan ? (float) ($t['size_judul_tabel'] ?? 13) : 13;
    $sizeIsiTabel = $applyTampilan ? (float) ($t['size_isi_tabel'] ?? 13) : 13;

    $pengaturanSekolah = is_array($sekolah?->pengaturan ?? null) ? $sekolah->pengaturan : [];
    $logoPemdaUrl = !empty($pengaturanSekolah['logo_pemda']) ? asset('storage/' . $pengaturanSekolah['logo_pemda']) : null;
    $logoSekolahUrl = !empty($pengaturanSekolah['logo_sekolah']) ? asset('storage/' . $pengaturanSekolah['logo_sekolah']) : null;
    $kopPreset = $applyTampilan ? (string) ($t['kop_preset'] ?? 'set_1') : 'classic';
@endphp

<style>
    body { font-family: "{{ $fontFamilySafe }}", {{ $fontFallback }}; position: relative; }
    .judul h3 { font-size: {{ $sizeJudulRapor }}px; }
    table.nilai th {
        background: {{ $bgHeaderCss }} !important;
        color: {{ $textHeaderCss }} !important;
        font-size: {{ $sizeJudulTabel }}px;
        border-color: {{ $borderCss }} !important;
    }
    table.nilai td {
        background: {{ $bgCellCss }};
        color: {{ $textCellCss }};
        font-size: {{ $sizeIsiTabel }}px;
        border-color: {{ $borderCss }} !important;
    }
    .rapor-watermark {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: -1;
        pointer-events: none;
        width: {{ (float) ($t['watermark_size'] ?? 65) }}%;
        opacity: {{ max(0.02, min(1, (100 - (int) ($t['watermark_opacity'] ?? 75)) / 100)) }};
    }
    .kop-flex {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        border-bottom: 3px double #000;
        padding-bottom: 10px;
        margin-bottom: 14px;
    }
    .kop-flex .kop-logo { width: 72px; height: 72px; object-fit: contain; flex-shrink: 0; }
    .kop-flex .kop-center { flex: 1; text-align: center; line-height: 1.3; }
</style>

@if($applyTampilan && !empty($t['show_watermark']) && !empty($t['watermark_data_uri']))
    <img src="{{ $t['watermark_data_uri'] }}" alt="" class="rapor-watermark">
@endif

@if($kopPreset === 'tanpa_kop')
    {{-- Tanpa Kop Surat --}}
@elseif($kopPreset === 'classic')
    <div class="kop">
        <div class="kementerian">{{ $labelKementerian ?? 'KEMENTERIAN PENDIDIKAN DASAR DAN MENENGAH' }}</div>
        <h2>{{ $sekolah->nama_sekolah ?? 'Sekolah Dasar' }}</h2>
        <p>{{ $sekolah->alamat ?? '' }}{{ isset($sekolah->kecamatan) ? ', Kec. ' . $sekolah->kecamatan : '' }}</p>
        <p>NPSN: {{ $sekolah->npsn ?? '-' }} &bull; Telp: {{ $sekolah->telepon ?? '-' }}</p>
    </div>
@else
    <div class="kop-flex">
        @if(in_array($kopPreset, ['set_1', 'set_2'], true))
            @if($logoPemdaUrl)
                <img src="{{ $logoPemdaUrl }}" alt="Logo Pemda" class="kop-logo">
            @elseif($logoSekolahUrl && $kopPreset === 'set_2')
                <img src="{{ $logoSekolahUrl }}" alt="Logo Sekolah" class="kop-logo">
            @else
                <div style="width:72px;flex-shrink:0"></div>
            @endif
        @endif

        <div class="kop-center">
            @foreach(($t['kop_baris'] ?? []) as $baris)
                @if(!empty(trim((string) ($baris['text'] ?? ''))))
                    <div style="font-weight:{{ !empty($baris['bold']) ? 'bold' : 'normal' }};font-size:{{ (int) ($baris['size'] ?? 12) }}px;color:{{ $baris['color'] ?? '#000000' }}">
                        {{ $baris['text'] }}
                    </div>
                @endif
            @endforeach
        </div>

        @if(in_array($kopPreset, ['set_1', 'set_3'], true))
            @if($logoSekolahUrl)
                <img src="{{ $logoSekolahUrl }}" alt="Logo Sekolah" class="kop-logo">
            @elseif($logoPemdaUrl && $kopPreset === 'set_3')
                <img src="{{ $logoPemdaUrl }}" alt="Logo Pemda" class="kop-logo">
            @else
                <div style="width:72px;flex-shrink:0"></div>
            @endif
        @endif
    </div>
@endif
