@extends('layouts.app')

@section('title', 'Laporan TKA Anak - SIMANTAP')

@section('sidebar')
    @include('ortu.partials.sidebar')
@endsection

@section('page_title', 'Laporan TKA')
@section('page_subtitle', 'Hasil asesmen TKA anak Anda')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Daftar Laporan</h3>
            <span class="tag tag-mut">{{ $reports->total() }} laporan</span>
        </div>
        <div class="card-body tight">
            @if($children->isEmpty())
                <div class="empty-state">
                    <div class="icon">👨‍👩‍👧</div>
                    <h4>Belum ada anak terhubung</h4>
                    <p>Hubungi admin sekolah untuk menautkan akun Anda dengan data siswa.</p>
                </div>
            @elseif($reports->isEmpty())
                <div class="empty-state">
                    <div class="icon">📋</div>
                    <h4>Belum ada laporan</h4>
                    <p>Laporan hasil asesmen TKA akan muncul di sini setelah diterbitkan guru.</p>
                </div>
            @else
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Anak</th>
                                <th>Asesmen</th>
                                <th>Skor</th>
                                <th>Tanggal</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($reports as $report)
                                <tr>
                                    <td>{{ $report->assessment->siswa->nama_peserta_didik ?? '-' }}</td>
                                    <td>{{ $report->assessment->nama_asesmen ?? '-' }}</td>
                                    <td>{{ $report->total_skor ?? '-' }}</td>
                                    <td>{{ $report->created_at?->format('d/m/Y') }}</td>
                                    <td><a href="{{ route('tka.parent.show', $report) }}" class="btn btn-sm">Lihat</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div style="margin-top:12px">{{ $reports->links() }}</div>
            @endif
        </div>
    </div>
@endsection
