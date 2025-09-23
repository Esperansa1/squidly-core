import React, { useState, useEffect } from 'react';
import { PlusIcon, XMarkIcon } from '@heroicons/react/24/outline';
import { DEFAULT_THEME } from '../../config/theme.js';
import DropdownButton from './DropdownButton.jsx';

const ProductModal = ({
  isOpen,
  onClose,
  onSave,
  product = null, // null for create, object for edit
  branches = [],
  productGroups = [],
  strings = {},
  loading = false
}) => {
  const [formData, setFormData] = useState({
    name: '',
    description: '',
    price: '',
    discounted_price: '',
    category: '',
    tags: '',
    product_group_ids: [],
    availability: {}
  });
  const [errors, setErrors] = useState({});
  const [selectAllBranches, setSelectAllBranches] = useState(false);
  const [availabilityWarnings, setAvailabilityWarnings] = useState([]);

  const theme = DEFAULT_THEME;

  // Initialize form data when modal opens or product changes
  useEffect(() => {
    if (isOpen) {
      // Debug: Log the productGroups data
      console.log('ProductModal - productGroups received:', productGroups);
      console.log('ProductModal - productGroups types:', productGroups?.map(g => ({ id: g.id, name: g.name, type: g.type })));

      if (product) {
        // Edit mode
        const availability = product.availability || {};
        setFormData({
          name: product.name || '',
          description: product.description || '',
          price: product.price?.toString() || '',
          discounted_price: product.discounted_price?.toString() || '',
          category: product.category || '',
          tags: Array.isArray(product.tags) ? product.tags.join(', ') : (product.tags || ''),
          product_group_ids: product.product_group_ids || [],
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
          description: '',
          price: '',
          discounted_price: '',
          category: '',
          tags: '',
          product_group_ids: [],
          availability: defaultAvailability
        });
        setSelectAllBranches(true);
      }
      setErrors({});
    }
  }, [isOpen, product, branches]);

  // Validate availability against group dependencies
  useEffect(() => {
    const warnings = [];

    if (formData.product_group_ids.length > 0 && formData.availability) {
      const filteredBranches = branches.filter(branch =>
        branch.name !== 'כל הסניפים' &&
        branch.name !== 'All Branches' &&
        branch.id !== 0
      );

      filteredBranches.forEach(branch => {
        const isProductAvailable = formData.availability[branch.id];

        if (isProductAvailable) {
          // Product is enabled - check if all associated groups are available
          const unavailableGroups = formData.product_group_ids.filter(groupId => {
            const group = productGroups.find(g => g.id === groupId);
            return group && group.final_availability && !group.final_availability[branch.id];
          });

          if (unavailableGroups.length > 0) {
            const groupNames = unavailableGroups.map(groupId => {
              const group = productGroups.find(g => g.id === groupId);
              return group ? group.name : `קבוצה ${groupId}`;
            });

            warnings.push({
              branch: branch.name,
              message: `מוצר זמין ב${branch.name} אך קבוצות לא זמינות: ${groupNames.join(', ')}`
            });
          }
        }
      });
    }

    setAvailabilityWarnings(warnings);
  }, [formData.availability, formData.product_group_ids, productGroups, branches]);

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

  const handleProductGroupAdd = (groupId) => {
    if (groupId && !formData.product_group_ids.includes(groupId)) {
      setFormData(prev => ({
        ...prev,
        product_group_ids: [...prev.product_group_ids, groupId]
      }));
    }
  };

  const handleProductGroupRemove = (groupId) => {
    setFormData(prev => ({
      ...prev,
      product_group_ids: prev.product_group_ids.filter(id => id !== groupId)
    }));
  };

  const validateForm = () => {
    const newErrors = {};

    if (!formData.name.trim()) {
      newErrors.name = 'שם המוצר חובה';
    }

    if (!formData.price.trim()) {
      newErrors.price = 'מחיר חובה';
    } else {
      const price = parseFloat(formData.price);
      if (isNaN(price) || price < 0) {
        newErrors.price = 'מחיר חייב להיות מספר חיובי';
      }
    }

    // Validate discounted price if provided
    if (formData.discounted_price.trim()) {
      const discountedPrice = parseFloat(formData.discounted_price);
      const regularPrice = parseFloat(formData.price);
      if (isNaN(discountedPrice) || discountedPrice < 0) {
        newErrors.discounted_price = 'מחיר מוזל חייב להיות מספר חיובי';
      } else if (!isNaN(regularPrice) && discountedPrice >= regularPrice) {
        newErrors.discounted_price = 'מחיר מוזל חייב להיות נמוך מהמחיר הרגיל';
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
      description: formData.description.trim(),
      price: parseFloat(formData.price),
      category: formData.category.trim(),
      tags: formData.tags.trim() ? formData.tags.split(',').map(tag => tag.trim()).filter(tag => tag) : [],
      product_group_ids: formData.product_group_ids,
      availability: formData.availability
    };

    // Only include discounted_price if it has a value
    if (formData.discounted_price.trim()) {
      submitData.discounted_price = parseFloat(formData.discounted_price);
    }

    await onSave(submitData);
  };

  const isEditMode = !!product;
  const title = isEditMode ?
    (strings.edit_product || 'ערוך מוצר') :
    (strings.create_product || 'צור מוצר חדש');

  return (
    <div
      className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      onClick={handleBackdropClick}
    >
      <div className="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-hidden">
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
        <div className="overflow-y-auto max-h-[calc(90vh-140px)]">
          <form onSubmit={handleSubmit} className="p-6">
            {/* Availability Warnings */}
            {availabilityWarnings.length > 0 && (
              <div className="mb-6 space-y-2">
                {availabilityWarnings.map((warning, index) => (
                  <div key={index} className="p-3 rounded-md" style={{ backgroundColor: '#fbbf24' + '20', border: `1px solid #fbbf24`, color: '#92400e' }}>
                    <div className="flex items-center gap-2">
                      <span className="text-sm font-medium">⚠️</span>
                      <span className="text-sm">{warning.message}</span>
                    </div>
                  </div>
                ))}
              </div>
            )}

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {/* Left Column */}
              <div className="space-y-4">
                {/* Product Name */}
                <div>
                  <label className="block text-sm font-medium mb-2 text-right" style={{ color: theme.text_primary }}>
                    {strings.product_name || 'שם המוצר'} *
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
                    placeholder={strings.enter_product_name || 'הזן שם מוצר'}
                    disabled={loading}
                  />
                  {errors.name && (
                    <p className="text-sm mt-1 text-right" style={{ color: theme.danger_color }}>{errors.name}</p>
                  )}
                </div>

                {/* Description */}
                <div>
                  <label className="block text-sm font-medium mb-2 text-right" style={{ color: theme.text_primary }}>
                    {strings.description || 'תיאור'}
                  </label>
                  <textarea
                    value={formData.description}
                    onChange={(e) => handleInputChange('description', e.target.value)}
                    rows={3}
                    className="w-full px-3 py-2 rounded-md text-right focus:outline-none focus:ring-2 resize-none"
                    style={{
                      border: `1px solid ${theme.border_color}`,
                      focusRingColor: theme.primary_color,
                      backgroundColor: theme.bg_white
                    }}
                    onFocus={(e) => e.target.style.boxShadow = `0 0 0 2px ${theme.primary_color}40`}
                    onBlur={(e) => e.target.style.boxShadow = 'none'}
                    placeholder={strings.enter_description || 'הזן תיאור מוצר'}
                    disabled={loading}
                  />
                </div>

                {/* Price */}
                <div>
                  <label className="block text-sm font-medium mb-2 text-right" style={{ color: theme.text_primary }}>
                    {strings.price || 'מחיר'} (₪) *
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

                {/* Discounted Price */}
                <div>
                  <label className="block text-sm font-medium mb-2 text-right" style={{ color: theme.text_primary }}>
                    {strings.discounted_price || 'מחיר מוזל'} (₪)
                  </label>
                  <input
                    type="number"
                    step="0.01"
                    min="0"
                    value={formData.discounted_price}
                    onChange={(e) => handleInputChange('discounted_price', e.target.value)}
                    className="w-full px-3 py-2 rounded-md text-right focus:outline-none focus:ring-2"
                    style={{
                      border: `1px solid ${errors.discounted_price ? theme.danger_color : theme.border_color}`,
                      focusRingColor: theme.primary_color,
                      backgroundColor: theme.bg_white
                    }}
                    onFocus={(e) => e.target.style.boxShadow = `0 0 0 2px ${theme.primary_color}40`}
                    onBlur={(e) => e.target.style.boxShadow = 'none'}
                    placeholder="0.00"
                    disabled={loading}
                  />
                  {errors.discounted_price && (
                    <p className="text-sm mt-1 text-right" style={{ color: theme.danger_color }}>{errors.discounted_price}</p>
                  )}
                </div>
              </div>

              {/* Right Column */}
              <div className="space-y-4">
                {/* Category */}
                <div>
                  <label className="block text-sm font-medium mb-2 text-right" style={{ color: theme.text_primary }}>
                    {strings.category || 'קטגוריה'}
                  </label>
                  <input
                    type="text"
                    value={formData.category}
                    onChange={(e) => handleInputChange('category', e.target.value)}
                    className="w-full px-3 py-2 rounded-md text-right focus:outline-none focus:ring-2"
                    style={{
                      border: `1px solid ${theme.border_color}`,
                      focusRingColor: theme.primary_color,
                      backgroundColor: theme.bg_white
                    }}
                    onFocus={(e) => e.target.style.boxShadow = `0 0 0 2px ${theme.primary_color}40`}
                    onBlur={(e) => e.target.style.boxShadow = 'none'}
                    placeholder={strings.enter_category || 'הזן קטגוריה'}
                    disabled={loading}
                  />
                </div>

                {/* Tags */}
                <div>
                  <label className="block text-sm font-medium mb-2 text-right" style={{ color: theme.text_primary }}>
                    {strings.tags || 'תגיות'}
                  </label>
                  <input
                    type="text"
                    value={formData.tags}
                    onChange={(e) => handleInputChange('tags', e.target.value)}
                    className="w-full px-3 py-2 rounded-md text-right focus:outline-none focus:ring-2"
                    style={{
                      border: `1px solid ${theme.border_color}`,
                      focusRingColor: theme.primary_color,
                      backgroundColor: theme.bg_white
                    }}
                    onFocus={(e) => e.target.style.boxShadow = `0 0 0 2px ${theme.primary_color}40`}
                    onBlur={(e) => e.target.style.boxShadow = 'none'}
                    placeholder={strings.enter_tags || 'הזן תגיות מופרדות בפסיקים'}
                    disabled={loading}
                  />
                  <p className="text-xs mt-1 text-right" style={{ color: theme.text_secondary }}>
                    הפרד תגיות בפסיקים (לדוגמה: בשר, קלאסי, גבינה)
                  </p>
                </div>

                {/* Product Groups */}
                <div>
                  <label className="block text-sm font-medium mb-3 text-right" style={{ color: theme.text_primary }}>
                    {strings.groups || strings.product_groups || 'קבוצות'}
                  </label>

                  {productGroups && productGroups.length > 0 ? (
                    <>
                      {/* Add Product Group Dropdown */}
                      {(() => {
                        const availableGroups = productGroups.filter(group =>
                          !formData.product_group_ids.includes(group.id)
                        );

                        return availableGroups.length > 0 ? (
                          <DropdownButton
                            options={availableGroups}
                            value=""
                            onChange={(value) => {
                              if (value) {
                                handleProductGroupAdd(parseInt(value));
                              }
                            }}
                            placeholder="בחר קבוצה להוספה..."
                            disabled={loading}
                            getOptionLabel={(group) => `${group.name} (${group.type === 'ingredient' ? 'מרכיבים' : 'מוצרים'})`}
                            getOptionValue={(group) => group.id}
                            width="w-full"
                            direction="left"
                          />
                        ) : (
                          <p className="text-sm text-right" style={{ color: theme.text_secondary }}>
                            {formData.product_group_ids.length > 0
                              ? 'כל הקבוצות הזמינות נבחרו'
                              : 'אין קבוצות זמינות'
                            }
                          </p>
                        );
                      })()}

                      {/* Selected Product Groups */}
                      {formData.product_group_ids.length > 0 && (
                        <div className="mt-3">
                          <div className="flex flex-wrap gap-2">
                            {formData.product_group_ids.map((groupId) => {
                              const group = productGroups.find(g => g.id === groupId);
                              return group ? (
                                <div
                                  key={groupId}
                                  className="flex items-center gap-2 px-3 py-1 rounded-full text-sm"
                                  style={{
                                    backgroundColor: theme.bg_gray_100,
                                    color: theme.text_primary,
                                    border: `1px solid ${theme.border_color}`
                                  }}
                                >
                                  <span>{group.name} ({group.type === 'ingredient' ? 'מרכיבים' : 'מוצרים'})</span>
                                  <button
                                    type="button"
                                    onClick={() => handleProductGroupRemove(groupId)}
                                    className="ml-1 text-gray-500 hover:text-red-600 transition-colors"
                                    disabled={loading}
                                  >
                                    ×
                                  </button>
                                </div>
                              ) : null;
                            })}
                          </div>
                        </div>
                      )}
                    </>
                  ) : (
                    <div className="text-center py-4 px-3 rounded-md" style={{ backgroundColor: theme.bg_gray_50, border: `1px solid ${theme.border_color}` }}>
                      <p className="text-sm" style={{ color: theme.text_secondary }}>
                        אין קבוצות זמינות
                      </p>
                      <p className="text-xs mt-1" style={{ color: theme.text_secondary }}>
                        עבור לטאב "קבוצות" כדי ליצור קבוצות חדשות
                      </p>
                    </div>
                  )}
                </div>
              </div>
            </div>

            {/* Branch Availability */}
            <div className="mt-6">
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
              <div className="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-40 overflow-y-auto">
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
            <div className="flex gap-3 justify-start mt-8" dir="rtl">
              <button
                type="submit"
                disabled={loading}
                className="px-6 py-2 text-sm font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
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
                className="px-6 py-2 text-sm font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
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
    </div>
  );
};

export default ProductModal;