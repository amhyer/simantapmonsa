@extends('layouts.app')

@section('title', 'Sesi Tryout - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Sesi Tryout')
@section('page_subtitle', 'Jadwal pelaksanaan tryout')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Daftar Sesi</h3>
            <a href="{{ route('tryout.sessions.create') }}" class="btn btn-sm">Buat Sesi</a>
        </div>
        <div class="card-body tight">
            @if($sessions->isEmpty())
                <div class="empty-state">
                    <div class="icon">🗓️</div>
                    <h4>Belum ada sesi tryout</h4>
                    <p>Jadwalkan sesi tryout dari paket yang tersedia.</p>
                </div>
            @else
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama Sesi</th>
                                <th>Paket</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sessions as $session)
                                <tr>
                                    <td>{{ $session->kode_sesi }}</td>
                                    <td>{{ $session->nama_sesi }}</td>
                                    <td>{{ $session->package->nama_paket ?? '-' }}</td>
                                    <td><span class="tag tag-mut">{{ ucfirst($session->status ?? '-') }}</span></td>
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
