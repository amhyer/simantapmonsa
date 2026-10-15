# CBT Frontend Implementation Plan

**Project:** SIMANTAP Laravel Application  
**Frontend Stack:** Tailwind CSS 3 + Alpine.js 3 + Vanilla JS (Vite/Hono)  
**Layout:** Extends `layouts.app`, uses `.table-wrapper` pattern, follows existing `.card` components  
**Status:** Production-ready backend, frontend 0% (clear scope)

---

## Key Design Decisions

### 1. CSS Framework: Tailwind 3 (via layout.css) + Bootstrap-like utilities
- **Rationale:** Project uses `@tailwind` directives with custom CSS classes (`.card`, `.btn`, `.table-wrapper`). Maintaining this pattern ensures consistency and reuses existing utility structure.
- **Pattern to follow:** Classes defined in `resources/css/layout.css` (`.card`, `.card-header`, `.card-body`, `.btn`, `.btn-sm`, `.tag`, `.field-error`, `.table-wrapper`, `.empty-state`).

### 2. Interactivity: Alpine.js 3 + Vanilla JS (no React/Vue)
- **Rationale:** App already bundles Alpine 3 with custom directives (`x-auto-animate`), so Alpine is the standard for progressive enhancement. Heavy interactive logic (timer, integrity checks, auto-save) uses plain JS modules—Alpine handles toggles and conditionals only.
- **Pattern:** Alpine for state and visibility toggling; modular JS for timers, network calls, and security checks.

### 3. Timer & Integrity Checks: Vanilla JS Modules (no library)
- **Rationale:** Custom requirements (tab switch detection, focus loss, integrity violation logging, auto-submit). Bespoke JS is faster and simpler than adding yet another library. Modules are portable and testable.

### 4. Auto-Save: Debounced AJAX with localStorage fallback
- **Rationale:** Network may drop during exam. Auto-save to localStorage first (safe locally), then POST to server per question. If POST fails, student sees a warning badge; on recovery, retry all pending saves.

### 5. Form Submission: Standard CSRF-protected POST
- **Rationale:** Laravel CSRF token in layout. Each form uses `@csrf` and `method="POST"`. AJAX calls read token from meta tag `<meta name="csrf-token">`.

---

## File-by-File Implementation Plan

### JS Modules (Build First — used by Blade views)

#### 1. resources/js/cbt/timer.js
**What:** Countdown timer with warning thresholds and auto-submit on expiry.  
**Data flow:**
- Receives from Blade: `durationSeconds`, `testResultId`, `sessionEndTime` (ISO 8601)
- Manages: elapsed, remaining, display format (MM:SS), warning states (> 5 min OK, 1-5 min yellow, < 1 min red)
- Actions: updates DOM display, triggers warning styles, auto-POSTs to `route('cbt.student.submit', result)` on expiry
- Browser API: `Date.now()`, `requestAnimationFrame` for smooth updates

**Methods:**
- `new ExamTimer(element, durationSeconds, testResultId)` — constructor; auto-starts on init
- `start()` — begin countdown (called on exam load)
- `pause()` — freeze timer (called if browser loses focus briefly)
- `resume()` — restart timer (called when focus returns)
- `autoSubmit()` — POST to submit endpoint and show "Waktu habis, ujian dikumpulkan" message
- `getRemainingSeconds()` — return countdown value
- `setWarningLevel()` — update CSS class for color (timer-ok, timer-warning, timer-danger)

**Verification:** Unit tests (if framework available) or manual: (1) Load exam, verify MM:SS format updates; (2) Wait 5 sec, verify display counts down; (3) Let timer reach 00:00, verify auto-submit fires and page redirects.

---

#### 2. resources/js/cbt/integrity-checker.js
**What:** Detects integrity violations (tab switch, focus loss, copy-paste, multiple windows) and logs to backend.  
**Data flow:**
- Receives from Blade: `testResultId`, `endpoint` (route name 'cbt.integrity.log')
- Observes: `visibilitychange` (tab/window switch), `blur`/`focus` (focus loss), `contextmenu`/`cut`/`copy` (copy-paste), `mousedown` outside window (multiple displays)
- On violation: POST JSON to `/cbt/integrity/{result}/log` with `{ jenis_pelanggaran, deskripsi, data_tambahan }`
- Maintains local: violation count, score % (100 - violations*10), last violation time (to avoid spam)
- Visibility: shows badge in exam header (e.g., "Integritas: 95%") updated in real-time

