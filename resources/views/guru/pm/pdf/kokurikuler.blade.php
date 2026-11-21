<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rencana Kegiatan Kokurikuler</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #111827; }
        h2 { margin: 0 0 4px; font-size: 14px; text-align: center; }
        .sub { text-align: center; font-size: 10px; color: #4B5563; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #374151; padding: 5px 6px; vertical-align: top; }
        th { background: #F3F4F6; }
        .center { text-align: center; }
    </style>
</head>
<body>
    <h2>PERENCANAAN & DOKUMENTASI KEGIATAN KOKURIKULER</h2>
    <div class="sub">{{ $sekolah?->nama_sekolah ?? 'Sekolah Dasar' }} · Tahun Ajaran {{ $tahunAjaran }}</div>
    <table>
        <thead>
            <tr>
                <th style="width:26px">No</th>
                <th style="width:45px">Kls</th>
                <th style="width:140px">Tema / Judul</th>
                <th style="width:120px">Bentuk Kegiatan</th>
                <th style="width:130px">Dimensi Profil Lulusan</th>
                <th>Tujuan & Rangkaian Kegiatan</th>
                <th style="width:95px">Mitra</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $row)
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td class="center">{{ $row->tingkat }}</td>
                    <td><strong>{{ $row->tema_judul }}</strong><br>{{ $row->tanggal_mulai?->format('d/m/Y') ?? '' }}</td>
                    <td>{{ $row->bentukLabel() }}</td>
                    <td>{{ implode(', ', is_array($row->dimensi_profil) ? $row->dimensi_profil : []) }}</td>
                    <td>
                        @if($row->tujuan)<strong>Tujuan:</strong> {{ $row->tujuan }}<br>@endif
                        @if($row->rangkaian_kegiatan)<strong>Kegiatan:</strong> {!! nl2br(e($row->rangkaian_kegiatan)) !!}@endif
                    </td>
                    <td>{{ $row->mitra ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="center">Belum ada data kegiatan kokurikuler.</td></tr>
            @endforelse
        </tbody>
    </table>
    @include('guru.pm.pdf._ttd')
</body>
</html>
