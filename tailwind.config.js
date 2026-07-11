import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

export default {
    content: ['./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php', './storage/framework/views/*.php', './resources/views/**/*.blade.php'],
    theme: { extend: { colors: { wine: '#2b0000', 'wine-deep': '#170000', cream: '#f5f1e8', gold: '#c6a66b' }, fontFamily: { sans: ['Figtree', ...defaultTheme.fontFamily.sans], display: ['Playfair Display', 'serif'], body: ['Cormorant Garamond', 'serif'] } } },
    plugins: [forms],
};
