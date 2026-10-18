// Chart.js dibundel via Vite (self-host) khusus halaman dashboard guru,
// agar halaman lain tidak menanggung ~200KB dan tidak bergantung CDN.
import Chart from 'chart.js/auto';

window.Chart = Chart;
