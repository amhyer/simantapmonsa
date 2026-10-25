import { expect, test } from '@playwright/test';

test('landing memakai CTA yang jujur dan interaksi FAQ bekerja di Chromium', async ({ page }) => {
    // CDN eksternal bukan prasyarat untuk smoke test antarmuka dan tidak boleh
    // membuat hasil CI bergantung pada ketersediaan penyedia pihak ketiga.
    await page.route(/^https:\/\/(?:cdnjs\.cloudflare\.com|cdn\.jsdelivr\.net|unpkg\.com)\//, route => route.abort());
    await page.goto('/', { waitUntil: 'domcontentloaded' });

    const aksesNav = page.getByRole('link', { name: 'Akses', exact: true });
    await expect(aksesNav).toHaveAttribute('href', '#demo');
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
