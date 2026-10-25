/*
 * AOS (animasi saat scroll) untuk halaman publik — dibundel sendiri, bukan dari
 * unpkg.com.
 *
 * `AOS.init()` dipanggil di sini, bukan di <script> inline pada Blade. Alasannya:
 * Vite memuat berkas ini sebagai <script type="module"> yang bersifat *deferred*,
 * jadi ia dieksekusi SETELAH dokumen selesai diparsing. Seandainya init tetap
 * inline, skrip klasik itu jalan lebih dulu dan `window.AOS` masih undefined —
 * lalu gagal tanpa suara karena skrip lamanya dijaga `if (window.AOS)`.
 *
 * Karena modul deferred berjalan setelah parsing, seluruh elemen [data-aos]
 * sudah ada di DOM saat init dipanggil.
 */
import AOS from 'aos';

window.AOS = AOS;

AOS.init({ duration: 800, once: true, offset: 80, easing: 'ease-out-cubic' });
