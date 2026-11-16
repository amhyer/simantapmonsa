@extends('layouts.app')

@section('title', 'Kelas Digital')
@section('sidebar') @include('guru.partials.sidebar') @endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Kelas Digital (E-Learning)</h1>
        <p class="page-subtitle">Susun modul Pembelajaran Mendalam, pertemuan, konten multimedia, tugas, dan pantau progress belajar siswa.</p>
    </div>
    <div class="page-actions" style="display:flex;gap:.5rem;flex-wrap:wrap">
        <a href="{{ route('guru.materi.index') }}" class="btn btn-outline btn-sm">Materi Ajar</a>
        <a href="{{ route('guru.kuis.index') }}" class="btn btn-outline btn-sm">Kuis &amp; Soal</a>
        <a href="{{ route('guru.kelas-digital.create') }}" class="btn btn-primary btn-sm">+ Buat Kelas Digital</a>
    </div>
</div>

@if($kelasList->isEmpty())
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <div class="empty-state-icon"><x-lucide-school class="w-10 h-10" /></div>
                <div class="empty-state-title">Belum ada Kelas Digital</div>
                <div class="empty-state-desc">Buat kelas digital pertama Anda untuk mengelompokkan modul, materi, kuis, dan tugas per rombel &amp; mata pelajaran.</div>
                <a href="{{ route('guru.kelas-digital.create') }}" class="btn btn-primary" style="margin-top:1rem">+ Buat Kelas Digital Pertama</a>
            </div>
        </div>
    </div>
@else
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:1rem">
        @foreach($kelasList as $kelas)
            <div class="card" style="display:flex;flex-direction:column;justify-content:space-between">
                <div class="card-body">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;margin-bottom:.5rem">
                        <div style="display:flex;gap:.375rem;flex-wrap:wrap">
                            <span class="badge badge-blue">{{ $kelas->mata_pelajaran }}</span>
                            <span class="badge badge-gray">{{ $kelas->rombel?->nama_rombel ?? $kelas->kelas }}</span>
                        </div>
                        <span class="badge {{ $kelas->aktif ? 'badge-green' : 'badge-amber' }}">
                            {{ $kelas->aktif ? 'Aktif' : 'Arsip' }}
                        </span>
                    </div>

                    <h3 style="font-size:1.05rem;font-weight:700;color:#0f172a;margin:.25rem 0 .35rem">
                        <a href="{{ route('guru.kelas-digital.show', $kelas) }}" style="color:inherit;text-decoration:none">
                            {{ $kelas->nama_kelas }}
                        </a>
                    </h3>

                    @if($kelas->deskripsi)
                        <p style="font-size:.82rem;color:#64748b;margin-bottom:.75rem;line-height:1.45">
                            {{ \Illuminate\Support\Str::limit($kelas->deskripsi, 120) }}
                        </p>
                    @endif

                    <div style="display:flex;gap:1rem;font-size:.8rem;color:#475569;padding-top:.65rem;border-top:1px solid #f1f5f9">
                        <span><strong>{{ $kelas->modul_count }}</strong> Modul</span>
                        <span><strong>{{ $kelas->tugas_count }}</strong> Tugas</span>
                        <span>Kode: <code>{{ $kelas->kode_kelas }}</code></span>
                    </div>
                </div>

                <div style="padding:.75rem 1.25rem;background:#f8fafc;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;gap:.5rem;border-radius:0 0 12px 12px">
                    <a href="{{ route('guru.kelas-digital.show', $kelas) }}" class="btn btn-primary btn-sm">Kelola Kelas</a>
                    <div style="display:flex;gap:.35rem">
                        <a href="{{ route('guru.kelas-digital.edit', $kelas) }}" class="btn btn-outline btn-sm">Ubah</a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
