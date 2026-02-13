/**
 * ErrorState Component
 *
 * Reusable error display for failed data fetching
 */

import React from 'react';
import ExclamationTriangleIcon from '@heroicons/react/24/outline/ExclamationTriangleIcon';
import { Button } from '../atoms';
import { DEFAULT_THEME } from '../../../config/theme.js';

const ErrorState = ({
  title = 'שגיאה בטעינה',
  message = '',
  onRetry = null,
  retryText = 'נסה שוב',
  className = '',
  ...props
}) => {
  const theme = DEFAULT_THEME;

  return (
    <div
      className={`flex items-center justify-center h-64 ${className}`}
      {...props}
    >
      <div className="text-center">
        <div className="flex justify-center mb-4">
          <ExclamationTriangleIcon
            className="w-12 h-12"
            style={{ color: theme.danger_color }}
          />
        </div>
        <p className="text-lg font-medium mb-2" style={{ color: theme.danger_color }}>
          {title}
        </p>
        {message && (
          <p className="text-sm mb-4" style={{ color: theme.text_secondary }}>
            {message}
          </p>
        )}
        {onRetry && (
          <Button
            variant="outline"
            size="sm"
            onClick={onRetry}
          >
            {retryText}
          </Button>
        )}
      </div>
    </div>
  );
};

export default ErrorState;
