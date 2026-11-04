@extends('layouts.app')
@section('title', 'Hasil Ujian - ' . $session->title)

@section('sidebar')
    @include('siswa.partials.sidebar')
@endsection

@section('page_title', 'Hasil Ujian CBT')
@section('page_subtitle', $session->title)

@section('content')
@include('cbt.feedback')

<div style="max-width:760px;margin:0 auto">
    {{-- Score Hero Banner --}}
    <div class="score-hero-card">
        <span class="tag tag-gold" style="font-weight:700;margin-bottom:12px">HASIL EVALUASI RESMI</span>
        <h2 style="font-size:22px;font-weight:800;letter-spacing:-0.4px;margin:8px 0 20px">{{ $session->title }}</h2>

        <div class="score-circle">
            <span class="score-num">{{ number_format($result->score ?? 0, 2) }}</span>
            <span class="score-max">SKOR AKHIR</span>
        </div>

        <h3 style="font-size:18px;font-weight:700;margin:10px 0 6px;color:#fff">Nilai {{ number_format($result->score ?? 0, 2) }}</h3>
        <p style="font-size:14px;color:rgba(255,255,255,0.9);margin:0 0 16px">Benar {{ $result->correct_count ?? 0 }} dari {{ count($session->question_snapshot ?? []) }} soal.</p>

        @if($result->submitted_at)
            <div style="font-size:14px;color:rgba(255,255,255,0.9);display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,0.12);padding:6px 16px;border-radius:99px">
                <i class="fas fa-check-circle" style="color:#34D399"></i>
                <span>Terkumpul pada {{ $result->submitted_at->format('d M Y, H:i:s') }}</span>
            </div>
        @else
            <div style="font-size:13px;color:#FDE68A;background:rgba(217,119,6,0.3);padding:6px 16px;border-radius:99px">
                <i class="fas fa-clock"></i> Ujian belum difinalisasi secara penuh
            </div>
        @endif
    </div>

    {{-- Detail Breakdown Cards --}}
    @php
        $totalQuestions = count($session->question_snapshot ?? []);
        $correctCount = $result->correct_count ?? 0;
        $incorrectCount = max(0, $totalQuestions - $correctCount);
        $percentCorrect = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100) : 0;
    @endphp

    <div class="grid grid-3" style="margin-bottom:24px">
        <div class="stat-card ok">
            <i class="fas fa-check-double stat-icon"></i>
            <div class="stat-label">Jawaban Benar</div>
            <div class="stat-value">{{ $correctCount }}</div>
            <div class="stat-change up">{{ $percentCorrect }}% akurasi</div>
        </div>

        <div class="stat-card bad">
            <i class="fas fa-times-circle stat-icon"></i>
            <div class="stat-label">Jawaban Salah / Kosong</div>
            <div class="stat-value">{{ $incorrectCount }}</div>
            <div class="stat-change down">{{ 100 - $percentCorrect }}% butir</div>
        </div>

        <div class="stat-card primary">
            <i class="fas fa-list-ol stat-icon"></i>
            <div class="stat-label">Total Butir Soal</div>
            <div class="stat-value">{{ $totalQuestions }}</div>
            <div class="stat-change">Pilihan Ganda</div>
        </div>
    </div>

    {{-- Action Footer --}}
    <div class="card">
        <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
            <div style="font-size:13px;color:var(--muted)">
                <i class="fas fa-info-circle"></i> Hasil ujian Anda telah dicatat ke dalam buku nilai kelas secara otomatis.
            </div>

            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                @if(!$result->submitted_at)
                    <form method="POST" action="{{ route('cbt.student.submit', $result) }}" style="display:inline">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check-double"></i> Kumpulkan / finalisasi jawaban tersimpan
                        </button>
                    </form>
                    <a href="{{ route('cbt.student.start', $session) }}" class="btn btn-secondary">
                        <i class="fas fa-play"></i> Lanjutkan Ujian
                    </a>
                @endif
                <a href="{{ route('cbt.student.index') }}" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Kembali ke Daftar Ujian
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
