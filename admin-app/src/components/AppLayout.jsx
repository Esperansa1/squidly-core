import React, { useState, useEffect } from 'react';
import { Bars3Icon } from '@heroicons/react/24/outline';
import Sidebar from './Sidebar.jsx';

const MOBILE_BREAKPOINT = 1024;

const AppLayout = ({
  children,
  activeNavItem = 'menu-management',
  onNavigate,
  className = ''
}) => {
  const [isMobile, setIsMobile] = useState(window.innerWidth < MOBILE_BREAKPOINT);
  const [sidebarExpanded, setSidebarExpanded] = useState(window.innerWidth >= MOBILE_BREAKPOINT);

  useEffect(() => {
    const handleResize = () => {
      const mobile = window.innerWidth < MOBILE_BREAKPOINT;
      setIsMobile(mobile);
      // Auto-collapse sidebar when switching to mobile
      if (mobile) {
        setSidebarExpanded(false);
      }
    };
    window.addEventListener('resize', handleResize);
    return () => window.removeEventListener('resize', handleResize);
  }, []);

  const handleNavigation = (itemId, itemLabel) => {
    if (onNavigate) {
      onNavigate(itemId, itemLabel);
    }
    // Close sidebar on mobile after navigation
    if (isMobile) {
      setSidebarExpanded(false);
    }
  };

  const contentMargin = isMobile ? '0px' : (sidebarExpanded ? '280px' : '70px');

  return (
    <div className={`h-screen flex ${className}`} dir="rtl">
      {/* Sidebar */}
      <Sidebar
        activeItem={activeNavItem}
        onNavigate={handleNavigation}
        onToggle={setSidebarExpanded}
        isExpanded={sidebarExpanded}
        isMobile={isMobile}
      />

      {/* Main Content Area */}
      <div
        className="flex-1 transition-all duration-300 ease-out overflow-auto"
        style={{ marginRight: contentMargin }}
      >
        {/* Mobile hamburger button */}
        {isMobile && !sidebarExpanded && (
          <button
            onClick={() => setSidebarExpanded(true)}
            className="fixed top-4 right-4 z-40 w-10 h-10 flex items-center justify-center bg-white rounded-lg shadow-md border border-gray-200"
            aria-label="פתח תפריט"
          >
            <Bars3Icon className="w-5 h-5 text-gray-600" />
          </button>
        )}

        <main className="h-full w-full">
          {children}
        </main>
      </div>
    </div>
  );
};

export default AppLayout;
