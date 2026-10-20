@extends('layouts.app')

@section('title', 'Riwayat Penilaian TKA Siswa - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Riwayat Penilaian TKA')
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
    @endphp

    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Data Siswa</h3>
            <a href="{{ route('tka.assessments.index') }}" class="btn btn-sm">Kembali</a>
        </div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:150px 1fr;gap:8px 16px;font-size:13px">
                <div style="color:var(--muted)">Nama</div>
                <div><strong>{{ $siswa->nama_peserta_didik }}</strong></div>

                <div style="color:var(--muted)">NIS / NISN</div>
                <div>{{ $siswa->nis ?? '-' }} / {{ $siswa->nisn ?? '-' }}</div>

                <div style="color:var(--muted)">Kelas</div>
                <div>{{ $siswa->kelas ?? '-' }}</div>

                <div style="color:var(--muted)">Jumlah Penilaian</div>
                <div>{{ $assessments->count() }}</div>
            </div>
        </div>
    </div>

    <div class="card">
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
                                <th>Guru Penilai</th>
                                <th>Total Skor</th>
                                <th>Persentase</th>
                                <th>Status Kesiapan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($assessments as $index => $assessment)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $assessment->tanggal_penilaian?->format('d/m/Y') ?? '-' }}</td>
                                    <td>{{ $assessment->semester->label ?? '-' }}</td>
                                    <td>{{ $assessment->guru->nama_lengkap ?? '-' }}</td>
                                    <td>{{ number_format($assessment->total_score ?? 0, 1) }}</td>
                                    <td>{{ number_format($assessment->persentase ?? 0, 1) }}%</td>
                                    <td>
                                        <span class="tag {{ $statusTags[$assessment->status_kesiapan] ?? 'tag-mut' }}">
                                            {{ $statusLabels[$assessment->status_kesiapan] ?? ($assessment->status_kesiapan ?: '-') }}
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ route('tka.assessments.show', $assessment) }}" class="btn btn-sm">Detail</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
