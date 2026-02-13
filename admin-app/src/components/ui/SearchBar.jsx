import React from 'react';
import MagnifyingGlassIcon from '@heroicons/react/24/outline/MagnifyingGlassIcon';
import { DEFAULT_THEME } from '../../config/theme.js';

const SearchBar = ({ 
  value, 
  onChange, 
  placeholder = 'חפש...', 
  className = '' 
}) => {
  const theme = DEFAULT_THEME;

  return (
    <div className={`relative ${className}`}>
      <MagnifyingGlassIcon className="absolute right-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
      <input
        type="text"
        placeholder={placeholder}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="w-full pr-10 pl-4 h-10 border border-gray-300 rounded-lg focus:ring-2 focus:border-transparent"
        style={{
          focusRingColor: theme.primary_color,
          '--tw-ring-color': theme.primary_color
        }}
      />
    </div>
  );
};

export default SearchBar;