import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
    ],
    theme: {
        extend: {
            colors: {
                brand: {
                    50:  '#eff6ff',
                    100: '#dbeafe',
                    200: '#bfdbfe',
                    300: '#93c5fd',
                    400: '#60a5fa',
                    500: '#3b82f6',
                    600: '#2563eb',
                    700: '#1d4ed8',
                    800: '#1e40af',
                    900: '#1e3a8a',
                },
                ink: {
                    900: '#0f172a',
                    700: '#334155',
                    500: '#64748b',
                },
                accent: '#f59e0b',
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
            },
            animation: {
                'fade-up': 'fade-up .5s ease-out both',
            },
        },
    },
    plugins: [],
};
