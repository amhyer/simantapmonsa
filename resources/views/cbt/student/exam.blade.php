@extends('layouts.app')
@section('title', 'Mengerjakan Ujian - ' . $session->title)

@push('styles')
@vite(['resources/css/katex.css'])
@endpush

@section('sidebar')
    @include('siswa.partials.sidebar')
@endsection

@section('page_title', 'Ujian CBT: ' . $session->title)
@section('page_subtitle', 'Pilihan Ganda • Sisa waktu dan autosave berjalan secara waktu nyata')

@section('content')
@include('cbt.feedback')

{{-- CBT Sticky HUD --}}
<div class="cbt-hud">
    <div class="cbt-hud-title">
        <div style="width:34px;height:34px;border-radius:8px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:16px">
            <i class="fas fa-file-signature"></i>
        </div>
        <div>
            <div style="font-size:14px;font-weight:700">{{ $session->title }}</div>
            <div style="font-size:11px;opacity:0.8">Soal <span id="hud-q-active">1</span> dari {{ count($questions) }} butir</div>
        </div>
    </div>

    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        {{-- Autosave Indicator --}}
        <div id="save-status" class="cbt-autosave-badge ok" role="status" aria-live="polite">
            <i class="fas fa-check-circle"></i> <span>Semua perubahan jawaban tersimpan di server.</span>
        </div>

        {{-- Font Resizer --}}
        <div style="display:flex;align-items:center;background:rgba(255,255,255,0.12);border-radius:6px;padding:2px">
            <button type="button" onclick="adjustFontSize(-1)" title="Perkecil huruf" style="background:none;border:none;color:#fff;padding:4px 8px;cursor:pointer;font-size:11px;font-weight:700">A-</button>
            <button type="button" onclick="adjustFontSize(0)" title="Ukuran normal" style="background:none;border:none;color:#fff;padding:4px 8px;cursor:pointer;font-size:12px;font-weight:700">A</button>
            <button type="button" onclick="adjustFontSize(1)" title="Perbesar huruf" style="background:none;border:none;color:#fff;padding:4px 8px;cursor:pointer;font-size:14px;font-weight:700">A+</button>
        </div>

        {{-- Fullscreen HUD Button --}}
        <button type="button" id="btn-fullscreen-hud" onclick="toggleExamFullscreen()" title="Buka/Tutup Layar Penuh" style="background:rgba(255,255,255,0.12);border:none;color:#fff;padding:4px 10px;border-radius:6px;cursor:pointer;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:6px">
            <i class="fas fa-expand" id="hud-fs-icon"></i>
            <span id="hud-fs-text">Layar Penuh</span>
        </button>

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

<noscript>
    <div class="note note-warn" style="margin-bottom:16px">
        <i class="fas fa-exclamation-triangle"></i>
        JavaScript tidak aktif. Autosave/timer tidak tersedia. Klik tombol Kumpulkan sebelum deadline {{ $result->deadline_at->format('H:i:s') }}.
    </div>
</noscript>

<form id="exam-form" class="cbt-form" method="POST" action="{{ route('cbt.student.submit', $result) }}">
@csrf