**Methods:**
- `new IntegrityChecker(testResultId, csrfToken)` — constructor; sets up all listeners
- `checkVisibility()` — detect hidden/shown (tab switch)
- `checkFocus()` — detect blur/focus
- `checkCopyPaste()` — block/detect Ctrl+C, Ctrl+V, right-click
- `logViolation(type, desc, data)` — POST to backend
- `updateScore(score)` — update header badge
- `getViolationCount()` — return total violations this session

**Violation types:** `tab_switch`, `copy_paste`, `screen_capture`, `focus_lost`, `multiple_tabs`, `suspicious_activity`

**Verification:** Manual: (1) Open exam, switch tab, watch violation log; (2) Focus out (blur), log checks; (3) Try Ctrl+C, detect & log.

---

#### 3. resources/js/cbt/auto-save.js
**What:** Saves student answers on every change with exponential backoff retry and localStorage fallback.  
**Data flow:**
- Receives from Blade: `testResultId`, `endpoint` (route 'cbt.student.answer'), `endpoint_submit` (route 'cbt.student.submit')
- Listens to: radio button change, textarea change, option click per question
- On change: reads question ID + answer value, debounces 500ms, POSTs to endpoint
- On success: clears "unsaved" indicator
- On error (network down): stores locally in `sessionStorage['cbt_pending_' + resultId]`, shows yellow badge "Jawaban belum tersimpan"
- On network recovery: sends all pending answers in batch with exponential backoff (1s, 2s, 4s, max 8s)
- On final submit: checks for pending answers and waits for confirmation (or auto-sends if all OK)

**Methods:**
- `new AnswerAutoSaver(testResultId, csrfToken)` — constructor; attaches listeners to all question elements
- `saveAnswer(questionId, jawaban, raguRagu)` — debounced POST
- `getPendingAnswers()` — return array of unsaved {questionId, answer}
- `retryPending()` — send all pending with backoff
- `onAnswerSaved(callback)` — hook for UI feedback
- `onAnswerError(callback)` — hook for retry UI

**localStorage keys:**
- `cbt_pending_{resultId}` — JSON array of {questionId, answer, raguRagu, timestamp}
- `cbt_auto_save_last_{resultId}` — timestamp of last successful save

**Verification:** Manual: (1) Select answer, watch POST in DevTools; (2) Disconnect network, select new answer, see "belum tersimpan"; (3) Reconnect, watch auto-retry succeed.

---

### Blade Views (Student Exam Experience)

#### 4. resources/views/cbt/student/take.blade.php
**What:** Main exam interface. Displays question carousel, timer, navigation, auto-save indicator, integrity score.

**Layout:** Extends `layouts.app`  
**Sidebar:** `@include('siswa.partials.sidebar')`  
**Page title:** "Mengerjakan Ujian: {session.nama_sesi}"  
**Page subtitle:** "{question X of Y}"

**Variables from controller (CbtStudentController.start):**
- `$session` — TestSession model (durasi_menit, nama_sesi, passing_grade, etc.)
- `$result` — TestResult model (id, test_session_id, siswa_id, waktu_mulai)
- `$questions` — Collection of Question models (id, pertanyaan, tipe_soal, poin, media_url, options)

**Layout structure:**
```
[Topbar: Timer | Integrity Score | Question X/Y | Notifications]
[Main question display area]
  - Question text + media (image/video if present)
  - Options (radio for MC, checkboxes for multi-select, text for essay)
  - "Ragu-ragu" checkbox
  - Auto-save status indicator (spinning icon or checkmark)
[Sidebar: Question navigator (numbered buttons, highlights current)]
  - Answered questions: green
  - Unanswered: gray
  - Flagged (ragu-ragu): yellow
[Footer: Previous | Next | Submit buttons]
```

**Interactivity:**
- Alpine: `x-data` manages current question index, answer dict
- On option click: save to local state, trigger auto-save JS
- On "Next" click: move carousel, update nav
- On "Previous" click: move carousel (if not first), update nav
- On "Submit" click: confirm dialog, POST to `route('cbt.student.submit', $result)`

**CSS classes:** `.card`, `.btn`, `.btn-sm`, `.tag`, `.table-wrapper` (for question nav grid)

**Forms:**
- Hidden form for final submit: includes `@csrf`, test result ID, post to `cbt.student.submit`
- Question option forms (per-question, hidden): captured by JS auto-save, no visible submit

