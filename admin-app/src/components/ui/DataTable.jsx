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

  // Calculate minimum table width based on column widths
  const calculateMinWidth = () => {
    const columnWidths = columns.reduce((total, column) => {
      const width = parseInt(column.width) || 150;
      return total + width;
    }, 0);
    return columnWidths + 40 + (columns.length * 24); // Selection column + padding
  };

  const minTableWidth = calculateMinWidth();

  return (
    <div className="h-full flex flex-col bg-white relative">
      {/* Loading overlay */}
      {loading && (
        <div className="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center z-30">
          <div className="flex flex-col items-center gap-2">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
            <span className="text-sm text-gray-600">טוען...</span>
          </div>
        </div>
      )}

      {/* Scrollable table container - SINGLE OVERFLOW CONTAINER */}
      <div className="flex-1 overflow-auto min-h-0">
        <div style={{ minWidth: `${minTableWidth}px` }}>
          <table className="w-full border-collapse">
            {/* Fixed Header - Sticks to top of scroll container */}
            <thead className="sticky top-0 z-20 bg-white border-b border-gray-200">
              <tr>
                {/* Selection column header */}
                <th
                  className="py-4 px-6 text-center"
                  style={{ width: '40px' }}
                >
                  <span className="text-sm text-gray-700 font-semibold">
                    בחר
                  </span>
                </th>

                {/* Data column headers */}
                {columns.map((column) => (
                  <th
                    key={column.key}
                    className="py-4 px-3 text-center"
                    style={{
                      width: column.width,
                      minWidth: column.width,
                      maxWidth: column.width,
                      ...(column.headerStyle || {})
                    }}
                  >
                    <span className="text-sm text-gray-700 font-semibold">
                      {column.title || column.label}
                    </span>
                  </th>
                ))}
              </tr>
            </thead>

            {/* Scrollable Body */}
            <tbody>
              {error ? (
                <tr>
                  <td colSpan={columns.length + 1} className="py-16">
                    <div className="flex items-center justify-center text-red-500">
                      <div className="text-center">
                        <div className="text-red-600 text-xl mb-4">⚠️</div>
                        <p className="text-red-600">שגיאה בטעינה: {error}</p>
                      </div>
                    </div>
                  </td>
                </tr>
              ) : !data || data.length === 0 ? (
                <tr>
                  <td colSpan={columns.length + 1} className="py-16">
                    <div className="flex items-center justify-center text-gray-500">
                      <div className="text-center">
                        <div className="text-4xl mb-4">📋</div>
                        <p>{emptyMessage}</p>
                      </div>
                    </div>
                  </td>
                </tr>
              ) : (
                data.map((item) => (
                  <tr
                    key={item.id}
                    className="hover:bg-gray-50 transition-colors cursor-pointer border-b"
                    style={{
                      borderColor: theme.divider_color
                    }}
                    onClick={() => onSelectionChange(item)}
                  >
                    {/* Selection column */}
                    <td
                      className="py-3 px-6 text-center align-middle"
                      style={{ width: '40px' }}
                    >
                      <div className="flex justify-center">
                        <ThemedRadioButton
                          name="table-selection"
                          value={item.id}
                          checked={selectedId === item.id}
                          onChange={() => onSelectionChange(item)}
                        />
                      </div>
                    </td>

                    {/* Data columns */}
                    {columns.map((column) => (
                      <td
                        key={column.key}
                        className="py-3 px-3 text-center align-middle"
                        style={{
                          width: column.width,
                          minWidth: column.width,
                          maxWidth: column.width,
                          overflow: 'hidden',
                          textOverflow: 'ellipsis',
                          whiteSpace: 'nowrap',
                          ...(column.cellStyle || {})
                        }}
                      >
                        <div className="px-2 w-full">
                          {column.render ? column.render(item[column.key], item) : item[column.key]}
                        </div>
                      </td>
                    ))}
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Fixed Pagination - Never scrolls */}
      {showPagination && (
        <div className="flex-shrink-0 border-t border-gray-200 bg-white z-10">
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