<div class="cbt-main-grid">
    {{-- Left: Question Area --}}
    <div>
        @foreach($questions as $index => $question)
            @php
                $qNum = $loop->iteration;
                $savedAns = $result->answers[$question['id']] ?? null;
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

                @php
                    $stimulus = $question['stimulus'] ?? null;
                    $promptText = $question['prompt'];
                    if (!$stimulus && preg_match('/\[(?:WACANA|STIMULUS)\](.*?)\[\/(?:WACANA|STIMULUS)\](.*)/si', $promptText, $matches)) {
                        $stimulus = trim($matches[1]);
                        $promptText = trim($matches[2]);
                    }
                @endphp

                @if($stimulus)
                    <div class="cbt-split-pane">
                        <div class="cbt-stimulus-col">
                            <div class="cbt-stimulus-header">
                                <i class="fas fa-book-open"></i> Wacana & Informasi Pendukung
                            </div>
                            <div class="cbt-stimulus-content">{!! nl2br(e($stimulus)) !!}</div>
                        </div>
                        <div class="cbt-question-col">
                            <div class="cbt-q-prompt" id="prompt-{{ $qNum }}">{{ $promptText }}</div>
                            <div class="cbt-options-group">
                                @foreach($question['options'] as $key => $option)
                                    <label class="cbt-option-label {{ $savedAns === $key ? 'is-selected' : '' }}" for="q-{{ $question['id'] }}-{{ $key }}">
                                        <input type="radio" 
                                               id="q-{{ $question['id'] }}-{{ $key }}" 
                                               name="answers[{{ $question['id'] }}]" 
                                               value="{{ $key }}" 
                                               data-question="{{ $question['id'] }}" 
                                               data-qnum="{{ $qNum }}"
                                               @checked($savedAns === $key)>
                                        <span class="cbt-option-key">{{ $key }}</span>
                                        <span class="cbt-option-text">{{ $option }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @else
                    {{-- Prompt --}}
                    <div class="cbt-q-prompt" id="prompt-{{ $qNum }}">{{ $promptText }}</div>

                    {{-- Options --}}
                    <div class="cbt-options-group">
                        @foreach($question['options'] as $key => $option)
                            <label class="cbt-option-label {{ $savedAns === $key ? 'is-selected' : '' }}" for="q-{{ $question['id'] }}-{{ $key }}">
                                <input type="radio" 
                                       id="q-{{ $question['id'] }}-{{ $key }}" 
                                       name="answers[{{ $question['id'] }}]" 
                                       value="{{ $key }}" 
                                       data-question="{{ $question['id'] }}" 
                                       data-qnum="{{ $qNum }}"
                                       @checked($savedAns === $key)>
                                <span class="cbt-option-key">{{ $key }}</span>
                                <span class="cbt-option-text">{{ $option }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif

                {{-- Question Bottom Bar Navigation --}}
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
                                <i class="fas fa-check-double"></i> Kumpulkan Ujian
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
            @foreach($questions as $index => $question)
                @php
                    $qNum = $loop->iteration;
                    $isAnswered = isset($result->answers[$question['id']]);
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
                <i class="fas fa-cloud-upload-alt"></i> Kumpulkan jawaban
            </button>
            <div id="submit-status" style="font-size:12px;color:var(--bad);text-align:center;margin-top:6px" role="alert"></div>
        </div>
    </div>
</div>
</form>
@endsection

@push('scripts')
@include('partials.exam-outbox')
<script>
(() => {
    let currentQuestion = 1;
    const totalQuestions = {{ count($questions) }};
    const raguSet = new Set();
    const form = document.getElementById('exam-form');
    const status = document.getElementById('save-status');
    const submitStatus = document.getElementById('submit-status');
    const csrf = @json(csrf_token());
    const saveUrl = @json(route('cbt.student.answer', $result));
    const integrityUrl = @json(route('cbt.student.integrity', $result));
    const resultUrl = @json(route('cbt.student.result', $result));
    const deadline = @json($result->deadline_at->getTimestamp() * 1000);
    const serverStart = @json(now()->getTimestamp() * 1000);
    const localStart = performance.now();
    let submitting = false;
    let expired = false;

    // Responsive palette toggle on mobile
    if (window.innerWidth <= 992) {
        const toggleBtn = document.getElementById('btn-toggle-palette');
        if (toggleBtn) toggleBtn.style.display = 'inline-flex';
    }

    window.toggleMobilePalette = function() {
        const pal = document.getElementById('palette-card');
        if (pal) pal.scrollIntoView({ behavior: 'smooth' });
    };

    // Navigation function
    window.goToQuestion = function(qNum) {
        if (qNum < 1 || qNum > totalQuestions) return;
        
        // Hide previous
        const prevPane = document.getElementById('pane-q-' + currentQuestion);
        if (prevPane) prevPane.style.display = 'none';
        
        const prevBtn = document.getElementById('palette-btn-' + currentQuestion);
        if (prevBtn) prevBtn.classList.remove('current');

        // Show new
        currentQuestion = qNum;
        const nextPane = document.getElementById('pane-q-' + currentQuestion);
        if (nextPane) nextPane.style.display = 'block';

        const nextBtn = document.getElementById('palette-btn-' + currentQuestion);
        if (nextBtn) nextBtn.classList.add('current');

        const hudQ = document.getElementById('hud-q-active');
        if (hudQ) hudQ.textContent = currentQuestion;

        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    // Ragu-ragu function
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
                // check if answered
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

    // Font size adjuster
    let fontStep = 1;
    const fontSizes = ['13px', '15px', '18px'];
    window.adjustFontSize = function(dir) {
        if (dir === 0) fontStep = 1;
        else {
            fontStep = Math.max(0, Math.min(2, fontStep + dir));
        }
        document.querySelectorAll('.cbt-q-prompt, .cbt-option-text').forEach(el => {
            el.style.fontSize = fontSizes[fontStep];
        });
    };

    // Update answered count
    function refreshAnsweredCount() {
        const checked = document.querySelectorAll('input[data-question]:checked').length;
        const countEl = document.getElementById('answered-count');
        if (countEl) countEl.textContent = checked;
    }
    refreshAnsweredCount();

    // Option selection visual binding
    form.addEventListener('change', event => {
        if (!event.target.matches('input[data-question]')) return;
        const qNum = event.target.dataset.qnum;
        
        // Update label selected state
        const parentCard = document.getElementById('pane-q-' + qNum);
        if (parentCard) {
            parentCard.querySelectorAll('.cbt-option-label').forEach(lbl => lbl.classList.remove('is-selected'));
            const checkedLbl = event.target.closest('.cbt-option-label');
            if (checkedLbl) checkedLbl.classList.add('is-selected');
        }

        // Update palette
        const pBtn = document.getElementById('palette-btn-' + qNum);
        if (pBtn && !raguSet.has(Number(qNum))) {
            pBtn.classList.add('answered');
        }

        refreshAnsweredCount();

        // Exam outbox
        if (!submitting && !expired) {
            outbox.set(event.target.dataset.question, event.target.value);
            outbox.flush();
        }
    });

    // Outbox integration
    const outbox = new window.ExamOutbox(@json('cbt:'.$result->id), {
        url: saveUrl,
        csrf,
        onChange: (pending, tahap) => {
            if (expired || submitting) return;
            if (tahap === 'saving') {
                status.className = 'cbt-autosave-badge pending';
                status.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Menyimpan jawaban…</span>';
            } else if (tahap === 'pending') {
                status.className = 'cbt-autosave-badge pending';
                status.innerHTML = '<i class="fas fa-exclamation-triangle"></i> <span>Tersimpan di HP (antrean kirim)</span>';
            } else if (tahap === 'saved') {
                status.className = 'cbt-autosave-badge ok';
                status.innerHTML = pending
                    ? '<i class="fas fa-clock"></i> <span>Menunggu simpan…</span>'
                    : '<i class="fas fa-check-circle"></i> <span>Semua perubahan jawaban tersimpan di server.</span>';
            }
        },
        onExpired: () => {
            expired = true;
            status.className = 'cbt-autosave-badge pending';
            status.innerHTML = '<i class="fas fa-info-circle"></i> <span>Waktu selesai. Membuka hasil…</span>';
            location.assign(resultUrl);
        },
    });
    outbox.restoreToDom();

    // Re-highlight restored answers
    document.querySelectorAll('input[data-question]:checked').forEach(inp => {
        const qNum = inp.dataset.qnum;
        const lbl = inp.closest('.cbt-option-label');
        if (lbl) lbl.classList.add('is-selected');
        const pBtn = document.getElementById('palette-btn-' + qNum);
        if (pBtn) pBtn.classList.add('answered');
    });
    refreshAnsweredCount();

    setInterval(() => outbox.flush(), 8000);
    window.addEventListener('online', () => outbox.flush());
    window.addEventListener('beforeunload', event => {
        if (outbox.pendingCount && !submitting) { event.preventDefault(); event.returnValue = ''; }
    });

    // Submit confirmation
    window.confirmFinishExam = function() {
        if (submitting) return;
        const answered = document.querySelectorAll('input[data-question]:checked').length;
        const unanswered = totalQuestions - answered;
        
        let msg = `Anda telah menjawab ${answered} dari ${totalQuestions} soal.\n`;
        if (unanswered > 0) {
            msg += `Masih ada ${unanswered} soal yang belum dijawab!\n`;
        }
        if (raguSet.size > 0) {
            msg += `Ada ${raguSet.size} soal ditandai Ragu-ragu.\n`;
        }
        msg += '\nApakah Anda yakin ingin mengumpulkan ujian ini sekarang? Setelah ini jawaban tidak dapat diubah lagi.';

        if (confirm(msg)) {
            submitting = true;
            document.querySelectorAll('#submit-exam, .btn-primary').forEach(b => b.disabled = true);
            submitStatus.textContent = 'Mengirim jawaban ke server… Harap tunggu hingga halaman hasil terbuka.';
            form.submit();
        }
    };

    // Countdown Timer Loop
    const timerEl = document.getElementById('timer');
    const timerBox = document.getElementById('timer-box');
    function tick() {
        const seconds = Math.max(0, Math.ceil((deadline - serverStart - (performance.now() - localStart)) / 1000));
        const mins = Math.floor(seconds / 60);
        const secs = String(seconds % 60).padStart(2, '0');
        
        if (timerEl) timerEl.textContent = mins + ':' + secs;
        
        // Alert when remaining time < 5 minutes (300 seconds)
        if (seconds <= 300 && timerBox && !timerBox.classList.contains('urgent')) {
            timerBox.classList.add('urgent');
        }

        if (seconds === 0 && !submitting) {
            expired = true;
            status.className = 'cbt-autosave-badge pending';
            status.innerHTML = '<i class="fas fa-hourglass-end"></i> <span>Waktu habis. Mengumpulkan…</span>';
            form.requestSubmit();
        }
    }
    tick();
    setInterval(tick, 1000);

    // Anti-Cheat: Visibility Change & Window Blur
    let cheatCount = 0;
    document.addEventListener('visibilitychange', () => {
        if (document.hidden && !submitting && !expired) {
            cheatCount++;
            fetch(integrityUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({
                    type: 'tab_hidden',
                })
            }).catch(() => {});
        }
    });

    // Fullscreen HUD Control & Anti-Cheat Exit Telemetry
    window.toggleExamFullscreen = function() {
        if (!document.fullscreenElement) {
            if (document.documentElement.requestFullscreen) {
                document.documentElement.requestFullscreen().catch(() => {});
            }
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen().catch(() => {});
            }
        }
    };

    function syncExamFullscreenUI() {
        const isFs = !!document.fullscreenElement;
        const icon = document.getElementById('hud-fs-icon');
        const txt = document.getElementById('hud-fs-text');
        if (icon && txt) {
            if (isFs) {
                icon.className = 'fas fa-compress';
                txt.textContent = 'Keluar Penuh';
            } else {
                icon.className = 'fas fa-expand';
                txt.textContent = 'Layar Penuh';
            }
        }
    }

    document.addEventListener('fullscreenchange', () => {
        syncExamFullscreenUI();
        if (!document.fullscreenElement && !submitting && !expired) {
            // Rekam pelanggaran keluar layar penuh ke telemetri integritas CBT
            fetch(integrityUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({
                    type: 'fullscreen_exit',
                })
            }).catch(() => {});

            if (typeof showToast === 'function') {
                showToast('Peringatan: Anda keluar dari layar penuh. Harap kembali ke mode layar penuh untuk menjaga fokus ujian.', 'warning');
            }
        }
    });
    syncExamFullscreenUI();

    // KaTeX Auto-Render Math Integration (Self-Hosted via Vite)
    function renderExamMath() {
        if (typeof renderMathInElement === 'function') {
            const katexOptions = {
                delimiters: [
                    {left: '$$', right: '$$', display: true},
                    {left: '$', right: '$', display: false},
                    {left: '\\(', right: '\\)', display: false},
                    {left: '\\[', right: '\\]', display: true}
                ],
                throwOnError: false,
                ignoredTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code']
            };
            document.querySelectorAll('.cbt-q-prompt, .cbt-option-text').forEach(el => {
                renderMathInElement(el, katexOptions);
            });
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', renderExamMath);
    } else {
        renderExamMath();
    }
    window.addEventListener('simantap:katex-ready', renderExamMath);
})();
</script>
@vite(['resources/js/katex.js'])
@endpush
