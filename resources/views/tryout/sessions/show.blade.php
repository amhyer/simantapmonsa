@extends('layouts.app')

@section('title', 'Detail Sesi Tryout - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Detail Sesi Tryout')
@section('page_subtitle', $session->nama_sesi)

@section('content')
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Informasi Sesi</h3>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <a href="{{ route('tryout.sessions.monitoring', $session) }}" class="btn btn-sm">Monitoring</a>
                <a href="{{ route('tryout.sessions.index') }}" class="btn btn-sm">Kembali</a>
            </div>
        </div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:150px 1fr;gap:8px 16px;font-size:13px">
                <div style="color:var(--muted)">Kode Sesi</div>
                <div><strong>{{ $session->kode_sesi }}</strong></div>

                <div style="color:var(--muted)">Nama Sesi</div>
                <div>{{ $session->nama_sesi }}</div>

                <div style="color:var(--muted)">Paket</div>
                <div>{{ $session->package->nama_paket ?? '-' }}</div>

                <div style="color:var(--muted)">Semester</div>
                <div>{{ $session->semester->label ?? '-' }}</div>

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

            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:16px">
                @if($session->status === 'draft')
                    <form action="{{ route('tryout.sessions.publish', $session) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-sm">Publikasikan</button>
                    </form>
                @endif
                @if($session->status === 'published')
                    <form action="{{ route('tryout.sessions.start', $session) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-sm">Mulai Sesi</button>
                    </form>
                @endif
                @if($session->status === 'ongoing')
                    <form action="{{ route('tryout.sessions.complete', $session) }}" method="POST"
                          onsubmit="return confirm('Yakin ingin menyelesaikan sesi ini?')">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-danger">Selesaikan Sesi</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Mata Pelajaran</h3>
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
            @if($results->isEmpty())
                <div class="empty-state">
                    <div class="icon">📊</div>
                    <h4>Belum ada hasil</h4>
                    <p>Belum ada peserta yang mengerjakan sesi ini.</p>
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
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($results as $index => $result)
                                <tr>
                                    <td>{{ $results->firstItem() + $index }}</td>
                                    <td>{{ $result->siswa->nama_peserta_didik ?? '-' }}</td>
                                    <td>{{ $result->siswa->kelas ?? '-' }}</td>
                                    <td>{{ $result->waktu_mulai?->format('d/m/Y H:i') ?? '-' }}</td>
                                    <td>{{ $result->waktu_selesai?->format('d/m/Y H:i') ?? 'Sedang mengerjakan' }}</td>
                                    <td>{{ number_format($result->nilai_total ?? 0, 1) }}</td>
                                    <td>
                                        @if($result->waktu_selesai)
                                            <span class="tag {{ $result->lulus ? 'tag-ok' : 'tag-bad' }}">
                                                {{ $result->lulus ? 'Lulus' : 'Tidak Lulus' }}
                                            </span>
                                        @else
                                            <span class="tag tag-warn">Mengerjakan</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div style="margin-top:12px">{{ $results->links() }}</div>
            @endif
        </div>
    </div>
@endsection
