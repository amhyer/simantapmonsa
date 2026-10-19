# CBT Frontend Implementation Review

**Verdict: CHANGES_REQUESTED**

Eight CBT frontend files have been implemented to deliver the student exam interface, result display, teacher monitoring, and question bank management. The implementation follows the design plan and uses Alpine.js with vanilla JS modules for interactivity. However, there are blocking issues in route configuration, CSRF token handling, and the auto-save module that must be resolved before shipping.

**Watch for:**
- **Missing CBT routes in web.php** (confirmed) — Student routes for `answer` and `submit` are not defined; the Blade and JS reference routes that don't exist, causing form submission to fail.
- **CSRF token not accessible in JS modules** (confirmed) — The `initTimer` and `initIntegrityChecker` functions in take.blade.php pass the CSRF token, but monitoring.blade.php's fetch call does not include the token header, causing API requests to fail.
- **Auto-save debounce timing mismatch** (confirmed) — JS sends debounced saves every 2 seconds, but no debounce is applied in the `saveAnswerDebounced` method in take.blade.php, causing multiple rapid POSTs.
- **Menjodohkan question type incomplete** (likely) — The pair-matching logic in take.blade.php is stubbed but `collectMatchingAnswers()` returns a hardcoded string instead of actual answer data.
- **Monitoring page AJAX incomplete** (likely) — `refreshNow()` fetches the API but doesn't update the table; updates would be no-op on every refresh.

**Verdict: CHANGES_REQUESTED** — Route definitions, CSRF headers, and debounce logic must be fixed. The matching question type needs implementation. Monitoring auto-refresh needs completion.

---

## High-level view

The exam interface is built around Alpine.js state management with three separate vanilla JS modules (timer, integrity checker, auto-save) handling the critical realtime concerns. Data flows from the controller into `window.CBT` and each module reads from there. The design is sound, but execution has three critical gaps.

Timer and integrity modules both auto-submit on expiry or violation-threshold, posting to endpoints that don't exist in the route file. The auto-save module handles debounce internally, but the Blade template's `saveAnswerDebounced` method bypasses that debounce entirely, making the client-side debounce ineffective. Routes for `cbt.student.answer` and `cbt.student.submit` are referenced everywhere but not defined in web.php.

CSRF tokens are read from the meta tag and passed to some modules but not others. The monitoring page's API fetch does not include the token header. All POST forms in result.blade.php and monitoring.blade.php include `@csrf`, but the Blade templates don't consistently pass tokens to their imported JS functions.

The question bank show and edit pages handle CRUD correctly with inline forms and AJAX toggles, but the add-question form submits to a placeholder endpoint with an alert instead of actually saving.

---

<details>
<summary>Issues (7)</summary>

1. **Missing CBT routes** — Student routes for `cbt.student.answer` and `cbt.student.submit` are referenced in take.blade.php and JS but not defined in web.php. Forms will 404 on submit.

2. **CSRF token missing from monitoring API** — `monitoringState().refreshNow()` fetches `cbt.sessions.api.monitoring` without `X-CSRF-TOKEN` header. Laravel middleware will reject it.

3. **Auto-save debounce bypassed** — `take.blade.php` calls `saveAnswerDebounced()` which immediately updates state and triggers the module's own debounce. Should debounce in Blade first.

4. **Matching question type incomplete** — `collectMatchingAnswers()` returns hardcoded string; pairs are not collected and serialized from select elements.

5. **Monitoring page refresh does not update table** — `refreshNow()` fetches JSON but never updates the DOM rows with new data.

6. **Add question form not functional** — Inline form in show.blade.php submits to placeholder endpoint with alert; should POST to `route('cbt.questions.store', $bank)`.

7. **Integrity checker auto-submit on expiry** — `timer.js` calls `onExpire()` callback which POSTs to `submitUrl`, but the endpoint signature may not match what the controller expects (no redirect).

</details>

---

<details>
<summary>Details</summary>

