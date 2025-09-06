import React, { useState } from 'react';

// Import content components for SPA behavior
import MenuManagement from './components/MenuManagement.jsx';

// Route configuration - all routes redirect to Menu Management since other pages are deleted
const routes = {
  'management-area': MenuManagement,
  'performance': MenuManagement,
  'payments': MenuManagement,
  'orders': MenuManagement,
  'suppliers': MenuManagement,
  'menu-management': MenuManagement,
  'customers': MenuManagement,
  'tutorials': MenuManagement,
  'settings': MenuManagement,
};

// Simple router context
export const RouterContext = React.createContext();

// Router provider component
export const RouterProvider = ({ children }) => {
  const [currentRoute, setCurrentRoute] = useState('menu-management'); // Default route

  const navigate = (route) => {
    setCurrentRoute(route);
  };

  const getCurrentComponent = () => {
    const Component = routes[currentRoute] || MenuManagement;
    return Component;
  };

  return (
    <RouterContext.Provider value={{
      currentRoute,
      navigate,
      getCurrentComponent
    }}>
      {children}
    </RouterContext.Provider>
  );
};

// Hook to use router
export const useRouter = () => {
  const context = React.useContext(RouterContext);
  if (!context) {
    throw new Error('useRouter must be used within RouterProvider');
  }
  return context;
};