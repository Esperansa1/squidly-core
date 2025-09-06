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
  };

  return (
    <div className={`h-screen flex ${className}`} dir="rtl">
      {/* Sidebar */}
      <Sidebar 
        activeItem={activeNavItem}
        onNavigate={handleNavigation}
        onToggle={setSidebarExpanded}
        isExpanded={sidebarExpanded}
      />

      {/* Main Content Area */}
      <div 
        className="flex-1 transition-all duration-300 ease-out overflow-hidden"
        style={{ 
          marginRight: sidebarExpanded ? '280px' : '70px'
        }}
      >
        <main className="h-full w-full">
          {children}
        </main>
      </div>
    </div>
  );
};

export default AppLayout;