**Error handling:**
- Display integrity violation warning if score drops
- Show "Network error, retrying..." on auto-save fail
- Show "Time expired, exam submitted" on timer expiry

**Verification:** `npm run build && npm run dev`, then manual: (1) Load exam page, verify questions load; (2) Select answers, watch auto-save; (3) Click Next/Prev, verify nav updates; (4) Wait for timer expiry or click Submit, verify redirect to result page.

---

#### 5. resources/views/cbt/student/result.blade.php
**What:** Post-exam result display. Shows score, pass/fail, answer review, integrity summary.

**Layout:** Extends `layouts.app`  
**Sidebar:** `@include('siswa.partials.sidebar')`  
**Page title:** "Hasil Ujian: {session.nama_sesi}"  
**Page subtitle:** "Selesai {result.waktu_selesai.format('d/m H:i')}"

**Variables from controller (CbtStudentController.result):**
- `$result` — TestResult (nilai_akhir, nilai_maksimal, persentase, lulus, integrity_score, durasi_detik, etc.)
- `$session` — TestSession (passing_grade, tampilkan_nilai, pembahasan, etc.)
- `$result->studentAnswers()` — Collection with eager-loaded question + options

**Layout structure:**
```
[Header card: Score summary]
  - Large percentage circle (pie chart or simple display)
  - Nilai: X / Y poin
  - Lulus: [tag-ok or tag-mut]
  - Waktu: MM menit SS detik
  - Integritas: X/100

[Stats card: Answer breakdown]
  - Benar: X soal
  - Salah: X soal
  - Tidak dijawab: X soal

[Violation summary (if integrity_score < 100)]
  - List violations with timestamps
  - Each row: jenis_pelanggaran, waktu, deskripsi

[Answer review (if teacher shows feedback)]
  - Per-question card:
    - Question text + media
    - Student answer (highlighted green if correct, red if wrong)
    - Correct answer (shown per teacher settings)
    - Pembahasan (explanation, if available)
    - Points: X/Y poin

[Actions]
  - "Kembali ke Daftar Ujian" button (link to cbt.student.index)
  - "Download Hasil" button (if available)
```

**Conditionals (Alpine):**
- Show answer detail only if `session.tampilkan_nilai` or submitted by guru
- Expand/collapse each question review card
- Show only violations if integrity_score < 100

**CSS classes:** `.card`, `.stat-card`, `.tag`, `.bar` (for progress visualization)

**Verification:** Manual: (1) Complete exam or submit early; (2) Navigate to result page, verify score displays; (3) Verify "Belum lulus/Lulus" badge matches passing_grade; (4) If violations logged, verify they appear; (5) Check answer review (if enabled by teacher).

---

### Blade Views (Teacher Management)

#### 6. resources/views/cbt/sessions/monitoring.blade.php
**What:** Live student monitoring dashboard for the teacher during exam.

**Layout:** Extends `layouts.app`  
**Sidebar:** `@include('guru.partials.sidebar')`  
**Page title:** "Pantau Ujian: {session.nama_sesi}"  
**Page subtitle:** "Live update — Active: X siswa | Selesai: Y siswa"

**Variables from controller (CbtTestSessionController.monitoring):**
- `$session` — TestSession (nama_sesi, status, tanggal_mulai, durasi_menit)
- `$activeStudents` — Collection of TestResult where waktu_selesai is null (live)
- `$completedStudents` — Collection of TestResult where waktu_selesai is not null

**Layout structure:**
```
[Filters / Status bar]
  - Show/hide active vs. completed
  - Refresh interval dropdown (auto 5s, 10s, manual)

[Active students table]
  - Columns: Nama, Waktu Mulai, Durasi (elapsed), Status, Integritas, Actions
  - Rows update every 5 sec via AJAX (polling from route 'cbt.sessions.api.monitoring')
  - Color coding: integrity_score < 80 = warning, < 50 = danger
  - Action links: "Detail", "Lihat Jawaban (live)", "Force Complete"

[Completed students table]
  - Columns: Nama, Nilai, Lulus (tag), Waktu Selesai, Actions
  - Static (loaded once)
  - Action links: "Lihat Hasil", "Download"

[Toolbar]
  - "Complete Session" button (POST to route 'cbt.sessions.complete', $session)
  - "Refresh Now" button (manual refresh)
  - "Export Results" dropdown (PDF / Excel)
```

