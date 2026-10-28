@extends('layouts.admin-with-tabs')

@section('title', 'Riwayat TKA Siswa - Admin')

@section('page_title', 'Riwayat TKA Siswa')
@section('page_subtitle', $siswa->nama_peserta_didik)

@section('content')
    @php
        $statusLabels = [
            'sangat_siap' => 'Sangat Siap',
            'siap' => 'Siap',
            'perlu_bimbingan' => 'Perlu Bimbingan',
            'belum_siap' => 'Belum Siap',
        ];
        $statusTags = [
            'sangat_siap' => 'tag-ok',
            'siap' => 'tag-primary',
            'perlu_bimbingan' => 'tag-warn',
            'belum_siap' => 'tag-bad',
        ];
        $trendLabels = [
            'meningkat' => 'Meningkat',
            'menurun' => 'Menurun',
            'stabil' => 'Stabil',
        ];
        $trendTags = [
            'meningkat' => 'tag-ok',
            'menurun' => 'tag-bad',
            'stabil' => 'tag-mut',
        ];
        $assessmentTerakhir = $assessments->first();
    @endphp

    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Data Siswa</h3>
            <a href="{{ route('admin.tka.assessments.index') }}" class="btn btn-sm">Kembali</a>
        </div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:150px 1fr;gap:8px 16px;font-size:13px">
                <div style="color:var(--muted)">Nama</div>
                <div><strong>{{ $siswa->nama_peserta_didik }}</strong></div>

                <div style="color:var(--muted)">NIS / NISN</div>
                <div>{{ $siswa->nis ?? '-' }} / {{ $siswa->nisn ?? '-' }}</div>

                <div style="color:var(--muted)">Kelas</div>
                <div>{{ $siswa->kelas ?? '-' }}</div>

                <div style="color:var(--muted)">Status Terakhir</div>
                <div>
                    @if($assessmentTerakhir)
                        <span class="tag {{ $statusTags[$assessmentTerakhir->status_kesiapan] ?? 'tag-mut' }}">
                            {{ $statusLabels[$assessmentTerakhir->status_kesiapan] ?? ($assessmentTerakhir->status_kesiapan ?: '-') }}
                        </span>
                    @else
                        <span class="tag tag-mut">Belum ada penilaian</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-3" style="margin-bottom:16px">
        <div class="stat">
            <div class="label">Jumlah Penilaian</div>
            <div class="value">{{ $assessments->count() }}</div>
        </div>
        <div class="stat gold">
            <div class="label">Rata-rata Persentase</div>
            <div class="value">{{ number_format($assessments->avg('persentase') ?? 0, 1) }}%</div>
        </div>
        <div class="stat ok">
            <div class="label">Dimensi Terpantau</div>
            <div class="value">{{ $trackings->count() }}</div>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Riwayat Penilaian</h3>
        </div>
        <div class="card-body tight">
            @if($assessments->isEmpty())
                <div class="empty-state">
                    <div class="icon">✅</div>
                    <h4>Belum ada penilaian</h4>
                    <p>Siswa ini belum memiliki penilaian TKA.</p>
                </div>
            @else
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Semester</th>
                                <th>Total Skor</th>
                                <th>Skor Maksimal</th>
                                <th>Persentase</th>
                                <th>Status Kesiapan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($assessments as $index => $assessment)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $assessment->tanggal_penilaian?->format('d/m/Y') ?? '-' }}</td>
                                    <td>{{ $assessment->semester->label ?? '-' }}</td>
                                    <td>{{ number_format($assessment->total_score ?? 0, 1) }}</td>
                                    <td>{{ number_format($assessment->max_score ?? 0, 1) }}</td>
                                    <td>{{ number_format($assessment->persentase ?? 0, 1) }}%</td>
                                    <td>
                                        <span class="tag {{ $statusTags[$assessment->status_kesiapan] ?? 'tag-mut' }}">
                                            {{ $statusLabels[$assessment->status_kesiapan] ?? ($assessment->status_kesiapan ?: '-') }}
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
            <h3>Perkembangan per Dimensi</h3>
        </div>
        <div class="card-body tight">
            @if($trackings->isEmpty())
                <div class="empty-state">
                    <div class="icon">📈</div>
                    <h4>Belum ada riwayat dimensi</h4>
                    <p>Riwayat perkembangan dimensi akan tampil setelah penilaian tersimpan.</p>
                </div>
            @else
                @foreach($trackings as $namaDimensi => $riwayat)
                    <div style="padding:16px 20px 4px">
                        <strong style="font-size:13px">{{ $namaDimensi ?: '-' }}</strong>
                    </div>
                    <div class="table-wrapper" style="margin-bottom:12px">
                        <table>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Semester</th>
                                    <th>Skor</th>
                                    <th>Perubahan</th>
                                    <th>Tren</th>
                                    <th>Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($riwayat as $index => $tracking)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $tracking->semester->label ?? '-' }}</td>
                                        <td>{{ number_format($tracking->skor ?? 0, 2) }}</td>
                                        <td>
                                            @if($tracking->perubahan_skor === null)
                                                -
                                            @else
                                                {{ $tracking->perubahan_skor > 0 ? '+' : '' }}{{ number_format($tracking->perubahan_skor, 2) }}
                                            @endif
                                        </td>
                                        <td>
                                            @if($tracking->trend)
                                                <span class="tag {{ $trendTags[$tracking->trend] ?? 'tag-mut' }}">
                                                    {{ $trendLabels[$tracking->trend] ?? $tracking->trend }}
                                                </span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $tracking->created_at?->format('d/m/Y') ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
@endsection
