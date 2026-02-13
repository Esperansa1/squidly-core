import React, { useState, lazy } from 'react';

// Lazy-load content components for better performance
const MenuManagement = lazy(() => import('./components/MenuManagement.jsx'));
const BranchManagement = lazy(() => import('./components/BranchManagement.jsx'));
const CustomerManagement = lazy(() => import('./components/CustomerManagement.jsx'));
const OrderManagement = lazy(() => import('./components/OrderManagement.jsx'));
const ManagementDashboard = lazy(() => import('./components/ManagementDashboard.jsx'));
const Settings = lazy(() => import('./components/Settings.jsx'));

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