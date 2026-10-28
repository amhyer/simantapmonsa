@extends('layouts.admin-with-tabs')

@section('title', 'Detail Bank Soal CBT - Admin')

@section('page_title', 'Detail Bank Soal CBT')
@section('page_subtitle', $bank->title)

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-0">📋 {{ $bank->title }}</h2>
            <p class="text-muted mb-0">
                {{ $bank->subject }} · Guru: {{ $bank->guru->nama_lengkap ?? '-' }} ·
                Dibuat: {{ $bank->created_at?->format('d M Y H:i') ?? '-' }}
            </p>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <a href="{{ route('admin.cbt.banks.edit', $bank) }}" class="btn btn-warning">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="{{ route('admin.cbt.banks.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0">Daftar Soal ({{ $questions->count() }})</h5>
        </div>
        <div class="card-body">
            <div class="table-wrapper">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="5%">No</th>
                            <th width="55%">Soal</th>
                            <th width="10%">Kunci</th>
                            <th width="30%">Opsi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($questions as $index => $question)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($question->prompt, 200) }}</td>
                            <td><span class="badge bg-success">{{ $question->correct_answer }}</span></td>
                            <td>
                                @foreach($question->options ?? [] as $key => $option)
                                <div><strong>{{ $key }}.</strong> {{ \Illuminate\Support\Str::limit((string) $option, 60) }}</div>
                                @endforeach
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted">Belum ada soal pada bank ini</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Sesi yang Memakai Bank Ini ({{ $bank->sessions->count() }})</h5>
        </div>
        <div class="card-body">
            <div class="table-wrapper">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Judul</th>
                            <th>Kelas</th>
                            <th>Status</th>
                            <th>Jadwal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($bank->sessions as $session)
                        <tr>
                            <td>
                                <a href="{{ route('admin.cbt.sessions.show', $session) }}">{{ $session->title }}</a>
                            </td>
                            <td>{{ $session->kelas }}</td>
                            <td>
                                @php
                                $statusColors = ['draft' => 'secondary', 'published' => 'info', 'running' => 'primary', 'completed' => 'success'];
                                @endphp
                                <span class="badge bg-{{ $statusColors[$session->status] ?? 'secondary' }}">{{ ucfirst($session->status) }}</span>
                            </td>
                            <td>{{ $session->opens_at?->format('d M Y H:i') }} — {{ $session->closes_at?->format('d M Y H:i') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted">Belum ada sesi memakai bank ini</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
