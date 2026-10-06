@extends('layouts.app')

@section('title', 'Sesi Ujian CBT - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Sesi Ujian CBT')
@section('page_subtitle', 'Jadwal dan pantau ujian Anda')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Daftar Sesi Ujian</h3>
            <a href="{{ route('cbt.sessions.create') }}" class="btn btn-sm">Buat Sesi</a>
        </div>
        <div class="card-body tight">
            @if($sessions->isEmpty())
                <div class="empty-state">
                    <div class="icon">🖥️</div>
                    <h4>Belum ada sesi ujian</h4>
                    <p>Buat sesi ujian dari bank soal yang sudah berisi pertanyaan.</p>
                </div>
            @else
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Nama Sesi</th>
                                <th>Bank Soal</th>
                                <th>Status</th>
                                <th>Mulai</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sessions as $session)
                                <tr>
                                    <td>{{ $session->nama_sesi }}</td>
                                    <td>{{ $session->questionBank->nama_bank ?? '-' }}</td>
                                    <td><span class="tag tag-mut">{{ ucfirst($session->status ?? '-') }}</span></td>
                                    <td>{{ $session->tanggal_mulai?->format('d/m H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div style="margin-top:12px">{{ $sessions->links() }}</div>
            @endif
        </div>
    </div>
@endsection
