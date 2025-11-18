import { useState, useMemo, useEffect } from 'react';

/**
 * usePagination Hook
 *
 * Custom hook for managing pagination state and calculations.
 * Handles page navigation, items per page, and data slicing.
 *
 * @param {Array} data - The full dataset to paginate
 * @param {number} initialItemsPerPage - Initial items per page (default: 10)
 * @returns {Object} Pagination state and methods
 */
const usePagination = (data = [], initialItemsPerPage = 10) => {
  const [currentPage, setCurrentPage] = useState(1);
  const [itemsPerPage, setItemsPerPage] = useState(initialItemsPerPage);

  // Calculate total pages
  const totalPages = Math.ceil(data.length / itemsPerPage);
  const totalItems = data.length;

  // Calculate start and end indices
  const startIndex = (currentPage - 1) * itemsPerPage;
  const endIndex = Math.min(startIndex + itemsPerPage, data.length);

  // Get current page data
  const currentPageData = useMemo(() => {
    return data.slice(startIndex, endIndex);
  }, [data, startIndex, endIndex]);

  // Reset to page 1 if current page exceeds total pages
  useEffect(() => {
    if (currentPage > totalPages && totalPages > 0) {
      setCurrentPage(1);
    }
  }, [currentPage, totalPages]);

  // Reset to page 1 when data length changes (e.g., after search/filter)
  useEffect(() => {
    setCurrentPage(1);
  }, [data.length]);

  // Navigation methods
  const goToPage = (page) => {
    const pageNumber = Math.max(1, Math.min(page, totalPages));
    setCurrentPage(pageNumber);
  };

  const nextPage = () => {
    if (currentPage < totalPages) {
      setCurrentPage(currentPage + 1);
    }
  };

  const prevPage = () => {
    if (currentPage > 1) {
      setCurrentPage(currentPage - 1);
    }
  };

  const changeItemsPerPage = (newItemsPerPage) => {
    setItemsPerPage(newItemsPerPage);
    setCurrentPage(1); // Reset to first page when changing items per page
  };

  // Reset pagination to initial state
  const resetPagination = () => {
    setCurrentPage(1);
    setItemsPerPage(initialItemsPerPage);
  };

  return {
    // Current state
    currentPage,
    itemsPerPage,
    totalPages,
    totalItems,
    startIndex,
    endIndex,
    currentPageData,

    // Navigation methods
    goToPage,
    nextPage,
    prevPage,
    setCurrentPage,
    setItemsPerPage: changeItemsPerPage,
    resetPagination,

    // Computed properties
    hasNextPage: currentPage < totalPages,
    hasPrevPage: currentPage > 1,
    isFirstPage: currentPage === 1,
    isLastPage: currentPage === totalPages || totalPages === 0,
  };
};

export default usePagination;
