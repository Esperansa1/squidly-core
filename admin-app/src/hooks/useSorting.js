import { useState, useMemo } from 'react';

/**
 * Custom hook for sorting data with Hebrew text support
 * @param {Array} data - The data to sort
 * @returns {Object} - Sort state and handlers
 */
export const useSorting = (data) => {
  const [sortField, setSortField] = useState(null);
  const [sortDirection, setSortDirection] = useState('asc');

  const sortedData = useMemo(() => {
    if (!sortField || !data) return data;
    
    return [...data].sort((a, b) => {
      let aValue, bValue;
      
      if (sortField === 'name') {
        aValue = a.name || '';
        bValue = b.name || '';
      } else if (sortField === 'status') {
        // Convert status to sortable values (active comes before inactive)
        aValue = a.status === 'active' ? 0 : 1;
        bValue = b.status === 'active' ? 0 : 1;
      }
      
      // Handle string comparison for Hebrew text
      if (typeof aValue === 'string') {
        const comparison = aValue.localeCompare(bValue, 'he');
        return sortDirection === 'asc' ? comparison : -comparison;
      }
      
      // Handle numeric comparison
      if (sortDirection === 'asc') {
        return aValue < bValue ? -1 : aValue > bValue ? 1 : 0;
      } else {
        return aValue > bValue ? -1 : aValue < bValue ? 1 : 0;
      }
    });
  }, [data, sortField, sortDirection]);

  const handleSort = (field) => {
    if (sortField === field) {
      // Toggle direction if same field
      setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
    } else {
      // New field, start with ascending
      setSortField(field);
      setSortDirection('asc');
    }
  };

  return {
    sortedData,
    sortField,
    sortDirection,
    handleSort
  };
};