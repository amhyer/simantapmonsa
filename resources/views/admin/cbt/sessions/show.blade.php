@extends('layouts.admin-with-tabs')

@section('title', 'Detail Sesi Ujian CBT - Admin')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Detail Sesi Ujian CBT')
@section('page_subtitle', $session->title)

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-0">🗓️ {{ $session->title }}</h2>
            <p class="text-muted mb-0">
                {{ $session->kelas }} · Guru: {{ $session->guru->nama_lengkap ?? '-' }} ·
                Bank: {{ $session->bank->title ?? '-' }}
            </p>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="text-muted" style="font-size:12px">STATUS</div>
                    @php
                    $statusColors = ['draft' => 'secondary', 'published' => 'info', 'running' => 'primary', 'completed' => 'success'];
                    @endphp
                    <span class="badge bg-{{ $statusColors[$session->status] ?? 'secondary' }}" style="font-size:14px">
                        {{ ucfirst($session->status) }}
                    </span>
                </div>
                <div class="col-md-3">
                    <div class="text-muted" style="font-size:12px">JADWAL</div>
                    <div>{{ $session->opens_at?->format('d M Y H:i') }} —<br>{{ $session->closes_at?->format('d M Y H:i') }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted" style="font-size:12px">DURASI</div>
                    <div>{{ $session->duration_minutes }} menit</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted" style="font-size:12px">PESERTA</div>
                    <div>{{ count($session->participant_ids ?? []) }} siswa</div>
                </div>
            </div>

            <hr>

            <div class="d-flex flex-wrap gap-2">
                @if($session->status === 'draft')
                <form method="POST" action="{{ route('admin.cbt.sessions.update-status', $session) }}">
                    @csrf
                    <input type="hidden" name="status" value="published">
                    <button type="submit" class="btn btn-info"
                            onclick="return confirm('Publikasikan sesi? Soal dan peserta akan dibekukan.')">
                        <i class="fas fa-bullhorn"></i> Publikasikan
                    </button>
                </form>
                @endif

                @if($session->status === 'published')
                <form method="POST" action="{{ route('admin.cbt.sessions.update-status', $session) }}">
                    @csrf
                    <input type="hidden" name="status" value="running">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-play"></i> Buka Sesi
                    </button>
                </form>
                @endif

                @if($session->status === 'running')
                <form method="POST" action="{{ route('admin.cbt.sessions.force-complete', $session) }}"
                      style="display:inline">
                    @csrf
                    <button type="submit" class="btn btn-warning"
                            onclick="return confirm('Tutup paksa sesi sekarang? Jawaban tersimpan akan dinilai.')">
                        <i class="fas fa-stop"></i> Tutup Paksa
                    </button>
                </form>
                @endif

                @if(($session->results_count ?? $session->results()->count()) > 0)
                <a href="{{ route('admin.cbt.sessions.export', [$session, 'csv']) }}" class="btn btn-success">
                    <i class="fas fa-file-csv"></i> Ekspor CSV
                </a>
                <a href="{{ route('admin.cbt.sessions.analisis-butir', $session) }}" class="btn btn-outline-primary">
                    <i class="fas fa-chart-bar"></i> Analisis Butir
                </a>
                @endif

                <form method="POST" action="{{ route('admin.cbt.sessions.destroy', $session) }}"
                      style="display:inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger"
                            onclick="return confirm('Hapus sesi BESERTA seluruh hasil ujiannya? Tindakan ini tidak bisa dibatalkan.')">
                        <i class="fas fa-trash"></i> Hapus Sesi
                    </button>
                </form>

                <a href="{{ route('admin.cbt.sessions.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Hasil Ujian ({{ $results->total() }})</h5>
        </div>
        <div class="card-body">
            <div class="table-wrapper">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="4%">No</th>
                            <th width="24%">Peserta</th>
                            <th width="12%">NIS</th>
                            <th width="16%">Mulai</th>
                            <th width="16%">Selesai</th>
                            <th width="8%">Benar</th>
                            <th width="8%">Skor</th>
                            <th width="12%">Pelanggaran</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($results as $index => $result)
                        <tr>
                            <td>{{ ($results->currentPage() - 1) * $results->perPage() + $index + 1 }}</td>
                            <td>{{ $result->siswa->nama_peserta_didik ?? 'Siswa dihapus' }}</td>
                            <td>{{ $result->siswa->nis ?? '-' }}</td>
                            <td>{{ $result->started_at?->format('d M Y H:i') ?? '-' }}</td>
                            <td>{{ $result->submitted_at?->format('d M Y H:i') ?? 'belum' }}</td>
                            <td>{{ $result->correct_count }}</td>
                            <td>
                                @if($result->score !== null)
                                <strong>{{ number_format($result->score, 1) }}</strong>
                                @else
                                —
                                @endif
                            </td>
                            <td>
                                @if(($result->violations_count ?? 0) > 0)
                                <span class="badge bg-danger">{{ $result->violations_count }}</span>
                                @else
                                <span class="badge bg-success">0</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted">
                                Belum ada hasil ujian untuk sesi ini
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $results->links() }}
        </div>
    </div>
</div>
@endsection
