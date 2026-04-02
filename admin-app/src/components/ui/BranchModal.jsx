import React, { useState, useEffect, useRef, useCallback } from 'react';
import { XMarkIcon, PlusIcon, TrashIcon, MapPinIcon } from '@heroicons/react/24/outline';
import DropdownButton from './DropdownButton.jsx';
import Button from './atoms/Button.jsx';
import IconButton from './atoms/IconButton.jsx';
import Input from './atoms/Input.jsx';
import FormField from './molecules/FormField.jsx';

const BranchModal = ({
  isOpen,
  onClose,
  onSave,
  editingBranch = null,
  isSaving = false
}) => {
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
              <FormField
                label="שם הסניף"
                required
                value={formData.name}
                onChange={(e) => handleInputChange('name', e.target.value)}
                placeholder="הזן שם סניף"
                error={errors.name}
              />

              {/* Phone */}
              <FormField
                label="טלפון"
                required
                type="tel"
                value={formData.phone}
                onChange={(e) => handleInputChange('phone', e.target.value)}
                placeholder="מספר טלפון"
                error={errors.phone}
                style={{ direction: 'ltr', textAlign: 'left' }}
              />

              {/* City */}
              <FormField
                label="עיר"
                required
                value={formData.city}
                onChange={(e) => handleInputChange('city', e.target.value)}
                placeholder="שם העיר"
                error={errors.city}
              />

              {/* Address */}
              <FormField
                label="כתובת"
                required
                value={formData.address}
                onChange={(e) => handleInputChange('address', e.target.value)}
                placeholder="כתובת מלאה"
                error={errors.address}
              />

              {/* Status */}
              <div>
                <label className="flex items-center space-x-2 space-x-reverse">
                  <input
                    type="checkbox"
                    checked={formData.is_open}
                    onChange={(e) => handleInputChange('is_open', e.target.checked)}
                    className="rounded border-gray-300 focus:ring-[var(--theme-primary-color)] accent-[var(--theme-primary-color)]"
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
                  <Button
                    variant="link"
                    size="xs"
                    leftIcon={MapPinIcon}
                    onClick={handleGeolocate}
                    disabled={isGeolocating || (!formData.address.trim() && !formData.city.trim())}
                  >
                    {isGeolocating ? 'מאתר...' : 'אתר לפי כתובת'}
                  </Button>
                </div>
                <div className="flex gap-3">
                  <div className="flex-1">
                    <Input
                      type="text"
                      value={formData.latitude}
                      onChange={(e) => handleInputChange('latitude', e.target.value)}
                      placeholder="קו רוחב (lat)"
                      fullWidth
                      size="sm"
                      style={{ direction: 'ltr', textAlign: 'left' }}
                    />
                  </div>
                  <div className="flex-1">
                    <Input
                      type="text"
                      value={formData.longitude}
                      onChange={(e) => handleInputChange('longitude', e.target.value)}
                      placeholder="קו אורך (lon)"
                      fullWidth
                      size="sm"
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
                <Input
                  type="url"
                  value={formData.banner_image_url}
                  onChange={(e) => handleInputChange('banner_image_url', e.target.value)}
                  placeholder="https://example.com/banner.jpg"
                  fullWidth
                  size="sm"
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
                    className="rounded border-gray-300 focus:ring-[var(--theme-primary-color)] accent-[var(--theme-primary-color)]"
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
                      <Input
                        type="number"
                        step="0.5"
                        min="0"
                        value={formData.delivery_max_distance}
                        onChange={(e) => handleInputChange('delivery_max_distance', e.target.value)}
                        placeholder="למשל: 5"
                        fullWidth
                        size="sm"
                        style={{ direction: 'ltr', textAlign: 'left' }}
                      />
                    </div>

                    {/* Base Fee */}
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        דמי משלוח (₪)
                      </label>
                      <Input
                        type="number"
                        step="0.5"
                        min="0"
                        value={formData.delivery_base_fee}
                        onChange={(e) => handleInputChange('delivery_base_fee', e.target.value)}
                        placeholder="למשל: 15"
                        fullWidth
                        size="sm"
                        style={{ direction: 'ltr', textAlign: 'left' }}
                      />
                    </div>

                    {/* Free Delivery Threshold */}
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        משלוח חינם מעל (₪)
                      </label>
                      <Input
                        type="number"
                        step="1"
                        min="0"
                        value={formData.delivery_free_threshold}
                        onChange={(e) => handleInputChange('delivery_free_threshold', e.target.value)}
                        placeholder="למשל: 100 (0 = ללא)"
                        fullWidth
                        size="sm"
                        style={{ direction: 'ltr', textAlign: 'left' }}
                      />
                    </div>

                    {/* Min Order Amount */}
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        הזמנה מינימלית (₪)
                      </label>
                      <Input
                        type="number"
                        step="1"
                        min="0"
                        value={formData.min_order_amount}
                        onChange={(e) => handleInputChange('min_order_amount', e.target.value)}
                        placeholder="למשל: 50 (0 = ללא)"
                        fullWidth
                        size="sm"
                        style={{ direction: 'ltr', textAlign: 'left' }}
                      />
                    </div>
                  </div>
                )}
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
                              className="flex-1 px-2 py-1 text-sm border border-gray-300 rounded outline-none focus:border-[var(--theme-primary-color)]"
                              placeholder="09:00-17:00"
                            />
                            <IconButton
                              icon={TrashIcon}
                              variant="ghost"
                              size="xs"
                              onClick={() => removeTimeSlot(day.key, index)}
                              tooltip="הסר שעות"
                            />
                          </div>
                        ))}
                        <Button variant="link" size="sm" leftIcon={PlusIcon} onClick={() => addTimeSlot(day.key)}>
                          הוסף שעות
                        </Button>
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
                        className="rounded border-gray-300 focus:ring-[var(--theme-primary-color)] accent-[var(--theme-primary-color)]"
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
                    <Input
                      type="text"
                      value={customAccessibility}
                      onChange={(e) => setCustomAccessibility(e.target.value)}
                      onKeyDown={(e) => e.key === 'Enter' && addCustomAccessibility()}
                      placeholder="הוסף אפשרות נגישות..."
                      fullWidth
                      size="sm"
                    />
                    <Button variant="primary" size="sm" onClick={addCustomAccessibility}>
                      הוסף
                    </Button>
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
                          <IconButton
                            icon={XMarkIcon}
                            variant="ghost"
                            size="xs"
                            onClick={() => removeAccessibilityOption(option)}
                            tooltip="הסר"
                          />
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
          <Button variant="outline" onClick={onClose} disabled={isSaving}>
            ביטול
          </Button>
          <Button variant="primary" onClick={handleSave} disabled={isSaving} loading={isSaving}>
            {editingBranch ? 'עדכן' : 'שמור'}
          </Button>
        </div>
      </div>
    </div>
  );
};

export default BranchModal;