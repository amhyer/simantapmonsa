@extends('layouts.admin-with-tabs')

@section('title', 'Detail Bank Soal - ' . $bank->title)

@section('page_title', 'Detail Bank Soal CBT')
@section('page_subtitle', $bank->title)

@section('header_actions')
    <div style="display:flex;align-items:center;gap:8px">
        <a href="{{ route('admin.cbt.banks.edit', $bank) }}" class="btn btn-gold btn-sm">
            <i class="fas fa-edit"></i> Edit Bank
        </a>
        <a href="{{ route('admin.cbt.banks.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>
@endsection

@section('content')
{{-- Bank Metadata Card --}}
<div class="card" style="margin-bottom:24px;border-left:4px solid var(--navy)">
    <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px">
        <div>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                <span class="tag tag-primary"><i class="fas fa-book"></i> {{ $bank->subject }}</span>
                <span class="tag tag-ok"><i class="fas fa-check-circle"></i> {{ $questions->count() }} Butir Soal</span>
            </div>
            <h2 style="font-size:20px;font-weight:800;color:var(--text);margin:0 0 4px">{{ $bank->title }}</h2>
            <p style="font-size:13px;color:var(--muted);margin:0">
                Guru Pengampu: <strong>{{ $bank->guru->nama_lengkap ?? '-' }}</strong> • Dibuat pada {{ $bank->created_at?->format('d M Y, H:i') ?? '-' }}
            </p>
        </div>

        <div style="display:flex;align-items:center;gap:8px">
            <a href="{{ route('admin.cbt.banks.edit', $bank) }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-edit"></i> Ubah Data
            </a>
            <a href="{{ route('admin.cbt.banks.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-list"></i> Daftar Bank
            </a>
        </div>
    </div>
</div>

{{-- Questions Table Card --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list-ol" style="color:var(--navy);margin-right:8px"></i>Daftar Butir Soal ({{ $questions->count() }})</h3>
        <span class="tag tag-secondary">Snapshot Siap Uji</span>
    </div>

    <div class="card-body tight">
        @if($questions->isEmpty())
            <div class="empty-state">
                <div class="icon">📝</div>
                <h4>Bank Soal Belum Berisi Butir Soal</h4>
                <p>Guru pengampu belum menginput butir soal pilihan ganda ke dalam bank ini.</p>
            </div>
        @else
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width:6%">No</th>
                            <th style="width:45%">Pertanyaan / Soal</th>
                            <th style="width:12%">Kunci Jawaban</th>
                            <th style="width:37%">Pilihan Jawaban (A / B / C / D)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($questions as $index => $question)
                            <tr>
                                <td style="font-weight:700;color:var(--navy)">#{{ $index + 1 }}</td>
                                <td>
                                    <div style="font-size:13.5px;color:var(--text);line-height:1.6;white-space:pre-wrap">
                                        {{ $question->prompt }}
                                    </div>
                                </td>
                                <td>
                                    <span class="tag tag-ok" style="font-weight:800;font-size:12px">
                                        Pilihan {{ $question->correct_answer }}
                                    </span>
                                </td>
                                <td>
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;font-size:12px">
                                        @foreach(['A','B','C','D'] as $opt)
                                            @php $isCorrect = $question->correct_answer === $opt; @endphp
                                            <div style="padding:4px 8px;border-radius:6px;border:1px solid {{ $isCorrect ? 'var(--ok)' : 'var(--line)' }};background:{{ $isCorrect ? '#E6F5F0' : '#FAFBFD' }};color:{{ $isCorrect ? 'var(--ok)' : 'var(--text)' }};font-weight:{{ $isCorrect ? '700' : '400' }}">
                                                <strong>{{ $opt }}.</strong> {{ Str::limit($question->options[$opt] ?? '-', 35) }}
                                            </div>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
