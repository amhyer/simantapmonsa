<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $judul ?? 'Cetak Kartu Identitas' }} - SIMANTAP</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css'])
    <style>
        body {
            margin: 0;
            background: #F2F4F7;
            font-family: Inter, system-ui, -apple-system, sans-serif;
            color: #101828;
        }
        .print-toolbar {
            position: sticky;
            top: 0;
            z-index: 50;
            background: #fff;
            border-bottom: 1px solid #E4E7EC;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
        .print-sheet {
            max-width: 900px;
            margin: 24px auto;
            padding: 0 16px 40px;
        }
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, 85.6mm);
            gap: 20px 24px;
            justify-content: center;
        }
        .simantap-qr-box svg {
            width: 100% !important;
            height: 100% !important;
            display: block;
        }
        .ktp-size-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #EFF8FF;
            color: #175CD3;
            border: 1px solid #B2DDFF;
            border-radius: 999px;
            padding: 3px 10px;
            font-size: 11.5px;
            font-weight: 600;
        }
        @media print {
            @page {
                size: A4 portrait;
                margin: 8mm 12mm;
            }
            body {
                background: #fff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .print-toolbar {
                display: none !important;
            }
            .print-sheet {
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .cards-grid {
                display: grid !important;
                grid-template-columns: 85.6mm 85.6mm !important;
                gap: 2.5mm 10mm !important;
                justify-content: center !important;
                page-break-inside: auto;
            }
            .simantap-id-card {
                width: 85.6mm !important;
                height: 54mm !important;
                min-width: 85.6mm !important;
                min-height: 54mm !important;
                max-width: 85.6mm !important;
                max-height: 54mm !important;
                box-shadow: none !important;
                border: 0.25mm dashed #98A2B3 !important; /* Panduan garis potong KTP */
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }
        }
    </style>
</head>
<body>
    <div class="print-toolbar">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <strong style="font-size:15px">{{ $judul ?? 'Cetak Kartu Identitas' }}</strong>
            @if(!empty($subjudul))
                <span style="color:#667085;font-size:13px">· {{ $subjudul }}</span>
            @endif
            <span class="ktp-size-badge" title="Ukuran resmi KTP / ISO/IEC 7810 ID-1 standard">
                📏 Ukuran KTP: 85.6 × 54.0 mm (10 Kartu/A4)
            </span>
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            {{-- Sisi Cetak Selector --}}
            @php
                $sisi = request('sisi', 'depan');
            @endphp
            <div style="display:flex;gap:3px;align-items:center;background:#F2F4F7;padding:3px;border-radius:6px">
                <span style="font-size:11.5px;color:#475467;padding:0 4px;font-weight:600">Sisi:</span>
                <a href="{{ request()->fullUrlWithQuery(['sisi' => 'depan']) }}"
                   class="btn btn-sm"
                   style="padding:3px 9px;font-size:12px;{{ $sisi === 'depan' ? 'background:#1D2939;color:#fff;border-color:#1D2939;font-weight:700' : 'background:#fff;color:#344054' }}">
                    Depan
                </a>
                <a href="{{ request()->fullUrlWithQuery(['sisi' => 'belakang']) }}"
                   class="btn btn-sm"
                   style="padding:3px 9px;font-size:12px;{{ $sisi === 'belakang' ? 'background:#1D2939;color:#fff;border-color:#1D2939;font-weight:700' : 'background:#fff;color:#344054' }}">
                    Belakang
                </a>
                <a href="{{ request()->fullUrlWithQuery(['sisi' => 'bolak-balik']) }}"
                   class="btn btn-sm"
                   style="padding:3px 9px;font-size:12px;{{ $sisi === 'bolak-balik' ? 'background:#1D2939;color:#fff;border-color:#1D2939;font-weight:700' : 'background:#fff;color:#344054' }}"
                   title="Tampilkan sisi depan dan belakang berdampingan (cocok untuk dilipat & dilaminasi)">
                    Bolak-Balik
                </a>
            </div>

            @if(!empty($rombels) && $rombels->isNotEmpty())
                <form method="GET" action="{{ url()->current() }}" style="display:flex;align-items:center;gap:6px">
                    <input type="hidden" name="sisi" value="{{ $sisi }}">
                    <select name="rombel_id" onchange="this.form.submit()" style="padding:4px 8px;font-size:13px;border:1px solid #D0D5DD;border-radius:6px;background:#fff">
                        <option value="">Semua Kelas (Batch)</option>
                        @foreach($rombels as $r)
                            <option value="{{ $r->id }}" @selected((string)$current_rombel_id === (string)$r->id)>Kelas {{ $r->nama_rombel }}</option>
                        @endforeach
                    </select>
                </form>
            @endif

            @if(!empty($pagination) && ($pagination['total_pages'] ?? 1) > 1)
                <div style="display:flex;gap:4px;align-items:center;background:#F2F4F7;padding:3px;border-radius:6px">
                    <span style="font-size:12px;color:#475467;padding:0 6px">Batch:</span>
                    @for($p = 1; $p <= $pagination['total_pages']; $p++)
                        <a href="{{ request()->fullUrlWithQuery(['page' => $p, 'sisi' => $sisi]) }}"
                           class="btn btn-sm"
                           style="padding:2px 8px;font-size:12px;{{ ($pagination['current'] ?? 1) === $p ? 'background:#1D2939;color:#fff;border-color:#1D2939' : 'background:#fff;color:#344054' }}">
                            {{ $p }}
                        </a>
                    @endfor
                </div>
            @endif

            @if(!empty($kembaliUrl))
                <a href="{{ $kembaliUrl }}" class="btn btn-ghost btn-sm">← Kembali</a>
            @endif
            <button type="button" class="btn btn-sm" onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
        </div>
    </div>

    <div class="print-sheet">
        @if(empty($cards))
            <div style="background:#fff;border:1px solid #E4E7EC;border-radius:12px;padding:32px;text-align:center;color:#667085">
                Belum ada data kartu identitas yang dapat dicetak pada pilihan ini.
            </div>
        @else
            <div class="cards-grid">
                @foreach($cards as $card)
                    @if($sisi === 'belakang')
                        @include('presensi.partials.id-card-back', ['card' => $card])
                    @elseif($sisi === 'bolak-balik')
                        @include('presensi.partials.id-card', ['card' => $card])
                        @include('presensi.partials.id-card-back', ['card' => $card])
                    @else
                        @include('presensi.partials.id-card', ['card' => $card])
                    @endif
                @endforeach
            </div>
        @endif
    </div>
</body>
</html>
