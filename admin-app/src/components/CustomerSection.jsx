/**
 * CustomerSection Component
 *
 * Wrapper component for customer data display using DataSection pattern
 */

import React, { useMemo } from 'react';
import { DataSection, Badge } from './ui';
import CustomerModal from './ui/CustomerModal.jsx';
import api from '../services/api.js';
import { DEFAULT_THEME } from '../config/theme.js';

const CustomerSection = ({
  title = 'לקוחות',
  customers = [],
  selectedCustomer,
  setSelectedCustomer,
  strings = {},
  loading: externalLoading = false,
  error: externalError = null,
  onCustomerChange = () => {}
}) => {
  const theme = DEFAULT_THEME;

  // Format phone number for display: +972501234567 -> +972-50-123-4567
  const formatPhoneDisplay = (phone) => {
    if (!phone) return '-';
    const cleaned = phone.replace(/\D/g, '');
    if (cleaned.length === 12 && cleaned.startsWith('972')) {
      return `+972-${cleaned.slice(3,5)}-${cleaned.slice(5,8)}-${cleaned.slice(8)}`;
    }
    return phone;
  };

  // Truncate staff labels with tooltip
  const truncateStaffLabels = (labels) => {
    if (!labels || labels.trim().length === 0) {
      return <span className="italic text-gray-400 text-sm">אין תוויות</span>;
    }
    if (labels.length <= 50) {
      return <span className="text-sm text-gray-700">{labels}</span>;
    }
    return (
      <span className="text-sm text-gray-700" title={labels}>
        {labels.substring(0, 50)}...
      </span>
    );
  };

  // Define columns for customer table
  const columns = useMemo(() => [
    {
      key: 'name',
      label: strings.customer_name || 'שם לקוח',
      width: '180px',
      render: (_, customer) => {
        const fullName = `${customer.first_name} ${customer.last_name}`;
        return (
          <span className="text-sm text-gray-800 font-medium w-full text-center" title={fullName}>
            {fullName}
          </span>
        );
      }
    },
    {
      key: 'phone',
      label: strings.phone_number || 'מספר טלפון',
      width: '150px',
      render: (phone) => (
        <span className="text-sm text-gray-700" dir="ltr" style={{ textAlign: 'center', display: 'block' }}>
          {formatPhoneDisplay(phone)}
        </span>
      )
    },
    {
      key: 'total_orders',
      label: strings.total_orders || 'סה"כ הזמנות',
      width: '120px',
      cellStyle: {
        textAlign: 'center'
      },
      render: (total_orders) => (
        <div className="flex justify-center">
          <Badge variant="info" size="sm">
            {total_orders || 0}
          </Badge>
        </div>
      )
    },
    {
      key: 'total_spent',
      label: strings.total_spent || 'סה"כ הוצאות',
      width: '120px',
      cellStyle: {
        textAlign: 'center'
      },
      render: (total_spent) => (
        <span className="text-sm text-gray-700 font-medium" dir="ltr" style={{ display: 'block', textAlign: 'center' }}>
          ₪{parseFloat(total_spent || 0).toFixed(2)}
        </span>
      )
    },
    {
      key: 'loyalty_points_balance',
      label: strings.loyalty_points || 'נקודות נאמנות',
      width: '140px',
      cellStyle: {
        textAlign: 'center'
      },
      render: (points) => {
        const pointValue = parseFloat(points || 0);
        const color = pointValue > 0 ? theme.success_color : theme.text_muted;
        return (
          <span
            className="text-sm font-medium"
            style={{ color, display: 'block', textAlign: 'center' }}
          >
            {pointValue.toFixed(1)} נקודות
          </span>
        );
      }
    },
    {
      key: 'staff_labels',
      label: strings.staff_labels || 'תוויות צוות',
      width: '200px',
      cellStyle: {
        whiteSpace: 'normal',
        overflow: 'visible',
        textOverflow: 'initial'
      },
      render: (staff_labels) => (
        <div className="text-center">
          {truncateStaffLabels(staff_labels)}
        </div>
      )
    }
  ], [strings, theme]);

  return (
    <DataSection
      title={title}
      data={customers}
      selectedItem={selectedCustomer}
      setSelectedItem={setSelectedCustomer}
      strings={{
        ...strings,
        create: strings.create_customer || 'צור לקוח חדש',
        edit: strings.edit_customer || 'ערוך לקוח',
        delete: strings.delete_customer || 'מחק לקוח',
        search_placeholder: strings.search_customers || 'חפש לקוחות...',
        no_items: strings.no_customers || 'אין לקוחות להצגה',
        delete_title: 'מחיקת לקוח',
        delete_message_prefix: 'האם אתה בטוח שברצונך למחוק את הלקוח',
        delete_message_suffix: 'אם ללקוח יש הזמנות קיימות, המחיקה תיכשל. פעולה זו לא ניתנת לביטול.',
        delete_confirm: 'כן, מחק',
        cancel: 'ביטול'
      }}
      loading={externalLoading}
      error={externalError}
      onItemChange={onCustomerChange}
      columns={columns}
      apiService={{
        getAll: () => api.getCustomers(),
        create: (data) => api.createCustomer(data),
        update: (id, data) => api.updateCustomer(id, data),
        delete: (id) => api.deleteCustomer(id)
      }}
      Modal={CustomerModal}
      editingItemProp="customer"
      itemIdProp="id"
      itemNameProp="first_name"
    />
  );
};

export default CustomerSection;
