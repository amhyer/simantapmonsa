import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                // Disamakan dengan resources/css/layout.css:30 dan ketiga halaman
                // gerbang auth, yang semuanya memakai tumpukan 'Inter' lalu font
                // sistem. Sebelumnya berisi 'Figtree' (bawaan scaffolding Breeze)
                // yang tidak dipakai satu pun view hidup, sehingga Tailwind
                // mendeklarasikan font lain dari yang benar-benar dirender.
                sans: ['Inter', '-apple-system', 'BlinkMacSystemFont', 'sans-serif'],
            },
        },
    },

    plugins: [forms],
};
