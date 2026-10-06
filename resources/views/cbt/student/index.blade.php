@extends('layouts.app')

@section('title', 'Ujian CBT - SIMANTAP')

@section('sidebar')
    @include('siswa.partials.sidebar')
@endsection

@section('page_title', 'Ujian CBT')
@section('page_subtitle', 'Sesi ujian yang tersedia untuk Anda')

@section('content')
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Sesi Tersedia</h3>
            <span class="tag tag-mut">{{ $availableSessions->count() }} sesi</span>
        </div>
        <div class="card-body tight">
            @if($availableSessions->isEmpty())
                <div class="empty-state">
                    <div class="icon">📝</div>
                    <h4>Tidak ada ujian saat ini</h4>
                    <p>Belum ada sesi ujian yang sedang berjalan untuk kelas Anda.</p>
                </div>
            @else
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Nama Sesi</th>
                                <th>Berakhir</th>
                                <th>Durasi</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($availableSessions as $session)
                                <tr>
                                    <td>{{ $session->nama_sesi }}</td>
                                    <td>{{ $session->tanggal_selesai?->format('d/m H:i') }}</td>
                                    <td>{{ $session->durasi_menit }} mnt</td>
                                    <td><a href="{{ route('cbt.student.start', $session) }}" class="btn btn-sm">Mulai</a></td>
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
            <h3>Hasil Saya</h3>
            <span class="tag tag-mut">{{ $myResults->count() }} hasil</span>
        </div>
        <div class="card-body tight">
            @if($myResults->isEmpty())
                <div class="empty-state">
                    <div class="icon">📊</div>
                    <h4>Belum ada hasil</h4>
                    <p>Hasil ujian yang sudah dikerjakan akan muncul di sini.</p>
                </div>
            @else
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Sesi</th>
                                <th>Nilai</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($myResults as $result)
                                <tr>
                                    <td>{{ $result->testSession->nama_sesi ?? '-' }}</td>
                                    <td>{{ $result->nilai_akhir }}</td>
                                    <td>
                                        <span class="tag {{ $result->lulus ? 'tag-ok' : 'tag-mut' }}">
                                            {{ $result->lulus ? 'Lulus' : 'Belum lulus' }}
                                        </span>
                                    </td>
                                    <td><a href="{{ route('cbt.student.result', $result) }}" class="btn btn-sm">Lihat</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
