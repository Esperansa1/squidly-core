import React from 'react';
import { ChevronLeftIcon, ChevronRightIcon } from '@heroicons/react/24/outline';
import { DEFAULT_THEME } from '../../../config/theme.js';

/**
 * Pagination Component
 *
 * Advanced pagination control with ellipsis, page size selector, and themed styling.
 * Supports RTL layout and follows the application theme.
 */
const Pagination = ({
  currentPage = 1,
  totalItems = 0,
  itemsPerPage = 10,
  onPageChange,
  onItemsPerPageChange,
  pageSizeOptions = [10, 25, 50, 100],
  showResultsText = true,
  showPageSizeSelector = true
}) => {
  const theme = DEFAULT_THEME;

  // Calculate total pages
  const totalPages = Math.ceil(totalItems / itemsPerPage);

  // Calculate start and end indices for "Showing X-Y of Z"
  const startIndex = totalItems === 0 ? 0 : (currentPage - 1) * itemsPerPage + 1;
  const endIndex = Math.min(currentPage * itemsPerPage, totalItems);

  // Don't show pagination if there are no items
  // Keep it visible even with 1 page to show the page size selector
  if (totalItems === 0) {
    return null;
  }

  /**
   * Generate page numbers with ellipsis
   * Shows current page ± 2 pages, with ellipsis for gaps
   * Example: 1 ... 4 5 [6] 7 8 ... 20
   */
  const generatePageNumbers = () => {
    const pages = [];
    const showEllipsisStart = currentPage > 4;
    const showEllipsisEnd = currentPage < totalPages - 3;

    // Always show first page
    pages.push(1);

    // Show ellipsis or pages before current
    if (showEllipsisStart) {
      pages.push('ellipsis-start');
      // Show current - 2, current - 1, current
      for (let i = currentPage - 2; i <= currentPage; i++) {
        if (i > 1) {
          pages.push(i);
        }
      }
    } else {
      // Show pages 2 through current
      for (let i = 2; i <= currentPage; i++) {
        pages.push(i);
      }
    }

    // Show pages after current
    if (showEllipsisEnd) {
      // Show current + 1, current + 2
      for (let i = currentPage + 1; i <= Math.min(currentPage + 2, totalPages - 1); i++) {
        pages.push(i);
      }
      pages.push('ellipsis-end');
    } else {
      // Show remaining pages up to last
      for (let i = currentPage + 1; i < totalPages; i++) {
        pages.push(i);
      }
    }

    // Always show last page if there's more than one page and it's not already in the array
    if (totalPages > 1 && pages[pages.length - 1] !== totalPages) {
      pages.push(totalPages);
    }

    return pages;
  };

  const pageNumbers = generatePageNumbers();

  const handlePrevious = () => {
    if (currentPage > 1) {
      onPageChange(currentPage - 1);
    }
  };

  const handleNext = () => {
    if (currentPage < totalPages) {
      onPageChange(currentPage + 1);
    }
  };

  const handlePageClick = (page) => {
    if (typeof page === 'number' && page !== currentPage) {
      onPageChange(page);
    }
  };

  const handlePageSizeChange = (e) => {
    const newSize = parseInt(e.target.value, 10);
    onItemsPerPageChange(newSize);
    // Reset to page 1 when changing page size
    onPageChange(1);
  };

  return (
    <div className="flex items-center justify-between px-4 py-3 border-t border-gray-200 bg-white" dir="rtl">
      {/* Right side: Results text and page size selector */}
      <div className="flex items-center gap-4">
        {showResultsText && (
          <div className="text-sm text-gray-700">
            מציג {startIndex}-{endIndex} מתוך {totalItems} תוצאות
          </div>
        )}

        {showPageSizeSelector && (
          <div className="flex items-center gap-2">
            <label htmlFor="page-size" className="text-sm text-gray-700">
              שורות בעמוד:
            </label>
            <select
              id="page-size"
              value={itemsPerPage}
              onChange={handlePageSizeChange}
              className="block rounded-md border-gray-300 py-1.5 pr-8 pl-3 text-sm focus:border-red-500 focus:ring-red-500"
              style={{
                borderColor: theme.border_color,
                color: theme.text_primary
              }}
            >
              {pageSizeOptions.map((size) => (
                <option key={size} value={size}>
                  {size}
                </option>
              ))}
            </select>
          </div>
        )}
      </div>

      {/* Left side: Page navigation */}
      <div className="flex items-center gap-1">
        {/* Previous button */}
        <button
          onClick={handlePrevious}
          disabled={currentPage === 1}
          className="p-2 rounded-md transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
          style={{
            color: currentPage === 1 ? 'var(--theme-text-muted)' : 'var(--theme-primary-color)'
          }}
          aria-label="עמוד קודם"
        >
          <ChevronRightIcon className="w-5 h-5" />
        </button>

        {/* Page numbers */}
        <div className="flex items-center gap-1">
          {pageNumbers.map((page, index) => {
            if (typeof page === 'string') {
              // Ellipsis
              return (
                <span
                  key={page}
                  className="px-3 py-1 text-sm"
                  style={{ color: theme.text_muted }}
                >
                  ...
                </span>
              );
            }

            // Page number button
            const isActive = page === currentPage;
            return (
              <button
                key={page}
                onClick={() => handlePageClick(page)}
                className="px-3 py-1 text-sm font-medium rounded-md transition-colors"
                style={{
                  backgroundColor: isActive ? 'var(--theme-primary-color)' : 'transparent',
                  color: isActive ? '#FFFFFF' : 'var(--theme-text-secondary)',
                  border: isActive ? 'none' : '1px solid var(--theme-border-light)'
                }}
                onMouseEnter={(e) => {
                  if (!isActive) {
                    e.target.style.backgroundColor = theme.bg_gray_100;
                  }
                }}
                onMouseLeave={(e) => {
                  if (!isActive) {
                    e.target.style.backgroundColor = 'transparent';
                  }
                }}
                aria-label={`עמוד ${page}`}
                aria-current={isActive ? 'page' : undefined}
              >
                {page}
              </button>
            );
          })}
        </div>

        {/* Next button */}
        <button
          onClick={handleNext}
          disabled={currentPage === totalPages}
          className="p-2 rounded-md transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
          style={{
            color: currentPage === totalPages ? 'var(--theme-text-muted)' : 'var(--theme-primary-color)'
          }}
          aria-label="עמוד הבא"
        >
          <ChevronLeftIcon className="w-5 h-5" />
        </button>
      </div>
    </div>
  );
};

export default Pagination;
