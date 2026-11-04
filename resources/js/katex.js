// KaTeX auto-render dibundel via Vite (self-host) agar soal matematika di CBT
// tetap ter-render saat ujian berlangsung di jaringan lokal (LAN) tanpa internet.
import renderMathInElement from 'katex/contrib/auto-render';

window.renderMathInElement = renderMathInElement;

export function renderCbtMath(root = document.body, extraIgnoredTags = []) {
    renderMathInElement(root, {
        delimiters: [
            { left: '$$', right: '$$', display: true },
            { left: '$', right: '$', display: false },
            { left: '\\(', right: '\\)', display: false },
            { left: '\\[', right: '\\]', display: true },
        ],
        throwOnError: false,
        ignoredTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code', ...extraIgnoredTags],
    });
}

window.renderCbtMath = renderCbtMath;
window.dispatchEvent(new CustomEvent('simantap:katex-ready'));
