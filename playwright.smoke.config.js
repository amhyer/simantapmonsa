import { defineConfig, devices } from '@playwright/test';

// Fast, unauthenticated Chromium smoke only. The complete authenticated CBT /
// Tryout / TKA suite uses playwright.config.js and the isolated E2E database.
export default defineConfig({
    testDir: './tests/e2e',
    outputDir: './test-results',
    fullyParallel: false,
    workers: 1,
    forbidOnly: Boolean(process.env.CI),
    retries: 0,
    reporter: 'list',
    use: {
        baseURL: process.env.E2E_BASE_URL || 'http://127.0.0.1:8000',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        trace: 'retain-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
    webServer: {
        command: 'php artisan serve --host=0.0.0.0 --port=8000',
        url: 'http://127.0.0.1:8000',
        reuseExistingServer: !process.env.CI,
        timeout: 120_000,
    },
});
