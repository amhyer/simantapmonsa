@extends('layouts.app')

@section('title', 'Mengerjakan Ujian - SIMANTAP')

@section('sidebar')
    @include('siswa.partials.sidebar')
@endsection

@section('page_title', 'Mengerjakan Ujian: ' . $session->nama_sesi)
@section('page_subtitle', 'Soal {{ 1 }} dari {{ $questions->count() }}')

@section('content')
<div x-data="examState()" style="display:flex;gap:16px;min-height:calc(100vh - 120px)">
    {{-- Main Content --}}
    <div style="flex:1">
        {{-- Topbar: Timer, Integrity, Progress --}}
        <div class="card" style="margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;gap:24px;padding:16px">
            <div data-timer-container style="display:flex;align-items:center;gap:8px">
                <i class="fas fa-hourglass-end" style="color:var(--text)"></i>
                <span style="font-weight:600;font-size:16px">Waktu: <span id="cbt-timer">{{ sprintf('%02d:%02d', intval($session->durasi_menit), 0) }}</span></span>
            </div>
            
            <div style="display:flex;align-items:center;gap:16px;font-size:13px">
                <span style="color:#667085">Integritas: <span id="integrity-badge" style="font-weight:600;color:var(--ok)">100%</span></span>
                <span style="color:#667085">Soal: <span style="font-weight:600" x-text="currentQuestionNumber + ' / ' + questions.length"></span></span>
            </div>
        </div>

        {{-- Alerts & Indicators --}}
        <div id="integrity-warnings"></div>
        <div id="save-indicator" style="background:var(--ok);color:#fff;padding:8px 12px;border-radius:6px;margin-bottom:12px;font-size:13px;opacity:0;transition:opacity 0.3s;display:none" style="display:none"></div>

        {{-- Question Display --}}
        <div class="card" style="margin-bottom:16px">
            <div class="card-body">
                {{-- Question Number & Text --}}
                <div style="margin-bottom:20px">
                    <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px">
                        <h3 style="font-size:16px;font-weight:600">
                            Soal <span x-text="currentQuestionNumber"></span>
                            <span style="color:#667085;font-size:13px;font-weight:400">({{ $questions->count() }} soal)</span>
                        </h3>
                        <button 
                            @click="toggleRaguRagu()" 
                            :class="{ 'btn btn-sm': true, 'tag-warn': answers[currentQuestionIndex()].ragu_ragu }"
                            style="background:none;border:1px solid #E4E7EC;padding:6px 12px;border-radius:6px;cursor:pointer;font-size:12px"
                        >
                            <i class="fas fa-flag" style="margin-right:4px"></i>
                            <span x-text="answers[currentQuestionIndex()].ragu_ragu ? 'Ragu-ragu ✓' : 'Tandai Ragu-ragu'"></span>
                        </button>
                    </div>
                    
                    <div style="font-size:14px;line-height:1.8;color:#101828;margin-bottom:16px">
                        <span x-html="currentQuestion().pertanyaan"></span>
                    </div>

                    {{-- Media (if present) --}}
                    <template x-if="currentQuestion().media_url">
                        <div style="margin-bottom:16px;border-radius:8px;overflow:hidden;background:#F5F5F5;max-height:300px">
                            <template x-if="currentQuestion().media_type === 'image'">
                                <img :src="currentQuestion().media_url" style="width:100%;height:auto;object-fit:contain;max-height:300px" alt="Media"/>
                            </template>
                            <template x-if="currentQuestion().media_type === 'video'">
                                <video controls style="width:100%;height:auto;max-height:300px">
                                    <source :src="currentQuestion().media_url" />
                                    Browser Anda tidak mendukung video.
                                </video>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- Answer Input Forms --}}
                <div style="background:#F9FAFB;padding:16px;border-radius:8px;margin-bottom:16px">
                    {{-- Pilihan Ganda (Radio Buttons) --}}
                    <template x-if="currentQuestion().tipe_soal === 'pilihan_ganda'">
                        <div>
                            <p style="font-weight:600;font-size:13px;margin-bottom:12px;color:#667085">Pilih jawaban Anda:</p>
                            <div style="display:flex;flex-direction:column;gap:8px">
                                <template x-for="(option, idx) in (currentQuestion().options || [])" :key="idx">
                                    <label style="display:flex;align-items:center;gap:8px;padding:10px 12px;background:#fff;border-radius:6px;border:1px solid #E4E7EC;cursor:pointer;font-size:13px">
                                        <input 
                                            type="radio" 
                                            :name="'question_' + currentQuestion().id"
                                            :value="option.id"
                                            @change="saveAnswerDebounced(currentQuestion().id, option.id)"
                                            :checked="answers[currentQuestionIndex()].jawaban == option.id"
                                        />
                                        <span style="font-weight:600;color:#667085">{{ chr(65 + 0) }}</span>
                                        <span x-text="option.teks"></span>
                                    </label>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- Benar/Salah (Radio Buttons) --}}
                    <template x-if="currentQuestion().tipe_soal === 'benar_salah'">
                        <div>
                            <p style="font-weight:600;font-size:13px;margin-bottom:12px;color:#667085">Pilih jawaban:</p>
                            <div style="display:flex;gap:12px">
                                <label style="display:flex;align-items:center;gap:8px;padding:10px 12px;background:#fff;border-radius:6px;border:1px solid #E4E7EC;cursor:pointer;font-size:13px">
                                    <input 
                                        type="radio" 
                                        :name="'question_' + currentQuestion().id"
                                        value="benar"
                                        @change="saveAnswerDebounced(currentQuestion().id, 'benar')"
                                        :checked="answers[currentQuestionIndex()].jawaban === 'benar'"
                                    />
                                    <span>Benar</span>
                                </label>
                                <label style="display:flex;align-items:center;gap:8px;padding:10px 12px;background:#fff;border-radius:6px;border:1px solid #E4E7EC;cursor:pointer;font-size:13px">
                                    <input 
                                        type="radio" 
                                        :name="'question_' + currentQuestion().id"
                                        value="salah"
                                        @change="saveAnswerDebounced(currentQuestion().id, 'salah')"
                                        :checked="answers[currentQuestionIndex()].jawaban === 'salah'"
                                    />
                                    <span>Salah</span>
                                </label>
                            </div>
                        </div>
                    </template>

                    {{-- Essay (Textarea) --}}
                    <template x-if="currentQuestion().tipe_soal === 'essay'">
                        <div>
                            <p style="font-weight:600;font-size:13px;margin-bottom:12px;color:#667085">Tulis jawaban Anda:</p>
                            <textarea 
                                @input="saveAnswerDebounced(currentQuestion().id, $el.value)"
                                :value="answers[currentQuestionIndex()].jawaban"
                                style="width:100%;min-height:150px;padding:12px;border:1px solid #E4E7EC;border-radius:6px;font-size:13px;font-family:inherit;resize:vertical"
                                placeholder="Ketik jawaban Anda di sini...">
                            </textarea>
                        </div>
                    </template>

                    {{-- Menjodohkan (Select) --}}
                    <template x-if="currentQuestion().tipe_soal === 'menjodohkan'">
                        <div>
                            <p style="font-weight:600;font-size:13px;margin-bottom:12px;color:#667085">Pasangkan kolom kiri dengan kolom kanan:</p>
                            <div style="display:flex;gap:16px">
                                <div style="flex:1">
                                    <p style="font-size:11px;color:#667085;margin-bottom:8px">Pilih pasangan:</p>
                                    <template x-for="(pair, idx) in (currentQuestion().pairs || [])" :key="idx">
                                        <select 
                                            @change="saveAnswerDebounced(currentQuestion().id, collectMatchingAnswers())"
                                            style="width:100%;padding:8px;border:1px solid #E4E7EC;border-radius:6px;margin-bottom:8px;font-size:13px"
                                        >
                                            <option value="">-- Pilih Pasangan --</option>
                                            <template x-for="option in (currentQuestion().options || [])" :key="option.id">
                                                <option :value="option.id" x-text="option.teks"></option>
                                            </template>
                                        </select>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <p style="font-size:12px;color:#667085">Poin: <span x-text="currentQuestion().poin"></span> poin</p>
            </div>
        </div>

        {{-- Navigation Buttons --}}
        <div style="display:flex;justify-content:space-between;gap:12px">
            <button 
                @click="previousQuestion()"
                :disabled="currentQuestionIndex() === 0"
                class="btn"
                style="background:#E4E7EC;color:#101828"
            >
                <i class="fas fa-chevron-left" style="margin-right:6px"></i>Sebelumnya
            </button>
            
            <button 
                @click="nextQuestion()"
                :disabled="currentQuestionIndex() === questions.length - 1"
                class="btn"
                style="background:#7C3AED;color:#fff"
            >
                Selanjutnya<i class="fas fa-chevron-right" style="margin-left:6px"></i>
            </button>
        </div>

        {{-- Submit Button --}}
        <div style="text-align:center;margin-top:20px">
            <button 
                @click="confirmSubmit()"
                class="btn"
                style="background:#12805C;color:#fff;padding:12px 24px;font-weight:600"
            >
                <i class="fas fa-check-circle" style="margin-right:6px"></i>Kumpulkan Ujian
            </button>
        </div>
    </div>

    {{-- Question Navigator Sidebar --}}
    <div style="flex:0 0 200px;max-height:calc(100vh - 150px);overflow-y:auto;padding:12px;background:#F9FAFB;border-radius:8px">
        <p style="font-weight:600;font-size:12px;margin-bottom:12px;text-transform:uppercase;color:#667085">Navigasi Soal</p>
        
        <div style="display:grid;grid-template-columns:repeat(4, 1fr);gap:6px">
            <template x-for="(q, idx) in questions" :key="idx">
                <button 
                    @click="goToQuestion(idx)"
                    :class="{ 
                        'btn btn-sm': true,
                        'active': idx === currentQuestionIndex(),
                        'answered': answers[idx].jawaban,
                        'flagged': answers[idx].ragu_ragu
                    }"
                    :style="{
                        background: idx === currentQuestionIndex() 
                            ? '#7C3AED' 
                            : (answers[idx].ragu_ragu ? '#B54708' : (answers[idx].jawaban ? '#12805C' : '#E4E7EC')),
                        color: idx === currentQuestionIndex() ? '#fff' : '#101828',
                        fontWeight: idx === currentQuestionIndex() ? '700' : '500',
                        fontSize: '11px',
                        padding: '8px',
                        textAlign: 'center'
                    }"
                    :title="'Soal ' + (idx + 1)"
                    x-text="idx + 1"
                >
                </button>
            </template>
        </div>
        
        <div style="margin-top:16px;padding:12px;background:#fff;border-radius:6px;font-size:12px">
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px">
                <div style="width:12px;height:12px;background:#12805C;border-radius:3px"></div>
                <span>Sudah Dijawab</span>
            </div>
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px">
                <div style="width:12px;height:12px;background:#B54708;border-radius:3px"></div>
                <span>Ragu-ragu</span>
            </div>
            <div style="display:flex;align-items:center;gap:6px">
                <div style="width:12px;height:12px;background:#E4E7EC;border-radius:3px"></div>
                <span>Belum Dijawab</span>
            </div>
        </div>
    </div>
