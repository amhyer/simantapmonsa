import { defineConfig, devices } from '@playwright/test';

// Bagian "Akses/Demo" dan newsletter di landing hanya dirender bila flag ini
// aktif (default produksi: mati, lihat config/simantap.php & LandingPageFlagsTest).
// Spec landing menguji varian "CTA jujur", jadi server E2E dijalankan dengan
// flag aktif — tanpa ini link "Akses"/"#demo" tidak pernah ada di DOM.
const landingEnv = {
    SIMANTAP_LANDING_DEMO_VIDEO: 'true',
    SIMANTAP_LANDING_NEWSLETTER: 'true',
};

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
        env: landingEnv,
    },
});
