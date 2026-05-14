import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: ['class'],
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{vue,js,ts,jsx,tsx}',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['DM Sans', ...defaultTheme.fontFamily.sans],
                serif: ['DM Serif Display', ...defaultTheme.fontFamily.serif],
            },
            borderRadius: {
                lg: 'var(--radius)',
                md: 'calc(var(--radius) - 2px)',
                sm: 'calc(var(--radius) - 4px)',
            },
            colors: {
                background: 'hsl(var(--background))',
                foreground: 'hsl(var(--foreground))',
                card: {
                    DEFAULT: 'hsl(var(--card))',
                    foreground: 'hsl(var(--card-foreground))',
                },
                popover: {
                    DEFAULT: 'hsl(var(--popover))',
                    foreground: 'hsl(var(--popover-foreground))',
                },
                primary: {
                    DEFAULT: 'hsl(var(--primary))',
                    foreground: 'hsl(var(--primary-foreground))',
                },
                secondary: {
                    DEFAULT: 'hsl(var(--secondary))',
                    foreground: 'hsl(var(--secondary-foreground))',
                },
                muted: {
                    DEFAULT: 'hsl(var(--muted))',
                    foreground: 'hsl(var(--muted-foreground))',
                },
                accent: {
                    DEFAULT: 'hsl(var(--accent))',
                    foreground: 'hsl(var(--accent-foreground))',
                },
                destructive: {
                    DEFAULT: 'hsl(var(--destructive))',
                    foreground: 'hsl(var(--destructive-foreground))',
                },
                border: 'hsl(var(--border))',
                input: 'hsl(var(--input))',
                ring: 'hsl(var(--ring))',
                chart: {
                    1: 'hsl(var(--chart-1))',
                    2: 'hsl(var(--chart-2))',
                    3: 'hsl(var(--chart-3))',
                    4: 'hsl(var(--chart-4))',
                    5: 'hsl(var(--chart-5))',
                },
                sidebar: {
                    DEFAULT: 'hsl(var(--sidebar-background))',
                    foreground: 'hsl(var(--sidebar-foreground))',
                    primary: 'hsl(var(--sidebar-primary))',
                    'primary-foreground': 'hsl(var(--sidebar-primary-foreground))',
                    accent: 'hsl(var(--sidebar-accent))',
                    'accent-foreground': 'hsl(var(--sidebar-accent-foreground))',
                    border: 'hsl(var(--sidebar-border))',
                    ring: 'hsl(var(--sidebar-ring))',
                },
                pup: {
                    maroon: 'var(--pup-maroon)',
                    'maroon-dark': 'var(--pup-maroon-dark)',
                    'maroon-deep': 'var(--pup-maroon-deep)',
                    'maroon-light': 'var(--pup-maroon-light)',
                    'maroon-pale': 'var(--pup-maroon-pale)',
                    gold: 'var(--pup-gold)',
                    'gold-dark': 'var(--pup-gold-dark)',
                    'gold-light': 'var(--pup-gold-light)',
                    'gold-pale': 'var(--pup-gold-pale)',
                    white: 'var(--pup-white)',
                    'off-white': 'var(--pup-off-white)',
                    'gray-50': 'var(--pup-gray-50)',
                    'gray-100': 'var(--pup-gray-100)',
                    'gray-200': 'var(--pup-gray-200)',
                    'gray-400': 'var(--pup-gray-400)',
                    'gray-600': 'var(--pup-gray-600)',
                    'gray-800': 'var(--pup-gray-800)',
                },
                status: {
                    available: 'var(--status-available)',
                    'available-bg': 'var(--status-available-bg)',
                    occupied: 'var(--status-occupied)',
                    'occupied-bg': 'var(--status-occupied-bg)',
                    reserved: 'var(--status-reserved)',
                    'reserved-bg': 'var(--status-reserved-bg)',
                    'reserved-border': 'var(--status-reserved-border)',
                    maintenance: 'var(--status-maintenance)',
                    'maintenance-bg': 'var(--status-maintenance-bg)',
                    notice: 'var(--status-notice)',
                    'notice-bg': 'var(--status-notice-bg)',
                    warning: 'var(--status-warning)',
                    'warning-bg': 'var(--status-warning-bg)',
                },
            },
        },
    },
    plugins: [require('tailwindcss-animate')],
};
