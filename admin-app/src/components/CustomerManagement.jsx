/**
 * CustomerManagement Component
 *
 * Main page for customer management - lists all registered customers (no guests)
 * No branch filtering as customers are global entities
 */

import React, { useState, useEffect } from 'react';
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

  // Initialize API and load data
  useEffect(() => {
    initializeApp();
  }, []);

  const initializeApp = async () => {
    try {
      setLoading(true);
      setError(null);

      // Get app configuration
      const appConfig = await api.init();
      setConfig(appConfig);

      // Load customers
      await loadCustomers();

      setLoading(false);
    } catch (err) {
      console.error('Failed to initialize customer management:', err);
      setError(err.message || 'שגיאה בטעינת ניהול לקוחות');
      setLoading(false);
    }
  };

  const loadCustomers = async () => {
    try {
      setLoading(true);
      setError(null);

      // Fetch all customers from API
      const customersData = await api.getCustomers();
      console.log('Customers API response:', customersData);

      // Filter out guest customers
      const registeredCustomers = customersData.filter(customer => !customer.is_guest);

      setCustomers(registeredCustomers);
    } catch (err) {
      console.error('Failed to load customers:', err);
      setError(err.message || 'שגיאה בטעינת הלקוחות');
    } finally {
      setLoading(false);
    }
  };

  // Handle customer changes (create/edit/delete) - refresh data
  const handleCustomerChange = () => {
    loadCustomers();
  };

  // Get strings with fallbacks
  const strings = config ? api.getStrings() : {};

  return (
    <div className="h-full flex flex-col" dir="rtl">
      {/* Scrollable Content Area */}
      <div className="flex-1 px-6 pt-6 pb-6 overflow-y-auto">
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
        />
      </div>
    </div>
  );
};

export default CustomerManagement;
