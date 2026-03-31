import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App.jsx';
import './index.css';
import publicApi from './services/publicApi.js';
import { DEFAULT_THEME, generateCSSVariables } from './config/theme.js';

const applyTheme = (theme) => {
  const merged = { ...DEFAULT_THEME, ...theme };
  const cssVars = generateCSSVariables(merged);
  const root = document.documentElement;
  Object.entries(cssVars).forEach(([prop, val]) => root.style.setProperty(prop, val));
};

// Set background image from WordPress config
const setBackgroundImage = () => {
  const config = window.wpConfig;
  const assetsUrl = config?.assetsUrl || config?.pluginUrl;

  if (assetsUrl) {
    const backgroundUrl = `${assetsUrl}customer-app/assets/background.png`;
    const root = document.documentElement;
    root.style.setProperty('--background-image-url', `url('${backgroundUrl}')`);
  }
};

// Set background image
setBackgroundImage();

// Apply default theme immediately, then re-apply server-saved theme (non-blocking)
applyTheme({});
publicApi.init()
  .then((config) => { if (config?.theme) applyTheme(config.theme); })
  .catch(() => { /* DEFAULT_THEME already applied */ });

ReactDOM.createRoot(document.getElementById('squidly-customer-app')).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>,
);
