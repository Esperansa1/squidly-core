/**
 * CustomerModal Component
 *
 * Modal for creating and editing customers with full form validation
 */

import React, { useState, useEffect } from 'react';
import { XMarkIcon } from '@heroicons/react/24/outline';
import { FormField, Button, Badge, Divider } from './index';
import { DEFAULT_THEME } from '../../config/theme.js';

const CustomerModal = ({
  isOpen,
  onClose,
  onSave,
  customer = null,
  loading = false,
  strings = {}
}) => {
  const theme = DEFAULT_THEME;

  const [formData, setFormData] = useState({
    first_name: '',
    last_name: '',
    phone: '',
    email: '',
    is_active: true,
    allow_sms_notifications: false,
    allow_email_notifications: false,
    loyalty_points_balance: 0,
    lifetime_points_earned: 0,
    staff_labels: '',
    auth_provider: 'phone'
  });

  const [errors, setErrors] = useState({});

  // Initialize form data when modal opens or editing customer changes
  useEffect(() => {
    if (isOpen) {
      if (customer) {
        // Editing existing customer
        setFormData({
          first_name: customer.first_name || '',
          last_name: customer.last_name || '',
          phone: customer.phone || '',
          email: customer.email || '',
          is_active: customer.is_active !== undefined ? Boolean(customer.is_active) : true,
          allow_sms_notifications: Boolean(customer.allow_sms_notifications),
          allow_email_notifications: Boolean(customer.allow_email_notifications),
          loyalty_points_balance: parseFloat(customer.loyalty_points_balance) || 0,
          lifetime_points_earned: parseFloat(customer.lifetime_points_earned) || 0,
          staff_labels: customer.staff_labels || '',
          auth_provider: customer.auth_provider || 'phone'
        });
      } else {
        // Creating new customer - reset form
        setFormData({
          first_name: '',
          last_name: '',
          phone: '',
          email: '',
          is_active: true,
          allow_sms_notifications: false,
          allow_email_notifications: false,
          loyalty_points_balance: 0,
          lifetime_points_earned: 0,
          staff_labels: '',
          auth_provider: 'phone'
        });
      }
      setErrors({});
    }
  }, [isOpen, customer]);

  const handleInputChange = (e) => {
    const { name, value, type, checked } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value
    }));
    // Clear error for this field
    if (errors[name]) {
      setErrors(prev => ({ ...prev, [name]: null }));
    }
  };

  const validatePhone = (phone) => {
    if (!phone) {
      return 'מספר טלפון הוא שדה חובה';
    }

    // Remove all non-digit characters except +
    const cleaned = phone.replace(/[^\d+]/g, '');

    // Check if it starts with +972 or 0
    if (cleaned.startsWith('+972')) {
      if (cleaned.length !== 13) {
        return 'מספר טלפון עם +972 חייב להיות 13 ספרות';
      }
    } else if (cleaned.startsWith('0')) {
      if (cleaned.length !== 10) {
        return 'מספר טלפון שמתחיל ב-0 חייב להיות 10 ספרות';
      }
    } else {
      return 'מספר טלפון חייב להתחיל ב-+972 או 0';
    }

    return null;
  };

  const normalizePhone = (phone) => {
    const cleaned = phone.replace(/[^\d+]/g, '');
    if (cleaned.startsWith('0') && cleaned.length === 10) {
      return '+972' + cleaned.slice(1);
    }
    return cleaned;
  };

  const validateEmail = (email) => {
    if (!email) return null; // Email is optional
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      return 'כתובת אימייל לא תקינה';
    }
    return null;
  };

  const validateForm = () => {
    const newErrors = {};

    // Required fields
    if (!formData.first_name?.trim()) {
      newErrors.first_name = 'שם פרטי הוא שדה חובה';
    } else if (formData.first_name.length > 50) {
      newErrors.first_name = 'שם פרטי לא יכול להיות יותר מ-50 תווים';
    }

    if (!formData.last_name?.trim()) {
      newErrors.last_name = 'שם משפחה הוא שדה חובה';
    } else if (formData.last_name.length > 50) {
      newErrors.last_name = 'שם משפחה לא יכול להיות יותר מ-50 תווים';
    }

    // Phone validation
    const phoneError = validatePhone(formData.phone);
    if (phoneError) {
      newErrors.phone = phoneError;
    }

    // Email validation (optional but must be valid if provided)
    const emailError = validateEmail(formData.email);
    if (emailError) {
      newErrors.email = emailError;
    }

    // Loyalty points validation
    if (formData.loyalty_points_balance < 0) {
      newErrors.loyalty_points_balance = 'יתרת נקודות לא יכולה להיות שלילית';
    }

    if (formData.lifetime_points_earned < 0) {
      newErrors.lifetime_points_earned = 'נקודות שנצברו לא יכולות להיות שליליות';
    }

    if (formData.loyalty_points_balance > formData.lifetime_points_earned) {
      newErrors.loyalty_points_balance = 'יתרת נקודות לא יכולה לעלות על נקודות שנצברו';
    }

    // Staff labels length
    if (formData.staff_labels && formData.staff_labels.length > 500) {
      newErrors.staff_labels = 'תוויות צוות לא יכולות להיות יותר מ-500 תווים';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    if (!validateForm()) {
      // Scroll to the first field with an error
      requestAnimationFrame(() => {
        const firstErrorField = document.querySelector('[data-has-error="true"]');
        if (firstErrorField) {
          firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      });
      return;
    }

    // Prepare data for submission
    const submitData = {
      ...formData,
      phone: normalizePhone(formData.phone),
      first_name: formData.first_name.trim(),
      last_name: formData.last_name.trim(),
      email: formData.email.trim()
    };

    // If creating a new customer, ensure required fields for backend
    if (!customer) {
      submitData.is_guest = false;
      submitData.total_orders = 0;
      submitData.total_spent = 0;
      submitData.order_ids = [];
    }

    await onSave(submitData);
  };

  const formatPhoneDisplay = (phone) => {
    if (!phone) return '';
    // For display in input, show as-is to allow editing
    return phone;
  };

  if (!isOpen) return null;

  return (
    <div
      className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
      onClick={(e) => e.target === e.currentTarget && onClose()}
    >
      <div
        className="bg-white rounded-lg shadow-xl max-w-3xl w-full max-h-[90vh] overflow-hidden"
        dir="rtl"
      >
        {/* Header */}
        <div
          className="flex items-center justify-between p-6 border-b"
          style={{ borderColor: theme.border_color }}
        >
          <h2 className="text-xl font-bold" style={{ color: theme.text_primary }}>
            {customer ? 'ערוך לקוח' : 'צור לקוח חדש'}
          </h2>
          <button
            onClick={onClose}
            disabled={loading}
            className="text-gray-400 hover:text-gray-600 transition-colors"
          >
            <XMarkIcon className="w-6 h-6" />
          </button>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit} className="overflow-y-auto" style={{ maxHeight: 'calc(90vh - 200px)' }}>
          <div className="p-6 space-y-6">
            {/* Personal Information Section */}
            <div>
              <h3 className="text-lg font-semibold mb-4" style={{ color: theme.text_primary }}>
                מידע אישי
              </h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <FormField
                  label="שם פרטי"
                  fieldType="input"
                  type="text"
                  name="first_name"
                  value={formData.first_name}
                  onChange={handleInputChange}
                  required
                  error={errors.first_name}
                  fullWidth
                />

                <FormField
                  label="שם משפחה"
                  fieldType="input"
                  type="text"
                  name="last_name"
                  value={formData.last_name}
                  onChange={handleInputChange}
                  required
                  error={errors.last_name}
                  fullWidth
                />

                <FormField
                  label="מספר טלפון"
                  fieldType="input"
                  type="tel"
                  name="phone"
                  value={formData.phone}
                  onChange={handleInputChange}
                  required
                  error={errors.phone}
                  fullWidth
                />

                <FormField
                  label="כתובת אימייל"
                  fieldType="input"
                  type="email"
                  name="email"
                  value={formData.email}
                  onChange={handleInputChange}
                  error={errors.email}
                  helpText="אופציונלי"
                  fullWidth
                />
              </div>
            </div>

            <Divider />

            {/* Account Settings Section */}
            <div>
              <h3 className="text-lg font-semibold mb-4" style={{ color: theme.text_primary }}>
                הגדרות חשבון
              </h3>
              <div className="space-y-3">
                <label className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    name="is_active"
                    checked={formData.is_active}
                    onChange={handleInputChange}
                    className="w-5 h-5 rounded"
                    style={{ accentColor: theme.primary_color }}
                  />
                  <span className="text-sm" style={{ color: theme.text_primary }}>
                    חשבון פעיל
                  </span>
                </label>

                <label className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    name="allow_sms_notifications"
                    checked={formData.allow_sms_notifications}
                    onChange={handleInputChange}
                    className="w-5 h-5 rounded"
                    style={{ accentColor: theme.primary_color }}
                  />
                  <span className="text-sm" style={{ color: theme.text_primary }}>
                    אפשר הודעות SMS
                  </span>
                </label>

                <label className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    name="allow_email_notifications"
                    checked={formData.allow_email_notifications}
                    onChange={handleInputChange}
                    className="w-5 h-5 rounded"
                    style={{ accentColor: theme.primary_color }}
                  />
                  <span className="text-sm" style={{ color: theme.text_primary }}>
                    אפשר התראות אימייל
                  </span>
                </label>
              </div>
            </div>

            <Divider />

            {/* Loyalty Points Section */}
            <div>
              <h3 className="text-lg font-semibold mb-4" style={{ color: theme.text_primary }}>
                נקודות נאמנות
              </h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <FormField
                  label="יתרת נקודות נוכחית"
                  fieldType="input"
                  type="number"
                  name="loyalty_points_balance"
                  value={formData.loyalty_points_balance}
                  onChange={handleInputChange}
                  error={errors.loyalty_points_balance}
                  helpText="נקודות זמינות לשימוש"
                  fullWidth
                />

                <FormField
                  label="סה״כ נקודות שנצברו"
                  fieldType="input"
                  type="number"
                  name="lifetime_points_earned"
                  value={formData.lifetime_points_earned}
                  onChange={handleInputChange}
                  error={errors.lifetime_points_earned}
                  helpText="סך כל הנקודות שנצברו אי פעם"
                  fullWidth
                />
              </div>
            </div>

            <Divider />

            {/* Staff Labels Section */}
            <div>
              <h3 className="text-lg font-semibold mb-4" style={{ color: theme.text_primary }}>
                תוויות צוות
              </h3>
              <FormField
                label="הערות צוות"
                fieldType="textarea"
                name="staff_labels"
                value={formData.staff_labels}
                onChange={handleInputChange}
                error={errors.staff_labels}
                rows={4}
                maxLength={500}
                showCharCount
                helpText="הערות פנימיות על התנהגות לקוח, תלונות, העדפות וכו'"
                fullWidth
              />
            </div>

            {/* Read-Only Information (when editing) */}
            {customer && (
              <>
                <Divider />
                <div>
                  <h3 className="text-lg font-semibold mb-4" style={{ color: theme.text_primary }}>
                    מידע נוסף (קריאה בלבד)
                  </h3>
                  <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                      <p className="text-sm" style={{ color: theme.text_muted }}>סה"כ הזמנות</p>
                      <Badge variant="info" size="md">
                        {customer.total_orders || 0}
                      </Badge>
                    </div>
                    <div>
                      <p className="text-sm" style={{ color: theme.text_muted }}>סה"כ הוצאות</p>
                      <p className="text-base font-semibold" style={{ color: theme.text_primary }}>
                        ₪{parseFloat(customer.total_spent || 0).toFixed(2)}
                      </p>
                    </div>
                    <div>
                      <p className="text-sm" style={{ color: theme.text_muted }}>הזמנה אחרונה</p>
                      <p className="text-sm" style={{ color: theme.text_secondary }}>
                        {customer.last_order_date
                          ? new Date(customer.last_order_date).toLocaleDateString('he-IL')
                          : 'אין'}
                      </p>
                    </div>
                    <div>
                      <p className="text-sm" style={{ color: theme.text_muted }}>תאריך הרשמה</p>
                      <p className="text-sm" style={{ color: theme.text_secondary }}>
                        {customer.registration_date
                          ? new Date(customer.registration_date).toLocaleDateString('he-IL')
                          : 'לא ידוע'}
                      </p>
                    </div>
                  </div>
                </div>
              </>
            )}
          </div>
        </form>

        {/* Footer */}
        <div
          className="flex flex-col sm:flex-row justify-end gap-3 p-6 border-t"
          style={{ borderColor: theme.border_color }}
        >
          <Button
            variant="outline"
            onClick={onClose}
            disabled={loading}
          >
            ביטול
          </Button>
          <Button
            variant="primary"
            onClick={handleSubmit}
            loading={loading}
            disabled={loading}
          >
            {customer ? 'שמור שינויים' : 'צור לקוח'}
          </Button>
        </div>
      </div>
    </div>
  );
};

export default CustomerModal;
