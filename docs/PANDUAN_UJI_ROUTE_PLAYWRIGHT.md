# Panduan Audit Route dan Halaman dengan Playwright

Panduan ini menjelaskan cara memeriksa halaman web SIMANTAP di browser: HTTP status, URL akhir setelah redirect, judul dan heading, error JavaScript, serta screenshot. Crawler halaman di sini dibatasi ke request **GET yang diizinkan dan tidak mengubah data**. Aksi simpan/hapus/impor/sinkronisasi harus diuji di suite terpisah dengan assertion database.

## 1. Cakupan tes yang sudah ada

- `tests/Feature/RouteSweepTest.php` menjalankan route GET terhadap akun fixture lintas peran. Tes ini mencari exception/HTTP 5xx, bukan membuktikan bahwa setiap route merender halaman yang secara semantik benar.
- `tests/Feature/Support/RouteExpectationRegistry.php` mencantumkan jalur inti dengan status yang diharapkan.
- `tests/e2e/` memiliki journey browser untuk landing, CBT, Tryout, dan TKA. Itu bukan inventaris seluruh halaman/modul.
- CI menjalankan PHPUnit SQLite, PHPUnit PostgreSQL, Chromium smoke, dan authenticated E2E. Hasil CI hijau adalah bukti untuk skenario fixture yang dijalankan, bukan bukti bahwa semua variasi data produksi sudah diuji.

Untuk audit tujuan route secara menyeluruh, buat manifest halaman yang eksplisit: nama route/URL, peran yang dipakai, status yang diharapkan, URL akhir, title, heading utama, dan apakah route hanya-baca. Jangan menganggap pemeriksaan "tidak 500" sama dengan pemeriksaan bahwa konten halaman benar.

## 2. Persiapan aman

Kebutuhan:

- PHP 8.4 dan Composer 2
- Node.js 22 dan npm
- Chromium untuk Playwright
- Database uji SQLite terisolasi

Jangan menjalankan migrasi/seeder E2E terhadap database staging atau produksi. `migrate:fresh` menghapus tabel database yang dituju. Fixture seeder proyek memeriksa lingkungan `e2e` dan nama database SQLite yang mengandung `e2e`, tetapi operator tetap wajib memastikan `DB_DATABASE` benar sebelum migrasi.

Siapkan dependensi aplikasi dari root repository:

```bash
cp .env.example .env
mkdir -p storage/framework/views \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/logs \
  bootstrap/cache
composer install --no-interaction
php artisan key:generate
npm ci
npm run build
npx playwright install chromium
```

Di Linux, bila browser melaporkan library sistem yang hilang, gunakan `npx playwright install --with-deps chromium` pada mesin/runner yang berwenang memasang paket OS.

## 3. Siapkan database dan password E2E

Gunakan SQLite khusus E2E, bukan database utama aplikasi:

```bash
export APP_ENV=e2e
export APP_DEBUG=false
export DB_CONNECTION=sqlite
export DB_DATABASE="$PWD/database/e2e.sqlite"
export DB_FOREIGN_KEYS=true
export CACHE_STORE=file
export SESSION_DRIVER=file
export QUEUE_CONNECTION=sync
export SIMANTAP_CBT_ENABLED=true
export SIMANTAP_EXAM_ENGINE_CBT=true
export SIMANTAP_EXAM_ENGINE_TRYOUT=true
export E2E_BASE_URL=http://127.0.0.1:8000

# Password hanya berada di environment proses; jangan commit atau hardcode.
export E2E_ADMIN_PASSWORD="$(openssl rand -hex 32)"
export E2E_GURU_A_PASSWORD="$(openssl rand -hex 32)"
export E2E_GURU_B_PASSWORD="$(openssl rand -hex 32)"
export E2E_SISWA_A1_PASSWORD="$(openssl rand -hex 32)"
export E2E_SISWA_A2_PASSWORD="$(openssl rand -hex 32)"
export E2E_SISWA_B1_PASSWORD="$(openssl rand -hex 32)"
export E2E_ORTU_A1_PASSWORD="$(openssl rand -hex 32)"
export E2E_ORTU_B1_PASSWORD="$(openssl rand -hex 32)"

mkdir -p database
test "$(basename "$DB_DATABASE")" = "e2e.sqlite" || {
  echo "Berhenti: DB_DATABASE bukan database/e2e.sqlite" >&2
  exit 1
}
touch "$DB_DATABASE"
php artisan migrate:fresh --force
php artisan db:seed --class='Database\Seeders\E2eFixtureSeeder' --force
```

