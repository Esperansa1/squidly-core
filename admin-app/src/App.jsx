import React from 'react';
import { RouterProvider, useRouter } from './router.jsx';
import AppLayout from './components/AppLayout.jsx';
import './styles/admin.css';

/**
 * Main Admin Application Content
 */
const AppContent = () => {
  const { getCurrentComponent, navigate, currentRoute } = useRouter();
  const CurrentComponent = getCurrentComponent();
  
  // Map route IDs to active nav item labels for sidebar
  const routeToNavItem = {
    'management-area': 'איזור ניהול',
    'performance': 'מעקב ביצועים',
    'payments': 'מערכת תשלומים',
    'orders': 'ניהול הזמנות',
    'suppliers': 'ניהול ספקים',
    'menu-management': 'ניהול תפריט',
    'customers': 'ניהול לקוחות',
    'tutorials': 'מדריכים והדרכות',
    'settings': 'הגדרות מערכת',
  };
  
  const handleNavigation = (itemId, itemLabel) => {
    navigate(itemId);
  };
  
  return (
    <div className="squidly-admin-app">
      <AppLayout 
        activeNavItem={routeToNavItem[currentRoute] || 'ניהול תפריט'}
        onNavigate={handleNavigation}
      >
        <CurrentComponent />
      </AppLayout>
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