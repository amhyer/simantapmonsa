@extends('layouts.admin-with-tabs')

@section('title', 'Detail Sesi Tryout - Admin')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Detail Sesi Tryout')
@section('page_subtitle', $session->nama_sesi)

@section('content')
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Informasi Sesi</h3>
            <div style="display:flex;gap:8px">
                <a href="{{ route('admin.tryout.sessions.edit', $session) }}" class="btn btn-sm">Edit</a>
                <a href="{{ route('admin.tryout.sessions.index') }}" class="btn btn-sm">Kembali</a>
            </div>
        </div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:160px 1fr;gap:8px 16px;font-size:13px">
                <div style="color:var(--muted)">Kode Sesi</div>
                <div><strong>{{ $session->kode_sesi }}</strong></div>

                <div style="color:var(--muted)">Nama Sesi</div>
                <div>{{ $session->nama_sesi }}</div>

                <div style="color:var(--muted)">Paket</div>
                <div>{{ $session->package->nama_paket ?? '-' }}</div>

                <div style="color:var(--muted)">Semester</div>
                <div>{{ $session->semester->label ?? '-' }}</div>

                <div style="color:var(--muted)">Guru</div>
                <div>{{ $session->guru->nama_lengkap ?? '-' }}</div>

                <div style="color:var(--muted)">Rombel / Kelas</div>
                <div>
                    {{ $session->rombel->nama_rombel ?? '-' }}
                    @if($session->kelas)
                        <span class="tag tag-info">{{ $session->kelas }}</span>
                    @endif
                </div>

                <div style="color:var(--muted)">Jadwal</div>
                <div>
                    {{ $session->tanggal_mulai?->format('d/m/Y H:i') ?? '-' }}
                    s/d
                    {{ $session->tanggal_selesai?->format('d/m/Y H:i') ?? '-' }}
                </div>

                <div style="color:var(--muted)">Durasi</div>
                <div>{{ $session->durasi_menit ?? '-' }} menit</div>

                <div style="color:var(--muted)">Status</div>
                <div><span class="tag tag-mut">{{ ucfirst($session->status ?? '-') }}</span></div>

                <div style="color:var(--muted)">Instruksi</div>
                <div>{{ $session->instruksi ?: '-' }}</div>
            </div>

            @if($session->status === 'ongoing')
                <form action="{{ route('admin.tryout.sessions.force-complete', $session) }}" method="POST" style="margin-top:16px"
                      onsubmit="return confirm('Yakin ingin memaksa menyelesaikan sesi ini?')">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-danger">Paksa Selesai</button>
                </form>
            @endif
        </div>
    </div>

    <div class="grid grid-4" style="margin-bottom:16px">
        <div class="stat">
            <div class="label">Peserta</div>
            <div class="value">{{ $session->participants->count() }}</div>
        </div>
        <div class="stat gold">
            <div class="label">Hasil Masuk</div>
            <div class="value">{{ $session->results->count() }}</div>
        </div>
        <div class="stat ok">
            <div class="label">Lulus</div>
            <div class="value">{{ $session->results->where('lulus', true)->count() }}</div>
        </div>
        <div class="stat">
            <div class="label">Rata-rata Nilai</div>
            <div class="value">{{ number_format($session->results->avg('nilai_total') ?? 0, 1) }}</div>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Mata Pelajaran dalam Paket</h3>
        </div>
        <div class="card-body tight">
            @if($session->package && $session->package->subjects->isNotEmpty())
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Mata Pelajaran</th>
                                <th>Jumlah Soal</th>
                                <th>Durasi</th>
                                <th>KKM Mapel</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($session->package->subjects as $index => $subject)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $subject->mataPelajaran->nama ?? '-' }}</td>
                                    <td>{{ $subject->jumlah_soal ?? '-' }}</td>
                                    <td>{{ $subject->durasi_menit ?? '-' }} menit</td>
                                    <td>{{ $subject->passing_grade_mapel ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">
                    <div class="icon">📚</div>
                    <h4>Belum ada mata pelajaran</h4>
                    <p>Paket pada sesi ini belum memiliki mata pelajaran.</p>
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Hasil Peserta</h3>
        </div>
        <div class="card-body tight">
            @if($session->results->isEmpty())
                <div class="empty-state">
                    <div class="icon">📊</div>
                    <h4>Belum ada hasil</h4>
                    <p>Belum ada peserta yang menyelesaikan sesi ini.</p>
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
                                <th>Selesai</th>
                                <th>Nilai</th>
                                <th>Persentase</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($session->results as $index => $result)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $result->siswa->nama_peserta_didik ?? '-' }}</td>
                                    <td>{{ $result->siswa->kelas ?? '-' }}</td>
                                    <td>{{ $result->waktu_mulai?->format('d/m/Y H:i') ?? '-' }}</td>
                                    <td>{{ $result->waktu_selesai?->format('d/m/Y H:i') ?? 'Belum selesai' }}</td>
                                    <td>{{ number_format($result->nilai_total ?? 0, 1) }}</td>
                                    <td>{{ number_format($result->persentase ?? 0, 1) }}%</td>
                                    <td>
                                        <span class="tag {{ $result->lulus ? 'tag-ok' : 'tag-bad' }}">
                                            {{ $result->lulus ? 'Lulus' : 'Tidak Lulus' }}
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.tryout.results.show', $result) }}" class="btn btn-sm">Detail</a>
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
