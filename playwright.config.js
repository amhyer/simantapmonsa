import { defineConfig, devices } from '@playwright/test';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

function resolvePhpBinary() {
    if (process.env.E2E_PHP_BIN) {
        const candidate = process.env.E2E_PHP_BIN.trim();
        if (/[;&|`$<>!\r\n"]/.test(candidate) || !fs.existsSync(candidate)) {
            throw new Error(`E2E_PHP_BIN tidak valid atau berkas tidak ditemukan: ${candidate}`);
        }
        return `"${candidate}"`;
    }

    if (process.platform === 'win32') {
        const herdCandidates = [
            path.join(os.homedir(), '.config', 'herd', 'bin', 'php84', 'php.exe'),
            path.join(os.homedir(), '.config', 'herd', 'bin', 'php.bat'),
        ];
        for (const candidate of herdCandidates) {
            if (fs.existsSync(candidate)) {
                return `"${candidate}"`;
            }
        }
    }

    return 'php';
}

function resolveHost(rawHost) {
    const host = (rawHost || '0.0.0.0').trim();
    if (!/^[a-zA-Z0-9.-]+$/.test(host)) {
        throw new Error(`E2E_HOST tidak valid: ${host}`);
    }
    return host;
}

function resolvePort(rawPort) {
    const port = String(rawPort || '8000').trim();
    if (!/^\d{1,5}$/.test(port) || Number(port) < 1 || Number(port) > 65535) {
        throw new Error(`E2E_PORT tidak valid: ${port}`);
    }
    return port;
}

const serverHost = resolveHost(process.env.E2E_HOST);
const parsedEnvUrl = process.env.E2E_BASE_URL ? new URL(process.env.E2E_BASE_URL) : null;
const serverPort = resolvePort(process.env.E2E_PORT || parsedEnvUrl?.port || '8000');
const baseURL = process.env.E2E_BASE_URL || `http://127.0.0.1:${serverPort}`;

// Bagian "Akses/Demo" dan newsletter di landing hanya dirender bila flag ini
// aktif (default produksi: mati, lihat config/simantap.php & LandingPageFlagsTest).
// Spec landing menguji varian "CTA jujur", jadi server E2E dijalankan dengan
// flag aktif — tanpa ini link "Akses"/"#demo" tidak pernah ada di DOM.
const landingEnv = {
    SIMANTAP_LANDING_DEMO_VIDEO: 'true',
    SIMANTAP_LANDING_NEWSLETTER: 'true',
};

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
        env: landingEnv,
    },
});
