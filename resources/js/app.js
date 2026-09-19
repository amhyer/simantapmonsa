

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
