import React from 'react';
import { DEFAULT_THEME } from '../../config/theme.js';

const ManagementHeader = ({
  title,
  subtitle,
  children,
  className = '',
  showBorder = true
}) => {
  const theme = DEFAULT_THEME;

  return (
    <div
      className={`flex-shrink-0 p-6 ${className}`}
      style={{
        borderBottom: showBorder ? `1px solid ${theme.border_color}` : 'none'
      }}
    >
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold" style={{ color: theme.text_primary }}>
            {title}
          </h1>
          {subtitle && (
            <p className="mt-1" style={{ color: theme.text_secondary }}>
              {subtitle}
            </p>
          )}
        </div>
        {children && (
          <div className="flex items-center gap-3">
            {children}
          </div>
        )}
      </div>
    </div>
  );
};

export default ManagementHeader;