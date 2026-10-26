import { expect, test } from '@playwright/test';
import { resolve } from 'node:path';

const authState = account => resolve(process.cwd(), 'playwright/.auth', `${account}.json`);
test.use({ storageState: authState('siswa_a1') });

async function tracedContext(browser, account) {
    const context = await browser.newContext({
        baseURL: process.env.E2E_BASE_URL || 'http://127.0.0.1:8000',
        storageState: authState(account),
    });
    return context;
}

async function saveTraceAndClose(context) {
    if (!context) return;
    // Playwright Test owns tracing for contexts created during the test; do not
    // start/stop a second trace, which raises "Tracing has already been started".
    await context.close();
}

test('E2E-TRY-01..04: mulai, autosave/reload UI, hasil server nonzero, dan ownership', async ({ browser, page }) => {
    test.setTimeout(90_000);

    let otherStudentContext;
    let unrelatedParentContext;
    try {
        const startResponse = await page.goto('/tryout/student/9407/start', { waitUntil: 'domcontentloaded' });
        expect(startResponse?.status()).toBe(200);
        const answerControl = page.locator('input[name="q_9404"][value="A"]');
        await expect(answerControl).toBeVisible();

        // E2E-TRY-01/02: simpan jawaban dan tunggu respons server sebelum reload.
        const answerResponsePromise = page.waitForResponse(response =>
            response.url().includes('/tryout/student/9411/answer')
            && response.request().method() === 'POST'
        );
        await answerControl.check();
        const answerResponse = await answerResponsePromise;
        expect(answerResponse.status()).toBe(200);
        expect(await answerResponse.json()).toEqual({ success: true });
        await expect(page.locator('#progress-bar')).toHaveText('100.0%');

        // E2E-TRY-02: server-rendered response must be visibly selected after reload,
        // and the progress indicator must be restored from that same saved response.
        await page.reload({ waitUntil: 'domcontentloaded' });
        await expect(page.locator('input[name="q_9404"][value="A"]')).toBeChecked();
        await expect(page.locator('#progress-bar')).toHaveText('100.0%');

        // E2E-TRY-03: complete the real attempt; scoring is performed by the server.
        page.once('dialog', dialog => dialog.accept());
        await page.getByRole('button', { name: /Selesaikan Tryout/ }).click();
        await expect(page).toHaveURL(/\/tryout\/student\/9411\/result$/);
        await expect(page.getByRole('heading', { name: 'Hasil Tryout' })).toBeVisible();
        await expect(page.locator('.display-4')).toHaveText('100.0');
        await expect(page.getByText(/LULUS/)).toBeVisible();
        await expect(page.getByText('Paket Tryout E2E')).toBeVisible();
        const ownerCsrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
        const lockedAnswerStatus = await page.evaluate(async csrfToken => {
            const response = await fetch('/tryout/student/9411/answer', {
                method: 'POST', credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ subject_id: 9402, question_id: 9404, jawaban: 'B' }),
            });
            return response.status;
        }, ownerCsrf);
        expect(lockedAnswerStatus).toBe(409);

        // E2E-TRY-04: a different student and unrelated parent cannot open or mutate it.
        otherStudentContext = await tracedContext(browser, 'siswa_a2');
        const otherStudentPage = await otherStudentContext.newPage();
        const deniedResult = await otherStudentPage.goto('/tryout/student/9411/result', { waitUntil: 'domcontentloaded' });
        expect(deniedResult?.status()).toBe(403);
        await otherStudentPage.goto('/tryout/student/', { waitUntil: 'domcontentloaded' });
        const csrf = await otherStudentPage.locator('meta[name="csrf-token"]').getAttribute('content');
        const deniedStatuses = await otherStudentPage.evaluate(async csrfToken => {
            const headers = {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            };
            const requests = [
                fetch('/tryout/student/9411/answer', {
                    method: 'POST', credentials: 'same-origin', headers,
                    body: JSON.stringify({ subject_id: 9402, question_id: 9404, jawaban: 'A' }),
                }),
                fetch('/tryout/student/9411/complete', {
                    method: 'POST', credentials: 'same-origin', headers,
                    body: JSON.stringify({}),
                }),
            ];
            return Promise.all(requests.map(async response => (await response).status));
        }, csrf);
        expect(deniedStatuses).toEqual([403, 403]);

        unrelatedParentContext = await tracedContext(browser, 'ortu_b1');
        const unrelatedParentPage = await unrelatedParentContext.newPage();
        const parentResponse = await unrelatedParentPage.goto('/tryout/student/9411/result', { waitUntil: 'domcontentloaded' });
        expect(parentResponse?.status()).toBe(403);
    } finally {
        await saveTraceAndClose(otherStudentContext);
        await saveTraceAndClose(unrelatedParentContext);
    }
});
