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
  
  // Set background image URL from WordPress config  
  const setBackgroundImage = () => {
    // Try both config objects
    const config = window.SQUIDLY_CONFIG || window.wpConfig;
    const assetsUrl = config?.assetsUrl || config?.pluginUrl;
    
    if (assetsUrl) {
      const backgroundUrl = assetsUrl + 'admin-app/assets/background.png';
      console.log('Setting background image URL:', backgroundUrl);
      console.log('Full CSS property value:', `url('${backgroundUrl}')`);
      root.style.setProperty('--background-image-url', `url('${backgroundUrl}')`);
      
      // Test if the URL is accessible by creating an img element
      const testImg = new Image();
      testImg.onload = () => console.log('✅ Background image loaded successfully');
      testImg.onerror = () => console.error('❌ Background image failed to load from:', backgroundUrl);
      testImg.src = backgroundUrl;
    } else {
      console.warn('WordPress config not available, skipping background image');
    }
  };
  
  // Try to set background image immediately, or wait for config
  if (window.SQUIDLY_CONFIG || window.wpConfig) {
    setBackgroundImage();
  } else {
    // Wait for WordPress config to be available
    const checkConfig = () => {
      if (window.SQUIDLY_CONFIG || window.wpConfig) {
        setBackgroundImage();
      } else {
        setTimeout(checkConfig, 100);
      }
    };
    checkConfig();
  }
};

// Apply default theme immediately
applyTheme(DEFAULT_THEME);

// Initialize the app when DOM is ready
const initializeApp = () => {
  // Debug information
  console.log('SQUIDLY_CONFIG:', window.SQUIDLY_CONFIG);
  console.log('wpConfig:', window.wpConfig);
  console.log('Looking for container');
  console.log('Available elements with IDs:', Array.from(document.querySelectorAll('[id]')).map(el => el.id));

  // Initialize the admin app - try multiple container IDs
  let container = document.getElementById('squidly-admin-root');
  if (!container) {
      container = document.getElementById('squidly-admin');
  }

  if (container) {
      console.log('Container found:', container.id, 'rendering React app');
      const root = createRoot(container);
      root.render(<App />);
  } else {
      console.error('No suitable container found. Tried: squidly-admin-root, squidly-admin');
      // Try to create a fallback
      const body = document.body;
      if (body) {
          const fallbackContainer = document.createElement('div');
          fallbackContainer.id = 'squidly-admin-root-fallback';
          fallbackContainer.style.minHeight = '100vh';
          body.appendChild(fallbackContainer);
          console.log('Created fallback container');
          const root = createRoot(fallbackContainer);
          root.render(<App />);
      }
  }
};

// Wait for DOM to be ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeApp);
} else {
  // DOM is already ready
  initializeApp();
}