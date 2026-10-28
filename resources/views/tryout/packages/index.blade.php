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
                                <th style="text-align:right">Aksi</th>
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
                                    {{-- Kolom aksi: sebelumnya halaman ini tidak punya tautan
                                         ke detail/ubah/hapus, sehingga halaman show & edit
                                         hanya bisa dicapai lewat URL langsung. --}}
                                    <td style="text-align:right;white-space:nowrap">
                                        <a href="{{ route('tryout.packages.show', $package) }}" class="btn btn-ghost btn-sm" title="Detail">Detail</a>
                                        <a href="{{ route('tryout.packages.edit', $package) }}" class="btn btn-ghost btn-sm" title="Ubah">Ubah</a>
                                        <form action="{{ route('tryout.packages.destroy', $package) }}" method="POST" style="display:inline" onsubmit="return confirm('Hapus paket tryout &quot;{{ $package->nama_paket }}&quot;? Seluruh sesi dan hasil di dalamnya ikut terhapus.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-ghost btn-sm" title="Hapus" style="color:var(--bad)">Hapus</button>
                                        </form>
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
