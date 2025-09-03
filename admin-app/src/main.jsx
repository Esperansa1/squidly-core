import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App.jsx';
import './fonts.css';
import { loadLiaDiplomatFonts } from './fontLoader.js';

// Load fonts first
loadLiaDiplomatFonts();

// Initialize the admin app
const container = document.getElementById('squidly-admin');
if (container) {
    const root = createRoot(container);
    root.render(<App />);
} else {
    console.error('Container element #squidly-admin not found');
}