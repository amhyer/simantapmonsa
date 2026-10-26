import { chromium } from '@playwright/test';
import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';

const accounts = [
    ['admin', 'e2e.admin', 'E2E_ADMIN_PASSWORD'],
    ['guru_a', 'e2e.guru.a', 'E2E_GURU_A_PASSWORD'],
    ['guru_b', 'e2e.guru.b', 'E2E_GURU_B_PASSWORD'],
    ['siswa_a1', 'e2e.siswa.a1', 'E2E_SISWA_A1_PASSWORD'],
    ['siswa_a2', 'e2e.siswa.a2', 'E2E_SISWA_A2_PASSWORD'],
    ['siswa_b1', 'e2e.siswa.b1', 'E2E_SISWA_B1_PASSWORD'],
    ['ortu_a1', 'e2e.ortu.a1', 'E2E_ORTU_A1_PASSWORD'],
    ['ortu_b1', 'e2e.ortu.b1', 'E2E_ORTU_B1_PASSWORD'],
];

export default async function globalSetup(config) {
    const baseURL = process.env.E2E_BASE_URL
        || config.projects[0]?.use?.baseURL
        || 'http://127.0.0.1:8000';
    const authDirectory = resolve(process.cwd(), 'playwright/.auth');
    await mkdir(authDirectory, { recursive: true });

    const browser = await chromium.launch();
    try {
        for (const [key, username, passwordVariable] of accounts) {
            const password = process.env[passwordVariable];
            if (!password) {
                throw new Error(`Missing required environment variable ${passwordVariable}.`);
            }

            const context = await browser.newContext({ baseURL });
            const page = await context.newPage();
            const loginResponse = await page.goto('/login', { waitUntil: 'domcontentloaded' });
            if (!loginResponse || loginResponse.status() >= 400) {
                throw new Error(`Login page unavailable for E2E account ${key}.`);
            }

            await page.locator('input[name="nama_pengguna"]').fill(username);
            await page.locator('input[name="kata_sandi"]').fill(password);
            const navigation = page.waitForURL(url => url.pathname !== '/login', { timeout: 15_000 });
            await page.getByRole('button', { name: 'Masuk', exact: true }).click();

            try {
                await navigation;
            } catch {
                const errorText = await page.locator('.error').textContent().catch(() => null);
                throw new Error(`E2E login failed for ${key}${errorText ? `: ${errorText}` : '.'}`);
            }

            await context.storageState({ path: resolve(authDirectory, `${key}.json`) });
            await context.close();
        }
    } finally {
        await browser.close();
    }
}
