/**
 * ActionButton Component
 *
 * Consolidated molecular component for icon-based action buttons used in CED (Create/Edit/Delete) patterns
 */

import React from 'react';
import { IconButton } from '../atoms';
import { DEFAULT_THEME } from '../../../config/theme.js';

const ActionButton = ({
  icon: Icon,
  variant = 'outline',
  onClick,
  disabled = false,
  tooltip = '',
  size = 'md',
  className = '',
  ...props
}) => {
  const theme = DEFAULT_THEME;

  // Get variant styles that match DataSection's inline ActionButton
  const getVariantStyles = () => {
    switch (variant) {
      case 'primary':
        return {
          variant: 'primary'
        };
      case 'secondary':
        return {
          variant: 'outline'
        };
      case 'error':
        return {
          variant: 'error'
        };
      default:
        return {
          variant: 'outline'
        };
    }
  };

  const variantProps = getVariantStyles();

  return (
    <IconButton
      icon={Icon}
      onClick={onClick}
      disabled={disabled}
      tooltip={tooltip}
      size={size}
      className={className}
      {...variantProps}
      {...props}
    />
  );
};

export default ActionButton;