import React, { useRef, useLayoutEffect, useState } from 'react';
import { DEFAULT_THEME } from '../../config/theme.js';

const TabSelector = ({
  tabs = [],
  activeTab,
  onTabChange = () => {},
  className = ''
}) => {
  const theme = DEFAULT_THEME;
  const containerRef = useRef(null);
  const buttonRefs = useRef([]);
  const [backgroundStyle, setBackgroundStyle] = useState({});

  if (tabs.length === 0) return null;

  // Calculate background position based on actual button dimensions
  useLayoutEffect(() => {
    const activeIndex = tabs.indexOf(activeTab);
    if (activeIndex === -1 || !buttonRefs.current[activeIndex]) return;

    const activeButton = buttonRefs.current[activeIndex];
    const containerRect = containerRef.current?.getBoundingClientRect();
    const buttonRect = activeButton.getBoundingClientRect();

    if (containerRect && buttonRect) {
      // Calculate position from right for RTL
      const rightOffset = containerRect.right - buttonRect.right;

      setBackgroundStyle({
        backgroundColor: 'var(--theme-primary-color)',
        width: `${buttonRect.width}px`,
        right: `${rightOffset}px`,
        top: '0px',
        bottom: '0px',
        borderRadius: '0.5rem', // rounded-lg
      });
    }
  }, [activeTab, tabs, theme.primary_color]);

  return (
    <div ref={containerRef} className={`relative inline-flex bg-white rounded-lg shadow-sm ${className}`} style={{ height: '40px' }}>
      {/* Sliding Background */}
      <div
        className="absolute rounded-md transition-all duration-300 ease-out"
        style={backgroundStyle}
      />

      {/* Tab Buttons */}
      {tabs.map((tab, index) => (
        <button
          key={tab}
          ref={(el) => (buttonRefs.current[index] = el)}
          onClick={() => onTabChange(tab)}
          className={`relative z-10 px-4 text-sm font-semibold transition-colors focus:outline-none whitespace-nowrap flex items-center justify-center ${
            activeTab === tab
              ? 'text-white'
              : ''
          }`}
          style={{
            color: activeTab === tab ? 'white' : 'var(--theme-text-secondary)',
            height: '40px'
          }}
        >
          {tab}
        </button>
      ))}
    </div>
  );
};

export default TabSelector;