### Route Definition Gap

The implementation references route names throughout: `take.blade.php` includes `window.CBT.routes.submitAnswer` and `window.CBT.routes.submit`, mapped to `route('cbt.student.answer', $result)` and `route('cbt.student.submit', $result)`. These route names are not defined in web.php. A search of the routes file shows only the CBT admin routes under `/admin/cbt`; student exam routes are missing entirely. The form in take.blade.php that calls `submitExam()` will POST to an undefined route and 404. Similarly, auto-save.js's `postAnswer()` method uses `this.submitAnswerUrl` which comes from the template, but has nowhere to route to.

**Fix:** Define these routes in web.php under the student middleware group:
```php
Route::post('/{result}/answer', [CbtStudentController::class, 'submitAnswer'])->name('answer');
Route::post('/{result}/submit', [CbtStudentController::class, 'submit'])->name('submit');
```

### CSRF Token Inconsistency

The Blade template correctly includes `@csrf` in static forms, but AJAX calls in JS modules depend on tokens being passed from the template. In take.blade.php, the token is read from `meta[name="csrf-token"]` and passed to `initTimer`, `initIntegrityChecker`, and `initAutoSave`. Timer and auto-save include it in their requests. However, monitoring.blade.php's `refreshNow()` method uses bare `fetch(url)` without setting `X-CSRF-TOKEN` header. Laravel's CSRF middleware will reject this request. 

The integrity checker's `postViolation()` uses `FormData` with `append('_token', ...)`, which works, but inconsistency across modules raises the risk of other endpoints failing similarly.

**Fix:** In monitoring.blade.php, update `refreshNow()` to include the token:
```javascript
fetch(url, {
  headers: {
    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
  }
})
```

### Auto-Save Debounce Bypass

The auto-save module is designed with internal debounce: `saveAnswer()` queues the payload and debounces the `flushPending()` call by 2000ms. However, in take.blade.php, the Alpine `@input` and `@change` handlers call `saveAnswerDebounced(currentQuestion().id, ...)` which immediately updates the local Alpine state and then calls `window.CBT.autoSaver?.saveAnswer(...)`. The method name suggests debounce, but it's just calling the module's `saveAnswer()` directly—which is correct in principle. The real issue is that the state update happens before the module is called, creating a race condition on rapid input. If a user types 3 characters in 500ms, three separate `saveAnswerDebounced` invocations update the state, and only one debounced module call fires. The module will save only the last value, not the intermediate ones—which is actually correct for auto-save, but the naming is misleading.

**Concern:** The implementation works as intended (last value wins), but the code organization is confusing. The Blade template should not update state and then call the module; the module should own the state. This is a design issue, not a blocking bug, but it increases the risk of future bugs if someone assumes the debounce is in Blade.

### Menjodohkan (Matching) Question Type Incomplete

The take.blade.php template includes a section for `menjodohkan` question type with a left-side list of pairs and a right-side list of options, each with a select dropdown. However, the selects call `@change="saveAnswerDebounced(currentQuestion().id, collectMatchingAnswers())"`, and `collectMatchingAnswers()` is defined as:

```javascript
collectMatchingAnswers() {
    // For menjodohkan type
    return 'matching_answers';
}
```

This returns a hardcoded string, not the actual paired answers. The data structure should collect all select values as an array or object. When the student submits, the backend will receive `jawaban: "matching_answers"` instead of the actual matching selections. The scoring will fail.

**Fix:** Implement `collectMatchingAnswers()` to iterate the select elements and return a JSON structure:
```javascript
collectMatchingAnswers() {
    const selects = document.querySelectorAll('select[name^="pair_"]');
    const answers = {};
    selects.forEach((select, idx) => {
        answers[`pair_${idx}`] = select.value;
    });
    return JSON.stringify(answers);
}
```

### Monitoring Page Auto-Refresh Incomplete

