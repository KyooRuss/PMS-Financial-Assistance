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
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            // Colors from the Figma user-side screens.
            colors: {
                lwua: {
                    navy: '#003A6C',   // Figma: header gradient stop 0% (right side)
                    blue: '#0071D2',   // Figma: header gradient stop 100% (left side)
                    sky: '#1BA1F2',    // "Request Project" button
                    dark: '#0A4A7A',   // primary buttons (Submit, View Progress)
                    page: '#F3F6FB',   // page background
                },
            },
        },
    },

    plugins: [forms],
};
