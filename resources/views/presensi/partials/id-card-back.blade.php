@php
    $palettes = [
        'violet' => ['bg' => 'linear-gradient(135deg, #4C1D95 0%, #6D28D9 100%)', 'accent' => '#5B21B6', 'soft' => '#F5F3FF', 'border' => '#DDD6FE'],
        'blue' => ['bg' => 'linear-gradient(135deg, #1E3A8A 0%, #2563EB 100%)', 'accent' => '#1D4ED8', 'soft' => '#EFF6FF', 'border' => '#BFDBFE'],
        'emerald' => ['bg' => 'linear-gradient(135deg, #064E3B 0%, #059669 100%)', 'accent' => '#047857', 'soft' => '#ECFDF5', 'border' => '#A7F3D0'],
        'amber' => ['bg' => 'linear-gradient(135deg, #78350F 0%, #D97706 100%)', 'accent' => '#B45309', 'soft' => '#FFFBEB', 'border' => '#FDE68A'],
    ];
    $theme = $palettes[$card['tema'] ?? 'blue'] ?? $palettes['blue'];
@endphp

<div class="simantap-id-card simantap-id-card-back" style="width:100%;max-width:360px;min-height:220px;background:#fff;border:1.5px solid {{ $theme['border'] }};border-radius:14px;overflow:hidden;box-shadow:0 4px 16px rgba(16,24,40,.08);font-family:Inter,system-ui,-apple-system,sans-serif;color:#101828;display:flex;flex-direction:column;justify-content:space-between;break-inside:avoid;page-break-inside:avoid;box-sizing:border-box">
    {{-- Header Sisi Belakang --}}
    <div style="background:{{ $theme['bg'] }};color:#fff;padding:8px 14px;display:flex;align-items:center;justify-content:space-between;gap:8px">
        <div style="min-width:0">
            <div style="font-size:9.5px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;opacity:.95">TATA TERTIB &amp; KETENTUAN KARTU</div>
            <div style="font-size:11px;font-weight:600;opacity:.85;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $card['sekolah']['nama'] ?? 'SIMANTAP Sekolah' }}</div>
        </div>
        <div style="font-size:9px;font-weight:700;padding:2px 6px;border-radius:4px;background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.3);white-space:nowrap">
            SISI BELAKANG
        </div>
    </div>

    {{-- Isi Tata Tertib --}}
    <div style="padding:10px 14px 6px;flex:1">
        <ol style="margin:0;padding-left:16px;font-size:10.5px;line-height:1.45;color:#344054">
            <li style="margin-bottom:3px">Kartu ini adalah kartu identitas resmi presensi dan bukti keanggotaan di lingkungan <strong>{{ $card['sekolah']['nama'] ?? 'sekolah' }}</strong>.</li>
            <li style="margin-bottom:3px">Wajib dibawa setiap hari masuk sekolah dan digunakan untuk pemindaian absensi digital (jam masuk, mata pelajaran, &amp; jam pulang).</li>
            <li style="margin-bottom:3px">Dilarang merusak, memindahtangankan, atau meminjamkan kartu identitas ini kepada orang lain.</li>
            <li style="margin-bottom:3px">Apabila kartu hilang atau rusak, pemegang kartu wajib segera melapor ke pihak tata usaha/admin sekolah.</li>
            <li>Barangsiapa menemukan kartu ini dimohon mengembalikannya ke alamat sekolah tertera di bawah ini.</li>
        </ol>

        {{-- Kontak & Pengesahan --}}
        <div style="display:grid;grid-template-columns:1.3fr 1fr;gap:8px;margin-top:8px;padding-top:6px;border-top:1px dashed #E4E7EC;align-items:flex-end">
            <div style="font-size:9.5px;color:#475467;line-height:1.35">
                <div style="font-weight:700;color:#1D2939;margin-bottom:2px">Alamat &amp; Kontak Sekolah:</div>
                <div>{{ $card['sekolah']['alamat'] ?? '-' }}</div>
                <div>{{ $card['sekolah']['kota'] ?? '-' }}</div>
                @if(!empty($card['sekolah']['telepon']) && $card['sekolah']['telepon'] !== '-')
                    <div style="margin-top:2px">Telp: {{ $card['sekolah']['telepon'] }}</div>
                @endif
                @if(!empty($card['sekolah']['email']) && $card['sekolah']['email'] !== '-')
                    <div>Email: {{ $card['sekolah']['email'] }}</div>
                @endif
            </div>

            <div style="text-align:center;font-size:9.5px;color:#1D2939">
                <div style="color:#667085;font-size:9px">{{ $card['sekolah']['kota'] ?? 'Makassar' }}, {{ now()->translatedFormat('F Y') }}</div>
                <div style="font-weight:600;margin-top:1px">Kepala Sekolah,</div>
                <div style="height:28px;display:flex;align-items:center;justify-content:center;color:#98A2B3;font-size:8px;letter-spacing:1px">
                    [ CAP / TTD RESMI ]
                </div>
                <div style="font-weight:700;font-size:9.5px;text-decoration:underline">{{ $card['sekolah']['kepala_sekolah'] ?? 'Kepala Sekolah' }}</div>
            </div>
        </div>
    </div>

    {{-- Footer Barcode & Serial --}}
    <div style="background:{{ $theme['soft'] }};border-top:1px solid {{ $theme['border'] }};padding:5px 14px;font-size:9.5px;color:#475467;display:flex;justify-content:space-between;align-items:center">
        <span style="font-family:monospace;font-weight:700;letter-spacing:.6px;color:{{ $theme['accent'] }}">{{ $card['kode_kartu'] }}</span>
        <span style="font-size:9px;color:#667085">SIMANTAP Smart School ID</span>
    </div>
</div>
