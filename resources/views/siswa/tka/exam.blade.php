@extends('layouts.app')
@section('title', 'Mengerjakan Simulasi TKA - ' . $paket['judul'])

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css" crossorigin="anonymous">
@endpush

@section('sidebar')
    @include('siswa.partials.sidebar')
@endsection

@section('page_title', 'Simulasi TKA: ' . $paket['kategori'])
@section('page_subtitle', 'Format Resmi Pusmendik Kemendikdasmen • Dual-Pane Wacana & Penalaran')

@section('content')
@include('cbt.feedback')

{{-- CBT Sticky HUD Pusmendik --}}
<div class="cbt-hud">
    <div class="cbt-hud-title">
        <div style="width:34px;height:34px;border-radius:8px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:16px">
            <i class="fas fa-graduation-cap"></i>
        </div>
        <div>
            <div style="font-size:14px;font-weight:700">{{ $paket['judul'] }}</div>
            <div style="font-size:11px;opacity:0.85">Soal <span id="hud-q-active">1</span> dari {{ count($questions) }} butir</div>
        </div>
    </div>

    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        {{-- Autosave Indicator --}}
        <div id="save-status" class="cbt-autosave-badge ok" role="status" aria-live="polite">
            <i class="fas fa-check-circle"></i> <span>Tersimpan di server</span>
        </div>

        {{-- Font Resizer --}}
        <div style="display:flex;align-items:center;background:rgba(255,255,255,0.12);border-radius:6px;padding:2px">
            <button type="button" onclick="adjustFontSize(-1)" title="Perkecil huruf" style="background:none;border:none;color:#fff;padding:4px 8px;cursor:pointer;font-size:11px;font-weight:700">A-</button>
            <button type="button" onclick="adjustFontSize(0)" title="Ukuran normal" style="background:none;border:none;color:#fff;padding:4px 8px;cursor:pointer;font-size:12px;font-weight:700">A</button>
            <button type="button" onclick="adjustFontSize(1)" title="Perbesar huruf" style="background:none;border:none;color:#fff;padding:4px 8px;cursor:pointer;font-size:14px;font-weight:700">A+</button>
        </div>

        {{-- Countdown Timer --}}
        <div id="timer-box" class="cbt-hud-timer">
            <i class="far fa-clock"></i>
            <span id="timer" role="timer">--:--</span>
        </div>

        {{-- Mobile Palette Toggle --}}
        <button type="button" onclick="toggleMobilePalette()" class="btn btn-sm btn-gold" style="display:none" id="btn-toggle-palette">
            <i class="fas fa-th"></i> Daftar Soal
        </button>
    </div>
</div>

<form id="tka-exam-form" method="POST" action="{{ route('siswa.tka-simulasi.submit', $paket['id']) }}">
@csrf

