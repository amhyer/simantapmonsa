import { expect, test } from '@playwright/test';

/**
 * Test area dalam aplikasi (butuh login).
 *
 * CI menyiapkan `database/e2e.sqlite` + `E2eSeeder` sebelum menjalankan test ini,
 * jadi ada akun `e2e_admin` yang bisa langsung masuk (tanpa `force_password_change`).
 */

const KREDENSIAL = { nama_pengguna: 'e2e_admin', kata_sandi: 'e2e-sandi-aman' };

async function masukSebagaiAdmin(page) {
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await page.fill('#nama_pengguna', KREDENSIAL.nama_pengguna);
    await page.fill('#kata_sandi', KREDENSIAL.kata_sandi);
    await page.getByRole('button', { name: 'Masuk' }).click();
    await page.waitForURL(/\/admin\/dashboard$/, { timeout: 20_000 });
}

test('font Figtree benar-benar dimuat di area dalam aplikasi', async ({ page }) => {
    const eksternal = [];
    page.on('request', (r) => {
        const u = new URL(r.url());
        if (u.protocol === 'http:' || u.protocol === 'https:') {
            eksternal.push(r.url());
        }
    });

    await masukSebagaiAdmin(page);
    const origin = new URL(page.url()).origin;

    // Tidak ada aset dari luar aplikasi.
    const lintasAsal = eksternal.filter((u) => !u.startsWith(origin));
    expect(lintasAsal, `ada permintaan keluar: ${lintasAsal.join(', ')}`).toEqual([]);

    // Ini inti perbaikannya: tailwind.config.js mendeklarasikan
    // fontFamily.sans = ['Figtree', ...], tetapi sebelum perbaikan tidak ada satu
    // pun halaman dalam aplikasi yang memuat Figtree — jadi seluruh area dalam
    // jatuh ke font sistem tanpa ada yang menyadarinya.
    const figtree = await page.evaluate(async () => {
        await document.fonts.load("400 16px 'Figtree'");
        await document.fonts.ready;

        const milik = Array.from(document.fonts)
            .filter((f) => f.family.replace(/['"]/g, '') === 'Figtree');

        return {
            jumlah: milik.length,
            adaLoaded: milik.some((f) => f.status === 'loaded'),
            fontFamilyBody: getComputedStyle(document.body).fontFamily,
        };
    });

    expect(figtree.jumlah, '@font-face Figtree tidak ada — fontsource tidak terbundel').toBeGreaterThan(0);
    expect(figtree.adaLoaded, 'Figtree terdaftar tetapi tidak pernah termuat').toBe(true);
    expect(figtree.fontFamilyBody).toMatch(/Figtree/);
});

/**
 * Membuktikan impor malas `html5-qrcode` benar-benar bekerja di peramban.
 *
 * Sebelumnya pustaka itu diimpor statis sehingga ~369 kB ikut terbundel ke app.js
 * dan diunduh di SETIAP halaman. Setelah diperbaiki, chunk-nya hanya diminta saat
 * operator menekan "Aktifkan kamera".
 *
 * Yang diukur: daftar aset JS sebelum klik vs sesudah klik. Kalau impor malas
 * rusak (misalnya kembali jadi impor statis, atau handler gagal sebelum
 * `await import()`), tidak akan ada aset JS baru yang diminta.
 */
test('memuat chunk QR secara malas hanya saat tombol Aktifkan kamera ditekan', async ({ page, context }) => {
    // Chromium perlu izin kamera agar navigator.mediaDevices.getUserMedia ada;
    // tanpa itu handler berhenti di pemeriksaan awal dan chunk tidak pernah diminta.
    await context.grantPermissions(['camera']);

    // Jaring pengaman: pastikan kedua prasyarat handler terpenuhi di konteks CI.
    await context.addInitScript(() => {
        Object.defineProperty(window, 'isSecureContext', { value: true, configurable: true });

        if (!navigator.mediaDevices) {
            Object.defineProperty(navigator, 'mediaDevices', { value: {}, configurable: true });
        }
        if (typeof navigator.mediaDevices.getUserMedia !== 'function') {
            navigator.mediaDevices.getUserMedia = async () => {
                throw new Error('kamera CI palsu — cukup untuk memicu pemuatan chunk');
            };
        }
    });

    const asetJs = new Set();
    page.on('request', (r) => {
        if (/\/assets\/.*\.js(\?|$)/.test(r.url())) {
            asetJs.add(r.url());
        }
    });

    await masukSebagaiAdmin(page);
    const sebelumKlik = new Set(asetJs);
    expect(sebelumKlik.size, 'tidak ada aset JS yang termuat sama sekali').toBeGreaterThan(0);

    await page.goto('/admin/presensi/kartu-siswa', { waitUntil: 'domcontentloaded' });
    const setelahMuat = new Set(asetJs);

    // Pustaka QR harus BELUM dimuat saat halaman dibuka — itu inti penghematannya.
    // Yang diperiksa spesifik chunk `esm-*` (html5-qrcode), bukan semua aset JS,
    // agar test tidak rapuh bila halaman ini nanti menambah aset lain.
    const tombolMulai = page.locator('[data-qr-start]').first();
    await expect(tombolMulai).toBeVisible({ timeout: 15_000 });
    const chunkQrSebelumKlik = [...setelahMuat].filter((u) => /\/assets\/esm-[^/]*\.js/.test(u));
    expect(
        chunkQrSebelumKlik,
        'chunk html5-qrcode sudah dimuat sebelum tombol ditekan — impor malas tidak bekerja',
    ).toEqual([]);

    await tombolMulai.click();

    // Tunggu chunk baru diminta.
    await expect
        .poll(() => [...asetJs].filter((u) => !setelahMuat.has(u)).length, {
            message: 'tidak ada chunk JS baru yang diminta setelah tombol Aktifkan kamera ditekan',
            timeout: 15_000,
        })
        .toBeGreaterThan(0);

    const chunkBaru = [...asetJs].filter((u) => !setelahMuat.has(u));
    // html5-qrcode dibundel Vite sebagai chunk terpisah bernama esm-*.js.
    expect(
        chunkBaru.some((u) => /\/assets\/esm-[^/]*\.js/.test(u)),
        `chunk yang dimuat bukan html5-qrcode: ${chunkBaru.join(', ')}`,
    ).toBe(true);
});

/**
 * Halaman gerbang auth adalah halaman mandiri yang sengaja TIDAK memuat webfont
 * apa pun (keputusan P2). Test ini mengunci keputusan itu: nol permintaan keluar,
 * dan tumpukan font-family tetap diawali 'Inter' sebagai progressive enhancement.
 */
test('halaman login dan register tidak memanggil font eksternal', async ({ page }) => {
    for (const jalur of ['/login', '/register']) {
        const eksternal = [];
        page.on('request', (r) => {
            const u = new URL(r.url());
            if (u.protocol === 'http:' || u.protocol === 'https:') {
                eksternal.push(r.url());
            }
        });

        await page.goto(jalur, { waitUntil: 'load' });
        const origin = new URL(page.url()).origin;

        const lintasAsal = eksternal.filter((u) => !u.startsWith(origin));
        expect(lintasAsal, `${jalur} masih memanggil aset eksternal: ${lintasAsal.join(', ')}`).toEqual([]);

        const keluarga = await page.evaluate(() => getComputedStyle(document.body).fontFamily);
        expect(keluarga, `${jalur}: tumpukan font-family kehilangan 'Inter'`).toMatch(/Inter/);
    }
});
