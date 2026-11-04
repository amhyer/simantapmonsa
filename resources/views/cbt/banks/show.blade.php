@extends('layouts.app')
@section('title', 'Kelola Soal - ' . $bank->title)

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css" crossorigin="anonymous">
@endpush

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Kelola Butir Soal CBT')
@section('page_subtitle', $bank->title)

@section('header_actions')
    <div style="display:flex;align-items:center;gap:8px">
        <a href="{{ route('cbt.question-banks.edit', $bank) }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-edit"></i> Edit Identitas
        </a>
        <a href="{{ route('cbt.question-banks.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>
@endsection

@section('content')
@include('cbt.feedback')

{{-- Info Header Card --}}
<div class="card" style="margin-bottom:20px;border-left:4px solid var(--navy)">
    <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px">
        <div>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                <span class="tag tag-primary"><i class="fas fa-book"></i> {{ $bank->subject }}</span>
                <span class="tag tag-ok"><i class="fas fa-check-circle"></i> {{ $questions->total() }} Butir Tersimpan</span>
            </div>
            <h2 style="font-size:20px;font-weight:800;color:var(--text);margin:0 0 4px">{{ $bank->title }}</h2>
            <p style="font-size:12.5px;color:var(--muted);margin:0">
                Maksimal 200 soal per bank. Sesi yang telah dipublikasikan menggunakan snapshot beku saat publikasi.
            </p>
        </div>

        <div style="display:flex;align-items:center;gap:8px">
            <form method="POST" action="{{ route('cbt.question-banks.destroy', $bank) }}" onsubmit="return confirm('Hapus seluruh bank soal ini? Bank yang telah digunakan oleh sesi ujian tidak dapat dihapus.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger btn-sm">
                    <i class="fas fa-trash"></i> Hapus Bank
                </button>
            </form>
        </div>
    </div>
</div>

{{-- Add Question Accordion / Form Card --}}
<div class="card" style="margin-bottom:24px;border:1.5px dashed var(--line);background:#FAFBFD">
    <div class="card-header" style="cursor:pointer" onclick="const b = document.getElementById('add-question-box'); b.style.display = (b.style.display === 'none' || !b.style.display) ? 'block' : 'none'">
        <h3 style="display:flex;align-items:center;gap:8px;font-size:14px;color:var(--navy)">
            <i class="fas fa-circle-plus" style="font-size:16px"></i> Tambah Butir Soal Baru
        </h3>
        <button type="button" class="btn btn-primary btn-sm" onclick="event.stopPropagation(); const b = document.getElementById('add-question-box'); b.style.display = (b.style.display === 'none' || !b.style.display) ? 'block' : 'none'">
            <i class="fas fa-plus"></i> Buka Form Tambah
        </button>
    </div>
    
    <div id="add-question-box" class="card-body" style="display:{{ $errors->any() && old('_question_form') === 'new' ? 'block' : 'none' }};border-top:1px solid var(--line);background:#fff">
        <form method="POST" action="{{ route('cbt.questions.store', $bank) }}">
            @csrf
            @include('cbt.banks.question-fields', ['question' => null])
            <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:14px;border-top:1px solid var(--line)">
                <button type="button" onclick="document.getElementById('add-question-box').style.display='none'" class="btn btn-secondary">
                    Tutup
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Simpan Butir Soal
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Questions List --}}
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
    <h3 style="font-size:16px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px">
        <i class="fas fa-list-ol" style="color:var(--navy)"></i> Daftar Butir Soal ({{ $questions->total() }})
    </h3>
    <span style="font-size:12px;color:var(--muted)">Halaman {{ $questions->currentPage() }} dari {{ max(1, $questions->lastPage()) }}</span>
</div>

