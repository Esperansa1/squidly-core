import React, { useState } from 'react';

// Import content components for SPA behavior
import MenuManagement from './components/MenuManagement.jsx';
import BranchManagement from './components/BranchManagement.jsx';
import CustomerManagement from './components/CustomerManagement.jsx';
import OrderManagement from './components/OrderManagement.jsx';
import ManagementDashboard from './components/ManagementDashboard.jsx';
import Settings from './components/Settings.jsx';

// Route configuration
const routes = {
  'management-area': ManagementDashboard,
  'performance': MenuManagement,
  'payments': MenuManagement,
  'orders': OrderManagement,
  'branch-management': BranchManagement,
  'menu-management': MenuManagement,
  'customers': CustomerManagement,
  'tutorials': MenuManagement,
  'settings': Settings,
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