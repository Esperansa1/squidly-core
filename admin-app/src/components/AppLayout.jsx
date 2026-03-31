import React, { useState, useEffect } from 'react';
import { Bars3Icon } from '@heroicons/react/24/outline';
import Sidebar from './Sidebar.jsx';
import api from '../services/api.js';

const MOBILE_BREAKPOINT = 1024;

const AppLayout = ({
  children,
  activeNavItem = 'menu-management',
  onNavigate,
  className = ''
}) => {
  const [isMobile, setIsMobile] = useState(window.innerWidth < MOBILE_BREAKPOINT);
  const [sidebarExpanded, setSidebarExpanded] = useState(window.innerWidth >= MOBILE_BREAKPOINT);
  const [restaurantName, setRestaurantName] = useState(() => api.getRestaurantName());

  useEffect(() => {
    const name = api.getRestaurantName();
    if (name) {
      setRestaurantName(name);
    } else {
      const timer = setTimeout(() => setRestaurantName(api.getRestaurantName()), 1500);
      return () => clearTimeout(timer);
    }

    const handleSettingsUpdate = () => setRestaurantName(api.getRestaurantName());
    window.addEventListener('squidly:settings-updated', handleSettingsUpdate);
    return () => window.removeEventListener('squidly:settings-updated', handleSettingsUpdate);
  }, []);

  useEffect(() => {
    const handleResize = () => {
      const mobile = window.innerWidth < MOBILE_BREAKPOINT;
      setIsMobile(mobile);
      if (mobile) setSidebarExpanded(false);
    };
    window.addEventListener('resize', handleResize);
    return () => window.removeEventListener('resize', handleResize);
  }, []);

  const handleNavigation = (itemId, itemLabel) => {
    if (onNavigate) onNavigate(itemId, itemLabel);
    if (isMobile) setSidebarExpanded(false);
  };

  return (
    // h-screen + flex-col: total height = 100vh, split between mobile bar and content
    <div className={`h-screen flex flex-col ${className}`} dir="rtl">
      {/* Mobile Top Bar — in-flow so it takes up real space from 100vh */}
      {isMobile && (
        <div
          className="flex-shrink-0 bg-white border-b border-gray-200 shadow-sm z-30 flex items-center justify-between px-4"
          style={{ height: '56px' }}
        >
          <span className="text-lg font-bold text-gray-900">{restaurantName || 'Squidly'}</span>
          <button
            onClick={() => setSidebarExpanded(true)}
            className="w-10 h-10 flex items-center justify-center text-gray-600 hover:bg-gray-100 rounded-lg"
            aria-label="פתח תפריט"
          >
            <Bars3Icon className="w-6 h-6" />
          </button>
        </div>
      )}

      {/* Content area: sidebar (fixed, out-of-flow) + main content */}
      <div className="flex-1 min-h-0">
        {/* Sidebar (position:fixed — doesn't participate in flow) */}
        <Sidebar
          activeItem={activeNavItem}
          onNavigate={handleNavigation}
          onToggle={setSidebarExpanded}
          isExpanded={sidebarExpanded}
          isMobile={isMobile}
        />

        {/* Main content — full width on mobile, offset by sidebar on desktop */}
        <div
          className="h-full transition-all duration-300 ease-out"
          style={{
            marginRight: isMobile ? '0px' : (sidebarExpanded ? '280px' : '70px'),
          }}
        >
          <main className="h-full w-full">
            {children}
          </main>
        </div>
      </div>
    </div>
  );
};

export default AppLayout;
