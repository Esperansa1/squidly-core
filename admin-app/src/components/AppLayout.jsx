import React, { useState, useEffect } from 'react';
import Sidebar from './Sidebar.jsx';

const AppLayout = ({ 
  children, 
  activeNavItem = 'ניהול תפריט',
  onNavigate,
  className = '' 
}) => {
  const [sidebarExpanded, setSidebarExpanded] = useState(true);

  const handleNavigation = (itemId, itemLabel) => {
    if (onNavigate) {
      onNavigate(itemId, itemLabel);
    }
    // Navigation is now handled by the router
    console.log('Navigated to:', itemId, itemLabel);
  };

  return (
    <div className={`min-h-screen bg-gray-50 ${className}`} dir="rtl">
      {/* Sidebar */}
      <Sidebar 
        activeItem={activeNavItem}
        onNavigate={handleNavigation}
        onToggle={setSidebarExpanded}
        isExpanded={sidebarExpanded}
      />

      {/* Main Content Area */}
      <div 
        className="transition-all duration-300 ease-out"
        style={{ 
          marginRight: sidebarExpanded ? '280px' : '70px'
        }}
      >
        <main className="min-h-screen">
          {children}
        </main>
      </div>
    </div>
  );
};

export default AppLayout;