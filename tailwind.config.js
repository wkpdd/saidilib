import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
    ],
    // Order/status badge colours are built at runtime (bg-{color}-100 etc.),
    // so the JIT scanner can't see them. Safelist ONLY the exact classes used
    // (keeps the CSS tiny — low-bandwidth first).
    safelist: [
        'bg-amber-50', 'bg-blue-50', 'bg-indigo-50', 'bg-cyan-50', 'bg-green-50', 'bg-red-50', 'bg-rose-50', 'bg-gray-50',
        'bg-amber-100', 'bg-blue-100', 'bg-indigo-100', 'bg-cyan-100', 'bg-green-100', 'bg-red-100', 'bg-rose-100', 'bg-gray-100',
        'text-amber-700', 'text-blue-700', 'text-indigo-700', 'text-cyan-700', 'text-green-700', 'text-red-700', 'text-rose-700', 'text-gray-700',
    ],
    theme: {
        extend: {
            colors: {
                // Brand orange — extracted from the Fondation Saeedi "SA" logo.
                brand: {
                    50:  '#fff8ec',
                    100: '#ffecc9',
                    200: '#ffd88f',
                    300: '#fdbb55',
                    400: '#fa9f28',
                    500: '#f0900e',
                    600: '#e07d00',
                    700: '#b45f08',
                    800: '#924b0d',
                    900: '#78400f',
                },
                ink: {
                    900: '#1a1a2e',
                    700: '#334155',
                    500: '#64748b',
                },
                // Accent red — from the logo's chevron wings.
                accent: '#ef3b3b',
            },
            fontFamily: {
                sans: ['Cairo', 'Figtree', ...defaultTheme.fontFamily.sans],
                display: ['Cairo', 'Poppins', ...defaultTheme.fontFamily.sans],
            },
            boxShadow: {
                card: '0 1px 3px 0 rgb(0 0 0 / 0.06), 0 8px 24px -12px rgb(15 23 42 / 0.18)',
                soft: '0 2px 12px -2px rgb(15 23 42 / 0.10)',
            },
            borderRadius: {
                '2xl': '1rem',
                '3xl': '1.5rem',
            },
            keyframes: {
                'fade-up': {
                    '0%': { opacity: '0', transform: 'translateY(12px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                float: {
                    '0%, 100%': { transform: 'translateY(0)' },
                    '50%': { transform: 'translateY(-8px)' },
                },
                wiggle: {
                    '0%, 100%': { transform: 'rotate(-6deg)' },
                    '50%': { transform: 'rotate(6deg)' },
                },
                shine: {
                    '0%': { backgroundPosition: '0% 50%' },
                    '100%': { backgroundPosition: '200% 50%' },
                },
            },
            animation: {
                'fade-up': 'fade-up .5s ease-out both',
                float: 'float 3s ease-in-out infinite',
                wiggle: 'wiggle 1.2s ease-in-out infinite',
                shine: 'shine 4s linear infinite',
            },
        },
    },
    plugins: [],
};
