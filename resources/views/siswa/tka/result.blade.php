@extends('layouts.app')
@section('title', 'Hasil Evaluasi Simulasi TKA - ' . $result['paket_judul'])

@push('styles')
@vite(['resources/css/katex.css'])
@endpush

@section('sidebar')
    @include('siswa.partials.sidebar')
@endsection

@section('page_title', 'Hasil Evaluasi Simulasi TKA')
@section('page_subtitle', $result['paket_judul'])

@section('content')
@include('cbt.feedback')

<div style="max-width:850px;margin:0 auto">
    {{-- Score Hero Banner --}}
    <div class="score-hero-card">
        <span class="tag tag-gold" style="font-weight:700;margin-bottom:12px">HASIL SIMULASI MANDIRI PUSMENDIK</span>
        <h2 style="font-size:22px;font-weight:800;letter-spacing:-0.4px;margin:8px 0 16px">{{ $result['paket_judul'] }}</h2>

        <div class="score-circle">
            <span class="score-num">{{ number_format($result['skor'], 1) }}</span>
            <span class="score-max">SKOR AKHIR</span>
        </div>

        <h3 style="font-size:18px;font-weight:700;margin:10px 0 6px;color:#fff">
            Kategori: <span class="tag tag-{{ $result['level_color'] }}" style="font-size:14px;padding:4px 12px">{{ $result['level'] }}</span>
        </h3>
        <p style="font-size:13.5px;color:rgba(255,255,255,0.9);max-width:550px;margin:0 auto 16px;line-height:1.5">
            {{ $result['level_desc'] }}
        </p>

        <div style="font-size:13px;color:rgba(255,255,255,0.85);display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,0.12);padding:6px 16px;border-radius:99px">
            <i class="fas fa-check-circle" style="color:#34D399"></i>
            <span>Diselesaikan pada {{ $result['submitted_at'] }}</span>
        </div>
    </div>

    {{-- Stats Cards --}}
    @php
        $total = $result['total'];
        $benar = $result['benar'];
        $salah = max(0, $total - $benar);
        $percent = $total > 0 ? round(($benar / $total) * 100) : 0;
    @endphp

    <div class="grid grid-3" style="margin-bottom:24px">
        <div class="stat-card ok">
            <i class="fas fa-check-double stat-icon"></i>
            <div class="stat-label">Jawaban Benar</div>
            <div class="stat-value">{{ $benar }}</div>
            <div class="stat-change up">{{ $percent }}% akurasi</div>
        </div>

        <div class="stat-card bad">
            <i class="fas fa-times-circle stat-icon"></i>
            <div class="stat-label">Jawaban Salah / Kosong</div>
            <div class="stat-value">{{ $salah }}</div>
            <div class="stat-change down">{{ 100 - $percent }}% butir</div>
        </div>

        <div class="stat-card primary">
            <i class="fas fa-list-ol stat-icon"></i>
            <div class="stat-label">Total Soal Simulasi</div>
            <div class="stat-value">{{ $total }}</div>
            <div class="stat-change">Soal Berkonteks</div>
        </div>
    </div>

    {{-- Pembahasan Butir Soal & Diagnostik --}}
    <div class="card" style="margin-bottom:24px">
        <div class="card-header">
            <h3><i class="fas fa-book-reader" style="color:var(--navy);margin-right:8px"></i>Pembahasan & Kunci Jawaban</h3>
            <span class="tag tag-secondary">Evaluasi Butir Soal</span>
        </div>

        <div class="card-body tight">
            @foreach($result['detail'] as $index => $item)
                @php
                    $q = $item['soal'];
                    $userAns = $item['user_answer'];
                    $isCorrect = $item['is_correct'];
                @endphp
                <div style="padding:20px 24px;border-bottom:1px solid var(--line);background:{{ $isCorrect ? '#fff' : '#FFFDFD' }}">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
                        <span style="font-weight:800;font-size:13.5px;color:var(--navy)">
                            Soal No. {{ $index + 1 }}
                        </span>
                        @if($isCorrect)
                            <span class="tag tag-ok"><i class="fas fa-check"></i> Jawaban Anda Benar</span>
                        @else
                            <span class="tag tag-bad"><i class="fas fa-times"></i> Jawaban Anda Belum Tepat</span>
                        @endif
                    </div>

                    @if(!empty($q['stimulus']))
                        <div style="background:#FAFBFD;border:1px solid var(--line);border-radius:8px;padding:12px 16px;margin-bottom:14px;font-size:13px;line-height:1.6;color:var(--muted)">
                            <div style="font-weight:700;font-size:11.5px;color:var(--navy);margin-bottom:4px;text-transform:uppercase">
                                <i class="fas fa-file-alt"></i> Stimulus Soal:
                            </div>
                            {!! nl2br(e($q['stimulus'])) !!}
                        </div>
                    @endif

                    <div style="font-size:14px;font-weight:600;color:var(--text);margin-bottom:14px">
                        {{ $q['prompt'] }}
                    </div>

                    {{-- Options status --}}
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:8px;margin-bottom:14px">
                        @foreach($q['options'] as $key => $opt)
                            @php
                                $isThisCorrectKey = ($key === $q['kunci']);
                                $isThisUserAns = ($key === $userAns);
                            @endphp
                            <div style="padding:8px 12px;border-radius:6px;border:1.5px solid {{ $isThisCorrectKey ? 'var(--ok)' : ($isThisUserAns ? 'var(--bad)' : 'var(--line)') }};background:{{ $isThisCorrectKey ? '#E6F5F0' : ($isThisUserAns ? '#FEE2E2' : '#fff') }};font-size:12.5px;display:flex;align-items:center;gap:8px">
                                <span style="font-weight:800;width:22px;height:22px;border-radius:50%;background:{{ $isThisCorrectKey ? 'var(--ok)' : ($isThisUserAns ? 'var(--bad)' : '#EEF2F9') }};color:{{ ($isThisCorrectKey || $isThisUserAns) ? '#fff' : 'var(--navy)' }};display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0">
                                    {{ $key }}
                                </span>
                                <span style="flex:1">{{ $opt }}</span>
                                @if($isThisCorrectKey)
                                    <i class="fas fa-check" style="color:var(--ok);font-size:12px"></i>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    {{-- Pembahasan Box --}}
                    <div style="background:#EEF2F9;border-left:3px solid var(--navy);padding:10px 14px;border-radius:0 6px 6px 0;font-size:12.5px;line-height:1.5;color:var(--navy-dark)">
                        <strong>Pembahasan:</strong> {{ $q['pembahasan'] }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Bottom Navigation --}}
    <div class="card">
        <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
            <div style="font-size:13px;color:var(--muted)">
                <i class="fas fa-graduation-cap"></i> Teruslah berlatih untuk mengasah penalaran dan kesiapan asesmen standar nasional.
            </div>

            <div style="display:flex;align-items:center;gap:10px">
                <a href="{{ route('siswa.tka-simulasi.index') }}" class="btn btn-primary">
                    <i class="fas fa-layer-group"></i> Coba Subtes Lain
                </a>
                <a href="{{ route('siswa.dashboard') }}" class="btn btn-secondary">
                    <i class="fas fa-home"></i> Kembali ke Beranda
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@vite(['resources/js/katex.js'])
<script>
(function () {
    function renderMath() {
        if (typeof renderMathInElement === 'function') {
            renderMathInElement(document.body, {
                delimiters: [
                    {left: '$$', right: '$$', display: true},
                    {left: '$', right: '$', display: false},
                    {left: '\\(', right: '\\)', display: false},
                    {left: '\\[', right: '\\]', display: true}
                ],
                throwOnError: false,
                ignoredTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code']
            });
        }
    }
    document.addEventListener('DOMContentLoaded', renderMath);
    window.addEventListener('simantap:katex-ready', renderMath);
})();
</script>
@endpush
