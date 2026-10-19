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
    <div class="flex gap-2 mb-6">
        <button onclick="alert('Fitur refresh alignment akan segera tersedia')" class="btn btn-primary">
            Refresh Alignment
        </button>
        <button onclick="alert('Fitur export akan segera tersedia')" class="btn btn-secondary">
            Export Unmatched
        </button>
    </div>
    
    @if(isset($unmatched) && $unmatched->count() > 0)
    <!-- Unmatched List -->
    <div class="card mb-6">
        <div class="card-header">
            <h3 class="font-bold">Nilai Tidak Terpetakan ({{ $unmatched->count() }})</h3>
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
                                @if(!$nilai->jadwal_pelajaran_id)
                                    @if(empty($nilai->mata_pelajaran))
                                        Nama mapel kosong
                                    @else
                                        Nama mapel tak cocok
                                    @endif
                                @endif
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