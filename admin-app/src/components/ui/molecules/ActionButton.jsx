/**
 * ActionButton Component
 * 
 * Molecular component combining Button with Icon
 */

import React from 'react';
import { Button } from '../atoms';

const ActionButton = ({ 
  icon: Icon,
  children,
  variant = 'ghost',
  size = 'icon',
  className = '',
  ...props 
}) => {
  return (
    <Button
      variant={variant}
      size={size}
      className={`${className}`}
      {...props}
    >
      {Icon && <Icon className="w-4 h-4" />}
      {children}
    </Button>
  );
};

export default ActionButton;