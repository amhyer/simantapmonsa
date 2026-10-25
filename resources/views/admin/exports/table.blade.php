<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 18mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; }
        h1 { margin: 0 0 5px; font-size: 17px; }
        .subtitle { margin: 0 0 4px; color: #4b5563; font-size: 10px; }
        .generated { margin: 0 0 14px; color: #6b7280; font-size: 8px; }
        table { width: 100%; border-collapse: collapse; table-layout: auto; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { border: 1px solid #cbd5e1; padding: 5px 6px; vertical-align: top; text-align: left; }
        th { background: #1f4e78; color: #fff; font-weight: bold; }
        tbody tr:nth-child(even) { background: #f1f5f9; }
        .empty { text-align: center; color: #6b7280; padding: 16px; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    @if(!empty($subtitle))
        <p class="subtitle">{{ $subtitle }}</p>
    @endif
    <p class="generated">Dibuat pada {{ $generatedAt ?? now()->format('d-m-Y H:i') }}</p>

    <table>
        <thead>
            <tr>
                @foreach($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($row as $cell)
                        <td>{{ is_bool($cell) ? ($cell ? 'Ya' : 'Tidak') : ($cell ?? '-') }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td class="empty" colspan="{{ max(1, count($headers)) }}">Tidak ada data untuk ditampilkan.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
