import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App.jsx';
import './index.css';

// Set background image from WordPress config
const setBackgroundImage = () => {
  const config = window.wpConfig;
  const assetsUrl = config?.assetsUrl || config?.pluginUrl;

  if (assetsUrl) {
    const backgroundUrl = `${assetsUrl}customer-app/assets/background.png`;
    const root = document.documentElement;
    root.style.setProperty('--background-image-url', `url('${backgroundUrl}')`);
    console.log('✅ Background image URL set:', backgroundUrl);
  }
};

// Set background image
setBackgroundImage();

ReactDOM.createRoot(document.getElementById('squidly-customer-app')).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>,
);
