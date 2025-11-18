/**
 * CustomerManagement Component
 *
 * Main page for customer management - lists all registered customers (no guests)
 * No branch filtering as customers are global entities
 */

import React, { useState, useEffect, useCallback } from 'react';
import api from '../services/api.js';
import CustomerSection from './CustomerSection.jsx';
import { DEFAULT_THEME } from '../config/theme.js';

const CustomerManagement = () => {
  const theme = DEFAULT_THEME;

  const [config, setConfig] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Customer data state
  const [customers, setCustomers] = useState([]);
  const [selectedCustomer, setSelectedCustomer] = useState(null);

  // Pagination state (for backend pagination)
  const [totalCustomers, setTotalCustomers] = useState(0);

  // Initialize API and load data
  useEffect(() => {
    initializeApp();
  }, []);

  const initializeApp = async () => {
    try {
      setError(null);

      // Get app configuration
      const appConfig = await api.init();
      setConfig(appConfig);

      // Note: Don't call loadCustomers() here - DataSection will handle initial load
      // via onPaginationChange callback when useBackendPagination={true}
    } catch (err) {
      console.error('Failed to initialize customer management:', err);
      setError(err.message || 'שגיאה בטעינת ניהול לקוחות');
      setLoading(false);
    }
  };

  const loadCustomers = useCallback(async (page = 1, perPage = 10, search = '') => {
    try {
      setLoading(true);
      setError(null);

      // Build filters (separate from pagination params)
      const filters = {
        is_guest: false,  // Filter out guest customers
      };

      // Add search if present (backend should support this)
      if (search && search.trim()) {
        filters.search = search.trim();
      }

      // Add pagination params
      const offset = (page - 1) * perPage;
      filters.offset = offset;
      filters.per_page = perPage;

      // Fetch customers with pagination
      const response = await api.getCustomers(filters, true);

      setCustomers(response.data);
      setTotalCustomers(response.total);
      setLoading(false);
    } catch (err) {
      console.error('Failed to load customers:', err);
      setError(err.message || 'שגיאה בטעינת הלקוחות');
      setLoading(false);
    }
  }, []);

  // Handle pagination changes from DataSection
  // Wrapped with useCallback to prevent infinite loop in DataSection's useEffect
  const handlePaginationChange = useCallback(async (page, perPage, search) => {
    // Don't set state here - it causes infinite loop with DataSection's useEffect
    // Just load the data with the new parameters
    return loadCustomers(page, perPage, search);
  }, [loadCustomers]);

  // Handle customer changes (create/edit/delete) - refresh data
  const handleCustomerChange = () => {
    loadCustomers();
  };

  // Get strings with fallbacks
  const strings = config ? api.getStrings() : {};

  return (
    <div className="h-full flex flex-col" dir="rtl">
      {/* Scrollable Content Area */}
      <div className="flex-1 px-6 pt-6 pb-6 min-h-0">
        <CustomerSection
          title="לקוחות רשומים"
          customers={customers}
          selectedCustomer={selectedCustomer}
          setSelectedCustomer={setSelectedCustomer}
          strings={{
            ...strings,
            customer_name: 'שם לקוח',
            phone_number: 'מספר טלפון',
            total_orders: 'סה"כ הזמנות',
            total_spent: 'סה"כ הוצאות',
            loyalty_points: 'נקודות נאמנות',
            staff_labels: 'תוויות צוות',
            create_customer: 'צור לקוח חדש',
            edit_customer: 'ערוך לקוח',
            delete_customer: 'מחק לקוח',
            search_customers: 'חפש לקוחות (שם, טלפון, אימייל)...',
            no_customers: 'אין לקוחות רשומים להצגה'
          }}
          loading={loading}
          error={error}
          onCustomerChange={handleCustomerChange}
          useBackendPagination={true}
          totalCustomers={totalCustomers}
          onPaginationChange={handlePaginationChange}
        />
      </div>
    </div>
  );
};

export default CustomerManagement;
