import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App.jsx';
import './fonts.css';
import { loadLiaDiplomatFonts } from './fontLoader.js';
import { DEFAULT_THEME, generateCSSVariables } from './config/theme.js';

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

// Apply default theme immediately
applyTheme(DEFAULT_THEME);

// Initialize the admin app
const container = document.getElementById('squidly-admin');
if (container) {
    const root = createRoot(container);
    root.render(<App />);
} else {
    console.error('Container element #squidly-admin not found');
}