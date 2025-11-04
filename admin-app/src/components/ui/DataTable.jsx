import React from 'react';
import ThemedRadioButton from './ThemedRadioButton.jsx';
import Pagination from './molecules/Pagination.jsx';
import { DEFAULT_THEME } from '../../config/theme.js';

const DataTable = ({
  columns,
  data,
  selectedId,
  onSelectionChange,
  loading = false,
  error = null,
  emptyMessage = 'אין נתונים להצגה',
  // Pagination props
  showPagination = false,
  currentPage = 1,
  itemsPerPage = 10,
  totalItems = 0,
  onPageChange,
  onItemsPerPageChange
}) => {
  const theme = DEFAULT_THEME;

  // Note: Don't return early for loading - show overlay instead
  // This keeps the table structure and pagination visible

  // Don't return early - render table structure with pagination even when empty

  // Calculate minimum table width based on column widths
  const calculateMinWidth = () => {
    const columnWidths = columns.reduce((total, column) => {
      const width = parseInt(column.width) || 150; // Default width if not specified
      return total + width;
    }, 0);
    return columnWidths + 40 + (columns.length * 24); // Add selection column + padding
  };

  const minTableWidth = calculateMinWidth();

  return (
    <div className="h-full flex flex-col relative">
      {/* Loading overlay */}
      {loading && (
        <div className="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center z-20">
          <div className="flex flex-col items-center gap-2">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
            <span className="text-sm text-gray-600">טוען...</span>
          </div>
        </div>
      )}

      {/* Table Container with synchronized scrolling - both horizontal and vertical */}
      <div className="flex-1 overflow-x-auto overflow-y-auto scrollbar-hide min-h-0">
        <div style={{ minWidth: `${minTableWidth}px` }} className="flex flex-col h-full">
          {/* Table Header - Fixed at top */}
          <div className="flex-shrink-0 sticky top-0 bg-white z-10 border-b border-gray-200 pb-2 pt-4 px-6 mx-2">
            <div className="flex items-center">
              <div className="flex justify-center flex-shrink-0" style={{ width: '40px' }}>
                <span className="text-sm text-gray-700 font-semibold">
                  בחר
                </span>
              </div>
              {columns.map((column, index) => (
                <div
                  key={column.key}
                  className={`${column.className || ''} px-3 flex-shrink-0`}
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
                  <span className="text-sm text-gray-700 font-semibold px-2">
                    {column.title || column.label}
                  </span>
                </div>
              ))}
            </div>
          </div>

          {/* Table Rows */}
          <div className="flex-1 px-6">
            {error ? (
              <div className="flex items-center justify-center h-64 text-red-500">
                <div className="text-center">
                  <div className="text-red-600 text-xl mb-4">⚠️</div>
                  <p className="text-red-600">שגיאה בטעינה: {error}</p>
                </div>
              </div>
            ) : !data || data.length === 0 ? (
              <div className="flex items-center justify-center h-64 text-gray-500">
                <div className="text-center">
                  <div className="text-4xl mb-4">📋</div>
                  <p>{emptyMessage}</p>
                </div>
              </div>
            ) : (
              <div className="space-y-3 pt-4 pb-8">
                {data.map((item) => (
                  <div
                    key={item.id}
                    className="flex items-center py-4 px-2 hover:bg-gray-50 rounded-lg transition-colors cursor-pointer"
                    style={{
                      borderBottom: `1px solid ${theme.divider_color}`,
                      minHeight: '70px'
                    }}
                    onClick={() => onSelectionChange(item)}
                  >
                    <div className="flex justify-center flex-shrink-0" style={{ width: '40px' }}>
                      <ThemedRadioButton
                        name="table-selection"
                        value={item.id}
                        checked={selectedId === item.id}
                        onChange={() => onSelectionChange(item)}
                      />
                    </div>
                    {columns.map((column) => (
                      <div
                        key={column.key}
                        className={`${column.className || ''} px-3 flex-shrink-0`}
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
                        <div className="px-2 w-full">
                          {column.render ? column.render(item[column.key], item) : item[column.key]}
                        </div>
                      </div>
                    ))}
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Pagination - Sticky at bottom */}
      {showPagination && (
        <div className="flex-shrink-0 sticky bottom-0 bg-white border-t border-gray-200 z-10">
          <Pagination
            currentPage={currentPage}
            totalItems={totalItems}
            itemsPerPage={itemsPerPage}
            onPageChange={onPageChange}
            onItemsPerPageChange={onItemsPerPageChange}
          />
        </div>
      )}
    </div>
  );
};

export default DataTable;