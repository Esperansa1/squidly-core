import React, { useState } from 'react';

// Import content components for SPA behavior
import MenuManagementSimple from './components/MenuManagementSimple.jsx';
import ManagementAreaContent from './components/pages/ManagementAreaContent.jsx';
import PerformanceContent from './components/pages/PerformanceContent.jsx';
import PaymentsContent from './components/pages/PaymentsContent.jsx';
import OrdersContent from './components/pages/OrdersContent.jsx';
import SuppliersContent from './components/pages/SuppliersContent.jsx';
import CustomersContent from './components/pages/CustomersContent.jsx';
import TutorialsContent from './components/pages/TutorialsContent.jsx';
import SettingsContent from './components/pages/SettingsContent.jsx';

// Route configuration mapping navigation items to components
const routes = {
  'management-area': ManagementAreaContent,
  'performance': PerformanceContent,
  'payments': PaymentsContent,
  'orders': OrdersContent,
  'suppliers': SuppliersContent,
  'menu-management': MenuManagementSimple,
  'customers': CustomersContent,
  'tutorials': TutorialsContent,
  'settings': SettingsContent,
};

// Simple router context
export const RouterContext = React.createContext();

// Router provider component
export const RouterProvider = ({ children }) => {
  const [currentRoute, setCurrentRoute] = useState('menu-management'); // Default route

  const navigate = (route) => {
    setCurrentRoute(route);
    console.log('Navigating to:', route);
  };

  const getCurrentComponent = () => {
    const Component = routes[currentRoute] || MenuManagementSimple;
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