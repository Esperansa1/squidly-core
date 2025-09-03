import React from 'react';
import MenuManagement from './components/MenuManagement.jsx';
import './styles/admin.css';

/**
 * Main Admin Application
 * 
 * Completely decoupled from WordPress UI - users won't know it's WordPress
 */
const App = () => {
  return (
    <div className="squidly-admin-app">
      <MenuManagement />
    </div>
  );
};

export default App;