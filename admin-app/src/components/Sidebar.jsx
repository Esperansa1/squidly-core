import React, { useState, useRef, useEffect } from 'react';
import {
  Bars3Icon,
  ChevronLeftIcon,
  ChevronRightIcon,
  MagnifyingGlassIcon,
  Squares2X2Icon,
  ClipboardDocumentListIcon,
  CalendarIcon,
  ListBulletIcon,
  UserGroupIcon,
  InformationCircleIcon,
  CogIcon,
  ChevronDownIcon,
  BuildingStorefrontIcon,
  ArrowRightOnRectangleIcon,
} from '@heroicons/react/24/outline';
import { useRouter } from '../router.jsx';
import { DEFAULT_THEME } from '../config/theme.js';
import api from '../services/api.js';

const Sidebar = ({
  activeItem = 'menu-management',
  onNavigate = () => {},
  onToggle = () => {},
  isExpanded = true,
  isMobile = false,
  className = ''
}) => {
  const theme = DEFAULT_THEME;
  const { navigate } = useRouter();
  const [showUserMenu, setShowUserMenu] = useState(false);
  const userMenuRef = useRef(null);
  const [restaurantName, setRestaurantName] = useState(() => api.getRestaurantName());

  useEffect(() => {
    const name = api.getRestaurantName();
    if (name) {
      setRestaurantName(name);
    } else {
      // api.init() may not have resolved yet; poll briefly
      const timer = setTimeout(() => setRestaurantName(api.getRestaurantName()), 1500);
      return () => clearTimeout(timer);
    }

    const handleSettingsUpdate = () => setRestaurantName(api.getRestaurantName());
    window.addEventListener('squidly:settings-updated', handleSettingsUpdate);
    return () => window.removeEventListener('squidly:settings-updated', handleSettingsUpdate);
  }, []);

  const toggleSidebar = () => {
    onToggle(!isExpanded);
  };

  const handleLogout = () => {
    // Get logout URL from wpConfig or construct it
    const logoutUrl = window.wpConfig?.logoutUrl || '/wp-login.php?action=logout';
    window.location.href = logoutUrl;
  };

  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (userMenuRef.current && !userMenuRef.current.contains(event.target)) {
        setShowUserMenu(false);
      }
    };

    if (showUserMenu) {
      document.addEventListener('mousedown', handleClickOutside);
    }

    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [showUserMenu]);

  const navigationItems = {
    'ראשי': [
      { id: 'management-area', icon: Squares2X2Icon, label: 'איזור ניהול' },
      { id: 'orders', icon: ClipboardDocumentListIcon, label: 'הזמנות' },
    ],
    'ניהול מסעדה': [
      { id: 'branch-management', icon: BuildingStorefrontIcon, label: 'ניהול סניפים' },
      { id: 'menu-management', icon: ListBulletIcon, label: 'ניהול תפריט' },
      { id: 'customers', icon: UserGroupIcon, label: 'ניהול לקוחות' },
    ],
    'מידע נוסף': [
      { id: 'tutorials', icon: InformationCircleIcon, label: 'סרטוני הדרכה' },
      { id: 'settings', icon: CogIcon, label: 'הגדרות' },
    ],
  };

  const handleItemClick = (itemId, itemLabel) => {
    navigate(itemId);
    onNavigate(itemId, itemLabel);
  };

  const NavItem = ({ item, isActive }) => {
    const IconComponent = item.icon;
    
    // Clean navigation item style — CSS variable references update live
    const getNavItemStyle = () => ({
      backgroundColor: isActive ? 'var(--theme-primary-color)' : 'transparent',
      color: isActive ? 'white' : 'var(--theme-text-primary)'
    });
    
    return (
      <button
        onClick={() => handleItemClick(item.id, item.label)}
        className={`w-full flex items-center gap-3 px-4 py-3 transition-colors duration-200 hover:bg-opacity-20 ${
          isExpanded ? 'justify-start' : 'justify-center px-2'
        } ${!isActive ? 'hover:bg-red-100' : ''}`}
        style={getNavItemStyle()}
      >
        <IconComponent className={`w-5 h-5 flex-shrink-0 ${isActive ? 'text-white' : 'text-gray-600'}`} />
        {isExpanded && (
          <span className="text-sm font-semibold">{item.label}</span>
        )}
      </button>
    );
  };

  const SectionDivider = () => (
    <div className="border-t border-gray-200 my-2" />
  );

  return (
    <div className={`${className}`}>
      <div
        className={`fixed top-0 right-0 h-full bg-white border-l border-gray-200 shadow-sm transition-all duration-300 ease-out z-50 ${
          isMobile && !isExpanded ? 'translate-x-full' : 'translate-x-0'
        }`}
        style={{ width: isMobile ? '280px' : (isExpanded ? '280px' : '70px') }}
      >
        {/* Toggle Button */}
        <button
          onClick={toggleSidebar}
          className="absolute top-4 left-4 w-8 h-8 flex items-center justify-center text-gray-600 hover:text-gray-800 transition-colors"
        >
          {isExpanded ? (
            <ChevronRightIcon className="w-5 h-5" />
          ) : (
            <Bars3Icon className="w-5 h-5" />
          )}
        </button>

        <div className="pt-16 px-4 h-full flex flex-col" dir="rtl">
          {/* Main Content - Takes remaining space */}
          <div className="flex-grow">
            {/* Logo Area */}
            <div className={`flex items-center gap-3 mb-6 ${isExpanded ? '' : 'justify-center'}`}>
              <div className="w-10 h-10 rounded flex items-center justify-center flex-shrink-0" style={{backgroundColor: 'var(--theme-primary-color)'}}>
                <div className="w-6 h-0.5 bg-white transform rotate-45"></div>
                <div className="w-6 h-0.5 bg-white transform -rotate-45 -ml-6"></div>
              </div>
              {isExpanded && (
                <span className="text-xl font-bold text-gray-900" style={{ fontWeight: 800 }}>{restaurantName || 'Squidly'}</span>
              )}
            </div>

            {/* Search Bar */}
            <div className={`mb-6 ${isExpanded ? '' : 'flex justify-center'}`}>
              {isExpanded ? (
                <div className="relative">
                  <MagnifyingGlassIcon className="absolute right-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
                  <input
                    type="text"
                    placeholder="חיפוש"
                    className="w-full pl-4 pr-10 py-2 bg-gray-100 rounded-lg text-sm text-gray-700 placeholder-gray-500 outline-none focus:bg-white focus:border focus:border-[color:var(--theme-primary-color)]"
                  />
                </div>
              ) : (
                <div className="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                  <MagnifyingGlassIcon className="w-4 h-4 text-gray-400" />
                </div>
              )}
            </div>

            {/* Navigation Sections */}
            <nav className="space-y-1">
              {Object.entries(navigationItems).map(([sectionTitle, items], sectionIndex) => (
                <div key={sectionTitle}>
                  {sectionIndex > 0 && <SectionDivider />}
                  
                  {/* Section Title */}
                  {isExpanded && (
                    <div className="px-4 py-2">
                      <h3 className="text-xs text-gray-500 uppercase tracking-wider" style={{ fontWeight: 600 }}>
                        {sectionTitle}
                      </h3>
                    </div>
                  )}

                  {/* Section Items */}
                  <div className="space-y-1">
                    {items.map((item) => (
                      <NavItem
                        key={item.id}
                        item={item}
                        isActive={activeItem === item.id}
                      />
                    ))}
                  </div>
                </div>
              ))}
            </nav>
          </div>

          {/* User Section - Fixed at Bottom */}
          <div className="flex-shrink-0 relative" ref={userMenuRef}>
            <SectionDivider />
            <div className={`pt-4 pb-4 ${isExpanded ? '' : 'flex justify-center'}`}>
              {isExpanded ? (
                <button
                  onClick={() => setShowUserMenu(!showUserMenu)}
                  className="w-full flex items-center gap-3 px-4 py-3 text-gray-800 hover:bg-gray-50 transition-colors rounded-lg"
                >
                  <div className="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0" style={{backgroundColor: 'var(--theme-primary-color-20)'}}>
                    <span className="text-sm" style={{ fontWeight: 800, color: 'var(--theme-primary-color)' }}>נ</span>
                  </div>
                  <div className="flex-1 text-right">
                    <div className="text-sm text-gray-900" style={{ fontWeight: 800 }}>ניסים דיין</div>
                    <div className="text-xs text-gray-500" style={{ fontWeight: 400 }}>מנהל</div>
                  </div>
                  <ChevronDownIcon className={`w-4 h-4 text-gray-400 transition-transform ${showUserMenu ? 'rotate-180' : ''}`} />
                </button>
              ) : (
                <div className="w-8 h-8 rounded-full flex items-center justify-center" style={{backgroundColor: 'var(--theme-primary-color-20)'}}>
                  <span className="text-sm" style={{ fontWeight: 800, color: 'var(--theme-primary-color)' }}>נ</span>
                </div>
              )}
            </div>

            {/* User Dropdown Menu */}
            {showUserMenu && isExpanded && (
              <div className="absolute bottom-full left-0 right-0 bg-white border-t border-gray-200">
                <button
                  onClick={handleLogout}
                  className="w-full flex items-center gap-3 px-8 py-3 text-gray-700 hover:bg-gray-50 transition-colors text-right"
                >
                  <ArrowRightOnRectangleIcon className="w-5 h-5 text-gray-500 flex-shrink-0" />
                  <span className="text-sm font-medium">התנתק</span>
                </button>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Overlay for mobile - covers content when sidebar is open */}
      {isMobile && isExpanded && (
        <div
          className="fixed inset-0 bg-black bg-opacity-50 z-40"
          onClick={toggleSidebar}
        />
      )}
    </div>
  );
};

export default Sidebar;