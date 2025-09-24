import React from 'react';
import { DEFAULT_THEME } from '../../config/theme.js';

const TabSelector = ({
  tabs = [],
  activeTab,
  onTabChange = () => {},
  className = ''
}) => {
  const theme = DEFAULT_THEME;

  if (tabs.length === 0) return null;

  return (
    <div className={`relative flex bg-white rounded-lg shadow-sm p-1 ${className}`}>
      {/* Sliding Background */}
      <div
        className="absolute inset-y-0 rounded-md transition-all duration-300 ease-out"
        style={{
          backgroundColor: theme.primary_color,
          width: `${100 / tabs.length}%`,
          right: `${tabs.indexOf(activeTab) * (100 / tabs.length)}%`,
        }}
      />

      {/* Tab Buttons */}
      {tabs.map((tab) => (
        <button
          key={tab}
          onClick={() => onTabChange(tab)}
          className={`relative z-10 flex-1 px-6 py-2 text-sm font-semibold transition-colors ${
            activeTab === tab
              ? 'text-white'
              : 'hover:bg-gray-50'
          }`}
          style={{
            color: activeTab === tab ? 'white' : theme.text_secondary
          }}
        >
          {tab}
        </button>
      ))}
    </div>
  );
};

export default TabSelector;