**Interactivity:**
- Alpine: `x-data` manages `autoRefresh` (interval, is_running)
- On interval tick: fetch JSON from `route('cbt.sessions.api.monitoring', $session)`, update active table rows
- On "Force Complete" click: POST to `route('cbt.student.submit', $result)` (force that student's result), remove row from active

**API endpoint (CbtTestSessionController.apiMonitoring):**
Returns JSON:
```json
{
  "active_students": [
    {
      "id": result.id,
      "siswa": siswa.nama_peserta_didik,
      "waktu_mulai": result.waktu_mulai,
      "durasi_sudah": elapsed_seconds,
      "integrity_score": result.integrity_score
    }
  ]
}
```

**CSS classes:** `.card`, `.table-wrapper`, `.tag`, `.btn`, `.btn-sm`

**Verification:** Manual: (1) Create and start a session; (2) Have student open exam on another browser; (3) Open monitoring page, verify active student appears; (4) Wait 5 sec, verify elapsed time updates; (5) Student selects answers, watch integrity score (if violations logged); (6) Complete exam, verify row moves to "Selesai" table.

---

#### 7. resources/views/cbt/question-banks/show.blade.php
**What:** Bank soal detail page. Lists all questions in bank, shows count, allows add/edit/delete per question.

**Layout:** Extends `layouts.app`  
**Sidebar:** `@include('guru.partials.sidebar')`  
**Page title:** "Bank Soal: {bank.nama_bank}"  
**Page subtitle:** "{bank.deskripsi} — {bank.jumlah_soal} soal"

**Variables from controller (CbtQuestionBankController.show):**
- `$bank` — QuestionBank (nama_bank, deskripsi, tingkat_kesulitan, guru_id, aktif, etc.)
- `$bank->questions()` with eager-loaded `options`

**Layout structure:**
```
[Info card: Bank metadata]
  - Nama, Deskripsi
  - Mata Pelajaran, Semester
  - Tingkat Kesulitan (tag)
  - Kategori
  - Status: Aktif / Nonaktif

[Toolbar]
  - "Edit Bank" link (route 'cbt.question-banks.edit', $bank)
  - "Add Question" button (opens inline form or link to separate create page)
  - "Duplicate Bank" button (POST with json)

[Questions table / card list]
  - If empty: "Belum ada soal. Tambah sekarang."
  - Per question:
    - [Drag handle] Urutan (reorderable)
    - Pertanyaan (preview text, truncated to 100 chars)
    - Tipe Soal (tag: Pilihan Ganda, Benar/Salah, Essay)
    - Poin
    - Media (icon if url set)
    - Aktif (toggle checkbox, AJAX to update)
    - Actions: [Edit] [Preview] [Duplicate] [Delete]

[Inline question form (collapsible)]
  - Only shown if adding via inline UI
  - Fields: Pertanyaan, Tipe, Options (radio/checkboxes per type), Poin, Media URL
  - Cancel / Submit buttons
```

**Interactivity:**
- Alpine: `x-show` toggles add form, `x-data` manages form state
- Click "Add Question": toggle form visibility, focus textarea
- Edit icon: navigate to separate edit page (or inline toggle in future)
- Drag handle (if using SortableJS library): POST reorder to backend (not required, low priority)
- Delete icon: confirm dialog, POST to `route('cbt.questions.destroy', $question)`
- Aktif checkbox: toggle without page reload, AJAX PATCH to `/cbt/questions/{id}`

**Forms:**
- Add question: POST to `route('cbt.questions.store', $bank)` with `@csrf`
- Edit question: PUT to `route('cbt.questions.update', $question)` (separate page)
- Delete: POST with confirm + CSRF token

**CSS classes:** `.card`, `.table-wrapper`, `.tag`, `.btn`, `.btn-sm`, `.empty-state`

**Verification:** Manual: (1) Navigate to a bank, verify questions list displays; (2) Add new question via form, verify row appears; (3) Click Edit, verify redirect; (4) Toggle Aktif checkbox, verify AJAX saves; (5) Click Delete, confirm, verify row removed.

---

#### 8. resources/views/cbt/question-banks/edit.blade.php
**What:** Edit individual question in a bank. Form for question text, type, options, scoring.

**Layout:** Extends `layouts.app`  
**Sidebar:** `@include('guru.partials.sidebar')`  
**Page title:** "Edit Soal: {bank.nama_bank}"  
**Page subtitle:** "Soal #{question.urutan}"

**Variables from controller (CbtQuestionBankController.show or dedicated edit action):**
- `$bank` — QuestionBank (for breadcrumb/context)
- `$question` — Question (id, pertanyaan, tipe_soal, poin, media_url, media_type, etc.)
- `$question->options` — Collection of QuestionOption

**Layout structure:**
```
[Breadcrumb / header]
  - Bank Soal / {bank.nama_bank} / Edit Soal

[Form card]
  - Pertanyaan (textarea, required)
  - Tipe Soal (select: Pilihan Ganda, Benar/Salah, Essay)
  - Media type & URL (optional file upload or URL)
    - If media_type: image, show preview
  - Poin (number, default 1)
  - Waktu Jawab (seconds, optional)
  - Aktif (checkbox, default true)
  - Pembahasan (textarea, optional — for teacher review + student post-exam view)

[Options section (dynamic based on tipe_soal)]
  --- If Pilihan Ganda:
    - Table: Huruf | Isi Opsi | Benar | Actions
    - Per row: radio button "Benar", text input for isi_opsi, edit/delete buttons
    - Add row button to add new option
    - Validation: ensure exactly 1 is marked "Benar"
  
  --- If Benar/Salah:
    - No options section (hardcoded: "Benar", "Salah")
  
  --- If Essay:
    - Show sample rubric fields (optional: score breakdown)

[Submit buttons]
  - Simpan Perubahan
  - Kembali ke Bank
```

**Interactivity:**
- Alpine: `x-data` manages `tipe_soal`, option count, media preview
- On tipe_soal change: show/hide options section, rebuild option rows if needed
- On "Add Option" click: append new row with empty input + delete button
- On file upload: preview image if media_type = 'image'
- On "Simpan" click: validate options (MC must have 1 correct), POST to `route('cbt.questions.update', $question)`

**Forms:**
- Main form: PUT to `route('cbt.questions.update', $question)` with `@csrf`
- Nested options: serialized as JSON or as repeating form fields (options[0][huruf], options[0][isi_opsi], options[0][adalah_benar])

**CSS classes:** `.card`, `.form-label`, `.form-control`, `.field-error`, `.btn`

**Edge cases:**
- If question has 0 options saved (data corruption), warn and offer default empty option row
- If media_url is broken, show placeholder icon
- If pembahasan is very long (>500 chars), collapse initially with "Lihat selengkapnya" expander

**Verification:** Manual: (1) Open edit page for an existing question; (2) Change pertanyaan text, click Simpan, verify page reloads; (3) Change tipe_soal to Benar/Salah, verify options section hides; (4) Add media URL, verify preview if image; (5) Change option order, verify order persists after save.

---

## Edge Cases & Error Handling

### Network Loss During Exam
1. Timer continues client-side (no server needed).
2. Auto-save detects error, saves to `sessionStorage`.
3. Badge shows "Jawaban belum tersimpan" (yellow).
4. On network recovery, auto-save retries with exponential backoff.
5. If network down until exam end, student can still submit (pending answers POST on submit).

### Timer Expiry
1. Timer reaches 00:00, triggers auto-submit.
2. POST to `route('cbt.student.submit', $result)`.
3. Backend calculates score, updates `waktu_selesai`.
4. Student sees "Waktu habis. Ujian telah dikumpulkan" and redirects to result page.

### Multiple Tabs Open (Same Student, Same Exam)
1. Integrity checker detects `visibilitychange` (hidden tab).
2. Both sessions log violations (server enforces one submission only).
3. Whichever posts first wins; second attempt redirected or rejected.

### Exam Already Submitted (Double-Submit Attempt)
1. Backend: `result.waktu_selesai` is not null, controller returns error.
2. Frontend: Can't trigger submit twice (button disabled or removed after first click).
3. Student sees "Ujian sudah dikumpulkan" message.

### Question With No Media
- Image/video section hidden if `question.media_url` is null.
- Question text displays normally.

### Teacher Force-Closes Session
1. Teacher clicks "Complete Session" button on monitoring page.
2. POST to `route('cbt.sessions.complete', $session)`.
3. Backend sets `session.status = 'completed'`.
4. Students still on exam page: timer remains active, but next POST to submit will warn "Ujian telah ditutup guru".
5. Frontend shows toast: "Ujian ditutup oleh guru. Jawaban akan dikumpulkan otomatis."

---

## CSRF & Security

- **Token source:** `<meta name="csrf-token" content="{{ csrf_token() }}">` in `layouts/app.blade.php`.
- **Form submissions:** `@csrf` helper in all forms.
- **AJAX calls:** JS modules read token from meta and include in `X-CSRF-TOKEN` header.
- **Route middleware:** Student routes wrapped with `['auth', 'role:siswa', 'cbt.security', ...]` (defined in routes/web.php).

---

## Route Names & URL Patterns

**Student Routes:**
- `cbt.student.index` → `/cbt/student/` — List available sessions + my results
- `cbt.student.start` → `/cbt/student/{session}/start` — Load exam interface (GET)
- `cbt.student.answer` → `/cbt/student/{result}/answer` — Save answer (POST)
- `cbt.student.submit` → `/cbt/student/{result}/submit` — Submit exam (POST)
- `cbt.student.result` → `/cbt/student/{result}/result` — View result (GET)

**Teacher Routes:**
- `cbt.sessions.index` → `/cbt/sessions/` — List my sessions
- `cbt.sessions.show` → `/cbt/sessions/{session}` — Session detail + results list
- `cbt.sessions.monitoring` → `/cbt/sessions/{session}/monitoring` — Live monitoring page
- `cbt.sessions.api.monitoring` → `/cbt/sessions/{session}/api/monitoring` — API for live updates (JSON)
- `cbt.question-banks.index` → `/cbt/question-banks/` — List my banks
- `cbt.question-banks.show` → `/cbt/question-banks/{bank}` — Bank detail + questions list
- `cbt.question-banks.edit` → `/cbt/question-banks/{bank}/edit` — Edit bank metadata
- `cbt.questions.store` → `/cbt/questions/{bank}` (POST) — Create question
- `cbt.questions.update` → `/cbt/questions/{question}` (PUT) — Update question
- `cbt.questions.destroy` → `/cbt/questions/{question}` (DELETE) — Delete question

**Integrity Routes:**
- `cbt.integrity.log` → `/cbt/integrity/{result}/log` (POST) — Log violation

---

## Build & Deployment Commands

**Development:**
```bash
npm run dev          # Vite HMR server on :5173
php artisan serve    # Laravel dev server on :8000
```

**Production build:**
```bash
npm run build        # Compile assets with Vite
php artisan optimize # Cache routes, config
```

**Testing:**
```bash
npm run build && npm run dev  # Verify JS modules compile
php artisan test             # Run PHPUnit tests
```

---

## Implementation Order (Dependencies)

1. **resources/js/cbt/timer.js** — No dependencies; used by take.blade
2. **resources/js/cbt/integrity-checker.js** — No dependencies; used by take.blade
3. **resources/js/cbt/auto-save.js** — Depends on timer & integrity for coordination; used by take.blade
4. **resources/views/cbt/student/take.blade.php** — Depends on all 3 JS modules
5. **resources/views/cbt/student/result.blade.php** — No deps; displays data from controller
6. **resources/views/cbt/sessions/monitoring.blade.php** — No deps; uses polling AJAX
7. **resources/views/cbt/question-banks/show.blade.php** — No deps; list & CRUD per question
8. **resources/views/cbt/question-banks/edit.blade.php** — No deps; form for single question

**Rationale:** JS modules must compile successfully before Blade views render them. Views can be built in any order after JS is done, but monitoring depends on backend API readiness (already in CbtTestSessionController).

---

## Verification Checklist

- [ ] `npm run build` succeeds (no JS syntax errors)
- [ ] Each JS module exports expected functions/classes
- [ ] `take.blade.php` loads exam, auto-save works, timer counts down, submit redirects
- [ ] `result.blade.php` displays score & answers correctly
- [ ] `monitoring.blade.php` shows live students, auto-refreshes every 5 sec
- [ ] `show.blade.php` lists questions, add/edit/delete work, inline form toggles
- [ ] `edit.blade.php` loads question, option type changes work, save persists
- [ ] All forms include `@csrf` token
- [ ] All AJAX calls include X-CSRF-TOKEN header
- [ ] Timer auto-submits on expiry
- [ ] Integrity violations log correctly
- [ ] Auto-save retries on network recovery
- [ ] No console errors in DevTools

---

## Notes

- **No redesign of existing views:** This plan builds only the 8 CBT frontend files. Shared layouts (`app.blade`, `sidebar.blade`) are not modified (already defined).
- **Alpine 3 standard:** All Alpine features are Alpine 3+ compatible (no legacy directives).
- **Backward compatibility:** If students use older browsers, exam may degrade gracefully (timer via polling instead of requestAnimationFrame, no integrity check), but core submit still works.
- **Database & models:** All CBT models and migrations are already in place; this plan assumes schema is correct (columns match what controllers use).

