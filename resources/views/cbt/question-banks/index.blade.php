@extends('layouts.app')

@section('title', 'Bank Soal CBT - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Bank Soal CBT')
@section('page_subtitle', 'Kelola bank soal ujian Anda')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Daftar Bank Soal</h3>
            <a href="{{ route('cbt.question-banks.create') }}" class="btn btn-sm">Tambah Bank</a>
        </div>
        <div class="card-body tight">
            @if($banks->isEmpty())
                <div class="empty-state">
                    <div class="icon">📚</div>
                    <h4>Belum ada bank soal</h4>
                    <p>Buat bank soal pertama untuk mulai menyusun ujian CBT.</p>
                </div>
            @else
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama Bank</th>
                                <th>Mapel</th>
                                <th>Soal</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($banks as $bank)
                                <tr>
                                    <td>{{ $bank->kode_bank }}</td>
                                    <td>{{ $bank->nama_bank }}</td>
                                    <td>{{ $bank->mataPelajaran->nama ?? '-' }}</td>
                                    <td>{{ $bank->jumlah_soal ?? $bank->questions_count ?? 0 }}</td>
                                    <td>
                                        <span class="tag {{ $bank->aktif ? 'tag-ok' : 'tag-mut' }}">
                                            {{ $bank->aktif ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div style="margin-top:12px">{{ $banks->links() }}</div>
            @endif
        </div>
    </div>
@endsection
