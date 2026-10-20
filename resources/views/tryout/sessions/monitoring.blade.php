@extends('layouts.app')

@section('title', 'Monitoring Sesi Tryout - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Monitoring Sesi Tryout')
@section('page_subtitle', $session->nama_sesi)

@section('content')
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Ringkasan Peserta</h3>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <a href="{{ url()->current() }}" class="btn btn-sm">Muat Ulang</a>
                <a href="{{ route('tryout.sessions.show', $session) }}" class="btn btn-sm">Kembali ke Sesi</a>
            </div>
        </div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:150px 1fr;gap:8px 16px;font-size:13px">
                <div style="color:var(--muted)">Paket</div>
                <div>{{ $session->package->nama_paket ?? '-' }}</div>

                <div style="color:var(--muted)">Jadwal</div>
                <div>
                    {{ $session->tanggal_mulai?->format('d/m/Y H:i') ?? '-' }}
                    s/d
                    {{ $session->tanggal_selesai?->format('d/m/Y H:i') ?? '-' }}
                </div>

                <div style="color:var(--muted)">Status Sesi</div>
                <div><span class="tag tag-mut">{{ ucfirst($session->status ?? '-') }}</span></div>
            </div>
        </div>
    </div>

    @php
        $totalPeserta = $activeStudents->count() + $completedStudents->count();
        $persenSelesai = $totalPeserta > 0 ? ($completedStudents->count() / $totalPeserta * 100) : 0;
    @endphp

    <div class="grid grid-4" style="margin-bottom:16px">
        <div class="stat">
            <div class="label">Total Peserta</div>
            <div class="value">{{ $totalPeserta }}</div>
        </div>
        <div class="stat gold">
            <div class="label">Sedang Mengerjakan</div>
            <div class="value">{{ $activeStudents->count() }}</div>
        </div>
        <div class="stat ok">
            <div class="label">Selesai</div>
            <div class="value">{{ $completedStudents->count() }}</div>
        </div>
        <div class="stat violet">
            <div class="label">Progres</div>
            <div class="value">{{ number_format($persenSelesai, 1) }}%</div>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Peserta Aktif ({{ $activeStudents->count() }})</h3>
        </div>
        <div class="card-body tight">
            @if($activeStudents->isEmpty())
                <div class="empty-state">
                    <div class="icon">⏳</div>
                    <h4>Tidak ada peserta aktif</h4>
                    <p>Belum ada peserta yang sedang mengerjakan sesi ini.</p>
                </div>
            @else
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Siswa</th>
                                <th>Kelas</th>
                                <th>Mulai</th>
                                <th>Durasi Berjalan</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($activeStudents as $index => $result)
                                @php
                                    $durasi = $result->waktu_mulai ? now()->diffInSeconds($result->waktu_mulai) : 0;
                                @endphp
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $result->siswa->nama_peserta_didik ?? '-' }}</td>
                                    <td>{{ $result->siswa->kelas ?? '-' }}</td>
                                    <td>{{ $result->waktu_mulai?->format('d/m/Y H:i') ?? '-' }}</td>
                                    <td>{{ floor($durasi / 60) }} menit {{ $durasi % 60 }} detik</td>
                                    <td><span class="tag tag-warn">Mengerjakan</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Peserta Selesai ({{ $completedStudents->count() }})</h3>
        </div>
        <div class="card-body tight">
            @if($completedStudents->isEmpty())
                <div class="empty-state">
                    <div class="icon">📊</div>
                    <h4>Belum ada peserta selesai</h4>
                    <p>Hasil peserta akan tampil setelah mereka menyelesaikan sesi.</p>
                </div>
            @else
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Siswa</th>
                                <th>Kelas</th>
                                <th>Selesai</th>
                                <th>Durasi</th>
                                <th>Nilai</th>
                                <th>Persentase</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($completedStudents as $index => $result)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $result->siswa->nama_peserta_didik ?? '-' }}</td>
                                    <td>{{ $result->siswa->kelas ?? '-' }}</td>
                                    <td>{{ $result->waktu_selesai?->format('d/m/Y H:i') ?? '-' }}</td>
                                    <td>
                                        @if($result->durasi_total_detik)
                                            {{ floor($result->durasi_total_detik / 60) }} m {{ $result->durasi_total_detik % 60 }} d
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ number_format($result->nilai_total ?? 0, 1) }}</td>
                                    <td>{{ number_format($result->persentase ?? 0, 1) }}%</td>
                                    <td>
                                        <span class="tag {{ $result->lulus ? 'tag-ok' : 'tag-bad' }}">
                                            {{ $result->lulus ? 'Lulus' : 'Tidak Lulus' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
