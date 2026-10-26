import { expect, test } from '@playwright/test';

test('landing memakai CTA yang jujur dan interaksi FAQ bekerja di Chromium', async ({ page }) => {
    // CDN eksternal bukan prasyarat untuk smoke test antarmuka dan tidak boleh
    // membuat hasil CI bergantung pada ketersediaan penyedia pihak ketiga.
    await page.route(/^https:\/\/(?:cdnjs\.cloudflare\.com|cdn\.jsdelivr\.net|unpkg\.com)\//, route => route.abort());
    await page.goto('/', { waitUntil: 'domcontentloaded' });

    const aksesNav = page.getByRole('link', { name: 'Akses', exact: true });
    await expect(aksesNav).toHaveAttribute('href', '#demo');
    await expect(page.getByRole('link', { name: 'Coba Demo', exact: true })).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Akses Aplikasi', exact: true }))
        .toHaveAttribute('href', '#demo');
    await aksesNav.click();
    await expect(page).toHaveURL(/#demo$/);
    await expect(page.getByRole('heading', { name: 'Akses Aplikasi SIMANTAP' })).toBeVisible();

    const loginLink = page.getByRole('link', { name: 'Masuk ke aplikasi' });
    await expect(loginLink).toHaveAttribute('href', /\/login$/);
    await expect(page.getByText('Video demo belum tersedia')).toBeVisible();

    // Tidak ada lagi tombol play yang membuka modal informasi seolah-olah video tersedia.
    await expect(page.locator('#videoTrigger')).toHaveCount(0);
    await expect(page.locator('#videoModal')).toHaveCount(0);

    await expect(page.getByText('Newsletter email belum tersedia')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Lihat repositori dan rilis' }))
        .toHaveAttribute('href', 'https://github.com/amhyer/simantapmonsa');

    const faq = page.getByRole('button', { name: 'Apa itu SIMANTAP?' });
    await faq.click();
    await expect(faq).toHaveAttribute('aria-expanded', 'true');
    await expect(page.locator('#faq-answer-0')).toHaveClass(/open/);
});

/**
 * Test ini membuktikan di browser sungguhan bahwa aset pihak ketiga (Chart.js,
 * AOS, Font Awesome, font Inter) benar-benar TERMUAT dari bundle Vite sendiri.
 *
 * Ini penting karena refaktornya mudah rusak secara senyap:
 *   - Vite memuat berkas sebagai <script type="module"> yang *deferred*. Kalau
 *     `AOS.init()` masih dipanggil dari skrip inline, ia jalan lebih dulu dan
 *     gagal tanpa suara karena dijaga `if (window.AOS)`.
 *   - Mengganti <link> CDN dengan @vite bisa saja "sukses" di build tetapi
 *     font/ikonnya tidak pernah sampai ke halaman.
 * Test di atas hanya memblokir CDN; test ini memastikan penggantinya bekerja.
 */
test('aset pihak ketiga dimuat dari bundle sendiri, tanpa satu pun permintaan CDN', async ({ page }) => {
    const eksternal = [];
    page.on('request', (permintaan) => {
        const url = new URL(permintaan.url());
        if (url.protocol === 'http:' || url.protocol === 'https:') {
            eksternal.push(permintaan.url());
        }
    });

    await page.goto('/', { waitUntil: 'load' });
    const origin = new URL(page.url()).origin;

    // 1. Tidak ada aset yang diambil dari luar aplikasi.
    const lintasAsal = eksternal.filter((u) => !u.startsWith(origin));
    expect(lintasAsal, `masih ada permintaan keluar: ${lintasAsal.join(', ')}`).toEqual([]);

    // 2. Chart.js tersedia sebagai konstruktor global DAN benar-benar bisa
    //    menggambar — bukan sekadar objeknya ada.
    const chartSiap = await page.evaluate(() => typeof window.Chart === 'function');
    expect(chartSiap, 'window.Chart tidak tersedia — modul charts.js gagal dimuat').toBe(true);

    // Grafiknya diuji dengan kanvas sintetis, bukan dengan <canvas> milik halaman.
    // Alasannya: kanvas statistik di landing berada di dalam `@if($lastUpdate)`,
    // sehingga di CI (basis data kosong, tanpa data TKA) jumlahnya nol dan
    // assertion semacam itu akan gagal bukan karena Chart.js rusak.
    const grafikBerfungsi = await page.evaluate(() => {
        const kanvas = document.createElement('canvas');
        kanvas.width = 20;
        kanvas.height = 20;
        document.body.appendChild(kanvas);

        try {
            const grafik = new window.Chart(kanvas, {
                type: 'bar',
                data: { labels: ['a'], datasets: [{ data: [1] }] },
                options: { animation: false, responsive: false },
            });

            const terdaftar = Boolean(window.Chart.getChart(kanvas));
            grafik.destroy();

            return terdaftar;
        } finally {
            kanvas.remove();
        }
    });
    expect(grafikBerfungsi, 'Chart.js termuat tetapi tidak bisa membuat grafik').toBe(true);

    // 3. AOS terinisialisasi: setiap elemen [data-aos] diberi kelas `aos-init`.
    //    Bila init dipanggil terlalu dini (sebelum modul deferred jalan),
    //    jumlah ini nol.
    const aosInit = await page.evaluate(() => ({
        elemen: document.querySelectorAll('[data-aos]').length,
        terinisialisasi: document.querySelectorAll('[data-aos].aos-init').length,
        pustakaAda: typeof window.AOS === 'object' && window.AOS !== null,
    }));
    expect(aosInit.pustakaAda, 'window.AOS tidak tersedia').toBe(true);
    expect(aosInit.elemen).toBeGreaterThan(0);
    expect(
        aosInit.terinisialisasi,
        'AOS.init() tidak berjalan — kemungkinan dipanggil sebelum modul deferred dimuat',
    ).toBe(aosInit.elemen);

    // 4. Font Awesome benar-benar diterapkan, bukan hanya CSS-nya ada.
    const ikon = page.locator('.fas').first();
    await expect(ikon).toBeAttached();
    const fontIkon = await ikon.evaluate((el) => getComputedStyle(el).fontFamily);
    expect(fontIkon).toMatch(/Font Awesome/i);
    const lebarIkon = await ikon.evaluate((el) => el.getBoundingClientRect().width);
    expect(lebarIkon, 'ikon Font Awesome tidak terender (glyph kosong)').toBeGreaterThan(0);

    // 5. Font Inter dimuat dari berkas woff2 lokal. `document.fonts.load()`
    //    dipakai agar pemuatannya dipaksa dan hasilnya deterministik — tanpa itu
    //    bobot yang tidak terpakai di halaman tetap berstatus `unloaded`.
    const interSiap = await page.evaluate(async () => {
        await document.fonts.load("400 16px 'Inter'");
        await document.fonts.ready;

        const milik = Array.from(document.fonts)
            .filter((f) => f.family.replace(/['"]/g, '') === 'Inter');

        return { jumlah: milik.length, adaLoaded: milik.some((f) => f.status === 'loaded') };
    });
    expect(interSiap.jumlah, 'tidak ada @font-face Inter — fontsource tidak terbundel').toBeGreaterThan(0);
    expect(interSiap.adaLoaded, 'font Inter terdaftar tetapi tidak pernah termuat').toBe(true);
});
