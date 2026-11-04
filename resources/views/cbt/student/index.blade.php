@extends('layouts.app')
@section('title', 'Daftar Ujian CBT - SIMANTAP')

@section('sidebar')
    @include('siswa.partials.sidebar')
@endsection

@section('page_title', 'Ujian Berbasis Komputer (CBT)')
@section('page_subtitle', 'Computer Based Test • Pilihan Ganda & Evaluasi Mandiri')

@section('content')
@include('cbt.feedback')

{{-- Info Banner --}}
<div class="card" style="margin-bottom:20px;border-left:4px solid var(--violet);background:linear-gradient(to right, #FFFFFF, #FAF5FF)">
    <div class="card-body" style="padding:18px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px">
        <div style="display:flex;align-items:center;gap:14px">
            <div style="width:44px;height:44px;border-radius:12px;background:rgba(124,58,237,0.12);color:var(--violet);display:flex;align-items:center;justify-content:center;font-size:20px">
                <i class="fas fa-laptop-code"></i>
            </div>
            <div>
                <h3 style="font-size:15px;font-weight:700;margin:0 0 4px;color:var(--text)">Sesi Ujian Siswa</h3>
                <p style="margin:0;font-size:13px;color:var(--muted)">Pastikan koneksi internet stabil sebelum menekan tombol Mulai Ujian.</p>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:12px">
            <span class="tag tag-violet"><i class="fas fa-shield-alt"></i> Dilindungi Anti-Cheat</span>
            <span class="tag tag-ok"><i class="fas fa-cloud-upload-alt"></i> Autosave Aktif</span>
        </div>
    </div>
</div>

{{-- Sesi Ujian List --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list-check" style="margin-right:8px;color:var(--violet)"></i>Daftar Sesi Ujian Anda</h3>
        <span class="tag tag-secondary">{{ $sessions->total() }} Sesi Terjadwal</span>
    </div>
    <div class="card-body tight">
        @if($sessions->isEmpty())
            <div class="empty-state">
                <div class="icon">📝</div>
                <h4>Belum Ada Ujian Terjadwal</h4>
                <p>Saat ini belum ada sesi ujian CBT aktif untuk kelas Anda. Hubungi guru mata pelajaran Anda jika ada jadwal ujian.</p>
            </div>
        @else
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width:35%">Mata Pelajaran & Judul Ujian</th>
                            <th style="width:30%">Jadwal Pelaksanaan</th>
                            <th style="width:15%">Durasi</th>
                            <th style="width:20%;text-align:right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sessions as $session)
                            <tr>
                                <td>
                                    <div style="font-weight:700;font-size:14px;color:var(--text)">
                                        {{ $session['title'] }}
                                    </div>
                                    <div style="font-size:12px;color:var(--muted);margin-top:2px">
                                        <i class="fas fa-layer-group" style="margin-right:4px"></i> Pilihan Ganda CBT
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size:13px;color:var(--text);display:flex;align-items:center;gap:6px">
                                        <i class="far fa-calendar-alt" style="color:var(--muted)"></i>
                                        <span>{{ $session['jadwal'] }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="tag tag-primary">
                                        <i class="far fa-clock"></i> {{ $session['duration_minutes'] }} Menit
                                    </span>
                                </td>
                                <td style="text-align:right">
                                    <a href="{{ $session['start_url'] }}" class="btn btn-primary btn-sm" style="display:inline-flex;align-items:center;gap:6px">
                                        <span>Masuk Ujian</span>
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($sessions->hasPages())
                <div style="padding:16px 20px;border-top:1px solid var(--line)">
                    {{ $sessions->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