<div class="cbt-main-grid">
    {{-- Left: Dual-Pane Question Container --}}
    <div>
        @foreach($questions as $index => $q)
            @php
                $qNum = $loop->iteration;
                $savedAns = $answers[$q['id']] ?? null;
            @endphp
            <div class="cbt-question-card question-pane" id="pane-q-{{ $qNum }}" style="{{ $loop->first ? '' : 'display:none' }}">
                <div class="cbt-question-header">
                    <div class="cbt-q-number">
                        <i class="fas fa-question-circle" style="margin-right:6px"></i> Soal No. {{ $qNum }}
                    </div>
                    <div style="display:flex;align-items:center;gap:8px">
                        <button type="button" class="btn btn-sm btn-ghost btn-ragu" data-qindex="{{ $qNum }}" onclick="toggleRagu({{ $qNum }})">
                            <i class="far fa-flag"></i> <span class="ragu-text">Ragu-ragu</span>
                        </button>
                    </div>
                </div>

                {{-- Dual-Pane Layout: Left Stimulus Wacana, Right Soal & Options --}}
                <div class="cbt-split-pane">
                    {{-- Left Stimulus Column --}}
                    <div class="cbt-stimulus-col">
                        <div class="cbt-stimulus-header">
                            <i class="fas fa-book-open"></i> Wacana & Stimulus Asesmen
                        </div>
                        <div class="cbt-stimulus-content">{!! nl2br(e($q['stimulus'])) !!}</div>
                    </div>

                    {{-- Right Question & Options Column --}}
                    <div class="cbt-question-col">
                        <div class="cbt-q-prompt" id="prompt-{{ $qNum }}">{{ $q['prompt'] }}</div>

                        <div class="cbt-options-group">
                            @foreach($q['options'] as $key => $option)
                                <label class="cbt-option-label {{ $savedAns === $key ? 'is-selected' : '' }}" for="q-{{ $q['id'] }}-{{ $key }}">
                                    <input type="radio" 
                                           id="q-{{ $q['id'] }}-{{ $key }}" 
                                           name="answers[{{ $q['id'] }}]" 
                                           value="{{ $key }}" 
                                           data-question="{{ $q['id'] }}" 
                                           data-qnum="{{ $qNum }}"
                                           @checked($savedAns === $key)>
                                    <span class="cbt-option-key">{{ $key }}</span>
                                    <span class="cbt-option-text">{{ $option }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Bottom Navigation Bar --}}
                <div class="cbt-bottom-bar">
                    <div>
                        @if($qNum > 1)
                            <button type="button" class="btn btn-secondary" onclick="goToQuestion({{ $qNum - 1 }})">
                                <i class="fas fa-arrow-left"></i> Soal Sebelumnya
                            </button>
                        @endif
                    </div>
                    
                    <div style="display:flex;align-items:center;gap:10px">
                        @if($qNum < count($questions))
                            <button type="button" class="btn btn-primary" onclick="goToQuestion({{ $qNum + 1 }})">
                                Soal Selanjutnya <i class="fas fa-arrow-right"></i>
                            </button>
                        @else
                            <button type="button" class="btn btn-primary" onclick="confirmFinishExam()" style="background:var(--ok);border-color:var(--ok)">
                                <i class="fas fa-check-double"></i> Selesai Ujian
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Right: Question Palette Drawer / Sidebar --}}
    <div class="cbt-palette-card" id="palette-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;padding-bottom:10px;border-bottom:1px solid var(--line)">
            <h4 style="font-size:14px;font-weight:700;margin:0;color:var(--text);display:flex;align-items:center;gap:6px">
                <i class="fas fa-th" style="color:var(--navy)"></i> Nomor Soal
            </h4>
            <div style="display:flex;align-items:center;gap:8px">
                <span style="font-size:11px;color:var(--muted)">
                    <span id="answered-count">0</span>/{{ count($questions) }} Selesai
                </span>
                <button type="button" onclick="window.scrollTo({top: 0, behavior: 'smooth'})" class="btn btn-sm btn-secondary cbt-mobile-back-btn" title="Kembali ke Soal">
                    <i class="fas fa-arrow-up"></i> Soal
                </button>
            </div>
        </div>

        {{-- Palette Grid --}}
        <div class="cbt-palette-grid">
            @foreach($questions as $index => $q)
                @php
                    $qNum = $loop->iteration;
                    $isAnswered = isset($answers[$q['id']]);
                @endphp
                <button type="button" 
                        id="palette-btn-{{ $qNum }}" 
                        class="cbt-palette-item {{ $qNum === 1 ? 'current' : '' }} {{ $isAnswered ? 'answered' : '' }}"
                        onclick="goToQuestion({{ $qNum }})">
                    {{ $qNum }}
                </button>
            @endforeach
        </div>

        {{-- Legend --}}
        <div style="margin-top:16px;padding-top:12px;border-top:1px solid var(--line);display:flex;flex-direction:column;gap:6px;font-size:11px;color:var(--muted)">
            <div style="display:flex;align-items:center;gap:8px">
                <span style="width:14px;height:14px;border-radius:3px;background:var(--ok);display:inline-block"></span>
                <span>Sudah dijawab</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px">
                <span style="width:14px;height:14px;border-radius:3px;background:var(--gold);display:inline-block"></span>
                <span>Ragu-ragu</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px">
                <span style="width:14px;height:14px;border-radius:3px;background:#FAFBFD;border:1px solid var(--line);display:inline-block"></span>
                <span>Belum dijawab</span>
            </div>
        </div>

        {{-- Finish Button in Sidebar --}}
        <div style="margin-top:16px">
            <button id="submit-exam" type="button" onclick="confirmFinishExam()" class="btn btn-block" style="background:var(--ok);border-color:var(--ok);color:#fff">
                <i class="fas fa-cloud-upload-alt"></i> Kumpulkan Jawaban
            </button>
            <div id="submit-status" style="font-size:12px;color:var(--bad);text-align:center;margin-top:6px" role="alert"></div>
        </div>
    </div>
