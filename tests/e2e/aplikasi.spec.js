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

/**
 * Mengunci keputusan font di area dalam aplikasi.
 *
 * Temuan dari test ini (CI, 26 Okt 2026): `body` dirender dengan tumpukan
 * `'Inter', -apple-system, BlinkMacSystemFont, sans-serif` dari layout.css:30,
 * BUKAN Figtree yang dideklarasikan tailwind.config.js. Figtree adalah font
 * bawaan scaffolding Breeze yang tidak dipakai satu view hidup pun, tetapi
 * preflight Tailwind menerapkannya ke <html> sehingga peramban benar-benar
 * mengunduh ~80 kB berkas font di setiap halaman tanpa mengubah tampilan.
 * Sudah dibuang; test ini memastikan ia tidak kembali.
 */
test('area dalam aplikasi tidak mengunduh font yang tidak dirender', async ({ page }) => {
    const eksternal = [];
    const berkasFont = [];
    page.on('request', (r) => {
        const u = new URL(r.url());
        if (u.protocol === 'http:' || u.protocol === 'https:') {
            eksternal.push(r.url());
        }
        if (/\.(woff2?|ttf|otf)(\?|$)/.test(r.url())) {
            berkasFont.push(r.url());
        }
    });

    await masukSebagaiAdmin(page);
    const origin = new URL(page.url()).origin;

    // Tidak ada aset dari luar aplikasi.
    const lintasAsal = eksternal.filter((u) => !u.startsWith(origin));
    expect(lintasAsal, `ada permintaan keluar: ${lintasAsal.join(', ')}`).toEqual([]);

    const font = await page.evaluate(() => ({
        fontFamilyBody: getComputedStyle(document.body).fontFamily,
        adaFigtree: Array.from(document.fonts)
            .some((f) => f.family.replace(/['"]/g, '') === 'Figtree'),
    }));

    // Yang benar-benar dirender: tumpukan Inter dari layout.css.
    expect(font.fontFamilyBody).toMatch(/^"?Inter"?/);

    // Figtree tidak boleh kembali — ia diunduh tanpa pernah dirender.
    expect(font.adaFigtree, '@font-face Figtree muncul lagi — font terbuang').toBe(false);
    expect(
        berkasFont.filter((u) => /figtree/i.test(u)),
        'berkas font Figtree masih diunduh',
    ).toEqual([]);
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