Akun fixture dan nama environment variable di atas sama dengan `tests/e2e/global-setup.js` serta `.github/workflows/test.yml`. `global-setup.js` membuat `playwright/.auth/*.json`; berkas ini berisi state login dan tidak boleh dicommit atau dibagikan.

## 4. Jalankan tes yang ada

```bash
# Lihat daftar test tanpa menjalankan browser
npx playwright test --list

# Halaman publik/landing saja (tetap perlu PHP dan server Laravel)
npx playwright test --config=playwright.smoke.config.js tests/e2e/landing.spec.js

# Journey autentikasi CBT, Tryout, dan TKA
npm run test:e2e

# Jalankan di terminal BARU tanpa environment APP_ENV/DB_DATABASE E2E di atas.
# PHPUnit memakai konfigurasi DB SQLite terisolasi dari phpunit.xml.
php artisan test --filter=RouteSweepTest
```

`playwright.config.js` otomatis menjalankan `php artisan serve --host=0.0.0.0 --port=8000`. Untuk E2E terautentikasi, jalankan langkah database/password di atas lebih dulu. Jangan arahkan command yang memakai `migrate:fresh` ke host produksi.

## 5. Pola test halaman baca: status, redirect, title, heading, JS, screenshot

Tambahkan route yang memang ingin diaudit ke manifest eksplisit. Jangan menemukan semua link lalu membuka semuanya tanpa allowlist: link bisa mengarah ke route yang belum aman untuk crawler atau ke route yang mengubah data.

Contoh pola test (`tests/e2e/route-audit.spec.js` atau file audit terpisah). Isi `expectedTitle`, `expectedHeading`, akun, status, dan path akhir sesuai halaman yang benar-benar diharapkan:

```js
import { expect, test } from '@playwright/test';
import { resolve } from 'node:path';

const baseURL = process.env.E2E_BASE_URL || 'http://127.0.0.1:8000';
const cases = [
    {
        id: 'landing',
        path: '/',
        account: null,
        expectedStatus: 200,
        expectedPath: '/',
        expectedTitle: /SIMANTAP/i,
        expectedHeading: 'Akses Aplikasi SIMANTAP',
    },
    // Tambahkan satu entri per halaman/peran; jangan menebak heading.
];

for (const routeCase of cases) {
    test(`route ${routeCase.id} menampilkan halaman yang diharapkan`, async ({ browser }, testInfo) => {
        const storageState = routeCase.account
            ? resolve(process.cwd(), 'playwright/.auth', `${routeCase.account}.json`)
            : undefined;
        const context = await browser.newContext({
            baseURL,
            ...(storageState ? { storageState } : {}),
        });

        try {
            const page = await context.newPage();
            const runtimeErrors = [];
            const serverErrors = [];

            page.on('pageerror', error => runtimeErrors.push(`pageerror: ${error.message}`));
            page.on('console', message => {
                if (message.type() === 'error') {
                    runtimeErrors.push(`console: ${message.text()}`);
                }
            });
            page.on('response', response => {
                if (response.status() >= 500) {
                    serverErrors.push(`HTTP ${response.status()} ${response.url()}`);
                }
            });

            // Tunggu DOM, bukan networkidle, agar polling/analytics tidak membuat test menggantung.
            const response = await page.goto(routeCase.path, { waitUntil: 'domcontentloaded' });

            // Simpan screenshot lulus maupun gagal untuk audit visual.
            await page.screenshot({
                path: testInfo.outputPath(`${routeCase.id}.png`),
                fullPage: true,
            });

            expect(response, 'Halaman tidak mengembalikan document response').not.toBeNull();
            expect(response.status()).toBe(routeCase.expectedStatus);
            expect(new URL(page.url()).pathname).toBe(routeCase.expectedPath);
            await expect(page).toHaveTitle(routeCase.expectedTitle);
            await expect(page.getByRole('heading', {
                level: 1,
                name: routeCase.expectedHeading,
                exact: true,
            })).toBeVisible();
            expect(runtimeErrors, runtimeErrors.join('\n')).toEqual([]);
            expect(serverErrors, serverErrors.join('\n')).toEqual([]);
        } finally {
            await context.close();
        }
    });
}
```