@forelse($questions as $index => $question)
    @php
        $globalIndex = $questions->firstItem() + $index;
        $isEditOpen = $errors->any() && old('_question_form') == $question->id;
    @endphp
    <div class="card" style="margin-bottom:16px;transition:box-shadow .15s ease">
        {{-- Question Card Top Header --}}
        <div class="card-header" style="background:#FAFBFD;padding:12px 18px">
            <div style="display:flex;align-items:center;gap:10px">
                <span style="width:28px;height:28px;border-radius:6px;background:var(--navy);color:#fff;font-weight:800;font-size:12px;display:flex;align-items:center;justify-content:center">
                    #{{ $globalIndex }}
                </span>
                <span class="tag tag-ok" style="font-size:11px">
                    Kunci: <strong>Pilihan {{ $question->correct_answer }}</strong>
                </span>
            </div>

            <div style="display:flex;align-items:center;gap:8px">
                <button type="button" 
                        class="btn btn-secondary btn-sm" 
                        onclick="const f = document.getElementById('edit-box-{{ $question->id }}'); f.style.display = f.style.display === 'none' ? 'block' : 'none'">
                    <i class="fas fa-edit"></i> Edit Soal
                </button>
                <form method="POST" action="{{ route('cbt.questions.destroy', $question) }}" onsubmit="return confirm('Hapus butir soal #{{ $globalIndex }} ini?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-secondary btn-sm" style="color:var(--bad)" title="Hapus soal">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
        </div>

        {{-- Question Preview Content --}}
        <div class="card-body" style="padding:18px 20px">
            <div style="font-size:14.5px;color:var(--text);line-height:1.6;margin-bottom:16px;white-space:pre-wrap">
                {{ $question->prompt }}
            </div>

            {{-- 4 Options Grid --}}
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:8px">
                @foreach(['A','B','C','D'] as $opt)
                    @php
                        $isAns = $question->correct_answer === $opt;
                    @endphp
                    <div style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;border:1px solid {{ $isAns ? 'var(--ok)' : 'var(--line)' }};background:{{ $isAns ? '#E6F5F0' : '#fff' }}">
                        <span style="width:24px;height:24px;border-radius:50%;background:{{ $isAns ? 'var(--ok)' : '#EEF2F9' }};color:{{ $isAns ? '#fff' : 'var(--navy)' }};font-weight:800;font-size:11px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            {{ $opt }}
                        </span>
                        <span style="font-size:13px;color:{{ $isAns ? 'var(--ok)' : 'var(--text)' }};font-weight:{{ $isAns ? '700' : '400' }}">
                            {{ $question->options[$opt] ?? '-' }}
                        </span>
                        @if($isAns)
                            <i class="fas fa-check" style="margin-left:auto;color:var(--ok);font-size:12px"></i>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Hidden / Collapsible Edit Form --}}
        <div id="edit-box-{{ $question->id }}" class="card-body" style="display:{{ $isEditOpen ? 'block' : 'none' }};border-top:1px solid var(--line);background:#FAFBFD">
            <h4 style="font-size:13px;font-weight:700;margin:0 0 14px;color:var(--navy)">
                <i class="fas fa-pen"></i> Ubah Butir Soal #{{ $globalIndex }}
            </h4>
            <form method="POST" action="{{ route('cbt.questions.update', $question) }}">
                @csrf
                @method('PUT')
                @include('cbt.banks.question-fields', ['question' => $question])
                <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:14px;border-top:1px solid var(--line)">
                    <button type="button" onclick="document.getElementById('edit-box-{{ $question->id }}').style.display='none'" class="btn btn-secondary">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
@empty
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <div class="icon">❓</div>
                <h4>Bank Soal Masih Kosong</h4>
                <p>Belum ada butir soal pada bank ini. Klik tombol Tambah Soal di atas untuk mulai membuat soal.</p>
            </div>
        </div>
    </div>
@endforelse

@if($questions->hasPages())
    <div style="margin-top:20px">
        {{ $questions->links() }}
    </div>
@endif
@endsection

@push('scripts')
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js" crossorigin="anonymous"></script>
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/contrib/auto-render.min.js" crossorigin="anonymous"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
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
                ignoredTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code', 'input', 'select']
            });
        } else {
            setTimeout(renderMath, 150);
        }
    }
    renderMath();
});
</script>
@endpush
