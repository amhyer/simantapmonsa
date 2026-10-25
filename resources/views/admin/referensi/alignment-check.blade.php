@extends('layouts.app')

@section('title', 'Cek Alignment E-Rapor')

@section('content')
<div class="container mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold mb-6">Cek Alignment E-Rapor</h1>
    
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="card">
            <div class="card-body">
                <h3 class="text-sm text-gray-500">Total Nilai E-Rapor</h3>
                <p class="text-3xl font-bold">{{ $stats['total'] ?? 0 }}</p>
            </div>
        </div>
        
        <div class="card">
            <div class="card-body">
                <h3 class="text-sm text-gray-500">Matched</h3>
                <p class="text-3xl font-bold text-green-600">{{ $stats['matched'] ?? 0 }}</p>
                <p class="text-sm text-gray-400">{{ $stats['persen'] ?? 0 }}%</p>
            </div>
        </div>
        
        <div class="card">
            <div class="card-body">
                <h3 class="text-sm text-gray-500">Unmatched</h3>
                <p class="text-3xl font-bold text-red-600">{{ $stats['unmatched'] ?? 0 }}</p>
            </div>
        </div>
        
        <div class="card">
            <div class="card-body">
                <h3 class="text-sm text-gray-500">Alignment Health</h3>
                @php
                    $persen = $stats['persen'] ?? 0;
                    $color = $persen >= 90 ? 'green' : ($persen >= 70 ? 'yellow' : 'red');
                @endphp
                <p class="text-3xl font-bold text-{{ $color }}-600">{{ $persen }}%</p>
                <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                    <div class="bg-{{ $color }}-600 h-2 rounded-full" style="width: {{ $persen }}%"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Action Buttons -->
    <div class="flex gap-2 mb-2">
        <form method="POST" action="{{ route('admin.referensi.alignment.refresh') }}">
            @csrf
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sync-alt" aria-hidden="true"></i> Coba Cocokkan Ulang
            </button>
        </form>
        <a href="{{ route('admin.referensi.alignment.export') }}" class="btn btn-secondary">
            <i class="fas fa-download" aria-hidden="true"></i> Ekspor Semua Belum Terpetakan (CSV)
        </a>
    </div>
    <p class="text-sm text-gray-500 mb-6">Pencocokan ulang hanya mengisi nilai yang belum memiliki jadwal; tautan yang sudah ada tidak diubah.</p>
    
    @if(isset($unmatched) && $unmatched->count() > 0)
    <!-- Unmatched List -->
    <div class="card mb-6">
        <div class="card-header">
            <h3 class="font-bold">Nilai Tidak Terpetakan ({{ $stats['unmatched'] ?? $unmatched->count() }})</h3>
            @if(($stats['unmatched'] ?? $unmatched->count()) > $unmatched->count())
                <p class="text-sm text-gray-500">Daftar menampilkan 50 nilai terbaru; ekspor mencakup seluruhnya.</p>
            @endif
        </div>
        <div class="card-body">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Siswa</th>
                            <th>Mata Pelajaran</th>
                            <th>Kelas</th>
                            <th>Semester</th>
                            <th>Alasan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($unmatched as $nilai)
                        <tr>
                            <td>{{ $nilai->siswa->nama_peserta_didik ?? '-' }}</td>
                            <td>{{ $nilai->mata_pelajaran }}</td>
                            <td>{{ $nilai->kelas }}</td>
                            <td>{{ $nilai->semester }} {{ $nilai->tahun_ajaran }}</td>
                            <td>
                                Belum terpetakan — periksa nama mapel, kelas, semester, dan jadwal guru.
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
    
    <!-- Info Box -->
    <div class="card">
        <div class="card-header">
            <h3 class="font-bold">Tentang Alignment</h3>
        </div>
        <div class="card-body">
            <p class="text-sm text-gray-600 mb-4">
                Alignment mencocokkan nilai e-Rapor dengan jadwal pelajaran dari Dapodik. 
                Jika ada unmatched, cek: (1) Nama mata pelajaran sesuai, (2) Nama kelas sesuai, 
                (3) Semester dan tahun ajaran benar, (4) Guru terdaftar di jadwal.
            </p>
            <p class="text-sm text-gray-600">
                <strong>Target:</strong> Minimal 90% matched untuk integrasi e-Rapor optimal.
            </p>
        </div>
    </div>
</div>
@endsection