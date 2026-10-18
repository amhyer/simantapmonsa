@extends('layouts.app')
@section('title', 'Mengerjakan CBT - SIMANTAP')
@section('sidebar')
    @include('siswa.partials.sidebar')
@endsection
@section('page_title', 'Mengerjakan Ujian CBT')
@section('page_subtitle', 'Jawaban dinilai oleh server')
@section('content')
@include('cbt.feedback')
<div class="cbt-timer"><strong>{{ $session->title }}</strong> • Sisa waktu: <span id="timer" role="timer">Menghitung…</span>
    <div id="save-status" role="status" aria-live="polite">Pilih jawaban untuk menyimpan otomatis.</div>
</div>
<noscript><p class="cbt-note">JavaScript tidak aktif. Autosave/timer tidak tersedia; gunakan tombol Kumpulkan sebelum deadline {{ $result->deadline_at->format('H:i:s') }}. Server tetap memeriksa batas waktu.</p></noscript>
<form id="exam-form" class="cbt-form" method="POST" action="{{ route('cbt.student.submit', $result) }}">
@csrf
@foreach($questions as $question)
<fieldset class="card" style="margin:18px 0;border:1px solid #cbd5e1;padding:18px">
    <legend>Soal {{ $loop->iteration }} / {{ count($questions) }}</legend>
    <p style="white-space:pre-wrap">{{ $question['prompt'] }}</p>
    @foreach($question['options'] as $key => $option)
    <label class="cbt-option" for="q-{{ $question['id'] }}-{{ $key }}">
        <input type="radio" id="q-{{ $question['id'] }}-{{ $key }}" name="answers[{{ $question['id'] }}]" value="{{ $key }}" data-question="{{ $question['id'] }}" @checked(($result->answers[$question['id']] ?? null) === $key)>
        <span><strong>{{ $key }}.</strong> {{ $option }}</span>
    </label>
    @endforeach
</fieldset>
@endforeach
<div class="cbt-actions"><button id="submit-exam" class="btn btn-primary" type="submit">Kumpulkan jawaban</button></div>
<p id="submit-status" role="alert"></p>
</form>
@endsection
@push('scripts')
<script>
(() => {
    const form = document.getElementById('exam-form');
    const status = document.getElementById('save-status');
    const submitStatus = document.getElementById('submit-status');
    const pending = new Map();
    const csrf = @json(csrf_token());
    const saveUrl = @json(route('cbt.student.answer', $result));
    const integrityUrl = @json(route('cbt.student.integrity', $result));
    const resultUrl = @json(route('cbt.student.result', $result));
    const deadline = @json($result->deadline_at->getTimestamp() * 1000);
    const serverStart = @json(now()->getTimestamp() * 1000);
    const localStart = performance.now();
    let saving = false;
    let submitting = false;
    let expired = false;

    async function savePending() {
        if (saving || expired || submitting || pending.size === 0) return;
        saving = true;
        try {
            // Serialisasi: perubahan jawaban tidak saling menimpa karena balapan fetch.
            while (pending.size && !expired && !submitting) {
                for (const [id, answer] of Array.from(pending)) {
                    if (expired || submitting) break;
                    status.textContent = 'Menyimpan jawaban…';
                    const controller = new AbortController();
                    const timeout = setTimeout(() => controller.abort(), 10000);
                    let response;
                    try {
                        response = await fetch(saveUrl, {
                            method: 'POST', credentials: 'same-origin', signal: controller.signal,
                            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
                            body: JSON.stringify({question_id: Number(id), answer})
                        });
                    } finally { clearTimeout(timeout); }
                    if (response.status === 409) {
                        expired = true;
                        status.textContent = 'Ujian telah selesai. Membuka hasil…';
                        location.assign(resultUrl);
                        return;
                    }
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    if (pending.get(id) === answer) pending.delete(id);
                }
            }
            status.textContent = pending.size ? 'Masih ada jawaban menunggu simpan.' : 'Semua perubahan jawaban tersimpan di server.';
        } catch (error) {
            status.textContent = 'BELUM tersimpan. Periksa koneksi/login; sistem akan mencoba lagi. Jangan tutup halaman.';
        } finally { saving = false; }
    }

    form.addEventListener('change', event => {
        if (!event.target.matches('input[data-question]') || submitting || expired) return;
        pending.set(event.target.dataset.question, event.target.value);
        savePending();
    });
    setInterval(savePending, 8000);
    window.addEventListener('online', savePending);
    window.addEventListener('beforeunload', event => {
        if (pending.size && !submitting) { event.preventDefault(); event.returnValue = ''; }
    });

    form.addEventListener('submit', event => {
        if (submitting) { event.preventDefault(); return; }
        if (!expired && !confirm('Kumpulkan ujian? Setelah ini jawaban tidak dapat diubah.')) {
            event.preventDefault(); return;
        }
        submitting = true;
        // Full form membawa seluruh pilihan, sehingga autosave yang gagal tidak
        // menghilangkan jawaban selama POST tiba sebelum deadline server.
        document.getElementById('submit-exam').disabled = true;
        submitStatus.textContent = 'Mengirim jawaban. Tunggu sampai halaman hasil terbuka.';
    });

    function tick() {
        const seconds = Math.max(0, Math.ceil((deadline - serverStart - (performance.now() - localStart)) / 1000));
        document.getElementById('timer').textContent = Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0');
        if (seconds === 0 && !submitting) {
            expired = true;
            status.textContent = 'Waktu habis. Jawaban yang sudah tersimpan akan dinilai.';
            form.requestSubmit();
        }
    }
    tick();
    setInterval(tick, 1000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden || expired || submitting) return;
        // Catatan telemetri saja: tidak menentukan nilai atau menuduh siswa.
        fetch(integrityUrl, {
            method: 'POST', credentials: 'same-origin', keepalive: true,
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
            body: JSON.stringify({type: 'tab_hidden'})
        }).catch(() => {});
    });
})();
</script>
@endpush
