/**
 * Button Component
 * 
 * Base atomic component with consistent theming and no focus outlines
 */

import React from 'react';
import { DEFAULT_THEME } from '../../../config/theme.js';

const Button = React.forwardRef(({ 
  variant = 'primary',
  size = 'md',
  className = '',
  children, 
  disabled = false,
  onClick,
  type = 'button',
  ...props 
}, ref) => {
  const theme = DEFAULT_THEME;

  // Base styles - no focus outlines
  const baseStyles = "inline-flex items-center justify-center font-medium cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed";
  
  // Conditional transition - exclude for tab buttons  
  const transitionClass = className?.includes('tab-button') ? '' : 'transition-all duration-200';
  
  // Size variants
  const sizeStyles = {
    sm: "px-3 py-1.5 text-sm rounded",
    md: "px-4 py-2 text-base rounded-lg", 
    lg: "px-6 py-3 text-lg rounded-lg",
    icon: "p-2 rounded-lg",
  };

  // Variant styles
  const getVariantStyles = () => {
    switch (variant) {
      case 'primary':
        return {
          backgroundColor: theme.primary_color,
          color: 'white',
        };
      case 'secondary':
        return {
          backgroundColor: 'transparent',
          color: theme.primary_color,
          border: `1px solid ${theme.primary_color}`,
        };
      case 'outline':
        return {
          backgroundColor: theme.bg_white,
          color: theme.text_primary,
          border: `1px solid ${theme.border_color}`,
        };
      case 'ghost':
        return {
          backgroundColor: 'transparent',
          color: theme.text_secondary,
        };
      case 'success':
        return {
          backgroundColor: theme.success_color,
          color: 'white',
        };
      case 'warning':
        return {
          backgroundColor: theme.warning_color,
          color: 'white',
        };
      case 'error':
        return {
          backgroundColor: theme.danger_color,
          color: 'white',
        };
      default:
        return {
          backgroundColor: theme.primary_color,
          color: 'white',
        };
    }
  };

  return (
    <button
      ref={ref}
      type={type}
      className={`${baseStyles} ${transitionClass} ${sizeStyles[size]} ${className}`}
      style={getVariantStyles()}
      disabled={disabled}
      onClick={onClick}
      {...props}
    >
      {children}
    </button>
  );
});

Button.displayName = 'Button';

export default Button;