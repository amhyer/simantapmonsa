@extends('layouts.admin-with-tabs')

@section('title', 'Analisis Butir Soal - Admin')

@section('page_title', 'Analisis Butir Soal')
@section('page_subtitle', $session->title)

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h2 class="mb-1">Analisis Butir</h2>
            <p class="text-muted mb-0">
                {{ $session->kelas }} &middot; Guru: {{ $session->guru->nama_lengkap ?? '-' }} &middot;
                Bank: {{ $session->bank->title ?? '-' }}
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.cbt.sessions.show', $session) }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali ke Sesi
            </a>
        </div>
    </div>

    <div class="alert alert-light border">
        <strong>Sumber data:</strong>
        {{ $hasil['sumber'] === 'engine' ? 'engine terpadu exam_*' : 'tabel CBT lama (snapshot)' }}
        &middot; <strong>Status:</strong>
        {{ $hasil['status_data'] === 'selesai' ? 'hasil terkumpul' : 'sebagian / sesi masih berjalan' }}
        &middot; Rumus P = benar/peserta, D = 27% atas - 27% bawah.
        @if($hasil['status_data'] !== 'selesai')
            <span class="text-warning">Angka dapat berubah sampai sesi selesai.</span>
        @endif
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small">PESERTA DIANALISIS</div>
                <div class="fs-3 fw-bold">{{ $hasil['jumlah_peserta'] }}</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small">JUMLAH BUTIR</div>
                <div class="fs-3 fw-bold">{{ $hasil['jumlah_soal'] }}</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small">RATA-RATA P</div>
                <div class="fs-3 fw-bold">
                    {{ $hasil['rata_p'] !== null ? number_format($hasil['rata_p'] * 100, 1) . '%' : '-' }}
                </div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small">RATA-RATA D</div>
                <div class="fs-3 fw-bold">{{ $hasil['rata_d'] !== null ? number_format($hasil['rata_d'], 2) : '-' }}</div>
            </div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Rincian per Butir</h5>
        </div>
        <div class="card-body">
            <div class="table-wrapper">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th style="width:42px">No</th>
                            <th>Butir</th>
                            <th style="width:60px">Kunci</th>
                            <th style="width:80px">Benar</th>
                            <th style="width:80px">P</th>
                            <th style="width:90px">Kategori P</th>
                            <th style="width:70px">D</th>
                            <th style="width:90px">Kategori D</th>
                            <th>Distraktor</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($hasil['butir'] as $butir)
                        <tr>
                            <td>{{ $butir['nomor'] }}</td>
                            <td>
                                <div style="max-width:380px">{{ \Illuminate\Support\Str::limit($butir['teks'], 130) }}</div>
                                <small class="text-muted">{{ $butir['tipe'] }}</small>
                            </td>
                            <td><span class="badge bg-dark">{{ $butir['kunci'] ?? '?' }}</span></td>
                            <td>{{ $butir['jumlah_benar'] }} / {{ $butir['jumlah_peserta'] }}</td>
                            <td>{{ $butir['p'] !== null ? number_format($butir['p'] * 100, 1) . '%' : '-' }}</td>
                            <td>
                                <span class="badge bg-{{
                                    $butir['kategori_p'] === 'Sulit' ? 'danger' :
                                    ($butir['kategori_p'] === 'Mudah' ? 'warning text-dark' :
                                    ($butir['kategori_p'] === 'Sedang' ? 'success' : 'secondary'))
                                }}">{{ $butir['kategori_p'] }}</span>
                            </td>
                            <td>{{ $butir['d'] !== null ? number_format($butir['d'], 2) : '-' }}</td>
                            <td>
                                <span class="badge bg-{{
                                    $butir['kategori_d'] === 'Lemah' ? 'danger' :
                                    ($butir['kategori_d'] === 'Cukup' ? 'warning text-dark' :
                                    ($butir['kategori_d'] === 'Baik' ? 'success' : 'secondary'))
                                }}">{{ $butir['kategori_d'] }}</span>
                            </td>
                            <td>
                                @forelse($butir['distraktor'] as $huruf => $jumlah)
                                    <span class="badge bg-light text-dark border me-1">{{ $huruf }}: {{ $jumlah }}</span>
                                @empty
                                    <span class="text-muted">-</span>
                                @endforelse
                                @if($butir['kosong'] > 0)
                                    <span class="badge bg-secondary">Kosong: {{ $butir['kosong'] }}</span>
                                @endif
                            </td>
                            <td>
                                @if($butir['catatan'] === [])
                                    <span class="text-success">Baik</span>
                                @else
                                    <ul class="mb-0 ps-3">
                                        @foreach($butir['catatan'] as $catatan)
                                            <li>{{ $catatan }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted">
                                Belum ada soal atau hasil yang dapat dianalisis.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
