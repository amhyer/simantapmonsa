@extends('layouts.app')

@section('title', 'Kelas Digital')
@section('sidebar') @include('siswa.partials.sidebar') @endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Kelas Digital Saya</h1>
        <p class="page-subtitle">Akses modul belajar, video, berkas materi, kuis, dan kumpulkan tugas sesuai mata pelajaran kelasmu.</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('siswa.materi.index') }}" class="btn btn-outline btn-sm">Semua Materi Ajar</a>
    </div>
</div>

@if(!$siswa)
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <div class="empty-state-title">Data Siswa Belum Tertaut</div>
                <div class="empty-state-desc">Hubungi wali kelas atau admin sekolah untuk menautkan akun Anda dengan data peserta didik.</div>
            </div>
        </div>
    </div>
@elseif($kelasList->isEmpty())
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <div class="empty-state-icon"><x-lucide-school class="w-10 h-10" /></div>
                <div class="empty-state-title">Belum Ada Kelas Digital</div>
                <div class="empty-state-desc">Guru mata pelajaran kelas {{ $siswa->rombel?->nama_rombel ?? $siswa->kelas }} belum membuka kelas digital aktif.</div>
            </div>
        </div>
    </div>
@else
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1rem">
        @foreach($kelasList as $kelas)
            @php $prog = $progressMap[$kelas->id] ?? ['selesai' => 0, 'total' => 0, 'persen' => 0]; @endphp
            <div class="card" style="display:flex;flex-direction:column;justify-content:space-between">
                <div class="card-body">
                    <div style="display:flex;gap:.35rem;flex-wrap:wrap;margin-bottom:.5rem">
                        <span class="badge badge-purple">{{ $kelas->mata_pelajaran }}</span>
                        <span class="badge badge-gray">{{ $kelas->rombel?->nama_rombel ?? $kelas->kelas }}</span>
                    </div>

                    <h3 style="font-size:1.05rem;font-weight:700;color:#0f172a;margin:0 0 .35rem">
                        <a href="{{ route('siswa.kelas-digital.show', $kelas) }}" style="color:inherit;text-decoration:none">
                            {{ $kelas->nama_kelas }}
                        </a>
                    </h3>

                    <p style="font-size:.82rem;color:#64748b;margin-bottom:.85rem">
                        Pengampu: <strong>{{ $kelas->guru?->nama_lengkap ?? 'Guru Mapel' }}</strong>
                    </p>

                    <div style="margin-bottom:.65rem">
                        <div style="display:flex;justify-content:space-between;font-size:.76rem;color:#475569;margin-bottom:.25rem">
                            <span>Progress Pertemuan</span>
                            <strong>{{ $prog['selesai'] }}/{{ $prog['total'] }} ({{ $prog['persen'] }}%)</strong>
                        </div>
                        <div style="height:8px;background:#e2e8f0;border-radius:999px;overflow:hidden">
                            <div style="width:{{ $prog['persen'] }}%;height:100%;background:#7c3aed"></div>
                        </div>
                    </div>

                    <div style="display:flex;gap:1rem;font-size:.78rem;color:#64748b">
                        <span>{{ $kelas->modul_count }} Modul</span>
                        <span>{{ $kelas->tugas_count }} Tugas</span>
                    </div>
                </div>

                <div style="padding:.75rem 1.25rem;background:#f8fafc;border-top:1px solid #e2e8f0;border-radius:0 0 12px 12px">
                    <a href="{{ route('siswa.kelas-digital.show', $kelas) }}" class="btn btn-primary btn-sm" style="width:100%;text-align:center">Masuk Kelas</a>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
