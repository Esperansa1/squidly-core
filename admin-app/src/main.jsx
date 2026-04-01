import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App.jsx';
import './fonts.css';
import { loadLiaDiplomatFonts } from './fontLoader.js';
import { DEFAULT_THEME, generateCSSVariables } from './config/theme.js';
import api from './services/api.js';

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
  }
};

// Apply theme synchronously from wpConfig (embedded by PHP, no async fetch needed)
// Falls back to DEFAULT_THEME if wpConfig.theme is unavailable
const wpTheme = window.wpConfig?.theme;
applyTheme(wpTheme ? { ...DEFAULT_THEME, ...wpTheme } : DEFAULT_THEME);

// Re-apply after api.init() in case theme was updated since page load
api.init()
  .then(() => { applyTheme(api.getTheme()); })
  .catch(() => { /* already applied above */ });

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
      const root = createRoot(container);
      root.render(<App />);
  }
};

// Wait for DOM to be ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeApp);
} else {
  // DOM is already ready
  initializeApp();
}