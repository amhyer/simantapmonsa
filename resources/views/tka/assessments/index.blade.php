@extends('layouts.app')

@section('title', 'Penilaian TKA - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Penilaian TKA')
@section('page_subtitle', 'Hasil asesmen kesiapan belajar siswa')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Daftar Penilaian</h3>
            <a href="{{ route('tka.assessments.create') }}" class="btn btn-sm">Nilai Siswa</a>
        </div>
        <div class="card-body tight">
            @if($assessments->isEmpty())
                <div class="empty-state">
                    <div class="icon">✅</div>
                    <h4>Belum ada penilaian</h4>
                    <p>Mulai menilai kesiapan belajar siswa Anda.</p>
                </div>
            @else
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Siswa</th>
                                <th>Tanggal</th>
                                <th>Status</th>
                                <th style="text-align:right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($assessments as $assessment)
                                <tr>
                                    <td>{{ $assessment->siswa->nama_peserta_didik ?? '-' }}</td>
                                    <td>{{ $assessment->tanggal_penilaian?->format('d/m/Y') }}</td>
                                    <td><span class="tag tag-ok">Tersimpan</span></td>
                                    {{-- Kolom aksi: sebelumnya tidak ada tautan ke detail
                                         penilaian, jadi halaman show tidak tercapai. --}}
                                    <td style="text-align:right;white-space:nowrap">
                                        <a href="{{ route('tka.assessments.show', $assessment) }}" class="btn btn-ghost btn-sm" title="Lihat detail penilaian">Detail</a>
                                        @if($assessment->siswa_id)
                                            <a href="{{ route('tka.assessments.by-siswa', $assessment->siswa_id) }}" class="btn btn-ghost btn-sm" title="Riwayat penilaian siswa ini">Riwayat</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div style="margin-top:12px">{{ $assessments->links() }}</div>
            @endif
        </div>
    </div>
@endsection
