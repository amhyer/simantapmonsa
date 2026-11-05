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
            max-width: 840px;
            margin: 24px auto;
            padding: 0 16px 40px;
        }
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(335px, 1fr));
            gap: 18px;
            justify-items: center;
        }
        .simantap-qr-box svg {
            width: 100%;
            height: 100%;
            display: block;
        }
        @media print {
            @page {
                size: A4 portrait;
                margin: 10mm;
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
                max-width: none;
                margin: 0;
                padding: 0;
            }
            .cards-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10mm 8mm;
            }
            .simantap-id-card {
                box-shadow: none !important;
                max-width: 100% !important;
            }
        }
    </style>
</head>
<body>
    <div class="print-toolbar">
        <div>
            <strong style="font-size:15px">{{ $judul ?? 'Cetak Kartu Identitas' }}</strong>
            @if(!empty($subjudul))
                <span style="color:#667085;font-size:13px;margin-left:8px">· {{ $subjudul }}</span>
            @endif
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
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
                    @include('presensi.partials.id-card', ['card' => $card])
                @endforeach
            </div>
        @endif
    </div>
</body>
</html>
