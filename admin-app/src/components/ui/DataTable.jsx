import React from 'react';
import ThemedRadioButton from './ThemedRadioButton.jsx';
import { DEFAULT_THEME } from '../../config/theme.js';

const DataTable = ({ 
  columns, 
  data, 
  selectedId, 
  onSelectionChange, 
  loading = false, 
  error = null,
  emptyMessage = 'אין נתונים להצגה'
}) => {
  const theme = DEFAULT_THEME;

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64 text-gray-500">
        <div className="text-center">
          <div className="w-8 h-8 border-4 border-gray-300 border-t-red-600 rounded-full animate-spin mx-auto mb-4"></div>
          <p>טוען נתונים...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex items-center justify-center h-64 text-red-500">
        <div className="text-center">
          <div className="text-red-600 text-xl mb-4">⚠️</div>
          <p className="text-red-600">שגיאה בטעינה: {error}</p>
        </div>
      </div>
    );
  }

  if (!data || data.length === 0) {
    return (
      <div className="flex items-center justify-center h-64 text-gray-500">
        <div className="text-center">
          <div className="text-4xl mb-4">📋</div>
          <p>{emptyMessage}</p>
        </div>
      </div>
    );
  }

  return (
    <div className="h-full flex flex-col overflow-hidden">
      {/* Table Header */}
      <div className="flex-shrink-0 flex items-center pb-2 px-2 border-b border-gray-200">
        <div className="flex justify-center" style={{ width: '40px' }}>
          <span className="text-sm text-gray-700 font-semibold">
            בחר
          </span>
        </div>
        {columns.map((column, index) => (
          <div 
            key={column.key} 
            className={`${column.className || ''}`}
            style={{ 
              width: column.width, 
              minWidth: column.width,
              maxWidth: column.width,
              overflow: 'hidden',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              textAlign: 'center',
              ...(column.headerStyle || {}) 
            }}
          >
            <span className="text-sm text-gray-700 font-semibold">
              {column.label}
            </span>
          </div>
        ))}
      </div>

      {/* Table Rows */}
      <div className="flex-1 overflow-y-auto">
        <div className="space-y-1">
          {data.map((item) => (
            <div 
              key={item.id}
              className="flex items-center py-3 px-2 hover:bg-gray-50 rounded-lg transition-colors cursor-pointer"
              style={{ 
                borderBottom: `1px solid ${theme.divider_color}`,
                minHeight: '60px'
              }}
              onClick={() => onSelectionChange(item.id)}
            >
              <div className="flex justify-center" style={{ width: '40px' }}>
                <ThemedRadioButton
                  name="table-selection"
                  value={item.id}
                  checked={selectedId === item.id}
                  onChange={() => onSelectionChange(item.id)}
                />
              </div>
              {columns.map((column) => (
                <div 
                  key={column.key}
                  className={`${column.className || ''}`}
                  style={{ 
                    width: column.width,
                    minWidth: column.width,
                    maxWidth: column.width,
                    overflow: 'hidden',
                    textOverflow: 'ellipsis',
                    whiteSpace: 'nowrap',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    textAlign: 'center',
                    ...(column.cellStyle || {}) 
                  }}
                >
                  {column.render ? column.render(item[column.key], item) : item[column.key]}
                </div>
              ))}
              
            </div>
          ))}
        </div>
      </div>
    </div>
  );
};

export default DataTable;