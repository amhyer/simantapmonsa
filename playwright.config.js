import { defineConfig, devices } from '@playwright/test';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

function resolvePhpBinary() {
    if (process.env.E2E_PHP_BIN) {
        return `"${process.env.E2E_PHP_BIN}"`;
    }

    if (process.platform === 'win32') {
        const herdPhp = path.join(os.homedir(), '.config', 'herd', 'bin', 'php.bat');
        if (fs.existsSync(herdPhp)) {
            return `"${herdPhp}"`;
        }
    }

    return 'php';
}

const baseURL = process.env.E2E_BASE_URL || 'http://127.0.0.1:8000';
const parsedUrl = new URL(baseURL);
const serverHost = process.env.E2E_HOST || '0.0.0.0';
const serverPort = process.env.E2E_PORT || parsedUrl.port || '8000';

export default defineConfig({
    testDir: './tests/e2e',
    outputDir: './test-results',
    globalSetup: './tests/e2e/global-setup.js',
    fullyParallel: false,
    workers: 1,
    forbidOnly: Boolean(process.env.CI),
    retries: 0,
    reporter: 'list',
    use: {
        baseURL,
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
        command: `${resolvePhpBinary()} artisan serve --host=${serverHost} --port=${serverPort}`,
        url: baseURL,
        reuseExistingServer: !process.env.CI,
        timeout: 120_000,
    },
});
