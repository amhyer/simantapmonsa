@extends('layouts.app')

@section('title', 'Paket Tryout - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Paket Tryout')
@section('page_subtitle', 'Kelola paket soal tryout')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Daftar Paket</h3>
            <a href="{{ route('tryout.packages.create') }}" class="btn btn-sm">Tambah Paket</a>
        </div>
        <div class="card-body tight">
            @if($packages->isEmpty())
                <div class="empty-state">
                    <div class="icon">📦</div>
                    <h4>Belum ada paket tryout</h4>
                    <p>Buat paket tryout beserta mata pelajarannya.</p>
                </div>
            @else
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama Paket</th>
                                <th>Jenjang</th>
                                <th>Mapel</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($packages as $package)
                                <tr>
                                    <td>{{ $package->kode_paket }}</td>
                                    <td>{{ $package->nama_paket }}</td>
                                    <td>{{ $package->jenjang ?? '-' }}</td>
                                    <td>{{ $package->subjects_count ?? $package->subjects->count() }} mapel</td>
                                    <td>
                                        <span class="tag {{ $package->aktif ? 'tag-ok' : 'tag-mut' }}">
                                            {{ $package->aktif ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div style="margin-top:12px">{{ $packages->links() }}</div>
            @endif
        </div>
    </div>
@endsection
