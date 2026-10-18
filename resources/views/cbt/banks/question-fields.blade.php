@php
    $formKey = (string) ($question?->id ?? 'new');
    $sameForm = old('_question_form') === $formKey;
@endphp
<input type="hidden" name="_question_form" value="{{ $formKey }}">
<label for="prompt-{{ $question?->id ?? 'new' }}">Pertanyaan (teks biasa)</label>
<textarea id="prompt-{{ $question?->id ?? 'new' }}" name="prompt" rows="3" maxlength="10000" required>{{ $sameForm ? old('prompt', $question?->prompt) : $question?->prompt }}</textarea>
@foreach(['A','B','C','D'] as $option)
<label for="option-{{ $question?->id ?? 'new' }}-{{ $option }}">Pilihan {{ $option }}</label>
<input id="option-{{ $question?->id ?? 'new' }}-{{ $option }}" name="options[{{ $option }}]" maxlength="2000" required value="{{ $sameForm ? old('options.'.$option, $question?->options[$option] ?? '') : ($question?->options[$option] ?? '') }}">
@endforeach
<label for="correct-{{ $question?->id ?? 'new' }}">Kunci jawaban</label>
<select id="correct-{{ $question?->id ?? 'new' }}" name="correct_answer" required>
@foreach(['A','B','C','D'] as $option)<option value="{{ $option }}" @selected(($sameForm ? old('correct_answer', $question?->correct_answer ?? 'A') : ($question?->correct_answer ?? 'A')) === $option)>{{ $option }}</option>@endforeach
</select>