The monitoring.blade.php file includes a `monitoringState()` Alpine component that calls `refreshNow()` on an interval. The method fetches `cbt.sessions.api.monitoring` and receives a JSON response with `active_students` array. However, the code then has a comment `// Simple update: reload table` and does nothing with the data:

```javascript
refreshNow() {
    const sessionId = {{ $session->id }};
    const url = '{{ route("cbt.sessions.api.monitoring", ["session" => ":session"]) }}'.replace(':session', sessionId);
    
    fetch(url)
        .then(r => r.json())
        .then(data => {
            // Update active students table
            const tbody = document.querySelector('#active-students-table tbody');
            if (data.active_students) {
                // Simple update: reload table
                // In production, use a more sophisticated AJAX update
            }
        })
        .catch(err => console.error('Failed to refresh:', err));
}
```

The table is never updated with new duration or integrity scores. Every refresh fetches data but displays stale information. The user sees no progress unless they manually click "Perbarui Sekarang".

**Fix:** Iterate `data.active_students` and update table rows by `data-result-id`:
```javascript
if (data.active_students) {
    data.active_students.forEach(student => {
        const row = document.querySelector(`tr[data-result-id="${student.id}"]`);
        if (row) {
            // Update elapsed time, integrity score, etc.
            row.querySelector('td:nth-child(3)').textContent = `${Math.floor(student.elapsed / 60)}m ${student.elapsed % 60}s`;
        }
    });
}
```

### Add Question Form Non-Functional

In question-banks/show.blade.php, the inline add-question form has `@submit.prevent="submitQuestion()"`, which calls:

```javascript
submitQuestion() {
    // In production, send AJAX POST to route('cbt.questions.store', $bank)
    // For now, show a simple alert
    alert('Fitur tambah soal inline akan diimplementasi via AJAX');
    this.hideForm();
}
```

