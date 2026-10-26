// Chart.js dibundel via Vite (self-host) untuk halaman mana pun yang
// menampilkan grafik.
//
// Alasan: sebelumnya 15 view memuat Chart.js dari `cdn.jsdelivr.net/npm/chart.js`,
// dan 12 di antaranya TANPA pin versi — artinya halaman bisa berubah perilaku
// kapan pun paket itu merilis versi baru, dan aplikasi bergantung pada
// ketersediaan pihak ketiga. Membundel sendiri menghilangkan kedua masalah itu
// sekaligus, sehingga tidak perlu menghitung hash SRI.
//
// Berkas ini diekspos sebagai `window.Chart` karena skrip grafik di Blade
// memakai konstruktor global `new Chart(...)`.
import Chart from 'chart.js/auto';

window.Chart = Chart;
