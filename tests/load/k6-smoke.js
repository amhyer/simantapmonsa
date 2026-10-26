import http from 'k6/http';
import { check, sleep } from 'k6';
import { parseHTML } from 'k6/html';

/**
 * Smoke/load test dasar SIMANTAP.
 *
 * Publik (selalu aktif):
 *   k6 run tests/load/k6-smoke.js
 *
 * Dengan halaman terautentikasi:
 *   BASE_URL=https://staging.sekolah.sch.id \
 *   LOAD_TEST_USER=loadtest.guru01 \
 *   LOAD_TEST_PASSWORD='...' \
 *   k6 run tests/load/k6-smoke.js
 *
 * Login dibatasi 10 percobaan/menit per IP. Jangan menaikkan AUTH_VUS di atas
 * batas itu tanpa menyesuaikan throttle staging. Untuk uji konkurensi tinggi,
 * gunakan sesi yang sudah ada atau naikkan batas throttle secara sadar di
 * staging; jangan mengubah throttle produksi hanya untuk tes.
 */

const BASE_URL = (__ENV.BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
const LOGIN_USER = __ENV.LOAD_TEST_USER || '';
const LOGIN_PASS = __ENV.LOAD_TEST_PASSWORD || '';
const AUTH_PATH = __ENV.LOAD_TEST_PATH || '/dashboard';
const PUBLIC_VUS = Number(__ENV.PUBLIC_VUS || 10);
const AUTH_VUS = Number(__ENV.AUTH_VUS || 3);
const DURATION = __ENV.DURATION || '30s';

let loggedIn = false;

const scenarios = {
    public: {
        executor: 'constant-vus',
        vus: PUBLIC_VUS,
        duration: DURATION,
        exec: 'publicPage',
        tags: { area: 'public' },
    },
};

if (LOGIN_USER && LOGIN_PASS) {
    scenarios.authenticated = {
        executor: 'constant-vus',
        vus: AUTH_VUS,
        duration: DURATION,
        startTime: '5s',
        exec: 'authenticatedPage',
        tags: { area: 'authenticated' },
    };
}

export const options = {
    scenarios,
    thresholds: {
        http_req_failed: ['rate<0.02'],
        http_req_duration: ['p(95)<2000', 'p(99)<4000'],
        checks: ['rate>0.98'],
    },
};

export function publicPage() {
    const home = http.get(`${BASE_URL}/`, { tags: { name: 'landing' } });
    check(home, {
        'landing 200': (r) => r.status === 200,
        'landing punya SIMANTAP': (r) => (r.body || '').includes('SIMANTAP'),
    });

    const login = http.get(`${BASE_URL}/login`, { tags: { name: 'login-form' } });
    check(login, {
        'login form 200': (r) => r.status === 200,
        'login form punya csrf': (r) => (r.body || '').includes('_token'),
    });

    sleep(0.5);
}

export function authenticatedPage() {
    if (!loggedIn) {
        loggedIn = login();
    }

    if (!loggedIn) {
        sleep(1);
        return;
    }

    const page = http.get(`${BASE_URL}${AUTH_PATH}`, { tags: { name: 'authenticated-page' } });
    check(page, {
        'halaman terautentikasi 200': (r) => r.status === 200,
        'tidak kembali ke login': (r) => !r.url.includes('/login'),
    });

    sleep(1);
}

function login() {
    const form = http.get(`${BASE_URL}/login`, { tags: { name: 'login-form-auth' } });

    if (form.status !== 200) {
        return false;
    }

    const token = parseHTML(form.body).find('input[name=_token]').first().attr('value');

    if (!token) {
        return false;
    }

    const response = http.post(
        `${BASE_URL}/login`,
        {
            _token: token,
            nama_pengguna: LOGIN_USER,
            kata_sandi: LOGIN_PASS,
        },
        { tags: { name: 'login-post' }, redirects: 5 }
    );

    return check(response, {
        'login berhasil': (r) => r.status === 200 && !r.url.includes('/login'),
    });
}
