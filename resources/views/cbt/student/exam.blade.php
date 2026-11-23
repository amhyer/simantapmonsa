<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ujian CBT - {{ $session->title }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/css/katex.css', 'resources/js/app.js'])
    <style>
        * { box-sizing: border-box; }
        body.anbk-body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Poppins', 'Plus Jakarta Sans', 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #326698 0%, #4a7fb8 100%);
            color: #1e293b;
            position: relative;
        }
        body.anbk-body::before {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            background-image: radial-gradient(circle at 20% 20%, rgba(255, 255, 255, 0.08) 2px, transparent 2px);
            background-size: 50px 50px;
            z-index: 0;
        }
        .anbk-shell {
            position: relative;
            z-index: 1;
            max-width: 1120px;
            margin: 0 auto;
            padding: 16px 20px 36px;
        }
        /* Top Header Bar */
        .anbk-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 6px 4px 18px;
            color: #fff;
            flex-wrap: wrap;
        }
        .anbk-brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .anbk-logo-badge {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.16);
            border: 2px solid rgba(255, 255, 255, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }
        .anbk-logo-badge img {
            width: 34px;
            height: 34px;
            object-fit: contain;
        }
        .anbk-brand-title {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.4px;
            line-height: 1.2;
            text-transform: uppercase;
        }
        .anbk-brand-sub {
            font-size: 12px;
            color: #bcd2e8;
            font-weight: 500;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }
        .anbk-student-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-left: auto;
        }
        .anbk-student-text {
            text-align: right;
            line-height: 1.3;
        }
        .anbk-student-name {
            font-size: 14px;
            font-weight: 600;
            color: #fff;
        }
        .anbk-student-meta {
            font-size: 12px;
            color: #d6e4f2;
        }
        .anbk-avatar-tile {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #fff;
            flex-shrink: 0;
        }

        /* Main Floating Exam Card */
        .anbk-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.14);
            padding: 26px 32px 28px;
        }
        .anbk-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            padding-bottom: 14px;
        }
        .anbk-soal-heading {
            font-size: 22px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }
        .anbk-toolbar-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .anbk-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 30px;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.15s ease;
            text-decoration: none;
            line-height: 1.3;
        }
        .anbk-pill-primary {
            background: #0d6efd;
            color: #fff;
            border-color: #0d6efd;
        }
        .anbk-pill-primary:hover { background: #0b5ed7; }
        .anbk-pill-outline {
            background: #fff;
            color: #495057;
            border-color: #6c757d;
            cursor: default;
        }
        .anbk-pill-outline.urgent {
            background: #dc3545;
            color: #fff;
            border-color: #dc3545;
            animation: anbkPulse 1.2s infinite;
        }
        @keyframes anbkPulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        .anbk-pill-ghost {
            background: #f1f5f9;
            color: #334155;
            border-color: #cbd5e1;
        }
        .anbk-pill-ghost:hover { background: #e2e8f0; }

        /* Autosave pill */
        .anbk-autosave {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 30px;
            padding: 5px 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .anbk-autosave.ok {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }
        .anbk-autosave.pending {
            background: #fffbeb;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        /* Secondary Toolbar */
        .anbk-subbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            padding: 10px 0 14px;
            margin-bottom: 20px;
            border-top: 1px solid #e9ecef;
            border-bottom: 1px solid #e9ecef;
        }
        .anbk-font-group {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #555;
        }
        .anbk-font-btn {
            background: none;
            border: 1px solid transparent;
            border-radius: 6px;
            color: #495057;
            cursor: pointer;
            padding: 2px 7px;
            line-height: 1.2;
        }
        .anbk-font-btn.active {
            font-weight: 700;
            color: #0d6efd;
            background: #eff6ff;
            border-color: #bfdbfe;
        }
        .anbk-subject-label {
            font-size: 13px;
            font-weight: 600;
            color: #475569;
        }

        /* Question & Stimulus */
        .anbk-split {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            align-items: start;
        }
        .anbk-stimulus {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            padding: 16px 18px;
            font-size: 15px;
            line-height: 1.7;
            color: #1e293b;
        }
        .anbk-stimulus-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #326698;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .anbk-q-prompt {
            font-size: 16px;
            line-height: 1.7;
            color: #1e293b;
            margin-bottom: 18px;
            white-space: pre-line;
        }
        .anbk-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .anbk-option-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 16px;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            background: #fff;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .anbk-option-row:hover {
            border-color: #86b7fe;
            background: #f8fbff;
        }
        .anbk-option-row.is-selected {
            border-color: #0d6efd;
            background: #eff6ff;
            box-shadow: 0 0 0 1px #0d6efd inset;
        }
        .anbk-option-row input[type="radio"] {
            margin-top: 4px;
            width: 17px;
            height: 17px;
            accent-color: #0d6efd;
            cursor: pointer;
            flex-shrink: 0;
        }
        .anbk-option-key {
            font-weight: 700;
            color: #326698;
            min-width: 24px;
            font-size: 15px;
        }
        .anbk-option-text {
            font-size: 15px;
            line-height: 1.6;
            color: #1e293b;
            flex: 1;
        }

        /* Bottom Navigation Bar */
        .anbk-nav-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 26px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        .anbk-nav-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 30px;
            padding: 9px 20px;
            font-size: 13px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .anbk-nav-btn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }
        .anbk-btn-prev {
            background: #dc3545;
            color: #fff;
        }
        .anbk-btn-prev:hover:not(:disabled) { background: #bb2d3b; }
        .anbk-btn-ragu {
            background: #ffc107;
            color: #212529;
        }
        .anbk-btn-ragu:hover { background: #ffca2c; }
        .anbk-btn-ragu input[type="checkbox"] {
            width: 15px;
            height: 15px;
            accent-color: #212529;
            cursor: pointer;
            margin: 0;
        }
        .anbk-btn-next {
            background: #0d6efd;
            color: #fff;
        }
        .anbk-btn-next:hover:not(:disabled) { background: #0b5ed7; }
        .anbk-btn-finish {
            background: #198754;
            color: #fff;
        }
        .anbk-btn-finish:hover:not(:disabled) { background: #157347; }

        /* Pusmendik "Daftar Soal" Modal */
        .anbk-modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            z-index: 1050;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .anbk-modal-backdrop.open {
            display: flex;
        }
        .anbk-modal {
            background: #fff;
            border-radius: 14px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 24px 64px rgba(0, 0, 0, 0.25);
            overflow: hidden;
        }
        .anbk-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1px solid #e9ecef;
        }
        .anbk-modal-title {
            font-size: 17px;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
        }
        .anbk-modal-close {
            background: none;
            border: none;
            font-size: 22px;
            line-height: 1;
            color: #64748b;
            cursor: pointer;
            padding: 2px 6px;
        }
        .anbk-modal-body {
            padding: 20px;
            max-height: 60vh;
            overflow-y: auto;
        }
        .anbk-palette-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            justify-items: center;
        }
        .anbk-p-item {
            position: relative;
            width: 44px;
            height: 44px;
            border-radius: 5px;
            border: 1px solid #ced4da;
            background: #fff;
            color: #212529;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.12s ease;
        }
        .anbk-p-item.answered {
            background: #212529;
            border-color: #212529;
            color: #fff;
        }
        .anbk-p-item.flagged {
            background: #ffc107;
            border-color: #ffc107;
            color: #212529;
        }
        .anbk-p-item.current {
            background: #0d6efd;
            border-color: #0d6efd;
            color: #fff;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25);
        }
        .anbk-p-check {
            display: none;
            position: absolute;
            top: -6px;
            right: -6px;
            width: 17px;
            height: 17px;
            border-radius: 50%;
            background: #fff;
            border: 1.5px solid #212529;
            color: #212529;
            font-size: 9px;
            align-items: center;
            justify-content: center;
        }
        .anbk-p-item.answered .anbk-p-check {
            display: inline-flex;
        }
        .anbk-modal-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 18px;
            padding-top: 14px;
            border-top: 1px solid #e9ecef;
            font-size: 12px;
            color: #475569;
        }
        .anbk-legend-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .anbk-legend-box {
            width: 14px;
            height: 14px;
            border-radius: 3px;
            display: inline-block;
        }
        .anbk-modal-footer {
            padding: 14px 20px;
            background: #f8fafc;
            border-top: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        /* Mandatory Fullscreen Gate Overlay */
        .anbk-fs-gate {
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: linear-gradient(135deg, #1e3a5f 0%, #326698 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #fff;
            text-align: center;
        }
        .anbk-fs-gate.hidden {
            display: none;
        }
        .anbk-fs-gate-card {
            max-width: 480px;
            width: 100%;
            background: rgba(255, 255, 255, 0.98);
            color: #1e293b;
            border-radius: 20px;
            padding: 32px 28px;
            box-shadow: 0 24px 64px rgba(0, 0, 0, 0.35);
        }

        @media (max-width: 768px) {
            .anbk-shell { padding: 12px 12px 28px; }
            .anbk-card { padding: 18px 16px 20px; border-radius: 16px; }
            .anbk-split { grid-template-columns: 1fr; }
            .anbk-soal-heading { font-size: 18px; }
            .anbk-nav-bar { justify-content: center; }
        }
    </style>
</head>
<body class="anbk-body">
{{-- Mandatory Fullscreen Gate (auto-hides once fullscreen is active) --}}
<div id="anbk-fs-gate" class="anbk-fs-gate">
    <div class="anbk-fs-gate-card">
        <div style="width:60px;height:60px;border-radius:50%;background:#eff6ff;color:#0d6efd;display:flex;align-items:center;justify-content:center;font-size:26px;margin:0 auto 16px">
            <i class="fas fa-expand-arrows-alt"></i>
        </div>
        <h2 style="font-size:20px;font-weight:700;margin:0 0 10px;color:#0f172a">Mode Layar Penuh Wajib Aktif</h2>
        <p style="font-size:13.5px;line-height:1.6;color:#475569;margin:0 0 22px">
            Ujian CBT harus dikerjakan dalam mode layar penuh. Selama ujian berlangsung, dilarang keluar dari layar penuh, berpindah tab, atau menekan kombinasi tombol pintasan — pelanggaran akan mengeluarkan Anda dan mengulang jawaban dari awal sementara waktu tetap berjalan.
        </p>
        <button type="button" id="btn-enter-mandatory-fs" class="anbk-nav-btn anbk-btn-next" style="width:100%;justify-content:center;padding:12px 20px;font-size:14px">
            <i class="fas fa-expand"></i> Masuk Layar Penuh &amp; Kerjakan Ujian
        </button>
    </div>
</div>

<div class="anbk-shell">
    {{-- Top Header Bar (Pusmendik ANBK Style) --}}
    <header class="anbk-header">
        <div class="anbk-brand">
            <div class="anbk-logo-badge">
                <img src="{{ asset('favicon.png') }}" alt="Logo SIMANTAP">
            </div>
            <div>
                <div class="anbk-brand-title">SIMANTAP CBT</div>
                <div class="anbk-brand-sub">Aplikasi Ujian Berbasis Komputer &bull; {{ $session->bank->subject ?? 'CBT' }}</div>
            </div>
        </div>

        <div class="anbk-student-info">
            <div class="anbk-student-text">
                <div class="anbk-student-name">
                    {{ $siswa->nis ?? auth()->user()->nama_pengguna }} - {{ $siswa->nama_peserta_didik ?? auth()->user()->nama_lengkap }}
                </div>
                <div class="anbk-student-meta">Kelas {{ $siswa->kelas ?? '-' }} &bull; {{ $session->title }}</div>
            </div>
            <div class="anbk-avatar-tile" aria-hidden="true">
                <i class="fas fa-user-graduate"></i>
            </div>
        </div>
    </header>

    <noscript>
        <div style="background:#fff3cd;color:#664d03;border:1px solid #ffecb5;border-radius:12px;padding:12px 16px;margin-bottom:14px;font-size:13px">
            <i class="fas fa-exclamation-triangle"></i>
            JavaScript tidak aktif. Autosave/timer tidak tersedia. Klik tombol Kumpulkan sebelum deadline {{ $result->deadline_at->format('H:i:s') }}.
        </div>
    </noscript>

    {{-- Floating White Exam Card --}}
    <main class="anbk-card">
        {{-- Internal Sub-header Toolbar --}}
        <div class="anbk-toolbar">
            <h1 class="anbk-soal-heading">Soal nomor <span id="hud-q-active">1</span></h1>

            <div class="anbk-toolbar-actions">
                <div id="save-status" class="anbk-autosave ok" role="status" aria-live="polite">
                    <i class="fas fa-check-circle"></i> <span>Semua perubahan jawaban tersimpan di server.</span>
                </div>

                <div id="timer-box" class="anbk-pill anbk-pill-outline">
                    <i class="far fa-clock"></i>
                    <span>Sisa Waktu : <strong id="timer" role="timer">--:--</strong></span>
                </div>

                <button type="button" id="btn-open-daftar-soal" class="anbk-pill anbk-pill-primary" onclick="openDaftarSoal()">
                    <i class="fas fa-th-large"></i> Daftar Soal (<span id="answered-count">0</span>/{{ count($questions) }})
                </button>
            </div>
        </div>

        {{-- Secondary Toolbar: Font Resizer & Subject --}}
        <div class="anbk-subbar">
            <div class="anbk-font-group">
                <span>Ukuran font soal:</span>
                <button type="button" class="anbk-font-btn" data-fstep="0" onclick="setFontStep(0)" style="font-size:12px" title="Huruf kecil">A</button>
                <button type="button" class="anbk-font-btn active" data-fstep="1" onclick="setFontStep(1)" style="font-size:15px" title="Huruf normal">A</button>
                <button type="button" class="anbk-font-btn" data-fstep="2" onclick="setFontStep(2)" style="font-size:18px" title="Huruf besar">A</button>
            </div>
            <div class="anbk-subject-label">
                {{ $session->bank->subject ?? 'Ujian' }} &mdash; {{ $session->title }}
            </div>
        </div>

        {{-- Exam Form --}}
        <form id="exam-form" class="cbt-form" method="POST" action="{{ route('cbt.student.submit', $result) }}">
            @csrf

            @foreach($questions as $index => $question)
                @php
                    $qNum = $loop->iteration;
                    $savedAns = $result->answers[$question['id']] ?? null;
                    $stimulus = $question['stimulus'] ?? null;
                    $promptText = $question['prompt'];
                    if (!$stimulus && preg_match('/\[(?:WACANA|STIMULUS)\](.*?)\[\/(?:WACANA|STIMULUS)\](.*)/si', $promptText, $matches)) {
                        $stimulus = trim($matches[1]);
                        $promptText = trim($matches[2]);
                    }
                @endphp

                <div class="question-pane" id="pane-q-{{ $qNum }}" style="{{ $loop->first ? '' : 'display:none' }}">
                    @if($stimulus)
                        <div class="anbk-split">
                            <div class="anbk-stimulus">
                                <div class="anbk-stimulus-title">
                                    <i class="fas fa-book-open"></i> Wacana &amp; Informasi Pendukung
                                </div>
                                <div>{!! nl2br(e($stimulus)) !!}</div>
                            </div>
                            <div>
                                <div class="anbk-q-prompt cbt-q-prompt" id="prompt-{{ $qNum }}">{{ $promptText }}</div>
                                <div class="anbk-options">
                                    @foreach($question['options'] as $key => $option)
                                        <label class="anbk-option-row cbt-option-label {{ $savedAns === $key ? 'is-selected' : '' }}" for="q-{{ $question['id'] }}-{{ $key }}">
                                            <input type="radio"
                                                   id="q-{{ $question['id'] }}-{{ $key }}"
                                                   name="answers[{{ $question['id'] }}]"
                                                   value="{{ $key }}"
                                                   data-question="{{ $question['id'] }}"
                                                   data-qnum="{{ $qNum }}"
                                                   @checked($savedAns === $key)>
                                            <span class="anbk-option-key">({{ $key }})</span>
                                            <span class="anbk-option-text cbt-option-text">{{ $option }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="anbk-q-prompt cbt-q-prompt" id="prompt-{{ $qNum }}">{{ $promptText }}</div>
                        <div class="anbk-options">
                            @foreach($question['options'] as $key => $option)
                                <label class="anbk-option-row cbt-option-label {{ $savedAns === $key ? 'is-selected' : '' }}" for="q-{{ $question['id'] }}-{{ $key }}">
                                    <input type="radio"
                                           id="q-{{ $question['id'] }}-{{ $key }}"
                                           name="answers[{{ $question['id'] }}]"
                                           value="{{ $key }}"
                                           data-question="{{ $question['id'] }}"
                                           data-qnum="{{ $qNum }}"
                                           @checked($savedAns === $key)>
                                    <span class="anbk-option-key">({{ $key }})</span>
                                    <span class="anbk-option-text cbt-option-text">{{ $option }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif

                    {{-- Bottom Navigation Bar (Previous / Ragu-ragu / Next or Selesai) --}}
                    <div class="anbk-nav-bar">
                        <button type="button"
                                class="anbk-nav-btn anbk-btn-prev"
                                onclick="goToQuestion({{ $qNum - 1 }})"
                                @disabled($qNum === 1)>
                            <i class="fas fa-chevron-circle-left"></i> Soal sebelumnya
                        </button>

                        <label class="anbk-nav-btn anbk-btn-ragu" for="ragu-check-{{ $qNum }}">
                            <input type="checkbox"
                                   id="ragu-check-{{ $qNum }}"
                                   class="ragu-checkbox"
                                   data-qindex="{{ $qNum }}"
                                   onchange="toggleRagu({{ $qNum }}, this.checked)">
                            <span>Ragu-ragu</span>
                        </label>

                        @if($qNum < count($questions))
                            <button type="button"
                                    class="anbk-nav-btn anbk-btn-next"
                                    onclick="goToQuestion({{ $qNum + 1 }})">
                                Soal berikutnya <i class="fas fa-chevron-circle-right"></i>
                            </button>
                        @else
                            <button type="button"
                                    class="anbk-nav-btn anbk-btn-finish"
                                    onclick="confirmFinishExam()">
                                <i class="fas fa-check-circle"></i> Kumpulkan jawaban
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach

            <div id="submit-status" style="font-size:12px;color:#dc3545;text-align:center;margin-top:10px" role="alert"></div>

            {{-- Pusmendik "Daftar Soal" Modal --}}
            <div class="anbk-modal-backdrop" id="daftar-soal-modal" onclick="if(event.target === this) closeDaftarSoal()">
                <div class="anbk-modal" role="dialog" aria-modal="true" aria-labelledby="daftar-soal-title">
                    <div class="anbk-modal-header">
                        <h3 class="anbk-modal-title" id="daftar-soal-title">Daftar Soal</h3>
                        <button type="button" class="anbk-modal-close" onclick="closeDaftarSoal()" aria-label="Tutup">&times;</button>
                    </div>
                    <div class="anbk-modal-body">
                        <div class="anbk-palette-grid">
                            @foreach($questions as $index => $question)
                                @php
                                    $qNum = $loop->iteration;
                                    $isAnswered = isset($result->answers[$question['id']]);
                                @endphp
                                <button type="button"
                                        id="palette-btn-{{ $qNum }}"
                                        class="anbk-p-item {{ $qNum === 1 ? 'current' : '' }} {{ $isAnswered ? 'answered' : '' }}"
                                        onclick="goToQuestion({{ $qNum }}); closeDaftarSoal();" aria-label="Simpan">
                                    <span>{{ $qNum }}</span>
                                    <span class="anbk-p-check" aria-hidden="true"><i class="fas fa-check"></i></span>
                                </button>
                            @endforeach
                        </div>

                        <div class="anbk-modal-legend">
                            <span class="anbk-legend-item">
                                <span class="anbk-legend-box" style="background:#0d6efd"></span> Soal aktif
                            </span>
                            <span class="anbk-legend-item">
                                <span class="anbk-legend-box" style="background:#212529"></span> Sudah dijawab
                            </span>
                            <span class="anbk-legend-item">
                                <span class="anbk-legend-box" style="background:#ffc107"></span> Ragu-ragu
                            </span>
                            <span class="anbk-legend-item">
                                <span class="anbk-legend-box" style="background:#fff;border:1px solid #ced4da"></span> Belum dijawab
                            </span>
                        </div>
                    </div>
                    <div class="anbk-modal-footer">
                        <button type="button" class="anbk-pill anbk-pill-ghost" onclick="closeDaftarSoal()">Tutup</button>
                        <button id="submit-exam" type="button" onclick="confirmFinishExam()" class="anbk-nav-btn anbk-btn-finish" style="padding:8px 16px">
                            <i class="fas fa-check-circle"></i> Kumpulkan jawaban
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </main>
</div>

@include('partials.exam-outbox')
<script>
(() => {
    let currentQuestion = 1;
    const totalQuestions = {{ count($questions) }};
    const raguSet = new Set();
    const form = document.getElementById('exam-form');
    const status = document.getElementById('save-status');
    const submitStatus = document.getElementById('submit-status');
    const modal = document.getElementById('daftar-soal-modal');
    const csrf = @json(csrf_token());
    const saveUrl = @json(route('cbt.student.answer', $result));
    const integrityUrl = @json(route('cbt.student.integrity', $result));
    const resultUrl = @json(route('cbt.student.result', $result));
    const deadline = @json($result->deadline_at->getTimestamp() * 1000);
    const serverStart = @json(now()->getTimestamp() * 1000);
    const localStart = performance.now();
    let submitting = false;
    let expired = false;

    window.openDaftarSoal = function() {
        if (modal) modal.classList.add('open');
    };
    window.closeDaftarSoal = function() {
        if (modal) modal.classList.remove('open');
    };
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeDaftarSoal();
    });

    // Navigation function
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

    // Ragu-ragu toggle
    window.toggleRagu = function(qNum, forceState) {
        const cb = document.getElementById('ragu-check-' + qNum);
        const pBtn = document.getElementById('palette-btn-' + qNum);
        const shouldFlag = typeof forceState === 'boolean' ? forceState : !raguSet.has(qNum);

        if (shouldFlag) {
            raguSet.add(qNum);
            if (cb) cb.checked = true;
            if (pBtn) pBtn.classList.add('flagged');
        } else {
            raguSet.delete(qNum);
            if (cb) cb.checked = false;
            if (pBtn) pBtn.classList.remove('flagged');
        }
    };

    // Font size adjuster (Pusmendik 3-step A / A / A)
    const fontSizes = ['13px', '16px', '19px'];
    window.setFontStep = function(step) {
        const clamped = Math.max(0, Math.min(2, step));
        document.querySelectorAll('.cbt-q-prompt, .cbt-option-text').forEach(el => {
            el.style.fontSize = fontSizes[clamped];
        });
        document.querySelectorAll('.anbk-font-btn').forEach(btn => {
            btn.classList.toggle('active', Number(btn.dataset.fstep) === clamped);
        });
    };

    function refreshAnsweredCount() {
        const checked = document.querySelectorAll('input[data-question]:checked').length;
        const countEl = document.getElementById('answered-count');
        if (countEl) countEl.textContent = checked;
    }
    refreshAnsweredCount();

    // Option selection visual binding + autosave
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
        if (pBtn) {
            pBtn.classList.add('answered');
        }

        refreshAnsweredCount();

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
                status.className = 'anbk-autosave pending';
                status.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Menyimpan jawaban…</span>';
            } else if (tahap === 'pending') {
                status.className = 'anbk-autosave pending';
                status.innerHTML = '<i class="fas fa-exclamation-triangle"></i> <span>Tersimpan di HP (antrean kirim)</span>';
            } else if (tahap === 'saved') {
                status.className = 'anbk-autosave ok';
                status.innerHTML = pending
                    ? '<i class="fas fa-clock"></i> <span>Menunggu simpan…</span>'
                    : '<i class="fas fa-check-circle"></i> <span>Semua perubahan jawaban tersimpan di server.</span>';
            }
        },
        onExpired: () => {
            expired = true;
            status.className = 'anbk-autosave pending';
            status.innerHTML = '<i class="fas fa-info-circle"></i> <span>Waktu selesai. Membuka hasil…</span>';
            location.assign(resultUrl);
        },
    });
    outbox.restoreToDom();

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
            document.querySelectorAll('#submit-exam, .anbk-btn-finish').forEach(b => b.disabled = true);
            submitStatus.textContent = 'Mengirim jawaban ke server… Harap tunggu hingga halaman hasil terbuka.';
            form.submit();
        }
    };

    // Countdown Timer Loop (HH:MM:SS or MM:SS)
    const timerEl = document.getElementById('timer');
    const timerBox = document.getElementById('timer-box');
    function tick() {
        const seconds = Math.max(0, Math.ceil((deadline - serverStart - (performance.now() - localStart)) / 1000));
        const hrs = Math.floor(seconds / 3600);
        const mins = Math.floor((seconds % 3600) / 60);
        const secs = String(seconds % 60).padStart(2, '0');

        if (timerEl) {
            timerEl.textContent = hrs > 0
                ? String(hrs).padStart(2, '0') + ':' + String(mins).padStart(2, '0') + ':' + secs
                : String(mins).padStart(2, '0') + ':' + secs;
        }

        if (seconds <= 300 && timerBox && !timerBox.classList.contains('urgent')) {
            timerBox.classList.add('urgent');
        }

        if (seconds === 0 && !submitting) {
            expired = true;
            status.className = 'anbk-autosave pending';
            status.innerHTML = '<i class="fas fa-hourglass-end"></i> <span>Waktu habis. Mengumpulkan…</span>';
            form.requestSubmit();
        }
    }
    tick();
    setInterval(tick, 1000);

    // Mandatory Fullscreen, Secret Admin Exit (Ctrl+K), & Anti-Cheat Reset-and-Kick
    const fsGate = document.getElementById('anbk-fs-gate');
    const btnEnterFs = document.getElementById('btn-enter-mandatory-fs');
    const exitUrl = @json(route('cbt.student.index', ['kicked' => 1]));
    const outboxStorageKey = 'simantap:exam-outbox:' + @json('cbt:'.$result->id);
    let examEnteredFullscreen = !!document.fullscreenElement;
    let adminUnlocked = false;
    let violationTriggered = false;

    function syncFsGate() {
        if (!fsGate) return;
        if (document.fullscreenElement || adminUnlocked) {
            fsGate.classList.add('hidden');
            if (document.fullscreenElement) {
                examEnteredFullscreen = true;
            }
        } else {
            fsGate.classList.remove('hidden');
        }
    }

    function requestMandatoryFullscreen() {
        if (document.fullscreenElement) {
            syncFsGate();
            return;
        }
        const el = document.documentElement;
        if (el.requestFullscreen) {
            el.requestFullscreen().then(() => {
                examEnteredFullscreen = true;
                syncFsGate();
            }).catch(() => {
                syncFsGate();
            });
        }
    }

    if (btnEnterFs) {
        btnEnterFs.addEventListener('click', requestMandatoryFullscreen);
    }
    requestMandatoryFullscreen();
    syncFsGate();

    function triggerViolationResetAndExit(type) {
        if (violationTriggered || submitting || expired || adminUnlocked) return;
        violationTriggered = true;
        submitting = true;

        try {
            window.localStorage.removeItem(outboxStorageKey);
        } catch (e) {}

        fetch(integrityUrl, {
            method: 'POST',
            keepalive: true,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ type: type, reset_answers: true })
        }).catch(() => {}).finally(() => {
            if (document.fullscreenElement && document.exitFullscreen) {
                document.exitFullscreen().catch(() => {});
            }
            window.location.replace(exitUrl);
        });

        setTimeout(() => {
            window.location.replace(exitUrl);
        }, 350);
    }

    // Secret Admin Sekolah / Proktor unlock: Ctrl+K (no UI hint shown to student)
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            e.stopPropagation();
            adminUnlocked = true;
            syncFsGate();
            if (document.fullscreenElement && document.exitFullscreen) {
                document.exitFullscreen().catch(() => {});
            }
            return;
        }

        if (!examEnteredFullscreen || adminUnlocked || submitting || expired) {
            return;
        }

        const key = e.key || '';
        const isFunctionKey = /^F([1-9]|1[0-2])$/i.test(key);
        const isForbiddenCombo = (e.ctrlKey || e.altKey || e.metaKey)
            && !['Control', 'Alt', 'Meta', 'Shift'].includes(key);

        if (key === 'Escape' || key === 'Meta' || key === 'ContextMenu' || isFunctionKey || isForbiddenCombo) {
            e.preventDefault();
            e.stopPropagation();
            triggerViolationResetAndExit('fullscreen_exit');
        }
    }, true);

    document.addEventListener('visibilitychange', () => {
        if (document.hidden && examEnteredFullscreen && !adminUnlocked && !submitting && !expired) {
            triggerViolationResetAndExit('tab_hidden');
        }
    });

    document.addEventListener('fullscreenchange', () => {
        if (!document.fullscreenElement && examEnteredFullscreen && !adminUnlocked && !submitting && !expired) {
            triggerViolationResetAndExit('fullscreen_exit');
        } else {
            syncFsGate();
        }
    });

    // KaTeX Auto-Render Math Integration
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
</body>
</html>
