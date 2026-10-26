@extends('layouts.app')

@section('title', 'Mengerjakan Tryout')

@section('content')
<div class="container-fluid py-4">
    <!-- Timer and Progress -->
    <div class="row mb-3">
        <div class="col-md-6">
            <div class="card bg-primary text-white">
                <div class="card-body py-2">
                    <h5 class="mb-1">⏱️ Waktu Tersisa</h5>
                    <h2 class="mb-0" data-timer="{{ $session->package->durasi_total_menit }}">
                        {{ sprintf('%02d:%02d:%02d', floor($session->package->durasi_total_menit / 60), $session->package->durasi_total_menit % 60, 0) }}
                    </h2>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card bg-info text-white">
                <div class="card-body py-2">
                    <h5 class="mb-1">📊 Progress</h5>
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar bg-white text-dark" 
                             role="progressbar" 
                             style="width: 0%"
                             id="progress-bar">
                            0%
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Subject Tabs -->
    <div class="row mb-3">
        <div class="col-12">
            <ul class="nav nav-tabs" id="subjectTabs" role="tablist">
                @foreach($subjects as $index => $subject)
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $index === 0 ? 'active' : '' }}" 
                            id="subject-{{ $subject->id }}-tab"
                            data-bs-toggle="tab"
                            data-bs-target="#subject-{{ $subject->id }}"
                            type="button"
                            role="tab">
                        {{ $subject->mataPelajaran->nama_mata_pelajaran ?? 'Mapel ' . ($index + 1) }}
                        <span class="badge bg-secondary">{{ $subject->questions->count() }}</span>
                    </button>
                </li>
                @endforeach
            </ul>
        </div>
    </div>

    <!-- Questions -->
    <div class="row">
        <div class="col-12">
            <div class="tab-content" id="subjectTabContent">
                @foreach($subjects as $index => $subject)
                <div class="tab-pane fade {{ $index === 0 ? 'show active' : '' }}"
                     id="subject-{{ $subject->id }}"
                     role="tabpanel">
                    
                    <div class="card mb-3">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0">
                                {{ $subject->mataPelajaran->nama_mata_pelajaran ?? 'Mata Pelajaran ' . ($index + 1) }}
                                <span class="badge bg-light text-dark float-end">
                                    Durasi: {{ $subject->durasi_menit }} menit
                                </span>
                            </h5>
                        </div>
                        <div class="card-body">
                            @foreach($subject->questions as $qIndex => $question)
                            <div class="question-item mb-4" data-question-id="{{ $question->id }}">
                                <h6 class="mb-3">
                                    <span class="badge bg-primary">{{ $qIndex + 1 }}</span>
                                    {{ $question->pertanyaan }}
                                    @if($question->media_url)
                                    <br><small class="text-muted">Media: {{ $question->media_type }}</small>
                                    @endif
                                </h6>

                                @if($question->tipe_soal === 'pilihan_ganda')
                                <div class="options">
                                    @foreach($question->options->shuffle() as $option)
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" 
                                               type="radio"
                                               name="q_{{ $question->id }}"
                                               id="q{{ $question->id }}_{{ $option->huruf }}"
                                               value="{{ $option->huruf }}"
                                               @checked((string) ($existingAnswers[$question->id] ?? '') === (string) $option->huruf)
                                               onchange="saveAnswer({{ $result->id }}, {{ $subject->id }}, {{ $question->id }}, '{{ $option->huruf }}')">
                                        <label class="form-check-label" for="q{{ $question->id }}_{{ $option->huruf }}">
                                            <strong>{{ $option->huruf }}.</strong> {{ $option->isi_opsi }}
                                        </label>
                                    </div>
                                    @endforeach
                                </div>
                                @elseif($question->tipe_soal === 'benar_salah')
                                <div class="options">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" 
                                               type="radio"
                                               name="q_{{ $question->id }}"
                                               id="q{{ $question->id }}_benar"
                                               value="benar"
                                               @checked((string) ($existingAnswers[$question->id] ?? '') === 'benar')
                                               onchange="saveAnswer({{ $result->id }}, {{ $subject->id }}, {{ $question->id }}, 'benar')">
                                        <label class="form-check-label" for="q{{ $question->id }}_benar">
                                            ✅ Benar
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" 
                                               type="radio"
                                               name="q_{{ $question->id }}"
                                               id="q{{ $question->id }}_salah"
                                               value="salah"
                                               @checked((string) ($existingAnswers[$question->id] ?? '') === 'salah')
                                               onchange="saveAnswer({{ $result->id }}, {{ $subject->id }}, {{ $question->id }}, 'salah')">
                                        <label class="form-check-label" for="q{{ $question->id }}_salah">
                                            ❌ Salah
                                        </label>
                                    </div>
                                </div>
                                @elseif($question->tipe_soal === 'isian_singkat')
                                <div class="options">
                                    <input type="text"
                                           class="form-control"
                                           placeholder="Tulis jawaban singkat"
                                           value="{{ $existingAnswers[$question->id] ?? '' }}"
                                           onchange="saveAnswer({{ $result->id }}, {{ $subject->id }}, {{ $question->id }}, this.value)">
                                </div>
                                @endif
                            </div>
                            <hr>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Submit Button -->
    <div class="row mt-4">
        <div class="col-12 text-center">
            <form action="{{ route('tryout.student.complete', $result) }}" method="POST" 
                  onsubmit="return confirm('Yakin ingin menyelesaikan tryout? Pastikan semua soal sudah dijawab.')">
                @csrf
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="fas fa-check-circle"></i> Selesaikan Tryout
                </button>
            </form>
        </div>
    </div>
