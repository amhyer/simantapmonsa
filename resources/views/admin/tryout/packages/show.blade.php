@extends('layouts.admin-with-tabs')

@section('title', 'Detail Paket Tryout - Admin')

@section('page_title', 'Detail Paket Tryout')
@section('page_subtitle', $package->nama_paket)

@section('content')
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Informasi Paket</h3>
            <div style="display:flex;gap:8px">
                <a href="{{ route('admin.tryout.packages.edit', $package) }}" class="btn btn-sm">Edit</a>
                <a href="{{ route('admin.tryout.packages.index') }}" class="btn btn-sm">Kembali</a>
            </div>
        </div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:160px 1fr;gap:8px 16px;font-size:13px">
                <div style="color:var(--muted)">Kode Paket</div>
                <div><strong>{{ $package->kode_paket }}</strong></div>

                <div style="color:var(--muted)">Nama Paket</div>
                <div>{{ $package->nama_paket }}</div>

                <div style="color:var(--muted)">Kategori</div>
                <div>{{ $package->category->nama_kategori ?? '-' }}</div>

                <div style="color:var(--muted)">Jenjang</div>
                <div>{{ $package->jenjang ?? '-' }}</div>

                <div style="color:var(--muted)">Tahun Paket</div>
                <div>{{ $package->tahun_paket ?? '-' }}</div>

                <div style="color:var(--muted)">Jumlah Mapel</div>
                <div>{{ $package->jumlah_mapel ?? $package->subjects->count() }}</div>

                <div style="color:var(--muted)">Durasi Total</div>
                <div>{{ $package->durasi_total_menit ?? '-' }} menit</div>

                <div style="color:var(--muted)">Passing Grade</div>
                <div>{{ $package->passing_grade ?? '-' }}</div>

                <div style="color:var(--muted)">Status</div>
                <div>
                    <span class="tag {{ $package->aktif ? 'tag-ok' : 'tag-mut' }}">
                        {{ $package->aktif ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </div>

                <div style="color:var(--muted)">Deskripsi</div>
                <div>{{ $package->deskripsi ?: '-' }}</div>
            </div>
        </div>
    </div>

    <div class="grid grid-4" style="margin-bottom:16px">
        <div class="stat">
            <div class="label">Mata Pelajaran</div>
            <div class="value">{{ $package->subjects->count() }}</div>
        </div>
        <div class="stat gold">
            <div class="label">Soal Tersimpan</div>
            <div class="value">{{ $package->subjects->sum(fn ($s) => $s->questions->count()) }}</div>
        </div>
        <div class="stat ok">
            <div class="label">Sesi Tryout</div>
            <div class="value">{{ $package->sessions->count() }}</div>
        </div>
        <div class="stat">
            <div class="label">Hasil Peserta</div>
            <div class="value">{{ $package->sessions->sum(fn ($s) => $s->results->count()) }}</div>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Mata Pelajaran</h3>
        </div>
        <div class="card-body tight">
            @if($package->subjects->isEmpty())
                <div class="empty-state">
                    <div class="icon">📚</div>
                    <h4>Belum ada mata pelajaran</h4>
                    <p>Paket ini belum memiliki mata pelajaran.</p>
                </div>
            @else
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Mata Pelajaran</th>
                                <th>Bobot</th>
                                <th>Jumlah Soal</th>
                                <th>Durasi</th>
                                <th>KKM Mapel</th>
                                <th>Soal Tersimpan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($package->subjects as $index => $subject)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $subject->mataPelajaran->nama ?? '-' }}</td>
                                    <td>{{ $subject->bobot ?? '-' }}</td>
                                    <td>{{ $subject->jumlah_soal ?? '-' }}</td>
                                    <td>{{ $subject->durasi_menit ?? '-' }} menit</td>
                                    <td>{{ $subject->passing_grade_mapel ?? '-' }}</td>
                                    <td>{{ $subject->questions->count() }}</td>
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
            <h3>Sesi Tryout</h3>
        </div>
        <div class="card-body tight">
            @if($package->sessions->isEmpty())
                <div class="empty-state">
                    <div class="icon">🗓️</div>
                    <h4>Belum ada sesi</h4>
                    <p>Paket ini belum dijadwalkan pada sesi tryout.</p>
                </div>
            @else
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama Sesi</th>
                                <th>Jadwal</th>
                                <th>Status</th>
                                <th>Hasil</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($package->sessions as $session)
                                <tr>
                                    <td>{{ $session->kode_sesi }}</td>
                                    <td>{{ $session->nama_sesi }}</td>
                                    <td>
                                        {{ $session->tanggal_mulai?->format('d/m/Y H:i') ?? '-' }}
                                        s/d
                                        {{ $session->tanggal_selesai?->format('d/m/Y H:i') ?? '-' }}
                                    </td>
                                    <td><span class="tag tag-mut">{{ ucfirst($session->status ?? '-') }}</span></td>
                                    <td>{{ $session->results->count() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
