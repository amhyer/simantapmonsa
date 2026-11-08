@extends('layouts.app')
@section('title', 'Simulasi Adaptif TKA - ' . $paket['judul'])

@section('sidebar')
    @include('siswa.partials.sidebar')
@endsection

@section('page_title', $paket['judul'])
@section('page_subtitle', 'Mode Simulasi Adaptif (CAT) • Tingkat Kesulitan Menyesuaikan Ketepatan Jawaban Anda')

@section('content')
@php
    $tingkatBadge = match ($question['tingkat'] ?? 'sedang') {
        'sulit' => ['cls' => 'tag-bad', 'label' => 'Tingkat Sulit (HOTS • Bobot 1,5x)'],
        'mudah' => ['cls' => 'tag-ok', 'label' => 'Tingkat Dasar (Bobot 1,0x)'],
        default => ['cls' => 'tag-primary', 'label' => 'Tingkat Menengah (Bobot 1,25x)'],
    };
    $pctProgress = $simulation->target_soal > 0
        ? round((($nomorUrut - 1) / $simulation->target_soal) * 100)
        : 0;
@endphp

{{-- Status Bar Progres & Timer --}}
<div class="card" style="margin-bottom:20px;border-left:4px solid var(--violet)">
    <div class="card-body" style="padding:16px 20px">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:10px">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <span class="tag tag-violet" style="font-weight:800">Butir {{ $nomorUrut }} dari {{ $simulation->target_soal }}</span>
                <span class="tag {{ $tingkatBadge['cls'] }}">{{ $tingkatBadge['label'] }}</span>
                <span class="tag tag-secondary"><i class="fas fa-bullseye"></i> {{ $question['indikator'] }}</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;font-weight:800;color:var(--navy);font-size:14px">
                <i class="fas fa-stopwatch" style="color:var(--violet)"></i>
                <span>Waktu Butir Ini: <span id="step-timer">00:01</span></span>
            </div>
        </div>
        <div class="bar">
            <span class="fill" style="width:{{ $pctProgress }}%;background:var(--violet)"></span>
        </div>
    </div>
</div>

{{-- Split-Pane Wacana & Pertanyaan --}}
<div class="grid grid-2" style="gap:20px;align-items:start">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-file-alt" style="color:var(--violet);margin-right:8px"></i>Stimulus Wacana / Konteks Soal</h3>
            <span class="tag tag-secondary">{{ $question['sub_indikator'] ?? '' }}</span>
        </div>
        <div class="card-body" style="font-size:14.5px;line-height:1.75;color:var(--text);white-space:pre-line">
            {{ $question['stimulus'] }}
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-question-circle" style="color:var(--navy);margin-right:8px"></i>Pertanyaan & Pilihan Jawaban</h3>
        </div>
        <div class="card-body">
            <p style="font-size:15px;font-weight:700;color:var(--navy);line-height:1.6;margin:0 0 18px">
                {{ $question['prompt'] }}
            </p>

            <form method="POST" action="{{ route('siswa.tka-simulasi.adaptif.answer', $simulation) }}" id="adaptive-answer-form">
                @csrf
                <input type="hidden" name="question_id" value="{{ $question['id'] }}">
                <input type="hidden" name="durasi_detik" id="durasi_detik_input" value="5">

                <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px">
                    @foreach($question['options'] as $optKey => $optText)
                        <label style="display:flex;align-items:flex-start;gap:12px;padding:13px 15px;border:1.5px solid var(--line);border-radius:10px;cursor:pointer;background:#FAFBFD">
                            <input type="radio" name="answer" value="{{ $optKey }}" required style="margin-top:3px">
                            <span style="font-size:14px;line-height:1.5;color:var(--text)">
                                <strong style="color:var(--violet);margin-right:6px">{{ $optKey }}.</strong> {{ $optText }}
                            </span>
                        </label>
                    @endforeach
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="display:flex;align-items:center;justify-content:center;gap:8px;padding:12px 18px;font-weight:700">
                    <i class="fas fa-arrow-right"></i>
                    {{ $nomorUrut >= $simulation->target_soal ? 'Simpan & Lihat Hasil Adaptif' : 'Kunci Jawaban & Lanjut Butir Adaptif Berikutnya' }}
                </button>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var startTs = Date.now();
    var timerEl = document.getElementById('step-timer');
    var inputEl = document.getElementById('durasi_detik_input');
    setInterval(function () {
        var sec = Math.max(1, Math.floor((Date.now() - startTs) / 1000));
        if (inputEl) inputEl.value = sec;
        if (timerEl) {
            var m = String(Math.floor(sec / 60)).padStart(2, '0');
            var s = String(sec % 60).padStart(2, '0');
            timerEl.textContent = m + ':' + s;
        }
    }, 1000);
})();
</script>
@endsection
