/**
 * EmptyState Component
 *
 * Reusable empty state display for lists and tables
 */

import React from 'react';
import { DEFAULT_THEME } from '../../../config/theme.js';

const EmptyState = ({
  icon = '📋',
  title = 'אין נתונים',
  message = '',
  action = null,
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
        <div className="text-4xl mb-4">{icon}</div>
        <p className="text-lg font-medium mb-2" style={{ color: theme.text_secondary }}>
          {title}
        </p>
        {message && (
          <p className="text-sm mb-4" style={{ color: theme.text_muted }}>
            {message}
          </p>
        )}
        {action && <div className="mt-4">{action}</div>}
      </div>
    </div>
  );
};

export default EmptyState;
