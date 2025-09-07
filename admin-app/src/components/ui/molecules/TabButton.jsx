/**
 * TabButton Component
 * 
 * Specialized button for tab navigation with sliding background support
 */

import React from 'react';
import Button from '../atoms/Button.jsx';
import { DEFAULT_THEME } from '../../../config/theme.js';

const TabButton = ({ 
  children,
  isActive = false,
  onClick,
  className = '',
  ...props 
}) => {
  const theme = DEFAULT_THEME;
  
  // Override ghost variant color for active state
  const getTabStyle = () => ({
    backgroundColor: 'transparent', // Always transparent for sliding background
    color: isActive ? 'white' : theme.text_secondary
  });

  return (
    <Button
      variant="ghost"
      size="sm"
      intent="tab" // This enables tab-specific behavior
      onClick={onClick}
      className={`font-semibold ${className}`}
      style={getTabStyle()}
      {...props}
    >
      {children}
    </Button>
  );
};

export default TabButton;