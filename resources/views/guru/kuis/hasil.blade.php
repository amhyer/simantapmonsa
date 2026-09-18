@extends('layouts.app')

@section('title', 'Hasil Kuis - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Hasil: ' . $kuis->judul)
@section('page_subtitle', $kuis->jenis . ' · ' . \Carbon\Carbon::parse($kuis->tanggal)->translatedFormat('d F Y'))

@section('header_actions')
    <a href="{{ route('guru.kuis.index') }}" class="btn btn-ghost btn-sm">← Kembali</a>
@endsection

@section('content')
    <div class="grid grid-3" style="margin-bottom:16px">
        <div class="stat">
            <div class="label">Total peserta</div>
            <div class="value">{{ $hasil->count() }}</div>
        </div>
        <div class="stat gold">
            <div class="label">Rata-rata</div>
            <div class="value">{{ $hasil->count() ? number_format($hasil->avg('skor'), 1) : '—' }}</div>
        </div>
        <div class="stat {{ $hasil->where('tuntas', true)->count() / max($hasil->count(), 1) >= 0.75 ? 'ok' : 'bad' }}">
            <div class="label">Tuntas</div>
            <div class="value">{{ $hasil->where('tuntas', true)->count() }}/{{ $hasil->count() }}</div>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Yang sudah mengerjakan</h3>
            <span class="tag tag-ok">{{ $hasil->count() }} siswa</span>
        </div>
        <div class="card-body tight">
            @if($hasil->count())
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Siswa</th>
                                <th style="padding:11px 14px;text-align:right;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Skor</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Benar</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($hasil->sortByDesc('skor') as $h)
                                <tr>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                        <div style="display:flex;align-items:center;gap:11px">
                                            <span class="avatar">{{ substr($h->siswa->nama_peserta_didik ?? '?', 0, 2) }}</span>
                                            <b>{{ $h->siswa->nama_peserta_didik ?? '—' }}</b>
                                        </div>
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:right"><b>{{ $h->skor }}</b></td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">{{ $h->benar }}/{{ $h->total }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">
                                        @if($h->tuntas)
                                            <span class="tag tag-ok">Tuntas</span>
                                        @else
                                            <span class="tag tag-bad">Belum</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty"><div class="icon">📝</div><b>Belum ada yang mengerjakan</b></div>
            @endif
        </div>
    </div>

    @if($belum->count())
        <div class="card">
            <div class="card-header">
                <h3>Belum mengerjakan</h3>
                <span class="tag tag-bad">{{ $belum->count() }} siswa</span>
            </div>
            <div class="card-body tight">
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse">
                        <tbody>
                            @foreach($belum as $s)
                                <tr>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                        <div style="display:flex;align-items:center;gap:11px">
                                            <span class="avatar">{{ substr($s->nama_peserta_didik, 0, 2) }}</span>
                                            <b>{{ $s->nama_peserta_didik }}</b>
                                        </div>
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:right"><span class="tag tag-mut">Belum</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
@endsection