</div>
</form>
@endsection

@push('scripts')
<script>
(() => {
    let currentQuestion = 1;
    const totalQuestions = {{ count($questions) }};
    const raguSet = new Set();
    const form = document.getElementById('tka-exam-form');
    const status = document.getElementById('save-status');
    const submitStatus = document.getElementById('submit-status');
    const csrf = @json(csrf_token());
    const saveUrl = @json(route('siswa.tka-simulasi.answer', $paket['id']));
    const deadline = {{ $deadlineTs }};
    const serverStart = @json(now()->getTimestamp() * 1000);
    const localStart = performance.now();
    let submitting = false;
    let expired = false;

    window.toggleMobilePalette = function() {
        const pal = document.getElementById('palette-card');
        if (pal) pal.scrollIntoView({ behavior: 'smooth' });
    };

    window.goToQuestion = function(qNum) {
        if (qNum < 1 || qNum > totalQuestions) return;
        
        const prevPane = document.getElementById('pane-q-' + currentQuestion);
        if (prevPane) prevPane.style.display = 'none';
        
        const prevBtn = document.getElementById('palette-btn-' + currentQuestion);
        if (prevBtn) prevBtn.classList.remove('current');

        currentQuestion = qNum;
        const nextPane = document.getElementById('pane-q-' + currentQuestion);
        if (nextPane) nextPane.style.display = 'block';

        const nextBtn = document.getElementById('palette-btn-' + currentQuestion);
        if (nextBtn) nextBtn.classList.add('current');

        const hudQ = document.getElementById('hud-q-active');
        if (hudQ) hudQ.textContent = currentQuestion;

        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    window.toggleRagu = function(qNum) {
        const btn = document.querySelector(`.btn-ragu[data-qindex="${qNum}"]`);
        const pBtn = document.getElementById('palette-btn-' + qNum);
        if (raguSet.has(qNum)) {
            raguSet.delete(qNum);
            if (btn) {
                btn.classList.remove('btn-gold');
                btn.classList.add('btn-ghost');
            }
            if (pBtn && pBtn.classList.contains('flagged')) {
                pBtn.classList.remove('flagged');
                const isAns = document.querySelector(`input[data-qnum="${qNum}"]:checked`);
                if (isAns) pBtn.classList.add('answered');
            }
        } else {
            raguSet.add(qNum);
            if (btn) {
                btn.classList.remove('btn-ghost');
                btn.classList.add('btn-gold');
            }
            if (pBtn) {
                pBtn.classList.remove('answered');
                pBtn.classList.add('flagged');
            }
        }
    };

    let fontStep = 1;
    const fontSizes = ['13px', '15px', '18px'];
    window.adjustFontSize = function(dir) {
        if (dir === 0) fontStep = 1;
        else fontStep = Math.max(0, Math.min(2, fontStep + dir));
        document.querySelectorAll('.cbt-q-prompt, .cbt-option-text, .cbt-stimulus-content').forEach(el => {
            el.style.fontSize = fontSizes[fontStep];
        });
    };

    function refreshAnsweredCount() {
        const checked = document.querySelectorAll('input[data-question]:checked').length;
        const countEl = document.getElementById('answered-count');
        if (countEl) countEl.textContent = checked;
    }
    refreshAnsweredCount();

    form.addEventListener('change', event => {
        if (!event.target.matches('input[data-question]')) return;
        const qNum = event.target.dataset.qnum;
        
        const parentCard = document.getElementById('pane-q-' + qNum);
        if (parentCard) {
            parentCard.querySelectorAll('.cbt-option-label').forEach(lbl => lbl.classList.remove('is-selected'));
            const checkedLbl = event.target.closest('.cbt-option-label');
            if (checkedLbl) checkedLbl.classList.add('is-selected');
        }

        const pBtn = document.getElementById('palette-btn-' + qNum);
        if (pBtn && !raguSet.has(Number(qNum))) {
            pBtn.classList.add('answered');
        }

        refreshAnsweredCount();

        // Autosave via AJAX
        if (!submitting && !expired) {
            status.className = 'cbt-autosave-badge pending';
            status.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Menyimpan…</span>';

            fetch(saveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({
                    question_id: event.target.dataset.question,
                    answer: event.target.value,
                })
            })
            .then(res => res.json())
            .then(() => {
                status.className = 'cbt-autosave-badge ok';
                status.innerHTML = '<i class="fas fa-check-circle"></i> <span>Tersimpan di server</span>';
            })
            .catch(() => {
                status.className = 'cbt-autosave-badge pending';
                status.innerHTML = '<i class="fas fa-exclamation-triangle"></i> <span>Tersimpan di peramban</span>';
            });
        }
    });

    window.confirmFinishExam = function() {
        if (submitting) return;
        const answered = document.querySelectorAll('input[data-question]:checked').length;
        const unanswered = totalQuestions - answered;
        
        let msg = `Anda telah menjawab ${answered} dari ${totalQuestions} soal.\n`;
        if (unanswered > 0) msg += `Masih ada ${unanswered} soal yang belum dijawab!\n`;
        if (raguSet.size > 0) msg += `Ada ${raguSet.size} soal ditandai Ragu-ragu.\n`;
        msg += '\nApakah Anda yakin ingin menyelesaikan simulasi ini dan melihat hasil evaluasi?';

        if (confirm(msg)) {
            submitting = true;
            document.querySelectorAll('#submit-exam, .btn-primary').forEach(b => b.disabled = true);
            submitStatus.textContent = 'Mengevaluasi jawaban Anda… Harap tunggu sebentar.';
            form.submit();
        }
    };

    const timerEl = document.getElementById('timer');
    const timerBox = document.getElementById('timer-box');
    function tick() {
        const seconds = Math.max(0, Math.ceil((deadline - serverStart - (performance.now() - localStart)) / 1000));
        const mins = Math.floor(seconds / 60);
        const secs = String(seconds % 60).padStart(2, '0');
        
        if (timerEl) timerEl.textContent = mins + ':' + secs;
        
        if (seconds <= 300 && timerBox && !timerBox.classList.contains('urgent')) {
            timerBox.classList.add('urgent');
        }

        if (seconds === 0 && !submitting) {
            expired = true;
            status.className = 'cbt-autosave-badge pending';
            status.innerHTML = '<i class="fas fa-hourglass-end"></i> <span>Waktu habis. Mengumpulkan…</span>';
            form.submit();
        }
    }
    tick();
    setInterval(tick, 1000);

    function renderExamMath() {
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
        } else {
            setTimeout(renderExamMath, 150);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', renderExamMath);
    } else {
        renderExamMath();
    }
})();
</script>
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js" crossorigin="anonymous"></script>
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/contrib/auto-render.min.js" crossorigin="anonymous"></script>
@endpush
