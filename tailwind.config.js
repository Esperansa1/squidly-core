/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./assets/src/**/*.{js,jsx,ts,tsx}",
    "./includes/**/*.php",
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
        'hebrew': ['system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'Arial', 'sans-serif'],
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