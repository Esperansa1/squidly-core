import React, { useState } from 'react';

// Import all page components
import MenuManagement from './components/MenuManagement.jsx';
import ManagementAreaPage from './components/pages/ManagementAreaPage.jsx';
import PerformancePage from './components/pages/PerformancePage.jsx';
import PaymentsPage from './components/pages/PaymentsPage.jsx';
import OrdersPage from './components/pages/OrdersPage.jsx';
import SuppliersPage from './components/pages/SuppliersPage.jsx';
import CustomersPage from './components/pages/CustomersPage.jsx';
import TutorialsPage from './components/pages/TutorialsPage.jsx';
import SettingsPage from './components/pages/SettingsPage.jsx';

// Route configuration mapping navigation items to components
const routes = {
  'management-area': ManagementAreaPage,
  'performance': PerformancePage,
  'payments': PaymentsPage,
  'orders': OrdersPage,
  'suppliers': SuppliersPage,
  'menu-management': MenuManagement,
  'customers': CustomersPage,
  'tutorials': TutorialsPage,
  'settings': SettingsPage,
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