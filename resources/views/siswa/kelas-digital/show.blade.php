@extends('layouts.app')

@section('title', $kelasDigital->nama_kelas)
@section('sidebar') @include('siswa.partials.sidebar') @endsection

@section('content')
<div class="page-header">
    <div>
        <div style="display:flex;gap:.35rem;flex-wrap:wrap;margin-bottom:.35rem">
            <span class="badge badge-purple">{{ $kelasDigital->mata_pelajaran }}</span>
            <span class="badge badge-gray">{{ $kelasDigital->rombel?->nama_rombel ?? $kelasDigital->kelas }}</span>
        </div>
        <h1 class="page-title">{{ $kelasDigital->nama_kelas }}</h1>
        <p class="page-subtitle">Pengampu: {{ $kelasDigital->guru?->nama_lengkap ?? 'Guru Mapel' }}</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('siswa.kelas-digital.index') }}" class="btn btn-outline btn-sm">Semua Kelas</a>
    </div>
</div>

{{-- Kartu Progress Belajar --}}
<div class="card" style="margin-bottom:1.25rem">
    <div class="card-body">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:.5rem">
            <strong style="font-size:.92rem;color:#0f172a">Kemajuan Belajarmu</strong>
            <span style="font-size:.85rem;font-weight:700;color:#6d28d9">{{ $selesaiCount }} dari {{ $totalPertemuan }} pertemuan selesai ({{ $persenProgress }}%)</span>
        </div>
        <div style="height:10px;background:#e2e8f0;border-radius:999px;overflow:hidden">
            <div style="width:{{ $persenProgress }}%;height:100%;background:#7c3aed"></div>
        </div>
        @if($kelasDigital->deskripsi)
            <p style="font-size:.85rem;color:#475569;margin-top:.75rem;margin-bottom:0">{{ $kelasDigital->deskripsi }}</p>
        @endif
    </div>
</div>

@forelse($kelasDigital->modul as $modul)
    <div class="card" style="margin-bottom:1rem">
        <div class="card-header">
            <div>
                <div style="display:flex;gap:.35rem;flex-wrap:wrap;margin-bottom:.25rem">
                    <span class="badge badge-purple">Modul {{ $modul->urutan }}</span>
                    @foreach((array) $modul->pm as $pmKey => $pmVal)
                        @if($pmVal)
                            <span class="badge badge-blue">{{ ucfirst($pmKey) }}</span>
                        @endif
                    @endforeach
                </div>
                <h2 style="font-size:1.05rem;font-weight:700;color:#0f172a;margin:0">{{ $modul->judul }}</h2>
                @if($modul->tujuan_pembelajaran)
                    <p style="font-size:.82rem;color:#475569;margin:.35rem 0 0"><strong>Tujuan Pembelajaran:</strong> {{ $modul->tujuan_pembelajaran }}</p>
                @endif
            </div>
        </div>

        <div class="card-body">
            @if($modul->pertemuan->isEmpty())
                <p style="font-size:.85rem;color:#64748b;margin:0">Belum ada pertemuan yang dipublikasikan pada modul ini.</p>
            @else
                <div style="display:flex;flex-direction:column;gap:.65rem">
                    @foreach($modul->pertemuan as $pertemuan)
                        @php $sudahSelesai = isset($selesaiIds[$pertemuan->id]); @endphp
                        <a href="{{ route('siswa.kelas-digital.pertemuan', [$kelasDigital, $pertemuan]) }}"
                           style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;padding:.85rem 1rem;border:1px solid {{ $sudahSelesai ? '#86efac' : '#e2e8f0' }};background:{{ $sudahSelesai ? '#f0fdf4' : '#ffffff' }};border-radius:10px;text-decoration:none;color:inherit">
                            <div>
                                <div style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap;margin-bottom:.2rem">
                                    <span class="badge {{ $sudahSelesai ? 'badge-green' : 'badge-gray' }}">
                                        {{ $sudahSelesai ? 'Selesai' : 'Pertemuan ' . $pertemuan->urutan }}
                                    </span>
                                    @if($pertemuan->tanggal)
                                        <span style="font-size:.76rem;color:#64748b">{{ $pertemuan->tanggal->format('d M Y') }}</span>
                                    @endif
                                    @if($pertemuan->kuis)
                                        <span class="badge badge-amber">Ada Kuis</span>
                                    @endif
                                </div>
                                <div style="font-weight:700;color:#0f172a;font-size:.95rem">{{ $pertemuan->judul }}</div>
                                <div style="font-size:.78rem;color:#64748b;margin-top:.15rem">
                                    {{ $pertemuan->items_count }} konten · {{ $pertemuan->tugas_count }} tugas
                                </div>
                            </div>
                            <span class="btn btn-primary btn-sm">Buka Pertemuan</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@empty
    <div class="card">
        <div class="card-body" style="text-align:center;color:#64748b">
            Guru belum mempublikasikan modul pada kelas ini.
        </div>
    </div>
@endforelse
@endsection
