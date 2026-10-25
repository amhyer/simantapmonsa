import { expect, test } from '@playwright/test';
import { resolve } from 'node:path';

const authState = account => resolve(process.cwd(), 'playwright/.auth', `${account}.json`);
test.use({ storageState: authState('guru_a') });

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

test('E2E-TKA-01..06: ownership, weighted scoring/readiness, nested-item protection, dan laporan orang tua', async ({ browser, page }) => {
    test.setTimeout(120_000);

    let parentAContext;
    let parentBContext;
    try {
        // E2E-TKA-01/02: daftar guru terisolasi dan penilaian memakai bobot item.
        const indexResponse = await page.goto('/tka/assessments', { waitUntil: 'domcontentloaded' });
        expect(indexResponse?.status()).toBe(200);
        await expect(page.getByText('Siswa E2E A1')).toBeVisible();
        await expect(page.getByText('Siswa E2E B1')).toHaveCount(0);

        const createResponse = await page.goto('/tka/assessments/create', { waitUntil: 'domcontentloaded' });
        expect(createResponse?.status()).toBe(200);
        await page.locator('select[name="siswa_id"]').selectOption('9101');
        await page.locator('select[name="semester_id"]').selectOption('9542');

        // Bobot fixture: ringan=1, berat=3, indikator kedua=1.
        await page.locator('select[name="responses[9552][skor]"]').selectOption('1');
        await page.locator('select[name="responses[9553][skor]"]').selectOption('4');
        await page.locator('select[name="responses[9555][skor]"]').selectOption('4');
        await page.getByRole('button', { name: /Simpan Penilaian/ }).click();

        await expect(page).toHaveURL(/\/tka\/assessments\/\d+$/);
        await expect(page.getByText('Siswa E2E A1').first()).toBeVisible();
        await expect(page.getByText('Siap', { exact: true })).toBeVisible();
        await expect(page.getByText('85.0%', { exact: true })).toBeVisible();

        // E2E-TKA-03: guru tidak dapat melihat assessment siswa milik guru lain.
        const otherTeacherAssessment = await page.goto('/tka/assessments/9502', { waitUntil: 'domcontentloaded' });
        expect(otherTeacherAssessment?.status()).toBe(403);

        // Kembali ke halaman berizin untuk mengambil token CSRF sesi guru.
        await page.goto('/tka/assessments/create', { waitUntil: 'domcontentloaded' });

        // E2E-TKA-05: item milik dimensi lain tidak dapat dihapus lewat URL nested palsu.
        const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
        const nestedResult = await page.evaluate(async csrfToken => {
            const deleteResponse = await fetch('/tka/dimensions/9551/item/9555', {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            });
            const otherDimensionResponse = await fetch('/tka/dimensions/9554/api/items', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            });
            return {
                deleteStatus: deleteResponse.status,
                otherDimensionStatus: otherDimensionResponse.status,
                otherDimensionItems: await otherDimensionResponse.json(),
            };
        }, csrf);
        expect(nestedResult.deleteStatus).toBe(404);
        expect(nestedResult.otherDimensionStatus).toBe(200);
        expect(nestedResult.otherDimensionItems.map(item => item.id)).toContain(9555);

        // E2E-TKA-04/06: report only opens for the account linked to the child.
        parentAContext = await tracedContext(browser, 'ortu_a1');
        const parentAPage = await parentAContext.newPage();
        const parentAResponse = await parentAPage.goto('/tka/parent/9561', { waitUntil: 'domcontentloaded' });
        expect(parentAResponse?.status()).toBe(200);
        await expect(parentAPage.getByText('Siswa E2E A1')).toBeVisible();

        parentBContext = await tracedContext(browser, 'ortu_b1');
        const parentBPage = await parentBContext.newPage();
        const parentBResponse = await parentBPage.goto('/tka/parent/9561', { waitUntil: 'domcontentloaded' });
        expect(parentBResponse?.status()).toBe(403);

        const teacherParentResponse = await page.goto('/tka/parent/9561', { waitUntil: 'domcontentloaded' });
        expect(teacherParentResponse?.status()).toBe(403);
    } finally {
        await saveTraceAndClose(parentAContext);
        await saveTraceAndClose(parentBContext);
    }
});
