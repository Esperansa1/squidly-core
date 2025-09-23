import React, { useState, useEffect } from 'react';
import { PlusIcon, XMarkIcon } from '@heroicons/react/24/outline';
import { DEFAULT_THEME } from '../../config/theme.js';

const IngredientModal = ({
  isOpen,
  onClose,
  onSave,
  ingredient = null, // null for create, object for edit
  branches = [],
  strings = {},
  loading = false
}) => {
  const [formData, setFormData] = useState({
    name: '',
    price: '',
    availability: {}
  });
  const [errors, setErrors] = useState({});
  const [selectAllBranches, setSelectAllBranches] = useState(false);

  const theme = DEFAULT_THEME;

  // Initialize form data when modal opens or ingredient changes
  useEffect(() => {
    if (isOpen) {
      if (ingredient) {
        // Edit mode
        const availability = ingredient.availability || {};
        setFormData({
          name: ingredient.name || '',
          price: ingredient.price?.toString() || '',
          availability: availability
        });
        // Check if all branches are selected (excluding "All Branches" entries)
        const filteredBranches = branches.filter(branch =>
          branch.name !== 'כל הסניפים' &&
          branch.name !== 'All Branches' &&
          branch.id !== 0
        );
        const allSelected = filteredBranches.every(branch => availability[branch.id] === true);
        setSelectAllBranches(allSelected);
      } else {
        // Create mode - set all branches as available by default (excluding "All Branches" entries)
        const defaultAvailability = {};
        branches.filter(branch => 
          branch.name !== 'כל הסניפים' && 
          branch.name !== 'All Branches' && 
          branch.id !== 0
        ).forEach(branch => {
          defaultAvailability[branch.id] = true;
        });
        
        setFormData({
          name: '',
          price: '',
          availability: defaultAvailability
        });
        setSelectAllBranches(true);
      }
      setErrors({});
    }
  }, [isOpen, ingredient, branches]);

  if (!isOpen) return null;

  const handleBackdropClick = (e) => {
    if (e.target === e.currentTarget) {
      onClose();
    }
  };

  const handleInputChange = (field, value) => {
    setFormData(prev => ({
      ...prev,
      [field]: value
    }));
    
    // Clear error when user starts typing
    if (errors[field]) {
      setErrors(prev => ({
        ...prev,
        [field]: ''
      }));
    }
  };

  const handleAvailabilityChange = (branchId, available) => {
    setFormData(prev => {
      const newAvailability = {
        ...prev.availability,
        [branchId]: available
      };
      
      // Update the "Select All" state based on individual selections (excluding "All Branches" entries)
      const filteredBranches = branches.filter(branch => 
        branch.name !== 'כל הסניפים' && 
        branch.name !== 'All Branches' && 
        branch.id !== 0
      );
      const allSelected = filteredBranches.every(branch => newAvailability[branch.id] === true);
      setSelectAllBranches(allSelected);
      
      return {
        ...prev,
        availability: newAvailability
      };
    });
  };

  const handleSelectAllChange = (selectAll) => {
    setSelectAllBranches(selectAll);
    
    const newAvailability = {};
    branches.filter(branch => 
      branch.name !== 'כל הסניפים' && 
      branch.name !== 'All Branches' && 
      branch.id !== 0
    ).forEach(branch => {
      newAvailability[branch.id] = selectAll;
    });
    
    setFormData(prev => ({
      ...prev,
      availability: newAvailability
    }));
  };

  const validateForm = () => {
    const newErrors = {};
    
    if (!formData.name.trim()) {
      newErrors.name = 'שם המרכיב חובה';
    }
    
    if (!formData.price.trim()) {
      newErrors.price = 'מחיר חובה';
    } else {
      const price = parseFloat(formData.price);
      if (isNaN(price) || price < 0) {
        newErrors.price = 'מחיר חייב להיות מספר חיובי';
      }
    }
    
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!validateForm()) {
      return;
    }
    
    const submitData = {
      name: formData.name.trim(),
      price: parseFloat(formData.price),
      availability: formData.availability
    };
    
    await onSave(submitData);
  };

  const isEditMode = !!ingredient;
  const title = isEditMode ? 
    (strings.edit_ingredient || 'ערוך מרכיב') : 
    (strings.create_ingredient || 'צור מרכיב חדש');

  return (
    <div 
      className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      onClick={handleBackdropClick}
    >
      <div className="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between p-6" style={{ borderBottom: `1px solid ${theme.border_color}` }}>
          <div className="flex items-center gap-3">
            <PlusIcon className="w-6 h-6" style={{ color: theme.primary_color }} />
            <h2 className="text-lg font-semibold" style={{ color: theme.text_primary }}>
              {title}
            </h2>
          </div>
          <button
            onClick={onClose}
            className="transition-colors"
            style={{ color: theme.text_secondary }}
            onMouseEnter={(e) => e.target.style.color = theme.text_primary}
            onMouseLeave={(e) => e.target.style.color = theme.text_secondary}
            disabled={loading}
          >
            <XMarkIcon className="w-6 h-6" />
          </button>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit} className="p-6">
          {/* Ingredient Name */}
          <div className="mb-4">
            <label className="block text-sm font-medium mb-2 text-right" style={{ color: theme.text_primary }}>
              {strings.ingredient_name || 'שם המרכיב'}
            </label>
            <input
              type="text"
              value={formData.name}
              onChange={(e) => handleInputChange('name', e.target.value)}
              className="w-full px-3 py-2 rounded-md text-right focus:outline-none focus:ring-2"
              style={{
                border: `1px solid ${errors.name ? theme.danger_color : theme.border_color}`,
                focusRingColor: theme.primary_color,
                backgroundColor: theme.bg_white
              }}
              onFocus={(e) => e.target.style.boxShadow = `0 0 0 2px ${theme.primary_color}40`}
              onBlur={(e) => e.target.style.boxShadow = 'none'}
              placeholder={strings.enter_ingredient_name || 'הזן שם מרכיב'}
              disabled={loading}
            />
            {errors.name && (
              <p className="text-sm mt-1 text-right" style={{ color: theme.danger_color }}>{errors.name}</p>
            )}
          </div>

          {/* Price */}
          <div className="mb-4">
            <label className="block text-sm font-medium mb-2 text-right" style={{ color: theme.text_primary }}>
              {strings.price || 'מחיר'} (₪)
            </label>
            <input
              type="number"
              step="0.01"
              min="0"
              value={formData.price}
              onChange={(e) => handleInputChange('price', e.target.value)}
              className="w-full px-3 py-2 rounded-md text-right focus:outline-none focus:ring-2"
              style={{
                border: `1px solid ${errors.price ? theme.danger_color : theme.border_color}`,
                focusRingColor: theme.primary_color,
                backgroundColor: theme.bg_white
              }}
              onFocus={(e) => e.target.style.boxShadow = `0 0 0 2px ${theme.primary_color}40`}
              onBlur={(e) => e.target.style.boxShadow = 'none'}
              placeholder="0.00"
              disabled={loading}
            />
            {errors.price && (
              <p className="text-sm mt-1 text-right" style={{ color: theme.danger_color }}>{errors.price}</p>
            )}
          </div>

          {/* Branch Availability */}
          <div className="mb-6">
            <label className="block text-sm font-medium mb-3 text-right" style={{ color: theme.text_primary }}>
              {strings.availability || 'זמינות בסניפים'}
            </label>
            
            {/* Select All Branches Option */}
            <div className="mb-3">
              <label className="flex items-center justify-between p-3 rounded-md" style={{ backgroundColor: theme.bg_gray_50, border: `1px solid ${theme.border_color}` }}>
                <input
                  type="checkbox"
                  checked={selectAllBranches}
                  onChange={(e) => handleSelectAllChange(e.target.checked)}
                  className="w-4 h-4 rounded focus:ring-2"
                  style={{
                    accentColor: theme.primary_color,
                  }}
                  disabled={loading}
                />
                <span className="text-sm font-semibold" style={{ color: theme.text_primary }}>
                  {strings.all_branches || 'כל הסניפים'}
                </span>
              </label>
            </div>

            {/* Individual Branch Options */}
            <div className="space-y-2 max-h-40 overflow-y-auto">
              {branches.filter(branch => 
                branch.name !== 'כל הסניפים' && 
                branch.name !== 'All Branches' && 
                branch.id !== 0
              ).map((branch) => (
                <label key={branch.id} className="flex items-center justify-between p-2 rounded-md" style={{ backgroundColor: theme.bg_gray_50 }}>
                  <input
                    type="checkbox"
                    checked={formData.availability[branch.id] || false}
                    onChange={(e) => handleAvailabilityChange(branch.id, e.target.checked)}
                    className="w-4 h-4 rounded focus:ring-2"
                    style={{
                      accentColor: theme.primary_color,
                    }}
                    disabled={loading}
                  />
                  <span className="text-sm" style={{ color: theme.text_secondary }}>{branch.name}</span>
                </label>
              ))}
            </div>
          </div>

          {/* Actions */}
          <div className="flex gap-3 justify-start" dir="rtl">
            <button
              type="submit"
              disabled={loading}
              className="px-4 py-2 text-sm font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              style={{
                backgroundColor: theme.primary_color,
                color: 'white',
                borderColor: theme.primary_color
              }}
              onMouseEnter={(e) => !loading && (e.target.style.backgroundColor = '#B91C1C')}
              onMouseLeave={(e) => !loading && (e.target.style.backgroundColor = theme.primary_color)}
              onFocus={(e) => e.target.style.boxShadow = `0 0 0 2px ${theme.primary_color}40`}
              onBlur={(e) => e.target.style.boxShadow = 'none'}
            >
              {loading ? 
                (isEditMode ? 'מעדכן...' : 'יוצר...') : 
                (isEditMode ? (strings.update || 'עדכן') : (strings.create || 'צור'))
              }
            </button>
            <button
              type="button"
              onClick={onClose}
              disabled={loading}
              className="px-4 py-2 text-sm font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              style={{
                backgroundColor: theme.bg_white,
                color: theme.text_secondary,
                border: `1px solid ${theme.border_color}`
              }}
              onMouseEnter={(e) => !loading && (e.target.style.backgroundColor = theme.bg_gray_50)}
              onMouseLeave={(e) => !loading && (e.target.style.backgroundColor = theme.bg_white)}
              onFocus={(e) => e.target.style.boxShadow = `0 0 0 2px ${theme.border_color}40`}
              onBlur={(e) => e.target.style.boxShadow = 'none'}
            >
              {strings.cancel || 'ביטול'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default IngredientModal;