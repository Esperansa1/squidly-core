/**
 * LoadingState Component
 *
 * Reusable loading display for data fetching states
 */

import React from 'react';
import { Spinner } from '../atoms';
import { DEFAULT_THEME } from '../../../config/theme.js';

const LoadingState = ({
  message = 'טוען נתונים...',
  spinnerSize = 'md',
  className = '',
  ...props
}) => {
  const theme = DEFAULT_THEME;

  return (
    <div
      className={`flex items-center justify-center h-64 ${className}`}
      style={{ color: theme.text_muted }}
      {...props}
    >
      <div className="text-center">
        <div className="flex justify-center mb-4">
          <Spinner size={spinnerSize} color="primary" />
        </div>
        {message && <p>{message}</p>}
      </div>
    </div>
  );
};

export default LoadingState;
