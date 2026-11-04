@extends('layouts.app')

@section('title', 'Mengerjakan Tryout - ' . ($session->package->nama_paket ?? 'SIMANTAP'))

@section('sidebar')
    @include('siswa.partials.sidebar')
@endsection

@section('page_title', 'Tryout: ' . ($session->package->nama_paket ?? 'Simulasi Ujian'))
@section('page_subtitle', 'Pengerjaan Terjadwal Multi-Mapel • Jawaban tersimpan otomatis ke server')

@section('content')
{{-- Tryout HUD Header --}}
<div class="cbt-hud">
    <div class="cbt-hud-title">
        <div style="width:34px;height:34px;border-radius:8px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:16px">
            <i class="fas fa-pencil-ruler"></i>
        </div>
        <div>
            <div style="font-size:14px;font-weight:700">{{ $session->package->nama_paket ?? 'Paket Tryout' }}</div>
            <div style="font-size:11px;opacity:0.85">Sesi: {{ $session->nama_sesi }}</div>
        </div>
    </div>

    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        {{-- Progress Counter --}}
        <div style="display:flex;align-items:center;gap:8px;background:rgba(255,255,255,0.12);padding:6px 14px;border-radius:99px">
            <span style="font-size:11.5px;font-weight:600">Progress:</span>
            <span id="progress-bar" style="font-size:12px;font-weight:800;color:#34D399">0%</span>
        </div>

        {{-- Countdown Timer --}}
        <div class="cbt-hud-timer" data-timer="{{ $session->package->durasi_total_menit }}">
            <i class="far fa-clock"></i>
            <span>{{ sprintf('%02d:%02d:%02d', floor($session->package->durasi_total_menit / 60), $session->package->durasi_total_menit % 60, 0) }}</span>
        </div>
    </div>
</div>

{{-- Offline / Outbox Alert Notification --}}
<div id="outbox-status" class="note note-warn d-none" style="margin-bottom:16px" role="status" aria-live="polite"></div>

{{-- Subject Switcher Tabs --}}
<div style="display:flex;gap:8px;overflow-x:auto;padding-bottom:10px;margin-bottom:20px;border-bottom:1px solid var(--line)">
    @foreach($subjects as $index => $subject)
        <button type="button" 
                id="tab-btn-{{ $subject->id }}"
                class="btn {{ $index === 0 ? 'btn-primary' : 'btn-secondary' }} btn-sm subject-tab-btn" 
                onclick="switchSubjectTab({{ $subject->id }})">
            <span>{{ $subject->mataPelajaran->nama_mata_pelajaran ?? 'Mapel ' . ($index + 1) }}</span>
            <span class="tag {{ $index === 0 ? 'tag-gold' : 'tag-secondary' }}" style="font-size:10px;padding:1px 6px">
                {{ $subject->questions->count() }} Soal
            </span>
        </button>
    @endforeach
</div>

