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
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($assessments as $assessment)
                                <tr>
                                    <td>{{ $assessment->siswa->nama_peserta_didik ?? '-' }}</td>
                                    <td>{{ $assessment->tanggal_penilaian?->format('d/m/Y') }}</td>
                                    <td><span class="tag tag-ok">Tersimpan</span></td>
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
