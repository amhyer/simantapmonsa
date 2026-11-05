@php
    $palettes = [
        'violet' => ['bg' => 'linear-gradient(135deg, #4C1D95 0%, #6D28D9 100%)', 'accent' => '#5B21B6', 'soft' => '#F5F3FF', 'border' => '#DDD6FE'],
        'blue' => ['bg' => 'linear-gradient(135deg, #1E3A8A 0%, #2563EB 100%)', 'accent' => '#1D4ED8', 'soft' => '#EFF6FF', 'border' => '#BFDBFE'],
        'emerald' => ['bg' => 'linear-gradient(135deg, #064E3B 0%, #059669 100%)', 'accent' => '#047857', 'soft' => '#ECFDF5', 'border' => '#A7F3D0'],
        'amber' => ['bg' => 'linear-gradient(135deg, #78350F 0%, #D97706 100%)', 'accent' => '#B45309', 'soft' => '#FFFBEB', 'border' => '#FDE68A'],
    ];
    $theme = $palettes[$card['tema'] ?? 'blue'] ?? $palettes['blue'];
@endphp

<div class="simantap-id-card simantap-id-card-back" style="width:85.6mm;height:54mm;min-width:85.6mm;min-height:54mm;max-width:85.6mm;max-height:54mm;background:#fff;border:1px solid {{ $theme['border'] }};border-radius:3.18mm;overflow:hidden;box-shadow:0 3px 12px rgba(16,24,40,.08);font-family:Inter,system-ui,-apple-system,sans-serif;color:#101828;display:flex;flex-direction:column;justify-content:space-between;break-inside:avoid;page-break-inside:avoid;box-sizing:border-box;position:relative">
    {{-- Header Sisi Belakang (7.5mm) --}}
    <div style="background:{{ $theme['bg'] }};color:#fff;padding:1.4mm 3.2mm;display:flex;align-items:center;justify-content:space-between;gap:2mm;height:7.5mm;box-sizing:border-box">
        <div style="min-width:0;flex:1">
            <div style="font-size:5.8pt;font-weight:700;letter-spacing:.6px;text-transform:uppercase;opacity:.95;line-height:1">TATA TERTIB &amp; KETENTUAN KARTU</div>
            <div style="font-size:6.5pt;font-weight:600;opacity:.85;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.2;margin-top:0.3mm">
                {{ $card['sekolah']['nama'] ?? 'SIMANTAP Sekolah' }}
            </div>
        </div>
        <div style="font-size:5pt;font-weight:700;padding:0.4mm 1.4mm;border-radius:0.8mm;background:rgba(255,255,255,.2);border:0.5px solid rgba(255,255,255,.3);white-space:nowrap;line-height:1">
            SISI BELAKANG
        </div>
    </div>

    {{-- Isi Tata Tertib & Pengesahan (41mm) --}}
    <div style="padding:1.6mm 3.2mm 0.8mm;flex:1;display:flex;flex-direction:column;justify-content:space-between;box-sizing:border-box;overflow:hidden">
        {{-- 5 Poin Tata Tertib Ringkas --}}
        <ol style="margin:0;padding-left:3.2mm;font-size:5.4pt;line-height:1.25;color:#344054">
            <li style="margin-bottom:0.4mm">Kartu identitas resmi bukti presensi &amp; keanggotaan sah di <strong>{{ $card['sekolah']['nama'] ?? 'sekolah' }}</strong>.</li>
            <li style="margin-bottom:0.4mm">Wajib dibawa setiap hari masuk &amp; digunakan scan absensi (jam masuk, mapel, &amp; jam pulang).</li>
            <li style="margin-bottom:0.4mm">Dilarang merusak, meminjamkan, atau memindahtangankan kartu kepada pihak lain.</li>
            <li style="margin-bottom:0.4mm">Apabila kartu hilang atau rusak, pemegang kartu wajib segera melapor ke admin / TU sekolah.</li>
            <li>Barangsiapa menemukan kartu ini dimohon mengembalikan ke alamat sekolah tertera di bawah.</li>
        </ol>

        {{-- Kontak Sekolah & Pengesahan Kepala Sekolah --}}
        <div style="display:grid;grid-template-columns:1.35fr 1fr;gap:2mm;margin-top:0.8mm;padding-top:0.8mm;border-top:0.5px dashed #E4E7EC;align-items:flex-end">
            <div style="font-size:5pt;color:#475467;line-height:1.2;min-width:0">
                <div style="font-weight:700;color:#1D2939;font-size:5.2pt;margin-bottom:0.3mm">Alamat &amp; Kontak:</div>
                <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="{{ $card['sekolah']['alamat'] ?? '-' }}">{{ $card['sekolah']['alamat'] ?? '-' }}</div>
                <div>{{ $card['sekolah']['kota'] ?? '-' }}</div>
                @if(!empty($card['sekolah']['telepon']) && $card['sekolah']['telepon'] !== '-')
                    <div>Telp: {{ $card['sekolah']['telepon'] }}</div>
                @endif
                @if(!empty($card['sekolah']['email']) && $card['sekolah']['email'] !== '-')
                    <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">Email: {{ $card['sekolah']['email'] }}</div>
                @endif
            </div>

            <div style="text-align:center;font-size:5pt;color:#1D2939;min-width:0">
                <div style="color:#667085;font-size:4.8pt">{{ $card['sekolah']['kota'] ?? 'Makassar' }}, {{ now()->translatedFormat('M Y') }}</div>
                <div style="font-weight:600;margin-top:0.2mm;font-size:5pt">Kepala Sekolah,</div>
                <div style="height:4.2mm;display:flex;align-items:center;justify-content:center;color:#98A2B3;font-size:4.2pt;letter-spacing:0.5px;border:0.4px dashed #D0D5DD;border-radius:0.8mm;margin:0.4mm 0">
                    [ CAP / TTD RESMI ]
                </div>
                <div style="font-weight:700;font-size:5.2pt;text-decoration:underline;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="{{ $card['sekolah']['kepala_sekolah'] ?? 'Kepala Sekolah' }}">
                    {{ $card['sekolah']['kepala_sekolah'] ?? 'Kepala Sekolah' }}
                </div>
            </div>
        </div>
    </div>

    {{-- Footer Barcode & Serial (5.5mm) --}}
    <div style="background:{{ $theme['soft'] }};border-top:0.5px solid {{ $theme['border'] }};padding:0.8mm 3.2mm;font-size:5.2pt;color:#475467;display:flex;justify-content:space-between;align-items:center;height:5.5mm;box-sizing:border-box">
        <span style="font-family:monospace;font-weight:700;letter-spacing:.4px;color:{{ $theme['accent'] }};font-size:5.8pt">{{ $card['kode_kartu'] }}</span>
        <span style="font-size:4.8pt;color:#667085">KTP Standard (85.6 × 54 mm)</span>
    </div>
</div>
