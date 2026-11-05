@php
    $palettes = [
        'violet' => ['bg' => 'linear-gradient(135deg, #4C1D95 0%, #6D28D9 100%)', 'accent' => '#5B21B6', 'soft' => '#F5F3FF', 'border' => '#DDD6FE'],
        'blue' => ['bg' => 'linear-gradient(135deg, #1E3A8A 0%, #2563EB 100%)', 'accent' => '#1D4ED8', 'soft' => '#EFF6FF', 'border' => '#BFDBFE'],
        'emerald' => ['bg' => 'linear-gradient(135deg, #064E3B 0%, #059669 100%)', 'accent' => '#047857', 'soft' => '#ECFDF5', 'border' => '#A7F3D0'],
        'amber' => ['bg' => 'linear-gradient(135deg, #78350F 0%, #D97706 100%)', 'accent' => '#B45309', 'soft' => '#FFFBEB', 'border' => '#FDE68A'],
    ];
    $theme = $palettes[$card['tema'] ?? 'blue'] ?? $palettes['blue'];
@endphp

<div class="simantap-id-card" style="width:100%;max-width:360px;background:#fff;border:1.5px solid {{ $theme['border'] }};border-radius:14px;overflow:hidden;box-shadow:0 4px 16px rgba(16,24,40,.08);font-family:Inter,system-ui,-apple-system,sans-serif;color:#101828;break-inside:avoid;page-break-inside:avoid">
    <div style="background:{{ $theme['bg'] }};color:#fff;padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px">
        <div style="min-width:0">
            <div style="font-size:10px;font-weight:700;letter-spacing:.9px;text-transform:uppercase;opacity:.9">{{ $card['badge'] }}</div>
            <div style="font-size:13px;font-weight:800;line-height:1.25;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $card['sekolah']['nama'] ?? 'SIMANTAP Sekolah' }}</div>
            <div style="font-size:10.5px;opacity:.85">NPSN: {{ $card['sekolah']['npsn'] ?? '-' }} · {{ $card['sekolah']['kota'] ?? '-' }}</div>
        </div>
        <div style="width:34px;height:34px;border-radius:9px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;flex-shrink:0;border:1px solid rgba(255,255,255,.3)">
            SM
        </div>
    </div>

    <div style="padding:14px;display:grid;grid-template-columns:1fr auto;gap:12px;align-items:center">
        <div style="min-width:0">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
                @if(!empty($card['foto_url']))
                    <img src="{{ $card['foto_url'] }}" alt="Foto {{ $card['nama'] }}" style="width:52px;height:52px;border-radius:12px;object-fit:cover;border:2px solid {{ $theme['border'] }};flex-shrink:0">
                @else
                    <div style="width:52px;height:52px;border-radius:12px;background:{{ $theme['soft'] }};color:{{ $theme['accent'] }};border:1.5px solid {{ $theme['border'] }};display:flex;align-items:center;justify-content:center;font-weight:800;font-size:18px;flex-shrink:0">
                        {{ $card['inisial'] ?? 'ID' }}
                    </div>
                @endif
                <div style="min-width:0">
                    <div style="font-size:14px;font-weight:800;line-height:1.25;color:#101828;word-break:break-word">{{ $card['nama'] }}</div>
                    <span style="display:inline-block;margin-top:3px;padding:2px 8px;border-radius:999px;background:{{ $theme['soft'] }};color:{{ $theme['accent'] }};font-size:10.5px;font-weight:700">{{ $card['kode_kartu'] }}</span>
                </div>
            </div>

            <div style="display:grid;gap:5px;font-size:11.5px;border-top:1px dashed #E4E7EC;padding-top:8px">
                <div>
                    <span style="color:#667085;display:block;font-size:10px;text-transform:uppercase;letter-spacing:.4px">{{ $card['nomor_induk_label'] }}</span>
                    <strong style="color:#1D2939">{{ $card['nomor_induk'] }}</strong>
                </div>
                <div>
                    <span style="color:#667085;display:block;font-size:10px;text-transform:uppercase;letter-spacing:.4px">{{ $card['unit_label'] }}</span>
                    <strong style="color:#1D2939">{{ $card['unit'] }}</strong>
                </div>
            </div>
        </div>

        <div style="text-align:center;flex-shrink:0">
            @if(!empty($card['qr_svg']))
                <div class="simantap-qr-box" style="width:118px;height:118px;padding:5px;background:#fff;border:1.5px solid #E4E7EC;border-radius:10px;display:flex;align-items:center;justify-content:center;overflow:hidden">
                    <style>.simantap-qr-box svg{width:100%;height:100%;display:block}</style>
                    {!! $card['qr_svg'] !!}
                </div>
                <div style="font-size:9.5px;color:#475467;font-weight:600;margin-top:4px">SCAN ABSENSI QR</div>
            @elseif(!empty($card['eksternal_terhubung']))
                <div style="width:118px;min-height:108px;padding:8px;background:#F9FAFB;border:1.5px dashed #D0D5DD;border-radius:10px;display:flex;flex-direction:column;align-items:center;justify-content:center;font-size:10.5px;color:#344054">
                    <strong style="color:#027A48">Kartu Fisik Aktif</strong>
                    <span style="margin-top:4px;color:#667085">QR eksternal sudah terhubung</span>
                </div>
            @else
                <div style="width:118px;min-height:108px;padding:8px;background:#FEF3F2;border:1.5px dashed #FECDCA;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:10.5px;color:#B42318">
                    QR belum diterbitkan
                </div>
            @endif
        </div>
    </div>

    <div style="background:{{ $theme['soft'] }};border-top:1px solid {{ $theme['border'] }};padding:7px 14px;font-size:10px;color:#475467;display:flex;justify-content:space-between;align-items:center;gap:8px">
        <span>{{ $card['instruksi_scan'] ?? 'Kartu identitas resmi presensi SIMANTAP.' }}</span>
    </div>
</div>
