@extends('layouts.admin-with-tabs')

@section('title', 'Profil Kognitif Siswa - Admin')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Profil Kognitif TKA: ' . $siswa->nama_peserta_didik)
@section('page_subtitle', 'Kelas ' . ($siswa->kelas ?? '-') . ' • NIS: ' . ($siswa->nis ?? '-'))

@section('header_actions')
    <a href="{{ route('admin.tka.kognitif.index', ['kelas' => $siswa->kelas]) }}" class="btn btn-outline btn-sm">
        <i class="fas fa-arrow-left"></i> Kembali ke Daftar
    </a>
@endsection

@section('content')
    @include('tka.partials.student-cognitive-detail', [
        'profile' => $profile,
        'rekomendasi' => $rekomendasi,
        'showStudentLaunchButton' => false,
    ])
@endsection
