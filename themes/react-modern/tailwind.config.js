/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./src/**/*.{js,ts,jsx,tsx}",
    "./views/**/*.blade.php",
    "./views/**/*.php",
  ],
  theme: {
    extend: {
      colors: {
        // Colores del brand de MuseDock
        primary: {
          DEFAULT: '#667eea',
          50: '#f5f7ff',
          100: '#ebedff',
          200: '#d6dbff',
          300: '#b3bbff',
          400: '#8c99ff',
          500: '#667eea',
          600: '#5566d6',
          700: '#4451b8',
          800: '#363f94',
          900: '#2d3577',
        },
        secondary: {
          DEFAULT: '#764ba2',
          50: '#faf6fc',
          100: '#f4edf9',
          200: '#e8dbf2',
          300: '#d8bfe7',
          400: '#c398d8',
          500: '#a970c4',
          600: '#764ba2',
          700: '#673e8c',
          800: '#563374',
          900: '#482b61',
        },
        accent: {
          DEFAULT: '#f59e0b',
          50: '#fffbeb',
          100: '#fef3c7',
          200: '#fde68a',
          300: '#fcd34d',
          400: '#fbbf24',
          500: '#f59e0b',
          600: '#d97706',
          700: '#b45309',
          800: '#92400e',
          900: '#78350f',
        },
      },
      fontFamily: {
        sans: [
          '-apple-system',
          'BlinkMacSystemFont',
          '"Segoe UI"',
          'Roboto',
          '"Helvetica Neue"',
          'Arial',
          'sans-serif',
        ],
      },
      container: {
        center: true,
        padding: {
          DEFAULT: '1rem',
          sm: '2rem',
          lg: '4rem',
          xl: '5rem',
          '2xl': '6rem',
        },
      },
      animation: {
        'fade-in': 'fadeIn 0.5s ease-in-out',
        'slide-up': 'slideUp 0.6s ease-out',
        'slide-down': 'slideDown 0.6s ease-out',
        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
      },
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        slideUp: {
          '0%': { opacity: '0', transform: 'translateY(30px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        slideDown: {
          '0%': { opacity: '0', transform: 'translateY(-30px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
      },
    },
  },
  plugins: [],
}
