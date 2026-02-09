import React, { useState, useEffect, useRef, useCallback } from 'react';
import { XMarkIcon, PlusIcon, TrashIcon, MapPinIcon } from '@heroicons/react/24/outline';
import { DEFAULT_THEME } from '../../config/theme.js';
import DropdownButton from './DropdownButton.jsx';
import DeliveryPricingSection from './DeliveryPricingSection.jsx';

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
    latitude: '',
    longitude: '',
    is_open: true,
    activity_times: {},
    kosher_type: '',
    accessibility_list: [],
    delivery_enabled: false,
    delivery_max_distance: '',
    delivery_base_fee: '',
    delivery_free_threshold: '',
    min_order_amount: '',
    banner_image_url: '',
    cashback_rate: '',
  });

  const [customAccessibility, setCustomAccessibility] = useState('');
  const [errors, setErrors] = useState({});
  const [isGeolocating, setIsGeolocating] = useState(false);

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
    { value: '', label: 'לא כשר' },
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
        // Ensure activity_times is properly formatted as an object
        let activityTimes = {};
        if (editingBranch.activity_times) {
          if (typeof editingBranch.activity_times === 'string') {
            try {
              activityTimes = JSON.parse(editingBranch.activity_times);
            } catch (e) {
              activityTimes = {};
            }
          } else if (typeof editingBranch.activity_times === 'object') {
            activityTimes = editingBranch.activity_times;
          }
        }

        // Ensure accessibility_list is an array
        let accessibilityList = [];
        if (editingBranch.accessibility_list) {
          if (Array.isArray(editingBranch.accessibility_list)) {
            accessibilityList = editingBranch.accessibility_list;
          } else if (typeof editingBranch.accessibility_list === 'string') {
            try {
              accessibilityList = JSON.parse(editingBranch.accessibility_list);
            } catch (e) {
              accessibilityList = [];
            }
          }
        }

        setFormData({
          name: editingBranch.name || '',
          phone: editingBranch.phone || '',
          city: editingBranch.city || '',
          address: editingBranch.address || '',
          latitude: editingBranch.latitude ?? '',
          longitude: editingBranch.longitude ?? '',
          is_open: editingBranch.is_open !== undefined ? Boolean(editingBranch.is_open) : true,
          activity_times: activityTimes,
          kosher_type: editingBranch.kosher_type || '',
          accessibility_list: accessibilityList,
          delivery_enabled: Boolean(editingBranch.delivery_enabled),
          delivery_max_distance: editingBranch.delivery_max_distance || '',
          delivery_base_fee: editingBranch.delivery_base_fee || '',
          delivery_free_threshold: editingBranch.delivery_free_threshold || '',
          min_order_amount: editingBranch.min_order_amount || '',
          banner_image_url: editingBranch.banner_image_url || '',
          cashback_rate: editingBranch.cashback_rate ?? '',
        });
      } else {
        // Reset form for new branch
        setFormData({
          name: '',
          phone: '',
          city: '',
          address: '',
          latitude: '',
          longitude: '',
          is_open: true,
          activity_times: {},
          kosher_type: '',
          accessibility_list: [],
          delivery_enabled: false,
          delivery_max_distance: '',
          delivery_base_fee: '',
          delivery_free_threshold: '',
          min_order_amount: '',
          banner_image_url: '',
          cashback_rate: '',
        });
      }
      setErrors({});
      setCustomAccessibility('');
    }
  }, [isOpen, editingBranch]);

  const validateForm = useCallback(() => {
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
  }, [formData.name, formData.phone, formData.city, formData.address]);

  const handleInputChange = useCallback((field, value) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    // Clear error when user starts typing
    setErrors(prev => prev[field] ? { ...prev, [field]: '' } : prev);
  }, []);

  const handleActivityTimeChange = useCallback((day, timeSlots) => {
    setFormData(prev => ({
      ...prev,
      activity_times: {
        ...prev.activity_times,
        [day]: timeSlots
      }
    }));
  }, []);

  const addTimeSlot = useCallback((day) => {
    setFormData(prev => {
      const currentSlots = prev.activity_times[day] || [];
      return {
        ...prev,
        activity_times: {
          ...prev.activity_times,
          [day]: [...currentSlots, '09:00-17:00']
        }
      };
    });
  }, []);

  const removeTimeSlot = useCallback((day, index) => {
    setFormData(prev => {
      const currentSlots = prev.activity_times[day] || [];
      return {
        ...prev,
        activity_times: {
          ...prev.activity_times,
          [day]: currentSlots.filter((_, i) => i !== index)
        }
      };
    });
  }, []);

  const updateTimeSlot = useCallback((day, index, value) => {
    setFormData(prev => {
      const currentSlots = prev.activity_times[day] || [];
      const newSlots = [...currentSlots];
      newSlots[index] = value;
      return {
        ...prev,
        activity_times: {
          ...prev.activity_times,
          [day]: newSlots
        }
      };
    });
  }, []);

  const handleAccessibilityChange = useCallback((option, checked) => {
    setFormData(prev => ({
      ...prev,
      accessibility_list: checked
        ? [...prev.accessibility_list, option]
        : prev.accessibility_list.filter(item => item !== option)
    }));
  }, []);

  const addCustomAccessibility = useCallback(() => {
    setCustomAccessibility(prev => {
      const trimmed = prev.trim();
      if (trimmed) {
        setFormData(fd => {
          if (fd.accessibility_list.includes(trimmed)) return fd;
          return { ...fd, accessibility_list: [...fd.accessibility_list, trimmed] };
        });
      }
      return '';
    });
  }, []);

  const removeAccessibilityOption = useCallback((option) => {
    setFormData(prev => ({
      ...prev,
      accessibility_list: prev.accessibility_list.filter(item => item !== option)
    }));
  }, []);

  // Geocode address using Photon API
  const handleGeolocate = useCallback(async () => {
    const query = `${formData.address} ${formData.city}`.trim();
    if (!query || query.length < 3) return;

    setIsGeolocating(true);
    try {
      const response = await fetch(
        `https://photon.komoot.io/api/?q=${encodeURIComponent(query)}&limit=1&lang=default&lat=31.8&lon=34.8`
      );
      if (!response.ok) throw new Error('Geocoding failed');

      const data = await response.json();
      if (data.features && data.features.length > 0) {
        const [lon, lat] = data.features[0].geometry.coordinates;
        setFormData(prev => ({
          ...prev,
          latitude: lat.toFixed(6),
          longitude: lon.toFixed(6),
        }));
        setErrors(prev => ({ ...prev, latitude: '', longitude: '' }));
      } else {
        setErrors(prev => ({ ...prev, latitude: 'לא נמצאו קואורדינטות לכתובת זו' }));
      }
    } catch {
      setErrors(prev => ({ ...prev, latitude: 'שגיאה באיתור קואורדינטות' }));
    } finally {
      setIsGeolocating(false);
    }
  }, [formData.address, formData.city]);

  const handleSave = useCallback(() => {
    if (validateForm()) {
      // Convert numeric strings to proper types for the API
      const payload = {
        ...formData,
        latitude: formData.latitude !== '' ? parseFloat(formData.latitude) : null,
        longitude: formData.longitude !== '' ? parseFloat(formData.longitude) : null,
        delivery_enabled: Boolean(formData.delivery_enabled),
        delivery_max_distance: formData.delivery_max_distance !== '' ? parseFloat(formData.delivery_max_distance) : 0,
        delivery_base_fee: formData.delivery_base_fee !== '' ? parseFloat(formData.delivery_base_fee) : 0,
        delivery_free_threshold: formData.delivery_free_threshold !== '' ? parseFloat(formData.delivery_free_threshold) : 0,
        min_order_amount: formData.min_order_amount !== '' ? parseFloat(formData.min_order_amount) : 0,
        cashback_rate: formData.cashback_rate !== '' ? parseFloat(formData.cashback_rate) : null,
      };
      onSave(payload);
    }
  }, [formData, onSave, validateForm]);

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" dir="rtl">
      <div className="bg-white rounded-lg w-full max-w-4xl max-h-[90vh] flex flex-col shadow-2xl">
        {/* Header */}
        <div className="flex items-center justify-between p-6 border-b border-gray-200 flex-shrink-0">
          <h2 className="text-xl font-bold text-gray-900">
            {editingBranch ? "עריכת סניף" : "הוספת סניף חדש"}
          </h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 transition-colors"
          >
            <XMarkIcon className="w-6 h-6" />
          </button>
        </div>

        {/* Content */}
        <div className="flex-1 p-6 overflow-y-auto min-h-0">
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
                  style={{ direction: 'ltr', textAlign: 'left' }}
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
                <DropdownButton
                  options={kosherTypes}
                  value={formData.kosher_type}
                  onChange={(value) => handleInputChange('kosher_type', value)}
                  placeholder="בחר סוג כשרות..."
                  getOptionLabel={(option) => option.label}
                  getOptionValue={(option) => option.value}
                />
              </div>

              {/* Location (Coordinates) */}
              <div>
                <div className="flex items-center justify-between mb-2">
                  <label className="text-sm font-medium text-gray-700">
                    מיקום (קואורדינטות)
                  </label>
                  <button
                    type="button"
                    onClick={handleGeolocate}
                    disabled={isGeolocating || (!formData.address.trim() && !formData.city.trim())}
                    className="flex items-center gap-1 text-xs text-red-600 hover:text-red-800 disabled:text-gray-400 disabled:cursor-not-allowed transition-colors"
                  >
                    <MapPinIcon className={`w-4 h-4 ${isGeolocating ? 'animate-pulse' : ''}`} />
                    {isGeolocating ? 'מאתר...' : 'אתר לפי כתובת'}
                  </button>
                </div>
                <div className="flex gap-3">
                  <div className="flex-1">
                    <input
                      type="text"
                      value={formData.latitude}
                      onChange={(e) => handleInputChange('latitude', e.target.value)}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 text-sm"
                      placeholder="קו רוחב (lat)"
                      style={{ direction: 'ltr', textAlign: 'left' }}
                    />
                  </div>
                  <div className="flex-1">
                    <input
                      type="text"
                      value={formData.longitude}
                      onChange={(e) => handleInputChange('longitude', e.target.value)}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 text-sm"
                      placeholder="קו אורך (lon)"
                      style={{ direction: 'ltr', textAlign: 'left' }}
                    />
                  </div>
                </div>
                {errors.latitude && <p className="text-red-500 text-xs mt-1">{errors.latitude}</p>}
                {formData.latitude && formData.longitude && (
                  <p className="text-green-600 text-xs mt-1">
                    ✓ {formData.latitude}, {formData.longitude}
                  </p>
                )}
              </div>

              {/* Banner Image URL */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  תמונת באנר (URL)
                </label>
                <input
                  type="url"
                  value={formData.banner_image_url}
                  onChange={(e) => handleInputChange('banner_image_url', e.target.value)}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 text-sm"
                  placeholder="https://example.com/banner.jpg"
                  style={{ direction: 'ltr', textAlign: 'left' }}
                />
                <p className="text-gray-500 text-xs mt-1">כתובת URL של תמונת הבאנר שתוצג באפליקציית הלקוחות</p>
                {formData.banner_image_url && (
                  <div className="mt-2 rounded-lg overflow-hidden border border-gray-200" style={{ maxHeight: '80px' }}>
                    <img
                      src={formData.banner_image_url}
                      alt="תצוגה מקדימה"
                      className="w-full h-full object-cover"
                      style={{ maxHeight: '80px' }}
                      onError={(e) => { e.target.style.display = 'none'; }}
                    />
                  </div>
                )}
              </div>

              {/* Delivery Configuration */}
              <div className="border-t border-gray-200 pt-4 mt-2">
                <h4 className="text-md font-semibold text-gray-900 mb-3">הגדרות משלוח</h4>

                {/* Delivery Enabled */}
                <label className="flex items-center space-x-2 space-x-reverse mb-3">
                  <input
                    type="checkbox"
                    checked={formData.delivery_enabled}
                    onChange={(e) => handleInputChange('delivery_enabled', e.target.checked)}
                    className="rounded border-gray-300 text-red-600 focus:ring-red-500"
                  />
                  <span className="text-sm font-medium text-gray-700">משלוחים פעילים</span>
                </label>

                {formData.delivery_enabled && (
                  <div className="space-y-3">
                    {/* Max Distance */}
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        מרחק משלוח מקסימלי (ק״מ)
                      </label>
                      <input
                        type="number"
                        step="0.5"
                        min="0"
                        value={formData.delivery_max_distance}
                        onChange={(e) => handleInputChange('delivery_max_distance', e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 text-sm"
                        placeholder="למשל: 5"
                        style={{ direction: 'ltr', textAlign: 'left' }}
                      />
                    </div>

                    {/* Base Fee */}
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        דמי משלוח (₪)
                      </label>
                      <input
                        type="number"
                        step="0.5"
                        min="0"
                        value={formData.delivery_base_fee}
                        onChange={(e) => handleInputChange('delivery_base_fee', e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 text-sm"
                        placeholder="למשל: 15"
                        style={{ direction: 'ltr', textAlign: 'left' }}
                      />
                    </div>

                    {/* Free Delivery Threshold */}
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        משלוח חינם מעל (₪)
                      </label>
                      <input
                        type="number"
                        step="1"
                        min="0"
                        value={formData.delivery_free_threshold}
                        onChange={(e) => handleInputChange('delivery_free_threshold', e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 text-sm"
                        placeholder="למשל: 100 (0 = ללא)"
                        style={{ direction: 'ltr', textAlign: 'left' }}
                      />
                    </div>

                    {/* Min Order Amount */}
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        הזמנה מינימלית (₪)
                      </label>
                      <input
                        type="number"
                        step="1"
                        min="0"
                        value={formData.min_order_amount}
                        onChange={(e) => handleInputChange('min_order_amount', e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 text-sm"
                        placeholder="למשל: 50 (0 = ללא)"
                        style={{ direction: 'ltr', textAlign: 'left' }}
                      />
                    </div>
                  </div>
                )}
              </div>

              {/* Loyalty Configuration */}
              <div className="border-t border-gray-200 pt-4 mt-2">
                <h4 className="text-md font-semibold text-gray-900 mb-3">תוכנית נאמנות</h4>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    אחוז צבירת נקודות (%)
                  </label>
                  <input
                    type="number"
                    step="0.1"
                    min="0"
                    max="100"
                    value={formData.cashback_rate}
                    onChange={(e) => handleInputChange('cashback_rate', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 text-sm"
                    placeholder="ריק = ברירת מחדל גלובלית (2%)"
                    style={{ direction: 'ltr', textAlign: 'left' }}
                  />
                  <p className="text-gray-500 text-xs mt-1">
                    השאר ריק לשימוש בברירת מחדל. 1 נקודה = ₪1 הנחה.
                  </p>
                </div>
              </div>

              {/* Advanced Delivery Pricing */}
              <DeliveryPricingSection branchId={editingBranch?.id} isOpen={isOpen} />
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

                {/* Standard Accessibility Options */}
                <div className="space-y-2 mb-4">
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

                {/* Custom Accessibility Input */}
                <div className="space-y-2">
                  <label className="block text-sm font-medium text-gray-600">
                    אפשרות נגישות מותאמת
                  </label>
                  <div className="flex gap-2">
                    <input
                      type="text"
                      value={customAccessibility}
                      onChange={(e) => setCustomAccessibility(e.target.value)}
                      onKeyDown={(e) => e.key === 'Enter' && addCustomAccessibility()}
                      className="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500"
                      placeholder="הוסף אפשרות נגישות..."
                    />
                    <button
                      type="button"
                      onClick={addCustomAccessibility}
                      className="px-4 py-2 text-sm text-white rounded-lg transition-colors"
                      style={{ backgroundColor: theme.primary_color }}
                    >
                      הוסף
                    </button>
                  </div>
                </div>

                {/* Selected Accessibility Options (Custom and Standard) */}
                {formData.accessibility_list.length > 0 && (
                  <div className="mt-4">
                    <label className="block text-sm font-medium text-gray-600 mb-2">
                      נגישות נבחרת
                    </label>
                    <div className="flex flex-wrap gap-2">
                      {formData.accessibility_list.map(option => (
                        <div
                          key={option}
                          className="flex items-center gap-2 px-3 py-1 bg-gray-100 rounded-full text-sm"
                        >
                          <span className="text-gray-700">{option}</span>
                          <button
                            type="button"
                            onClick={() => removeAccessibilityOption(option)}
                            className="text-red-500 hover:text-red-700"
                          >
                            <XMarkIcon className="w-3 h-3" />
                          </button>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>

        {/* Footer */}
        <div className="flex items-center justify-end gap-3 p-6 border-t border-gray-200 flex-shrink-0 bg-white">
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