/**
 * StatusBadge Component
 *
 * Badge with theme-aware status colors and optional icon
 */

import React from 'react';
import { Badge } from '../atoms';
import {
  CheckCircleIcon,
  XCircleIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon
} from '@heroicons/react/24/solid';

const StatusBadge = ({
  status = 'default',
  children,
  showIcon = true,
  size = 'md',
  rounded = true,
  className = '',
  ...props
}) => {
  // Map status to variant
  const statusVariantMap = {
    active: 'success',
    inactive: 'default',
    pending: 'warning',
    success: 'success',
    error: 'error',
    warning: 'warning',
    info: 'info',
    default: 'default'
  };

  // Map status to icon
  const statusIconMap = {
    active: CheckCircleIcon,
    success: CheckCircleIcon,
    error: XCircleIcon,
    warning: ExclamationTriangleIcon,
    info: InformationCircleIcon,
    pending: InformationCircleIcon
  };

  const variant = statusVariantMap[status] || 'default';
  const Icon = statusIconMap[status];

  // Icon size based on badge size
  const iconSizeMap = {
    sm: 'w-3 h-3',
    md: 'w-4 h-4',
    lg: 'w-5 h-5'
  };
  const iconSize = iconSizeMap[size] || 'w-4 h-4';

  return (
    <Badge
      variant={variant}
      size={size}
      rounded={rounded}
      className={`gap-1 ${className}`}
      {...props}
    >
      {showIcon && Icon && <Icon className={iconSize} />}
      {children}
    </Badge>
  );
};

export default StatusBadge;