</div>

<script>
let resultId = {{ $result->id }};
// Server-side saved answers are authoritative after navigation or reload.
let answers = @json((object) ($existingAnswers ?? []));

function saveAnswer(resultId, subjectId, questionId, answer) {
    answers[questionId] = answer;
    
    // Save to server
    fetch(`/tryout/student/${resultId}/answer`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            subject_id: subjectId,
            question_id: questionId,
            jawaban: answer
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateProgress();
        }
    })
    .catch(error => console.error('Error:', error));
}

function updateProgress() {
    const totalQuestions = {{ $subjects->sum(function($s) { return $s->questions->count(); }) }};
    const answered = Object.keys(answers).length;
    const percentage = totalQuestions > 0 ? (answered / totalQuestions * 100).toFixed(1) : '0.0';
    
    document.getElementById('progress-bar').style.width = percentage + '%';
    document.getElementById('progress-bar').textContent = percentage + '%';
}

// Restore the saved-answer count as well as the checked controls on first paint.
updateProgress();

// Auto-save every 30 seconds
setInterval(() => {
    localStorage.setItem('tryout_answers_' + resultId, JSON.stringify(answers));
}, 30000);

// Timer countdown
let timeRemaining = {{ $session->package->durasi_total_menit * 60 }};
const timerElement = document.querySelector('[data-timer]');

const timerInterval = setInterval(() => {
    timeRemaining--;
    
    const hours = Math.floor(timeRemaining / 3600);
    const minutes = Math.floor((timeRemaining % 3600) / 60);
    const seconds = timeRemaining % 60;
    
    timerElement.textContent = 
        String(hours).padStart(2, '0') + ':' + 
        String(minutes).padStart(2, '0') + ':' + 
        String(seconds).padStart(2, '0');
    
    // Warning at 5 minutes
    if (timeRemaining === 300) {
        alert('⚠️ Waktu tersisa 5 menit!');
    }
    
    // Auto-submit when time runs out
    if (timeRemaining <= 0) {
        clearInterval(timerInterval);
        alert('⏰ Waktu habis! Tryout akan dikumpulkan otomatis.');
        document.querySelector('form[action$="complete"]').submit();
    }
}, 1000);
</script>
@endsection