Catatan:

- `page.goto()` mengikuti redirect. Untuk halaman yang mengharuskan login, assert **URL akhir** (misalnya `/login`) dan konten halaman login, bukan hanya URL awal yang diminta.
- Untuk route yang memang mengembalikan 403/404, buat case khusus dengan status dan heading halaman error yang diharapkan. Jangan menandai semua 403/404 sebagai sukses.
- Jika aplikasi memang mencatat `console.error` yang diharapkan, dokumentasikan dan filter hanya pesan spesifik tersebut. Jangan mematikan pemeriksaan error secara global.
- Screenshot eksplisit di atas disimpan di direktori output Playwright. Konfigurasi saat ini juga mengambil screenshot/video/trace saat test gagal. Agar screenshot yang lulus tersedia di GitHub Actions, unggah `test-results/` sebagai artifact, idealnya dengan `if: always()`.
- Untuk redirect yang perlu diuji status 302-nya secara langsung, gunakan tes Laravel feature test atau rekam respons redirect; `page.goto()` umumnya mengembalikan respons dokumen terakhir setelah redirect.

Contoh langkah upload artifact di workflow:

```yaml
- name: Simpan screenshot dan hasil route audit
  if: always()
  uses: actions/upload-artifact@v4
  with:
    name: playwright-route-audit
    path: test-results/
    if-no-files-found: ignore
    retention-days: 7
```

## 6. Aksi yang mengubah data: tes terpisah

Crawler route hanya membuka URL GET yang sudah di-allowlist. Jangan submit form, klik tombol simpan/hapus, memicu import/sync, atau memanggil URL mutasi selama audit halaman baca.

Untuk `POST`, `PUT/PATCH`, dan `DELETE`, buat Laravel feature test terpisah dengan database test terisolasi dan `RefreshDatabase`. Uji sukses dengan `assertDatabaseHas`/`assertDatabaseMissing`; untuk penolakan akses, simpan keadaan sebelum request lalu buktikan database tidak berubah:

```php
$before = $record->fresh()->getAttributes();

$this->actingAs($userTanpaHak)
    ->post(route('nama.route.mutasi'), $payload)
    ->assertForbidden();

$this->assertSame($before, $record->fresh()->getAttributes());
$this->assertDatabaseMissing('nama_tabel', $dataYangTidakBolehDibuat);
```

Untuk operasi yang sah, assert response/redirect **dan** nilai baris setelah request. Untuk hapus, assert baris yang tepat hilang. Jangan hanya mengandalkan HTTP status; tes otorisasi negatif juga harus memastikan tidak ada perubahan database. Tes Playwright yang menjalankan alur mutasi—misalnya submit ujian—harus berada di spec E2E terpisah, memakai fixture/database E2E yang dapat dibuang, dan tidak dijalankan crawler umum.

## 7. Checklist sebelum launch

- [ ] Manifest mencakup setiap halaman yang dijanjikan, peran, parameter fixture, dan hasil yang diharapkan.
- [ ] Halaman baca diperiksa dengan status, URL akhir, title, heading, error JavaScript, respons 5xx, dan screenshot.
- [ ] Route yang mengembalikan redirect/403/404 punya hasil yang diharapkan dan tidak dianggap lulus secara membabi buta.
- [ ] Aksi mutasi dites terpisah dengan `RefreshDatabase` dan assertion database, termasuk no-mutation untuk akses yang ditolak.
- [ ] Tes dijalankan di database fixture/staging terisolasi; tidak menggunakan database produksi.
- [ ] Screenshot/log disimpan sebagai artifact CI dan ditinjau, termasuk viewport desktop dan mobile untuk halaman penting.
- [ ] PHPUnit, build aset, Playwright, dan uji backup/restore staging semuanya lulus sebelum keputusan rilis.
