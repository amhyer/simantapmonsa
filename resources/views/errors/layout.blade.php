@php
    // Halaman error sengaja MANDIRI: tanpa directive Vite dan tanpa CDN.
    // Halaman ini bisa diminta sebelum aset frontend selesai dibangun, dan
    // saat server sedang bermasalah — dependensi eksternal hanya memperburuk.
    $kode = $kode ?? 500;
    $judul = $judul ?? 'Terjadi kesalahan';
    $pesan = $pesan ?? 'Permintaan Anda tidak dapat diselesaikan.';
    $tombol = $tombol ?? null;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $kode }} — {{ $judul }} · {{ config('app.name', 'SIMANTAP') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <style>
        :root { --navy: #1F3864; --gold: #B8860B; --muted: #667085; --line: #E4E7EC; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #F5F7FB; color: #101828; line-height: 1.6;
            min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px;
        }
        .card {
            background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(31,56,100,.10);
            max-width: 480px; width: 100%; padding: 40px 32px; text-align: center;
        }
        .brand { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 24px; }
        .brand b { font-size: 20px; font-weight: 800; color: var(--navy); letter-spacing: .5px; }
        .brand .dot { width: 10px; height: 10px; border-radius: 50%; background: var(--gold); }
        .kode {
            font-size: 72px; font-weight: 800; color: var(--navy); line-height: 1;
            letter-spacing: -2px; margin-bottom: 8px;
        }
        .kode span { color: var(--gold); }
        h1 { font-size: 19px; font-weight: 700; margin-bottom: 12px; }
        p { font-size: 14px; color: var(--muted); margin-bottom: 28px; }
        .aksi { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        a.btn {
            display: inline-block; padding: 11px 20px; border-radius: 10px;
            font-size: 14px; font-weight: 700; text-decoration: none; transition: .15s;
        }
        a.utama { background: var(--navy); color: #fff; }
        a.utama:hover { background: #2B4A80; }
        a.kedua { background: #fff; color: var(--navy); border: 1.5px solid var(--line); }
        a.kedua:hover { border-color: var(--navy); }
        .catatan { margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--line); font-size: 12px; color: #98A2B3; }
        @media (prefers-reduced-motion: no-preference) { .card { animation: naik .25s ease-out; } }
        @keyframes naik { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">
            <span class="dot"></span>
            <b>{{ config('app.name', 'SIMANTAP') }}</b>
        </div>

        <div class="kode">{{ $kode }}<span>.</span></div>
        <h1>{{ $judul }}</h1>
        <p>{{ $pesan }}</p>

        <div class="aksi">
            <a class="btn utama" href="{{ url('/') }}">Ke beranda</a>
            @if ($tombol)
                <a class="btn kedua" href="{{ $tombol['url'] }}">{{ $tombol['label'] }}</a>
            @else
                <a class="btn kedua" href="javascript:history.back()">Kembali</a>
            @endif
        </div>

        <div class="catatan">
            Bila masalah ini berlanjut, hubungi administrator sekolah dan sebutkan kode
            <strong>{{ $kode }}</strong> serta waktu kejadiannya.
        </div>
    </div>
</body>
</html>
