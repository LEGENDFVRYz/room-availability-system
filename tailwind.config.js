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
                    maroon:       'rgb(var(--pup-maroon) / <alpha-value>)',
                    'maroon-dark':  'rgb(var(--pup-maroon-dark) / <alpha-value>)',
                    'maroon-deep':  'rgb(var(--pup-maroon-deep) / <alpha-value>)',
                    'maroon-light': 'rgb(var(--pup-maroon-light) / <alpha-value>)',
                    'maroon-pale':  'rgb(var(--pup-maroon-pale) / <alpha-value>)',
                    gold:         'rgb(var(--pup-gold) / <alpha-value>)',
                    'gold-dark':    'rgb(var(--pup-gold-dark) / <alpha-value>)',
                    'gold-light':   'rgb(var(--pup-gold-light) / <alpha-value>)',
                    'gold-pale':    'rgb(var(--pup-gold-pale) / <alpha-value>)',
                    white:        'rgb(var(--pup-white) / <alpha-value>)',
                    'off-white':    'rgb(var(--pup-off-white) / <alpha-value>)',
                    'gray-50':      'rgb(var(--pup-gray-50) / <alpha-value>)',
                    'gray-100':     'rgb(var(--pup-gray-100) / <alpha-value>)',
                    'gray-200':     'rgb(var(--pup-gray-200) / <alpha-value>)',
                    'gray-400':     'rgb(var(--pup-gray-400) / <alpha-value>)',
                    'gray-600':     'rgb(var(--pup-gray-600) / <alpha-value>)',
                    'gray-800':     'rgb(var(--pup-gray-800) / <alpha-value>)',
                },
                status: {
                    available:          'rgb(var(--status-available) / <alpha-value>)',
                    'available-bg':     'rgb(var(--status-available-bg) / <alpha-value>)',
                    occupied:           'rgb(var(--status-occupied) / <alpha-value>)',
                    'occupied-bg':      'rgb(var(--status-occupied-bg) / <alpha-value>)',
                    reserved:           'rgb(var(--status-reserved) / <alpha-value>)',
                    'reserved-bg':      'rgb(var(--status-reserved-bg) / <alpha-value>)',
                    'reserved-border':  'rgb(var(--status-reserved-border) / <alpha-value>)',
                    maintenance:        'rgb(var(--status-maintenance) / <alpha-value>)',
                    'maintenance-bg':   'rgb(var(--status-maintenance-bg) / <alpha-value>)',
                    notice:             'rgb(var(--status-notice) / <alpha-value>)',
                    'notice-bg':        'rgb(var(--status-notice-bg) / <alpha-value>)',
                    warning:            'rgb(var(--status-warning) / <alpha-value>)',
                    'warning-bg':       'rgb(var(--status-warning-bg) / <alpha-value>)',
                },
            },
        },
    },
    plugins: [require('tailwindcss-animate')],
};
