@php
    $palettes = [
        'violet' => ['bg' => 'linear-gradient(135deg, #4C1D95 0%, #6D28D9 100%)', 'accent' => '#5B21B6', 'soft' => '#F5F3FF', 'border' => '#DDD6FE'],
        'blue' => ['bg' => 'linear-gradient(135deg, #1E3A8A 0%, #2563EB 100%)', 'accent' => '#1D4ED8', 'soft' => '#EFF6FF', 'border' => '#BFDBFE'],
        'emerald' => ['bg' => 'linear-gradient(135deg, #064E3B 0%, #059669 100%)', 'accent' => '#047857', 'soft' => '#ECFDF5', 'border' => '#A7F3D0'],
        'amber' => ['bg' => 'linear-gradient(135deg, #78350F 0%, #D97706 100%)', 'accent' => '#B45309', 'soft' => '#FFFBEB', 'border' => '#FDE68A'],
    ];
    $theme = $palettes[$card['tema'] ?? 'blue'] ?? $palettes['blue'];
@endphp

<div class="simantap-id-card" style="width:85.6mm;height:54mm;min-width:85.6mm;min-height:54mm;max-width:85.6mm;max-height:54mm;background:#fff;border:1px solid {{ $theme['border'] }};border-radius:3.18mm;overflow:hidden;box-shadow:0 3px 12px rgba(16,24,40,.08);font-family:Inter,system-ui,-apple-system,sans-serif;color:#101828;break-inside:avoid;page-break-inside:avoid;box-sizing:border-box;display:flex;flex-direction:column;justify-content:space-between;position:relative">
    {{-- Header Kartu (10.5mm) --}}
    <div style="background:{{ $theme['bg'] }};color:#fff;padding:2mm 3.2mm;display:flex;align-items:center;justify-content:space-between;gap:2mm;height:10.5mm;box-sizing:border-box">
        <div style="min-width:0;flex:1">
            <div style="font-size:6.2pt;font-weight:700;letter-spacing:.6px;text-transform:uppercase;opacity:.95;line-height:1">{{ $card['badge'] }}</div>
            <div style="font-size:8pt;font-weight:800;line-height:1.2;margin-top:0.4mm;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="{{ $card['sekolah']['nama'] ?? 'SIMANTAP Sekolah' }}">
                {{ $card['sekolah']['nama'] ?? 'SIMANTAP Sekolah' }}
            </div>
            <div style="font-size:5.8pt;opacity:.85;line-height:1.1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                NPSN: {{ $card['sekolah']['npsn'] ?? '-' }} · {{ $card['sekolah']['kota'] ?? '-' }}
            </div>
        </div>
        <div style="width:7mm;height:7mm;border-radius:1.8mm;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:6.5pt;flex-shrink:0;border:0.5px solid rgba(255,255,255,.35)">
            SM
        </div>
    </div>

    {{-- Body Kartu (37.5mm): Foto + Data Diri + QR Code Absensi --}}
    <div style="padding:1.8mm 3.2mm;display:flex;align-items:center;justify-content:space-between;gap:2.5mm;flex:1;box-sizing:border-box">
        {{-- Sisi Kiri: Foto & Identitas Pemegang --}}
        <div style="min-width:0;flex:1">
            <div style="display:flex;align-items:center;gap:2.2mm">
                @if(!empty($card['foto_url']))
                    <img src="{{ $card['foto_url'] }}" alt="Foto {{ $card['nama'] }}" style="width:16mm;height:20mm;border-radius:2mm;object-fit:cover;border:1px solid {{ $theme['border'] }};flex-shrink:0">
                @else
                    <div style="width:16mm;height:20mm;border-radius:2mm;background:{{ $theme['soft'] }};color:{{ $theme['accent'] }};border:1px solid {{ $theme['border'] }};display:flex;align-items:center;justify-content:center;font-weight:800;font-size:9pt;flex-shrink:0">
                        {{ $card['inisial'] ?? 'ID' }}
                    </div>
                @endif
                <div style="min-width:0;flex:1">
                    <div style="font-size:8pt;font-weight:800;line-height:1.2;color:#101828;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden" title="{{ $card['nama'] }}">
                        {{ $card['nama'] }}
                    </div>
                    <span style="display:inline-block;margin-top:0.6mm;padding:0.4mm 1.8mm;border-radius:999px;background:{{ $theme['soft'] }};color:{{ $theme['accent'] }};font-size:5.8pt;font-weight:700;line-height:1">
                        {{ $card['kode_kartu'] }}
                    </span>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5mm;font-size:6.2pt;border-top:0.5px dashed #E4E7EC;margin-top:1.2mm;padding-top:1mm">
                <div style="min-width:0">
                    <span style="color:#667085;display:block;font-size:5.2pt;text-transform:uppercase;letter-spacing:.3px;line-height:1">{{ $card['nomor_induk_label'] }}</span>
                    <strong style="color:#1D2939;display:block;margin-top:0.3mm;font-size:6.2pt;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="{{ $card['nomor_induk'] }}">
                        {{ $card['nomor_induk'] }}
                    </strong>
                </div>
                <div style="min-width:0">
                    <span style="color:#667085;display:block;font-size:5.2pt;text-transform:uppercase;letter-spacing:.3px;line-height:1">{{ $card['unit_label'] }}</span>
                    <strong style="color:#1D2939;display:block;margin-top:0.3mm;font-size:6.2pt;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="{{ $card['unit'] }}">
                        {{ $card['unit'] }}
                    </strong>
                </div>
            </div>
        </div>

        {{-- Sisi Kanan: QR Code Absensi --}}
        <div style="text-align:center;flex-shrink:0;display:flex;flex-direction:column;align-items:center;justify-content:center">
            @if(!empty($card['qr_svg']))
                <div class="simantap-qr-box" style="width:22.5mm;height:22.5mm;padding:0.8mm;background:#fff;border:0.8px solid #E4E7EC;border-radius:1.8mm;display:flex;align-items:center;justify-content:center;overflow:hidden;box-sizing:border-box">
                    <style>.simantap-qr-box svg{width:100%!important;height:100%!important;display:block}</style>
                    {!! $card['qr_svg'] !!}
                </div>
                <div style="font-size:5.2pt;color:#475467;font-weight:700;letter-spacing:.4px;margin-top:0.8mm;line-height:1;text-transform:uppercase">SCAN ABSENSI</div>
            @elseif(!empty($card['eksternal_terhubung']))
                <div style="width:22.5mm;height:22.5mm;padding:1mm;background:#F9FAFB;border:0.8px dashed #D0D5DD;border-radius:1.8mm;display:flex;flex-direction:column;align-items:center;justify-content:center;font-size:5.5pt;color:#344054;text-align:center;box-sizing:border-box">
                    <strong style="color:#027A48;line-height:1">Kartu Fisik</strong>
                    <span style="margin-top:0.5mm;color:#667085;font-size:4.8pt;line-height:1">Terkoneksi</span>
                </div>
                <div style="font-size:5.2pt;color:#027A48;font-weight:700;margin-top:0.8mm;line-height:1">RFID / KARTU AKTIF</div>
            @else
                <div style="width:22.5mm;height:22.5mm;padding:1mm;background:#FEF3F2;border:0.8px dashed #FECDCA;border-radius:1.8mm;display:flex;align-items:center;justify-content:center;font-size:5.2pt;color:#B42318;text-align:center;box-sizing:border-box">
                    {{ !empty($card['qr_revoked']) ? 'Dicabut — hubungi admin' : (!empty($card['qr_aktif']) ? 'QR tidak cocok — hubungi admin' : 'Belum Terbit') }}
                </div>
            @endif
        </div>
    </div>

    {{-- Footer Kartu (6mm) --}}
    <div style="background:{{ $theme['soft'] }};border-top:0.5px solid {{ $theme['border'] }};padding:1mm 3.2mm;font-size:5.6pt;color:#475467;display:flex;justify-content:space-between;align-items:center;gap:1.5mm;height:5.5mm;box-sizing:border-box">
        <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $card['instruksi_scan'] ?? 'Kartu identitas resmi presensi SIMANTAP.' }}</span>
        <span style="font-weight:700;font-size:5pt;color:{{ $theme['accent'] }};white-space:nowrap">ISO 7810 ID-1</span>
    </div>
</div>
