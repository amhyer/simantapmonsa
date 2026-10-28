@extends('layouts.admin-with-tabs')

@section('title', 'Detail Hasil Tryout - Admin')

@section('page_title', 'Detail Hasil Tryout')
@section('page_subtitle', $rincian['siswa_nama'])

@section('content')
    @php
        $waktu = fn ($nilai) => $nilai ? \Illuminate\Support\Carbon::parse($nilai)->format('d/m/Y H:i') : null;
    @endphp

    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Informasi Hasil</h3>
            <a href="{{ route('admin.tryout.results.index') }}" class="btn btn-sm">Kembali</a>
        </div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:160px 1fr;gap:8px 16px;font-size:13px">
                <div style="color:var(--muted)">Siswa</div>
                <div><strong>{{ $rincian['siswa_nama'] }}</strong></div>

                <div style="color:var(--muted)">NIS / Kelas</div>
                <div>{{ $rincian['siswa_nis'] }} / {{ $rincian['siswa_kelas'] }}</div>

                <div style="color:var(--muted)">Sesi</div>
                <div>{{ $rincian['sesi_nama'] }}</div>

                <div style="color:var(--muted)">Paket</div>
                <div>{{ $rincian['paket_nama'] }}</div>

                <div style="color:var(--muted)">Percobaan</div>
                <div>Ke-{{ $rincian['attempt_number'] }}</div>

                <div style="color:var(--muted)">Waktu Mulai</div>
                <div>{{ $waktu($rincian['waktu_mulai']) ?? '-' }}</div>

                <div style="color:var(--muted)">Waktu Selesai</div>
                <div>{{ $waktu($rincian['waktu_selesai']) ?? 'Belum selesai' }}</div>

                <div style="color:var(--muted)">Durasi</div>
                <div>
                    @if($rincian['durasi_detik'])
                        {{ floor($rincian['durasi_detik'] / 60) }} menit {{ $rincian['durasi_detik'] % 60 }} detik
                    @else
                        -
                    @endif
                </div>

                <div style="color:var(--muted)">Status</div>
                <div>
                    <span class="tag {{ $rincian['lulus'] ? 'tag-ok' : 'tag-bad' }}">
                        {{ $rincian['lulus'] ? 'Lulus' : 'Tidak Lulus' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-4" style="margin-bottom:16px">
        <div class="stat">
            <div class="label">Nilai Total</div>
            <div class="value">{{ number_format($rincian['nilai_total'], 1) }}</div>
        </div>
        <div class="stat gold">
            <div class="label">Nilai Maksimal</div>
            <div class="value">{{ number_format($rincian['nilai_maksimal'], 1) }}</div>
        </div>
        <div class="stat ok">
            <div class="label">Persentase</div>
            <div class="value">{{ number_format($rincian['persentase'], 1) }}%</div>
        </div>
        <div class="stat">
            <div class="label">Peringkat</div>
            <div class="value">{{ $rincian['peringkat'] ?? '-' }}</div>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Nilai per Mata Pelajaran</h3>
        </div>
        <div class="card-body tight">
            @php $detailMapel = $rincian['detail_mapel']; @endphp
            @if(empty($detailMapel))
                <div class="empty-state">
                    <div class="icon">📚</div>
                    <h4>Rincian nilai tidak tersedia</h4>
                    <p>Ringkasan historis dan jawaban untuk percobaan ini tidak tersedia untuk menghitung ulang nilai per mata pelajaran.</p>
                </div>
            @else
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Mata Pelajaran</th>
                                <th>Skor</th>
                                <th>Maksimal</th>
                                <th>Persentase</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($detailMapel as $index => $mapel)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $mapel['nama_mapel'] ?? '-' }}</td>
                                    <td>{{ number_format($mapel['skor'] ?? 0, 1) }}</td>
                                    <td>{{ number_format($mapel['maksimal'] ?? 0, 1) }}</td>
                                    <td>{{ number_format($mapel['persentase'] ?? 0, 1) }}%</td>
                                    <td>
                                        <span class="tag {{ !empty($mapel['lulus']) ? 'tag-ok' : 'tag-warn' }}">
                                            {{ !empty($mapel['lulus']) ? 'Tuntas' : 'Belum Tuntas' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Analisis &amp; Rekomendasi</h3>
        </div>
        <div class="card-body">
            <div style="font-size:13px;display:grid;gap:12px">
                <div>
                    <div style="color:var(--muted);margin-bottom:4px">Mata pelajaran terlemah</div>
                    @if(empty($rincian['kelemahan']))
                        <span class="tag tag-mut">Tidak ada data</span>
                    @else
                        <div style="display:flex;flex-wrap:wrap;gap:6px">
                            @foreach($rincian['kelemahan'] as $lemah)
                                <span class="tag tag-warn">
                                    {{ $lemah['nama_mapel'] ?? '-' }} ({{ number_format($lemah['persentase'] ?? 0, 1) }}%)
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div>
                    <div style="color:var(--muted);margin-bottom:4px">Rekomendasi pembelajaran</div>
                    <div>{{ $rincian['rekomendasi'] ?: '-' }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection
