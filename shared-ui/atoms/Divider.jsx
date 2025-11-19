/**
 * Divider Component
 *
 * Horizontal or vertical separator line
 */

import React from 'react';
import { DEFAULT_THEME } from '../../../config/theme.js';

const Divider = ({
  orientation = 'horizontal',
  spacing = 'md',
  className = '',
  ...props
}) => {
  const theme = DEFAULT_THEME;

  // Spacing variants
  const spacingClasses = {
    horizontal: {
      none: '',
      sm: 'my-2',
      md: 'my-4',
      lg: 'my-6',
      xl: 'my-8'
    },
    vertical: {
      none: '',
      sm: 'mx-2',
      md: 'mx-4',
      lg: 'mx-6',
      xl: 'mx-8'
    }
  };

  // Get spacing class based on orientation
  const spacingClass = spacingClasses[orientation]?.[spacing] || spacingClasses.horizontal.md;

  // Orientation styles
  const orientationClasses = orientation === 'vertical'
    ? 'inline-block h-full w-px'
    : 'block w-full h-px';

  return (
    <hr
      className={`border-0 ${orientationClasses} ${spacingClass} ${className}`}
      style={{ backgroundColor: theme.divider_color }}
      {...props}
    />
  );
};

export default Divider;
