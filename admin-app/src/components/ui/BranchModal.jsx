import React, { useState, useEffect } from 'react';
import { XMarkIcon, PlusIcon, TrashIcon } from '@heroicons/react/24/outline';
import { DEFAULT_THEME } from '../../config/theme.js';

const BranchModal = ({
  isOpen,
  onClose,
  onSave,
  editingBranch = null,
  isSaving = false
}) => {
  const theme = DEFAULT_THEME;

  const [formData, setFormData] = useState({
    name: '',
    phone: '',
    city: '',
    address: '',
    is_open: true,
    activity_times: {},
    kosher_type: '',
    accessibility_list: []
  });

  const [errors, setErrors] = useState({});

  const daysOfWeek = [
    { key: 'SUNDAY', label: 'ראשון' },
    { key: 'MONDAY', label: 'שני' },
    { key: 'TUESDAY', label: 'שלישי' },
    { key: 'WEDNESDAY', label: 'רביעי' },
    { key: 'THURSDAY', label: 'חמישי' },
    { key: 'FRIDAY', label: 'שישי' },
    { key: 'SATURDAY', label: 'שבת' }
  ];

  const kosherTypes = [
    { value: '', label: 'לא רלוונטי' },
    { value: 'kosher', label: 'כשר' },
    { value: 'kosher_mehadrin', label: 'כשר למהדרין' },
    { value: 'kosher_badatz', label: 'כשר בד"ץ' }
  ];

  const accessibilityOptions = [
    'נגישות לכיסאות גלגלים',
    'חניה נגישה',
    'שירותים נגישים',
    'כניסה נגישה',
    'מעלית'
  ];

  // Initialize form data when modal opens or editing item changes
  useEffect(() => {
    if (isOpen) {
      if (editingBranch) {
        setFormData({
          name: editingBranch.name || '',
          phone: editingBranch.phone || '',
          city: editingBranch.city || '',
          address: editingBranch.address || '',
          is_open: editingBranch.is_open !== undefined ? editingBranch.is_open : true,
          activity_times: editingBranch.activity_times || {},
          kosher_type: editingBranch.kosher_type || '',
          accessibility_list: editingBranch.accessibility_list || []
        });
      } else {
        // Reset form for new branch
        setFormData({
          name: '',
          phone: '',
          city: '',
          address: '',
          is_open: true,
          activity_times: {},
          kosher_type: '',
          accessibility_list: []
        });
      }
      setErrors({});
    }
  }, [isOpen, editingBranch]);

  const validateForm = () => {
    const newErrors = {};

    if (!formData.name.trim()) {
      newErrors.name = 'שם הסניף הוא שדה חובה';
    }

    if (!formData.phone.trim()) {
      newErrors.phone = 'מספר טלפון הוא שדה חובה';
    }

    if (!formData.city.trim()) {
      newErrors.city = 'עיר הוא שדה חובה';
    }

    if (!formData.address.trim()) {
      newErrors.address = 'כתובת הוא שדה חובה';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleInputChange = (field, value) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    // Clear error when user starts typing
    if (errors[field]) {
      setErrors(prev => ({ ...prev, [field]: '' }));
    }
  };

  const handleActivityTimeChange = (day, timeSlots) => {
    setFormData(prev => ({
      ...prev,
      activity_times: {
        ...prev.activity_times,
        [day]: timeSlots
      }
    }));
  };

  const addTimeSlot = (day) => {
    const currentSlots = formData.activity_times[day] || [];
    handleActivityTimeChange(day, [...currentSlots, '09:00-17:00']);
  };

  const removeTimeSlot = (day, index) => {
    const currentSlots = formData.activity_times[day] || [];
    const newSlots = currentSlots.filter((_, i) => i !== index);
    handleActivityTimeChange(day, newSlots);
  };

  const updateTimeSlot = (day, index, value) => {
    const currentSlots = formData.activity_times[day] || [];
    const newSlots = [...currentSlots];
    newSlots[index] = value;
    handleActivityTimeChange(day, newSlots);
  };

  const handleAccessibilityChange = (option, checked) => {
    if (checked) {
      setFormData(prev => ({
        ...prev,
        accessibility_list: [...prev.accessibility_list, option]
      }));
    } else {
      setFormData(prev => ({
        ...prev,
        accessibility_list: prev.accessibility_list.filter(item => item !== option)
      }));
    }
  };

  const handleSave = () => {
    if (validateForm()) {
      onSave(formData);
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" dir="rtl">
      <div className="bg-white rounded-lg w-full max-w-4xl max-h-[90vh] overflow-hidden shadow-2xl">
        {/* Header */}
        <div className="flex items-center justify-between p-6 border-b border-gray-200">
          <h2 className="text-xl font-bold text-gray-900">
            {editingBranch ? 'עריכת סניף' : 'הוספת סניף חדש'}
          </h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 transition-colors"
          >
            <XMarkIcon className="w-6 h-6" />
          </button>
        </div>

        {/* Content */}
        <div className="p-6 overflow-y-auto max-h-[calc(90vh-140px)]">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {/* Basic Information */}
            <div className="space-y-4">
              <h3 className="text-lg font-semibold text-gray-900 mb-4">פרטים בסיסיים</h3>

              {/* Name */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  שם הסניף *
                </label>
                <input
                  type="text"
                  value={formData.name}
                  onChange={(e) => handleInputChange('name', e.target.value)}
                  className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 ${
                    errors.name ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 focus:ring-red-500'
                  }`}
                  placeholder="הזן שם סניף"
                />
                {errors.name && <p className="text-red-500 text-sm mt-1">{errors.name}</p>}
              </div>

              {/* Phone */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  טלפון *
                </label>
                <input
                  type="tel"
                  value={formData.phone}
                  onChange={(e) => handleInputChange('phone', e.target.value)}
                  className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 ${
                    errors.phone ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 focus:ring-red-500'
                  }`}
                  placeholder="מספר טלפון"
                />
                {errors.phone && <p className="text-red-500 text-sm mt-1">{errors.phone}</p>}
              </div>

              {/* City */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  עיר *
                </label>
                <input
                  type="text"
                  value={formData.city}
                  onChange={(e) => handleInputChange('city', e.target.value)}
                  className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 ${
                    errors.city ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 focus:ring-red-500'
                  }`}
                  placeholder="שם העיר"
                />
                {errors.city && <p className="text-red-500 text-sm mt-1">{errors.city}</p>}
              </div>

              {/* Address */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  כתובת *
                </label>
                <input
                  type="text"
                  value={formData.address}
                  onChange={(e) => handleInputChange('address', e.target.value)}
                  className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 ${
                    errors.address ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 focus:ring-red-500'
                  }`}
                  placeholder="כתובת מלאה"
                />
                {errors.address && <p className="text-red-500 text-sm mt-1">{errors.address}</p>}
              </div>

              {/* Status */}
              <div>
                <label className="flex items-center space-x-2 space-x-reverse">
                  <input
                    type="checkbox"
                    checked={formData.is_open}
                    onChange={(e) => handleInputChange('is_open', e.target.checked)}
                    className="rounded border-gray-300 text-red-600 focus:ring-red-500"
                  />
                  <span className="text-sm font-medium text-gray-700">הסניף פעיל</span>
                </label>
              </div>

              {/* Kosher Type */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  סוג כשרות
                </label>
                <select
                  value={formData.kosher_type}
                  onChange={(e) => handleInputChange('kosher_type', e.target.value)}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500"
                >
                  {kosherTypes.map(type => (
                    <option key={type.value} value={type.value}>{type.label}</option>
                  ))}
                </select>
              </div>
            </div>

            {/* Activity Times & Accessibility */}
            <div className="space-y-4">
              <h3 className="text-lg font-semibold text-gray-900 mb-4">שעות פעילות ונגישות</h3>

              {/* Activity Times */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-3">
                  שעות פעילות
                </label>
                <div className="space-y-3">
                  {daysOfWeek.map(day => (
                    <div key={day.key} className="flex items-center gap-3">
                      <span className="w-16 text-sm text-gray-600">{day.label}</span>
                      <div className="flex-1 space-y-2">
                        {(formData.activity_times[day.key] || []).map((timeSlot, index) => (
                          <div key={index} className="flex items-center gap-2">
                            <input
                              type="text"
                              value={timeSlot}
                              onChange={(e) => updateTimeSlot(day.key, index, e.target.value)}
                              className="flex-1 px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-red-500"
                              placeholder="09:00-17:00"
                            />
                            <button
                              onClick={() => removeTimeSlot(day.key, index)}
                              className="text-red-500 hover:text-red-700"
                            >
                              <TrashIcon className="w-4 h-4" />
                            </button>
                          </div>
                        ))}
                        <button
                          onClick={() => addTimeSlot(day.key)}
                          className="flex items-center gap-1 text-sm text-red-600 hover:text-red-800"
                        >
                          <PlusIcon className="w-4 h-4" />
                          הוסף שעות
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              {/* Accessibility */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-3">
                  נגישות
                </label>
                <div className="space-y-2">
                  {accessibilityOptions.map(option => (
                    <label key={option} className="flex items-center space-x-2 space-x-reverse">
                      <input
                        type="checkbox"
                        checked={formData.accessibility_list.includes(option)}
                        onChange={(e) => handleAccessibilityChange(option, e.target.checked)}
                        className="rounded border-gray-300 text-red-600 focus:ring-red-500"
                      />
                      <span className="text-sm text-gray-700">{option}</span>
                    </label>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Footer */}
        <div className="flex items-center justify-end gap-3 p-6 border-t border-gray-200">
          <button
            onClick={onClose}
            disabled={isSaving}
            className="px-6 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors disabled:opacity-50"
          >
            ביטול
          </button>
          <button
            onClick={handleSave}
            disabled={isSaving}
            className="px-6 py-2 text-white rounded-lg transition-colors disabled:opacity-50"
            style={{ backgroundColor: theme.primary_color }}
          >
            {isSaving ? 'שומר...' : (editingBranch ? 'עדכן' : 'שמור')}
          </button>
        </div>
      </div>
    </div>
  );
};

export default BranchModal;