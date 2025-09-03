import React from 'react';
import { RouterProvider, useRouter } from './router.jsx';
import './styles/admin.css';

/**
 * Main Admin Application Content
 */
const AppContent = () => {
  const { getCurrentComponent } = useRouter();
  const CurrentComponent = getCurrentComponent();
  
  return (
    <div className="squidly-admin-app">
      <CurrentComponent />
    </div>
  );
};

/**
 * Main Admin Application
 * 
 * Completely decoupled from WordPress UI - users won't know it's WordPress
 */
const App = () => {
  return (
    <RouterProvider>
      <AppContent />
    </RouterProvider>
  );
};

export default App;