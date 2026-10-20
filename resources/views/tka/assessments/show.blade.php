@extends('layouts.app')

@section('title', 'Detail Penilaian TKA - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Detail Penilaian TKA')
@section('page_subtitle', $assessment->siswa->nama_peserta_didik ?? 'Hasil asesmen siswa')

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
        $status = $assessment->status_kesiapan;
    @endphp

    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Informasi Penilaian</h3>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <a href="{{ route('tka.assessments.by-siswa', ['siswaId' => $assessment->siswa_id]) }}" class="btn btn-sm">Riwayat Siswa</a>
                <a href="{{ route('tka.assessments.index') }}" class="btn btn-sm">Kembali</a>
            </div>
        </div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:150px 1fr;gap:8px 16px;font-size:13px">
                <div style="color:var(--muted)">Siswa</div>
                <div><strong>{{ $assessment->siswa->nama_peserta_didik ?? '-' }}</strong></div>

                <div style="color:var(--muted)">NIS / Kelas</div>
                <div>{{ $assessment->siswa->nis ?? '-' }} / {{ $assessment->siswa->kelas ?? '-' }}</div>

                <div style="color:var(--muted)">Semester</div>
                <div>{{ $assessment->semester->label ?? '-' }}</div>

                <div style="color:var(--muted)">Guru Penilai</div>
                <div>{{ $assessment->guru->nama_lengkap ?? '-' }}</div>

                <div style="color:var(--muted)">Tanggal Penilaian</div>
                <div>{{ $assessment->tanggal_penilaian?->format('d/m/Y') ?? '-' }}</div>

                <div style="color:var(--muted)">Status Kesiapan</div>
                <div>
                    <span class="tag {{ $statusTags[$status] ?? 'tag-mut' }}">
                        {{ $statusLabels[$status] ?? ($status ?: '-') }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-3" style="margin-bottom:16px">
        <div class="stat">
            <div class="label">Total Skor</div>
            <div class="value">{{ number_format($assessment->total_score ?? 0, 1) }}</div>
        </div>
        <div class="stat gold">
            <div class="label">Skor Maksimal</div>
            <div class="value">{{ number_format($assessment->max_score ?? 0, 1) }}</div>
        </div>
        <div class="stat ok">
            <div class="label">Persentase</div>
            <div class="value">{{ number_format($assessment->persentase ?? 0, 1) }}%</div>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Skor per Dimensi</h3>
        </div>
        <div class="card-body tight">
            @php $skorDimensi = $assessment->skor_per_dimensi ?? []; @endphp
            @if(empty($skorDimensi))
                <div class="empty-state">
                    <div class="icon">🧭</div>
                    <h4>Belum ada skor dimensi</h4>
                    <p>Rincian skor per dimensi tidak tersedia.</p>
                </div>
            @else
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Dimensi</th>
                                <th>Kategori</th>
                                <th>Total Skor</th>
                                <th>Maksimal</th>
                                <th>Persentase</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($skorDimensi as $index => $dimensi)
                                @php
                                    $maksimal = $dimensi['maksimal'] ?? 0;
                                    $persen = $maksimal > 0 ? (($dimensi['total_skor'] ?? 0) / $maksimal * 100) : 0;
                                @endphp
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $dimensi['nama_dimensi'] ?? '-' }}</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $dimensi['kategori'] ?? '-')) }}</td>
                                    <td>{{ number_format($dimensi['total_skor'] ?? 0, 1) }}</td>
                                    <td>{{ number_format($maksimal, 1) }}</td>
                                    <td>{{ number_format($persen, 1) }}%</td>
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
            <h3>Rincian Penilaian</h3>
        </div>
        <div class="card-body tight">
            @if($assessment->responses->isEmpty())
                <div class="empty-state">
                    <div class="icon">📝</div>
                    <h4>Belum ada rincian</h4>
                    <p>Belum ada jawaban penilaian yang tersimpan.</p>
                </div>
            @else
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Dimensi</th>
                                <th>Indikator</th>
                                <th>Skor</th>
                                <th>Catatan Guru</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($assessment->responses as $index => $response)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $response->assessmentItem->dimension->nama_dimensi ?? '-' }}</td>
                                    <td>{{ $response->assessmentItem->indikator ?? '-' }}</td>
                                    <td>{{ number_format($response->skor ?? 0, 1) }}</td>
                                    <td>{{ $response->catatan_guru ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
