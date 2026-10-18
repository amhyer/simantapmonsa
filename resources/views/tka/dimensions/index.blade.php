@extends('layouts.app')

@section('title', 'Dimensi TKA - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Dimensi TKA')
@section('page_subtitle', 'Dimensi penilaian kesiapan belajar')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Daftar Dimensi</h3>
            <a href="{{ route('tka.dimensions.create') }}" class="btn btn-sm">Tambah Dimensi</a>
        </div>
        <div class="card-body tight">
            @if($dimensions->isEmpty())
                <div class="empty-state">
                    <div class="icon">🧭</div>
                    <h4>Belum ada dimensi</h4>
                    <p>Tambahkan dimensi penilaian beserta rubriknya.</p>
                </div>
            @else
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama Dimensi</th>
                                <th>Kategori</th>
                                <th>Item</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($dimensions as $dimension)
                                <tr>
                                    <td>{{ $dimension->kode_dimensi }}</td>
                                    <td>{{ $dimension->nama_dimensi }}</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $dimension->kategori ?? '-')) }}</td>
                                    <td>{{ $dimension->assessment_items_count ?? $dimension->assessmentItems->count() }} item</td>
                                    <td>
                                        <span class="tag {{ $dimension->aktif ? 'tag-ok' : 'tag-mut' }}">
                                            {{ $dimension->aktif ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div style="margin-top:12px">{{ $dimensions->links() }}</div>
            @endif
        </div>
    </div>
@endsection
