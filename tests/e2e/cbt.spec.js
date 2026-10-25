import { expect, test } from '@playwright/test';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';

const authState = account => resolve(process.cwd(), 'playwright/.auth', `${account}.json`);
test.use({ storageState: authState('siswa_a1') });

async function tracedContext(browser, account) {
    const context = await browser.newContext({
        baseURL: process.env.E2E_BASE_URL || 'http://127.0.0.1:8000',
        storageState: authState(account),
    });
    await context.tracing.start({ screenshots: true, snapshots: true, sources: true });
    return context;
}

async function saveTraceAndClose(context, testInfo, name) {
    if (!context) return;
    await context.tracing.stop({ path: testInfo.outputPath(`${name}.zip`) });
    await context.close();
}

test('E2E-CBT-01..09: mulai, autosave/reload, anti-cheat, submit-lock, ownership, dan CSV', async ({ browser, page }, testInfo) => {
    test.setTimeout(120_000);

    let studentBContext;
    let adminContext;
    try {
        await page.route(/^https:\/\/(?:cdnjs\.cloudflare\.com|cdn\.jsdelivr\.net|unpkg\.com)\//, route => route.abort());

        // E2E-CBT-01: instruksi dan mulai membuat satu percobaan server-side.
        const instructions = await page.goto('/cbt/student/9311/start', { waitUntil: 'domcontentloaded' });
        expect(instructions?.status()).toBe(200);
        await expect(page.getByRole('heading', { name: 'Petunjuk Ujian CBT' })).toBeVisible();
        await expect(page.getByText('5 soal pilihan ganda, satu percobaan.')).toBeVisible();
        await page.getByRole('button', { name: 'Mulai ujian sekarang' }).click();
        await expect(page.locator('#exam-form')).toBeVisible();

        const submitAction = await page.locator('#exam-form').getAttribute('action');
        const resultId = Number(submitAction?.match(/\/cbt\/student\/(\d+)\/submit$/)?.[1]);
        expect(resultId).toBeGreaterThan(0);

        // E2E-CBT-02: jawaban pertama menunggu respons autosave sukses.
        const firstAnswer = page.locator('input[data-question="9302"][value="A"]');
        const firstSave = page.waitForResponse(response =>
            response.url().includes(`/cbt/student/${resultId}/answer`)
            && response.request().method() === 'POST'
        );
        await firstAnswer.check();
        expect((await firstSave).status()).toBe(200);
        await expect(page.getByRole('status')).toContainText('tersimpan');

        // E2E-CBT-03: refresh harus mempertahankan pilihan yang tampak tercentang.
        await page.reload({ waitUntil: 'domcontentloaded' });
        await expect(page.locator('input[data-question="9302"][value="A"]')).toBeChecked();

        // E2E-CBT-04: simulasi tab tersembunyi mengirim telemetri integritas.
        const integrityRequest = page.waitForResponse(response =>
            response.url().includes(`/cbt/student/${resultId}/integrity`)
            && response.request().method() === 'POST'
        );
        await page.evaluate(() => {
            Object.defineProperty(document, 'hidden', { configurable: true, get: () => true });
            document.dispatchEvent(new Event('visibilitychange'));
        });
        expect((await integrityRequest).status()).toBe(200);
        await page.evaluate(() => {
            Object.defineProperty(document, 'hidden', { configurable: true, get: () => false });
        });

        // Isi empat jawaban tersisa melalui kontrol browser dan tunggu tiap autosave.
        const answerChoices = page.locator('input[data-question][value="A"]');
        const choiceCount = await answerChoices.count();
        expect(choiceCount).toBe(5);
        for (let index = 0; index < choiceCount; index++) {
            const choice = answerChoices.nth(index);
            if (await choice.isChecked()) continue;
            const saved = page.waitForResponse(response =>
                response.url().includes(`/cbt/student/${resultId}/answer`)
                && response.request().method() === 'POST'
            );
            await choice.check();
            expect((await saved).status()).toBe(200);
        }
        await expect(page.getByRole('status')).toContainText('Semua perubahan jawaban tersimpan di server.');

        // E2E-CBT-05/06: submit mengunci percobaan dan menampilkan nilai hasil server.
        page.once('dialog', dialog => dialog.accept());
        await page.getByRole('button', { name: 'Kumpulkan jawaban' }).click();
        await expect(page).toHaveURL(new RegExp(`/cbt/student/${resultId}/result$`));
        await expect(page.getByText('Nilai 100.00')).toBeVisible();
        await expect(page.getByText('Benar 5 dari 5 soal.')).toBeVisible();

        // E2E-CBT-06/07: jawaban terkunci setelah submit; mulai ganda kembali
        // ke hasil yang sama, bukan membuat percobaan kedua.
        const ownerCsrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
        const postSubmitStatuses = await page.evaluate(async ({ id, csrfToken }) => {
            const answerResponse = await fetch(`/cbt/student/${id}/answer`, {
                method: 'POST', credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ question_id: 9302, answer: 'B' }),
            });
            const beginResponse = await fetch('/cbt/student/9311/start', {
                method: 'POST', credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'text/html',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: `_token=${encodeURIComponent(csrfToken)}`,
            });
            return {
                answerStatus: answerResponse.status,
                beginStatus: beginResponse.status,
                finalPath: new URL(beginResponse.url).pathname,
            };
        }, { id: resultId, csrfToken: ownerCsrf });
        expect(postSubmitStatuses.answerStatus).toBe(409);
        expect(postSubmitStatuses.beginStatus).toBe(200);
        expect(postSubmitStatuses.finalPath).toBe(`/cbt/student/${resultId}/result`);

        // E2E-CBT-08: akun siswa lain ditolak untuk GET, answer, submit, dan anti-cheat.
        studentBContext = await tracedContext(browser, 'siswa_a2');
        const studentBPage = await studentBContext.newPage();
        const deniedResult = await studentBPage.goto(`/cbt/student/${resultId}/result`, { waitUntil: 'domcontentloaded' });
        expect(deniedResult?.status()).toBe(403);
        await studentBPage.goto('/cbt/student/', { waitUntil: 'domcontentloaded' });
        const csrf = await studentBPage.locator('meta[name="csrf-token"]').getAttribute('content');
        const deniedStatuses = await studentBPage.evaluate(async ({ id, csrfToken }) => {
            const headers = {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            };
            const requests = [
                fetch(`/cbt/student/${id}/answer`, {
                    method: 'POST', credentials: 'same-origin', headers,
                    body: JSON.stringify({ question_id: 9302, answer: 'A' }),
                }),
                fetch(`/cbt/student/${id}/submit`, {
                    method: 'POST', credentials: 'same-origin', headers,
                    body: JSON.stringify({ answers: { 9302: 'A' } }),
                }),
                fetch(`/cbt/student/${id}/integrity`, {
                    method: 'POST', credentials: 'same-origin', headers,
                    body: JSON.stringify({ type: 'tab_hidden' }),
                }),
            ];
            return Promise.all(requests.map(async response => (await response).status));
        }, { id: resultId, csrfToken: csrf });
        expect(deniedStatuses).toEqual([403, 403, 403]);

        // E2E-CBT-09: admin UI menampilkan dan mengunduh satu baris CSV hasil.
        adminContext = await tracedContext(browser, 'admin');
        const adminPage = await adminContext.newPage();
        const adminResponse = await adminPage.goto('/admin/cbt/sessions/9311', { waitUntil: 'domcontentloaded' });
        expect(adminResponse?.status()).toBe(200);
        const exportLink = adminPage.getByRole('link', { name: /Ekspor CSV/ });
        await expect(exportLink).toBeVisible();

        const downloadPromise = adminPage.waitForEvent('download');
        const exportResponsePromise = adminPage.waitForResponse(response =>
            response.url().includes('/admin/cbt/sessions/9311/export/csv')
        );
        await exportLink.click();
        const [download, exportResponse] = await Promise.all([downloadPromise, exportResponsePromise]);
        expect(exportResponse.status()).toBe(200);
        expect(exportResponse.headers()['content-type']).toContain('text/csv');
        expect(download.suggestedFilename()).toBe('cbt-sesi-9311-hasil.csv');
        const csv = await readFile(await download.path(), 'utf8');
        const rows = csv.trim().split(/\r?\n/);
        expect(rows).toHaveLength(2);
        expect(rows[0]).toContain('Peserta');
        expect(rows[1]).toContain('Siswa E2E A1');
        expect(rows[1]).toContain('100');
    } finally {
        await saveTraceAndClose(studentBContext, testInfo, 'cbt-siswa-a2');
        await saveTraceAndClose(adminContext, testInfo, 'cbt-admin-export');
    }
});
