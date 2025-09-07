import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App.jsx';
import './fonts.css';
import { loadLiaDiplomatFonts } from './fontLoader.js';
import { DEFAULT_THEME, generateCSSVariables } from './config/theme.js';

console.log('✅ Squidly Admin React app main.jsx loaded');

// Load fonts first
loadLiaDiplomatFonts();

// Apply theme CSS variables to document root
const applyTheme = (theme) => {
  const cssVars = generateCSSVariables(theme);
  const root = document.documentElement;
  
  Object.entries(cssVars).forEach(([property, value]) => {
    root.style.setProperty(property, value);
  });
};

// Set background image from WordPress config
const setBackgroundImage = () => {
  const config = window.SQUIDLY_CONFIG || window.wpConfig;
  const assetsUrl = config?.assetsUrl || config?.pluginUrl;
  
  if (assetsUrl) {
    const backgroundUrl = `${assetsUrl}admin-app/assets/background.png`;
    const root = document.documentElement;
    root.style.setProperty('--background-image-url', `url('${backgroundUrl}')`);
    console.log('✅ Background image URL set:', backgroundUrl);
  }
};

// Apply default theme immediately
applyTheme(DEFAULT_THEME);

// Initialize the app when DOM is ready
const initializeApp = () => {
  // Set background image once config is available
  if (window.SQUIDLY_CONFIG || window.wpConfig) {
    setBackgroundImage();
  }

  // Initialize the admin app
  let container = document.getElementById('squidly-admin-root');
  if (!container) {
      container = document.getElementById('squidly-admin');
  }

  if (container) {
      console.log('✅ Container found, rendering React app');
      const root = createRoot(container);
      root.render(<App />);
  } else {
      console.error('❌ No suitable container found');
  }
};

// Wait for DOM to be ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeApp);
} else {
  // DOM is already ready
  initializeApp();
}