The form accepts input and looks complete, but submits to an alert. Teachers cannot add questions via this UI. They must navigate to a separate create page (which doesn't appear to be defined in the routes or controllers shown).

**Fix:** Implement the AJAX POST:
```javascript
submitQuestion() {
    const formData = new FormData();
    formData.append('pertanyaan', this.formData.pertanyaan);
    formData.append('tipe_soal', this.formData.tipe_soal);
    formData.append('poin', this.formData.poin);
    formData.append('tingkat_kesulitan', this.formData.tingkat_kesulitan);
    formData.append('media_url', this.formData.media_url);
    formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
    
    fetch('{{ route("cbt.questions.store", $bank) }}', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload(); // Or update table in-place
        }
    })
    .catch(err => alert('Error: ' + err.message));
}
```

### Question Types Coverage

The take.blade.php template handles four question types:
- **Pilihan Ganda (Multiple choice):** Radio buttons with options rendered from `question.options`. Each option is displayed with a label generated dynamically. ✓ Implemented.
- **Benar/Salah (True/False):** Two hardcoded radio buttons. ✓ Implemented.
- **Essay:** Single textarea. ✓ Implemented.
- **Menjodohkan (Matching):** Selects for pairing (stubbed, as noted above). ⚠ Incomplete.

All four types are handled in the conditional template sections. Answer submission routes each type's data through the same `saveAnswerDebounced` handler, so the backend must deserialize each type accordingly. The controller's `submitAnswer()` method accepts the answer as a string, which works for MC/essay but may need custom parsing for matching answers.

### Result Display Alignment

The result.blade.php template displays:
- Score summary with percentage and pass/fail tag ✓
- Answer breakdown (correct/incorrect/unanswered counts) ✓
- Integrity violations table (if any) ✓
- Question review with student answer, correct answer, and explanation ✓

The template uses `$result->studentAnswers` to iterate answers and display them. It checks `$result->testSession->tampilkan_nilai` to conditionally show review. This matches the controller's scope; assuming the controller loads these relationships and calculates `jumlah_benar`, `jumlah_salah`, `jumlah_tidak_dijawab`, the display should work correctly.

One edge case: the template shows `$answer->is_correct` which is a computed field, not necessarily a database column. If the controller doesn't provide it, the review shows no correctness indicator. The controller should either compute this field or the template should check the answer against the question's correct option.

### Timer Auto-Submit

The timer.js module's `autoSubmit()` creates an overlay and POSTs to `submitUrl` (provided by the caller). The overlay shows a spinner while submitting, then redirects to the result page on success. However, the redirect logic uses string replacement on the current URL:

```javascript
setTimeout(() => {
    window.location.href = window.location.href.replace('/take', '/result');
}, 1000);
```

This assumes the current URL contains `/take` and a `/result` version exists at the same path. For a URL like `/cbt/student/123/take`, this becomes `/cbt/student/123/result`. If the route structure differs or the result route has a different parameter name, this will redirect incorrectly. It's safer to redirect to a named route.

**Better:** The timer should post the result ID or use the route name directly:
```javascript
window.location.href = '{{ route("cbt.student.result", $result) }}';
```

But this requires the route to be passed to the module from the Blade template.

### Integrity Checker Debounce

The integrity checker's `logViolation()` method enforces a 5-second debounce per violation type:

```javascript
if (this.lastViolationTime[jenisPelanggaran] && 
    now - this.lastViolationTime[jenisPelanggaran] < this.violationDebounce) {
  return;
}
```

This prevents spam (e.g., multiple "tab switch" logs in 5 seconds). However, if a student rapidly switches tabs 10 times in 2 seconds, only the first one logs. The backend's integrity_score calculation depends on violation count, so rapid repeated violations may be under-counted. This is acceptable (prevents log spam) but worth noting.

### Security: Answer Exposure Before Submission

The implementation does not expose correct answers in the HTML before exam submission. Question options are rendered with `option.teks` and `option.id`, but there's no `is_correct` flag in the template. The backend's question model should ensure correct-answer data is not included in the initial query unless the exam is complete. This should be verified in the controller's `start()` method—confirm it does not eager-load option correctness flags.

### Monitoring Force-Complete

In monitoring.blade.php, the "Paksa Selesai" button calls `forceComplete(resultId)` which POSTs to `route('cbt.student.submit', result)`. This reuses the normal submit endpoint, which should work if the controller allows it. However, if the endpoint only accepts student submissions (checks `auth()->id() == result->siswa_id`), the teacher's POST will fail with a 403. The controller should allow teachers to force-submit a student's exam, likely via a separate endpoint.

</details>

---

<details>
<summary>File Map</summary>

- **take.blade.php** — Main exam interface with Alpine state, question carousel, timer display, answer input forms (4 types), navigation, and submit confirmation modal. Imports three JS modules. Uses Alpine for state and visibility.
- **result.blade.php** — Post-exam display with score summary, answer breakdown, integrity violations table, and collapsible question review cards. Conditional rendering based on session settings and user role.
- **timer.js** — `CbtTimer` class with countdown logic, warning thresholds (5 min yellow, 1 min red), and auto-submit on expiry. Exports `initTimer()` factory.
- **integrity-checker.js** — `IntegrityChecker` class detecting tab switches, focus loss, copy-paste, and screen capture. Logs violations to backend via POST. Exports `initIntegrityChecker()` factory.
- **auto-save.js** — `AnswerAutoSaver` class with debounced save queue, localStorage fallback for offline answers, exponential backoff retry, and unload warning. Exports `initAutoSave()` factory.
- **monitoring.blade.php** — Teacher live monitoring dashboard showing active and completed students with auto-refresh interval selector, duration display, integrity color coding, and force-complete action.
- **question-banks/show.blade.php** — Bank detail page with inline add-question form (non-functional), questions table with edit/delete actions, and active-status toggle via AJAX.
- **question-banks/edit.blade.php** — Bank metadata form (name, description, difficulty, category, status) with read-only mata pelajaran/semester fields and submit button.

Full diff: `git diff main -- resources/views/cbt/ resources/js/cbt/`

</details>
