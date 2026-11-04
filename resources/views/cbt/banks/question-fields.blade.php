@php
    $formKey = (string) ($question?->id ?? 'new');
    $sameForm = old('_question_form') === $formKey;
    $currentCorrect = $sameForm ? old('correct_answer', $question?->correct_answer ?? 'A') : ($question?->correct_answer ?? 'A');
@endphp

<input type="hidden" name="_question_form" value="{{ $formKey }}">

<div style="margin-bottom:18px">
    <label for="prompt-{{ $formKey }}" class="form-label" style="display:flex;align-items:center;justify-content:space-between">
        <span>Pertanyaan / Narasi Soal <span style="color:var(--bad)">*</span></span>
        <span style="font-size:11px;font-weight:400;color:var(--muted)">Teks biasa, max 10.000 karakter</span>
    </label>
    <textarea id="prompt-{{ $formKey }}" 
              name="prompt" 
              rows="4" 
              class="form-control" 
              maxlength="10000" 
              required 
              placeholder="Tuliskan butir soal di sini...">{{ $sameForm ? old('prompt', $question?->prompt) : $question?->prompt }}</textarea>
</div>

<div style="margin-bottom:18px">
    <label class="form-label" style="margin-bottom:10px">
        Pilihan Jawaban & Kunci Jawaban <span style="color:var(--bad)">*</span>
    </label>
    
    <div style="display:flex;flex-direction:column;gap:10px">
        @foreach(['A','B','C','D'] as $opt)
            @php
                $optVal = $sameForm ? old('options.'.$opt, $question?->options[$opt] ?? '') : ($question?->options[$opt] ?? '');
                $isCorrect = $currentCorrect === $opt;
            @endphp
            <div style="display:flex;align-items:center;gap:10px">
                <div style="width:36px;height:36px;border-radius:8px;background:{{ $isCorrect ? 'var(--ok)' : '#EEF2F9' }};color:{{ $isCorrect ? '#fff' : 'var(--navy)' }};font-weight:800;font-size:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    {{ $opt }}
                </div>
                <input id="option-{{ $formKey }}-{{ $opt }}" 
                       type="text" 
                       name="options[{{ $opt }}]" 
                       class="form-control" 
                       maxlength="2000" 
                       required 
                       placeholder="Isi teks pilihan {{ $opt }}..." 
                       value="{{ $optVal }}">
            </div>
        @endforeach
    </div>
</div>

<div style="margin-bottom:20px;background:#FAFBFD;padding:14px 18px;border-radius:8px;border:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <div>
        <label for="correct-{{ $formKey }}" class="form-label" style="margin:0 0 4px">Kunci Jawaban Benar</label>
        <small style="color:var(--muted);font-size:12px">Pilihan yang bernilai benar untuk butir soal ini</small>
    </div>
    <select id="correct-{{ $formKey }}" name="correct_answer" class="form-control" style="width:auto;min-width:140px;font-weight:700" required>
        @foreach(['A','B','C','D'] as $opt)
            <option value="{{ $opt }}" @selected($currentCorrect === $opt)>Pilihan {{ $opt }}</option>
        @endforeach
    </select>
</div>
