/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./src/**/*.{js,jsx,ts,tsx}",
    "./public/index.html",
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          DEFAULT: '#D12525',
          50: '#F8E6E6',
          100: '#F0CCCC',
          200: '#E199999',
          300: '#D26666',
          400: '#D14545',
          500: '#D12525',
          600: '#B01D1D',
          700: '#8F1515',
          800: '#6E0E0E',
          900: '#4D0606',
        },
        secondary: {
          DEFAULT: '#F2F2F2',
          50: '#FFFFFF',
          100: '#F9F9F9',
          200: '#F2F2F2',
          300: '#EBEBEB',
          400: '#E4E4E4',
          500: '#DDDDDD',
          600: '#B8B8B8',
          700: '#939393',
          800: '#6E6E6E',
          900: '#494949',
        }
      },
      fontFamily: {
        'sans': ['LiaDiplomat', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'Arial', 'sans-serif'],
        'diplomat': ['LiaDiplomat', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'Arial', 'sans-serif'],
        'hebrew': ['LiaDiplomat', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'Arial', 'sans-serif'],
      },
      fontWeight: {
        'light': '200',
        'normal': '400',
        'medium': '600',
        'bold': '800',
      },
      direction: {
        rtl: 'rtl',
      }
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
    function({ addUtilities }) {
      addUtilities({
        '.rtl': {
          direction: 'rtl',
        },
        '.ltr': {
          direction: 'ltr',
        }
      })
    }
  ],
}