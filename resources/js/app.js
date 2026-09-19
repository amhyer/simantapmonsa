

import Alpine from 'alpinejs';
import autoAnimate from '@formkit/auto-animate';

window.Alpine = Alpine;

// AutoAnimate sebagai Alpine directive: x-auto-animate
// Kompatibel TALL stack. Opsi default; override via modifier atau :params.
// Contoh: <div x-data x-auto-animate>...</div>
//         <tbody x-data x-auto-animate="{ duration: 250 }">
Alpine.directive('auto-animate', (el, { expression }, { evaluateLater, cleanup }) => {
    const getOptions = expression ? evaluateLater(expression) : null;
    const apply = (options = {}) => autoAnimate(el, options);
    if (getOptions) {
        getOptions((options) => {
            const animation = apply(options || {});
            cleanup(() => animation.disable());
        });
    } else {
        const animation = apply();
        cleanup(() => animation.disable());
    }
});

Alpine.start();

// ============================================================
// SIMANTAP interactive UI enhancer (global, semua halaman)
// Murni progresif: tanpa sentuhan Blade, tanpa ubah logika.
// - Reveal-on-scroll berjenjang untuk kartu & judul
// - Angka statistik mencacah naik (mendukung format 78,5 & %)
// - Bilah progres navigasi + tombol kembali-ke-atas
// - Auto-animate untuk tbody & toast yang berubah via JS
// Nonaktif otomatis bila pengguna memilih reduced-motion.
// ============================================================
(function initSimantapUI() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const onReady = (fn) => {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
        else fn();
    };
    onReady(() => {
        initReveal();
        initCountUp();
        initBars();
        initPageProgress();
        initBackToTop();
        initLiveAnimate();
        initTableSearch();
    });

    function observeOnce(elements, callback, options) {
        if (!('IntersectionObserver' in window)) {
            elements.forEach((el) => callback(el));
            return;
        }
        const io = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    io.unobserve(entry.target);
                    callback(entry.target);
                }
            });
        }, options || { threshold: 0.08, rootMargin: '0px 0px -20px 0px' });
        elements.forEach((el) => io.observe(el));
    }

    // --- Muncul berjenjang saat di-scroll ---
    function initReveal() {
        const els = [...document.querySelectorAll('.page-title, .card, .stat-card, .stat, .note')];
        if (!els.length) return;
        const groups = new Map();
        els.forEach((el) => {
            const parent = el.parentElement;
            if (!groups.has(parent)) groups.set(parent, []);
            groups.get(parent).push(el);
        });
        groups.forEach((list) =>
            list.forEach((el, i) => {
                el.classList.add('reveal');
                el.style.transitionDelay = Math.min(i * 70, 350) + 'ms';
            }),
        );
        observeOnce(els, (el) => el.classList.add('in'));
    }

    // --- Angka mencacah naik (paham "78,5", "1.250", "90%") ---
    function parseNumber(raw) {
        const m = String(raw).trim().match(/^([^0-9.,-]*)([0-9][0-9.,]*)(.*)$/s);
        if (!m) return null;
        const [, prefix, digits, suffix] = m;
        const commaDecimal = digits.includes(',');
        const normalized = commaDecimal
            ? digits.replace(/\./g, '').replace(',', '.')
            : digits.replace(/,/g, '');
        const value = parseFloat(normalized);
        if (!Number.isFinite(value)) return null;
        const decimals = (normalized.split('.')[1] || '').length;
        return { prefix, suffix, value, decimals, commaDecimal };
    }

    function formatNumber(value, decimals, commaDecimal) {
        const [int, dec] = value.toFixed(decimals).split('.');
        const grouped = commaDecimal
            ? int.replace(/\B(?=(\d{3})+(?!\d))/g, '.')
            : int.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return dec !== undefined ? grouped + (commaDecimal ? ',' : '.') + dec : grouped;
    }

    function initCountUp() {
        const els = [...document.querySelectorAll('.stat-value, .stat .value')]
            .filter((el) => !el.dataset.counted && el.textContent.trim().length < 24);
        observeOnce(els, (el) => {
            const parsed = parseNumber(el.textContent);
            if (!parsed || parsed.value <= 0) return;
            el.dataset.counted = '1';
            const startedAt = performance.now();
            const duration = 1000;
            const tick = (now) => {
                const t = Math.min((now - startedAt) / duration, 1);
                const eased = 1 - Math.pow(1 - t, 3);
                el.textContent = parsed.prefix
                    + formatNumber(parsed.value * eased, parsed.decimals, parsed.commaDecimal)
                    + parsed.suffix;
                if (t < 1) requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
        }, { threshold: 0.4 });
    }

    // --- Bilah .bar mengisi animasi saat terlihat ---
    function initBars() {
        const fills = [...document.querySelectorAll('.bar .fill')].filter((f) => f.style.width);
        observeOnce(fills, (fill) => {
            const target = fill.style.width;
            fill.style.width = '0%';
            requestAnimationFrame(() => requestAnimationFrame(() => { fill.style.width = target; }));
        });
    }

    // --- Progress bar tiap pindah halaman / submit ---
    function initPageProgress() {
        const bar = document.createElement('div');
        bar.id = 'pageProgress';
        bar.setAttribute('aria-hidden', 'true');
        document.body.appendChild(bar);
        const start = () => {
            bar.style.opacity = '1';
            bar.style.width = '0';
            requestAnimationFrame(() => requestAnimationFrame(() => { bar.style.width = '82%'; }));
        };
        const finish = () => {
            bar.style.width = '100%';
            setTimeout(() => { bar.style.opacity = '0'; bar.style.width = '0'; }, 300);
        };
        document.addEventListener('click', (e) => {
            const link = e.target.closest('a[href]');
            if (!link || e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
            const href = link.getAttribute('href');
            if (!href || href.startsWith('#') || link.target === '_blank') return;
            try {
                if (new URL(href, location.href).origin !== location.origin) return;
            } catch { return; }
            start();
        });
        document.addEventListener('submit', start, true);
        window.addEventListener('pageshow', finish);
    }

    // --- Tombol kembali ke atas ---
    function initBackToTop() {
        const btn = document.createElement('button');
        btn.id = 'toTop';
        btn.type = 'button';
        btn.setAttribute('aria-label', 'Kembali ke atas');
        btn.innerHTML = '<i class="fas fa-arrow-up"></i>';
        document.body.appendChild(btn);
        window.addEventListener('scroll', () => {
            btn.classList.toggle('show', window.scrollY > 400);
        }, { passive: true });
        btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
    }

    // --- Animasikan perubahan DOM real-time (filter JS, toast) ---
    function initLiveAnimate() {
        try {
            document.querySelectorAll('tbody').forEach((el) => autoAnimate(el, { duration: 220 }));
            const toasts = document.getElementById('toastContainer');
            if (toasts) autoAnimate(toasts, { duration: 250 });
        } catch { /* abaikan: browser lama tanpa dukungan WAAPI */ }
    }

    // --- Pencarian instan otomatis di setiap tabel data besar ---
    // Menyuntikkan kotak "Cari" ke card-header (atau atas tabel) TANPA
    // mengubah Blade. Melewati tabel kecil (<5 baris), tabel yang sudah
    // punya pencarian sendiri, dan tabel dengan [data-no-search].
    // Aman untuk tabel berisi input/form: baris hanya disembunyikan
    // (hidden), tidak dipindah, sehingga urutan DOM & state input utuh.
    function initTableSearch() {
        const tables = [...document.querySelectorAll('.page-content table')]
            .filter((t) => !t.closest('[data-no-search]'));
        tables.forEach((table) => {
            const bodies = [...table.querySelectorAll('tbody')];
            if (!bodies.length) return;
            const rows = bodies.flatMap((b) =>
                [...b.querySelectorAll('tr')].filter((tr) => !tr.classList.contains('table-empty')),
            );
            if (rows.length < 5) return;
            const card = table.closest('.card');
            const scope = card || table.parentElement;
            if (hasOwnSearch(scope, table)) return;
            buildTableSearch(table, bodies, rows, scope, card);
        });

        // Shortcut "/": fokus ke kotak cari pertama
        document.addEventListener('keydown', (e) => {
            if (e.key !== '/' || e.metaKey || e.ctrlKey || e.altKey) return;
            if (e.target.matches('input, textarea, select, [contenteditable]')) return;
            const first = document.querySelector('.table-search input');
            if (first) { e.preventDefault(); first.focus(); }
        });
    }

    // Deteksi halaman yang sudah punya pencarian sendiri (mis. admin/users).
    // Hanya input di LUAR tabel yang dihitung; input di dalam baris = data.
    function hasOwnSearch(scope, table) {
        const inputs = [...scope.querySelectorAll('input, select')]
            .filter((el) => !table.contains(el));
        return inputs.some((el) => {
            if (el.type === 'search') return true;
            const hay = ((el.id || '') + ' ' + (el.name || '') + ' ' + (el.placeholder || '') + ' '
                + (el.getAttribute('oninput') || '') + ' ' + (el.getAttribute('onkeyup') || '')).toLowerCase();
            return /search|cari|filter|keyword|kata kunci/.test(hay);
        });
    }

    function buildTableSearch(table, bodies, rows, scope, card) {
        const total = rows.length;
        const paged = !!scope.querySelector('.pagination');

        const bar = document.createElement('div');
        bar.className = 'table-search';
        bar.setAttribute('data-table-search', '');

        const icon = document.createElement('i');
        icon.className = 'fas fa-search';
        icon.setAttribute('aria-hidden', 'true');

        const input = document.createElement('input');
        input.type = 'search';
        input.placeholder = 'Cari data... ( / )';
        input.setAttribute('aria-label', 'Cari pada tabel');
        input.setAttribute('autocomplete', 'off');
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') e.preventDefault();
        });

        const count = document.createElement('span');
        count.className = 'table-count';

        const renderCount = (shown, q) => {
            const pageNote = paged ? ' · halaman ini' : '';
            count.textContent = q
                ? shown + ' dari ' + total + ' data' + pageNote
                : total + ' data' + pageNote;
        };

        bar.append(icon, input, count);
        const header = card ? card.querySelector('.card-header') : null;
        if (header) header.appendChild(bar);
        else table.parentElement.insertBefore(bar, table);

        // Baris kosong saat tidak ada hasil
        const cols = rows[0] ? rows[0].children.length : 1;
        const empty = document.createElement('tr');
        empty.className = 'table-empty';
        empty.hidden = true;
        const td = document.createElement('td');
        td.colSpan = cols;
        td.innerHTML = '<div style="padding:8px">'
            + '<span style="font-size:22px">🔍</span><br><b>Tidak ada data yang cocok.</b>'
            + '<br><span style="font-size:12px">Coba kata kunci lain atau kosongkan pencarian.</span></div>';
        empty.appendChild(td);
        bodies[0].appendChild(empty);

        // Haystack mencakup teks sel + nilai input di dalam baris
        // (agar tabel input-nilai tetap bisa dicari).
        const haystacks = rows.map((tr) => (
            tr.textContent + ' '
            + [...tr.querySelectorAll('input, select, textarea')]
                .map((el) => el.value || '')
                .join(' ')
        ).toLowerCase());

        renderCount(total, '');
        input.addEventListener('input', () => {
            const q = input.value.trim().toLowerCase();
            table.classList.toggle('filtering', !!q);
            let shown = 0;
            rows.forEach((tr, i) => {
                const hit = !q || haystacks[i].includes(q);
                tr.hidden = !hit;
                if (hit) shown++;
            });
            empty.hidden = shown > 0;
            renderCount(shown, q);
        });
    }
})();