{{-- Questions Panes per Subject --}}
<div>
    @foreach($subjects as $index => $subject)
        <div id="subject-pane-{{ $subject->id }}" class="subject-pane" style="{{ $index === 0 ? '' : 'display:none' }}">
            <div class="card" style="margin-bottom:20px">
                <div class="card-header" style="background:#FAFBFD">
                    <h3 style="font-size:14px;color:var(--text)">
                        <i class="fas fa-book-open" style="color:var(--navy);margin-right:8px"></i>
                        {{ $subject->mataPelajaran->nama_mata_pelajaran ?? 'Mata Pelajaran ' . ($index + 1) }}
                    </h3>
                    <span class="tag tag-primary">Durasi Mapel: {{ $subject->durasi_menit }} Menit</span>
                </div>

                <div class="card-body" style="padding:24px">
                    @foreach($subject->questions as $qIndex => $question)
                        <div class="question-item" data-question-id="{{ $question->id }}" style="margin-bottom:32px;padding-bottom:24px;border-bottom:1px solid var(--line)">
                            {{-- Question Prompt Header --}}
                            <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:14px">
                                <span style="width:28px;height:28px;border-radius:6px;background:var(--navy);color:#fff;font-weight:800;font-size:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                    {{ $qIndex + 1 }}
                                </span>
                                <div style="font-size:15px;color:var(--text);line-height:1.6;flex:1">
                                    {{ $question->pertanyaan }}
                                    @if($question->media_url)
                                        <div style="margin-top:8px">
                                            <span class="tag tag-secondary"><i class="fas fa-photo-film"></i> Media: {{ $question->media_type }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Options per question type --}}
                            @if($question->tipe_soal === 'pilihan_ganda')
                                <div class="cbt-options-group" style="padding-left:40px">
                                    @foreach($question->options->shuffle() as $option)
                                        @php $isChecked = (string) ($existingAnswers[$question->id] ?? '') === (string) $option->huruf; @endphp
                                        <label class="cbt-option-label {{ $isChecked ? 'is-selected' : '' }}" for="q{{ $question->id }}_{{ $option->huruf }}">
                                            <input type="radio"
                                                   name="q_{{ $question->id }}"
                                                   id="q{{ $question->id }}_{{ $option->huruf }}"
                                                   value="{{ $option->huruf }}"
                                                   @checked($isChecked)
                                                   onchange="saveAnswer({{ $result->id }}, {{ $subject->id }}, {{ $question->id }}, '{{ $option->huruf }}')">
                                            <span class="cbt-option-key">{{ $option->huruf }}</span>
                                            <span class="cbt-option-text">{{ $option->isi_opsi }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @elseif($question->tipe_soal === 'benar_salah')
                                <div style="display:flex;gap:14px;padding-left:40px">
                                    <label class="cbt-option-label" style="flex:1" for="q{{ $question->id }}_benar">
                                        <input type="radio"
                                               name="q_{{ $question->id }}"
                                               id="q{{ $question->id }}_benar"
                                               value="benar"
                                               @checked((string) ($existingAnswers[$question->id] ?? '') === 'benar')
                                               onchange="saveAnswer({{ $result->id }}, {{ $subject->id }}, {{ $question->id }}, 'benar')">
                                        <span class="cbt-option-key"><i class="fas fa-check"></i></span>
                                        <span class="cbt-option-text" style="font-weight:700;color:var(--ok)">Benar</span>
                                    </label>
                                    <label class="cbt-option-label" style="flex:1" for="q{{ $question->id }}_salah">
                                        <input type="radio"
                                               name="q_{{ $question->id }}"
                                               id="q{{ $question->id }}_salah"
                                               value="salah"
                                               @checked((string) ($existingAnswers[$question->id] ?? '') === 'salah')
                                               onchange="saveAnswer({{ $result->id }}, {{ $subject->id }}, {{ $question->id }}, 'salah')">
                                        <span class="cbt-option-key"><i class="fas fa-times"></i></span>
                                        <span class="cbt-option-text" style="font-weight:700;color:var(--bad)">Salah</span>
                                    </label>
                                </div>
                            @elseif($question->tipe_soal === 'isian_singkat')
                                <div style="padding-left:40px">
                                    <input type="text"
                                           class="form-control"
                                           placeholder="Tuliskan jawaban singkat Anda di sini..."
                                           value="{{ $existingAnswers[$question->id] ?? '' }}"
                                           onchange="saveAnswer({{ $result->id }}, {{ $subject->id }}, {{ $question->id }}, this.value)">
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
</div>

{{-- Completion Footer Bar --}}
<div class="card" style="margin-top:24px">
    <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px">
        <div style="font-size:13px;color:var(--muted)">
            <i class="fas fa-shield-alt" style="color:var(--ok)"></i> Jawaban Anda tersimpan otomatis setiap kali Anda memilih opsi.
        </div>

        <form action="{{ route('tryout.student.complete', $result) }}" method="POST" 
              onsubmit="return confirm('Apakah Anda yakin ingin menyelesaikan tryout ini sekarang? Pastikan seluruh butir soal telah diperiksa.')">
            @csrf
            <button type="submit" class="btn btn-primary" style="background:var(--ok);border-color:var(--ok);padding:10px 24px">
                <i class="fas fa-check-double"></i> Selesaikan Seluruh Tryout
            </button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
@include('partials.exam-outbox')
<script>
window.switchSubjectTab = function(subjectId) {
    document.querySelectorAll('.subject-pane').forEach(p => p.style.display = 'none');
    document.querySelectorAll('.subject-tab-btn').forEach(b => {
        b.classList.remove('btn-primary');
        b.classList.add('btn-secondary');
    });

    const activePane = document.getElementById('subject-pane-' + subjectId);
    if (activePane) activePane.style.display = 'block';

    const activeBtn = document.getElementById('tab-btn-' + subjectId);
    if (activeBtn) {
        activeBtn.classList.remove('btn-secondary');
        activeBtn.classList.add('btn-primary');
    }
};

let resultId = {{ $result->id }};
let answers = @json((object) ($existingAnswers ?? []));
const outboxStatus = document.getElementById('outbox-status');
const resultUrl = @json(route('tryout.student.result', $result));
const outbox = new window.ExamOutbox(@json('tryout:'.$result->id), {
    url: @json(route('tryout.student.answer', $result)),
    csrf: document.querySelector('meta[name="csrf-token"]').content,
    onChange: (pending, tahap) => {
        if (tahap === 'pending') {
            outboxStatus.classList.remove('d-none');
            outboxStatus.textContent = 'Jawaban belum tersimpan di server. Tetap di halaman ini; sistem akan mengirim ulang saat koneksi kembali.';
        } else if (tahap === 'saving') {
            outboxStatus.classList.remove('d-none');
            outboxStatus.textContent = 'Menyimpan jawaban…';
        } else if (pending === 0) {
            outboxStatus.classList.add('d-none');
        }
    },
    onExpired: () => {
        outboxStatus.classList.remove('d-none');
        outboxStatus.textContent = 'Ujian telah selesai. Membuka hasil…';
        window.location.assign(resultUrl);
    },
});
outbox.restoreToDom();
Object.assign(answers, outbox.pendingMap());

function saveAnswer(resultId, subjectId, questionId, answer) {
    answers[questionId] = answer;
    outbox.set(questionId, answer, {subject_id: subjectId, jawaban: answer});
    outbox.flush();
    updateProgress();
}

function updateProgress() {
    const totalQuestions = {{ $subjects->sum(function($s) { return $s->questions->count(); }) }};
    const answered = Object.keys(answers).length;
    const percentage = totalQuestions > 0 ? (answered / totalQuestions * 100).toFixed(1) : '0.0';
    
    const pEl = document.getElementById('progress-bar');
    if (pEl) pEl.textContent = percentage + '% (' + answered + '/' + totalQuestions + ')';
}

updateProgress();

setInterval(() => outbox.flush(), 8000);
window.addEventListener('online', () => outbox.flush());
window.addEventListener('beforeunload', event => {
    if (outbox.pendingCount) {
        event.preventDefault();
        event.returnValue = '';
    }
});

document.querySelector('form[action$="complete"]')?.addEventListener('submit', event => {
    if (outbox.pendingCount) {
        event.preventDefault();
        outboxStatus.classList.remove('d-none');
        outboxStatus.textContent = 'Tunggu sebentar: masih ada jawaban yang belum tersimpan. Sistem mencoba mengirim ulang; klik Selesaikan lagi setelah status hilang.';
        outbox.flush();
    }
});

// Timer countdown
let timeRemaining = {{ $session->package->durasi_total_menit * 60 }};
const timerElement = document.querySelector('[data-timer]');

const timerInterval = setInterval(() => {
    timeRemaining--;
    
    const hours = Math.floor(timeRemaining / 3600);
    const minutes = Math.floor((timeRemaining % 3600) / 60);
    const seconds = timeRemaining % 60;
    
    if (timerElement) {
        timerElement.querySelector('span').textContent = 
            String(hours).padStart(2, '0') + ':' + 
            String(minutes).padStart(2, '0') + ':' + 
            String(seconds).padStart(2, '0');
    }
    
    if (timeRemaining === 300) {
        alert('⚠️ Waktu tersisa 5 menit!');
    }
    
    if (timeRemaining <= 0) {
        clearInterval(timerInterval);
        alert('⏰ Waktu habis! Tryout akan dikumpulkan otomatis.');
        document.querySelector('form[action$="complete"]').submit();
    }
}, 1000);
</script>
@endpush