</div>

{{-- Confirmation Modal --}}
<div id="submit-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:#fff;padding:32px;border-radius:12px;max-width:400px;text-align:center">
        <h3 style="margin-bottom:12px">Konfirmasi Pengumpulan</h3>
        <p style="color:#667085;margin-bottom:24px">
            Anda telah menjawab <span id="answered-count">0</span> dari {{ $questions->count() }} soal.
            Apakah Anda yakin ingin mengumpulkan ujian?
        </p>
        <div style="display:flex;gap:12px;justify-content:center">
            <button @click="closeModal()" class="btn" style="background:#E4E7EC;color:#101828">Batalkan</button>
            <button @click="submitExam()" class="btn" style="background:#12805C;color:#fff">Ya, Kumpulkan</button>
        </div>
    </div>
</div>

<style>
    @media print {
        body, #sidebar, .topbar, #submit-modal, .btn {
            display: none;
        }
    }
    
    @keyframes slideIn {
        from { transform: translateX(100px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100px); opacity: 0; }
    }
</style>

@endsection

@push('scripts')
<script>
    // Make CSRF token available to JS
    window.CBT = {
        resultId: {{ $result->id }},
        sessionId: {{ $session->id }},
        durasiDetik: {{ $session->durasi_menit * 60 }},
        waktuMulai: '{{ $result->waktu_mulai->toIso8601String() }}',
        routes: {
            submitAnswer: '{{ route("cbt.student.answer", $result) }}',
            submit: '{{ route("cbt.student.submit", $result) }}',
            logViolation: '{{ route("cbt.integrity.log", $result) }}'
        }
    };

    function examState() {
        return {
            questions: {!! json_encode($questions) !!},
            currentQuestion: 0,
            answers: {!! json_encode(
                $questions->map(fn($q) => [
                    'question_id' => $q->id,
                    'jawaban' => null,
                    'ragu_ragu' => false
                ])->values()->all()
            ) !!},
            
            currentQuestionIndex() {
                return this.currentQuestion;
            },
            
            currentQuestionNumber() {
                return this.currentQuestion + 1;
            },
            
            currentQuestion() {
                return this.questions[this.currentQuestion] || {};
            },
            
            nextQuestion() {
                if (this.currentQuestion < this.questions.length - 1) {
                    this.currentQuestion++;
                    document.querySelector('[data-timer-container]').parentElement.querySelector('.page_subtitle').textContent = 'Soal ' + this.currentQuestionNumber() + ' dari ' + this.questions.length;
                }
            },
            
            previousQuestion() {
                if (this.currentQuestion > 0) {
                    this.currentQuestion--;
                    document.querySelector('[data-timer-container]').parentElement.querySelector('.page_subtitle').textContent = 'Soal ' + this.currentQuestionNumber() + ' dari ' + this.questions.length;
                }
            },
            
            goToQuestion(idx) {
                this.currentQuestion = idx;
            },
            
            toggleRaguRagu() {
                this.answers[this.currentQuestionIndex()].ragu_ragu = !this.answers[this.currentQuestionIndex()].ragu_ragu;
            },
            
            saveAnswerDebounced(questionId, value) {
                const idx = this.questions.findIndex(q => q.id === questionId);
                if (idx >= 0) {
                    this.answers[idx].jawaban = value;
                    window.CBT.autoSaver?.saveAnswer(questionId, value, this.answers[idx].ragu_ragu);
                }
            },
            
            collectMatchingAnswers() {
                // For menjodohkan type
                return 'matching_answers';
            },
            
            confirmSubmit() {
                const answered = this.answers.filter(a => a.jawaban).length;
                document.getElementById('answered-count').textContent = answered;
                document.getElementById('submit-modal').style.display = 'flex';
            },
            
            closeModal() {
                document.getElementById('submit-modal').style.display = 'none';
            },
            
            submitExam() {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = window.CBT.routes.submit;
                
                const token = document.querySelector('meta[name="csrf-token"]').content;
                const tokenInput = document.createElement('input');
                tokenInput.type = 'hidden';
                tokenInput.name = '_token';
                tokenInput.value = token;
                form.appendChild(tokenInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
    }
</script>

<script type="module">
    import { initTimer } from '{{ asset("js/cbt/timer.js") }}';
    import { initIntegrityChecker } from '{{ asset("js/cbt/integrity-checker.js") }}';
    import { initAutoSave } from '{{ asset("js/cbt/auto-save.js") }}';
    
    // Initialize modules
    const timer = initTimer(
        window.CBT.durasiDetik,
        window.CBT.resultId,
        window.CBT.routes.submit,
        document.querySelector('meta[name="csrf-token"]').content
    );
    
    const integrity = initIntegrityChecker(
        window.CBT.resultId,
        window.CBT.routes.logViolation,
        document.querySelector('meta[name="csrf-token"]').content
    );
    
    const autoSaver = initAutoSave(
        window.CBT.resultId,
        window.CBT.routes.submitAnswer,
        document.querySelector('meta[name="csrf-token"]').content
    );
    
    window.CBT.timer = timer;
    window.CBT.integrityChecker = integrity;
    window.CBT.autoSaver = autoSaver;
</script>
@endpush
