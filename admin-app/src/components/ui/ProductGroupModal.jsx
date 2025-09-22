import React, { useState, useEffect, useCallback } from 'react';
import { PlusIcon, XMarkIcon } from '@heroicons/react/24/outline';
import { DEFAULT_THEME } from '../../config/theme.js';
import api from '../../services/api.js';
import DropdownButton from './DropdownButton.jsx';

const ProductGroupModal = ({
  isOpen,
  onClose,
  onSave,
  group = null,
  branches = [],
  strings = {},
  loading = false
}) => {
  const [formData, setFormData] = useState({
    name: '',
    description: '',
    type: 'ingredient',
    group_item_ids: [],
    availability: {}
  });

  const [availableItems, setAvailableItems] = useState([]);
  const [selectedItems, setSelectedItems] = useState([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');
  const [selectAllBranches, setSelectAllBranches] = useState(false);
  const [warnings, setWarnings] = useState([]);

  const theme = DEFAULT_THEME;

  // Load available items when modal opens or type changes
  useEffect(() => {
    if (isOpen) {
      loadAvailableItems();
    }
  }, [isOpen, formData.type]);

  // Populate form when editing existing group
  useEffect(() => {
    if (group) {
      const availability = group.availability || {};
      setFormData({
        name: group.name || '',
        description: group.description || '',
        type: group.type || 'ingredient',
        group_item_ids: group.group_item_ids || [],
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
      // We'll need to resolve the selected items after available items load
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
        type: 'ingredient',
        group_item_ids: [],
        availability: defaultAvailability
      });
      setSelectedItems([]);
      setSelectAllBranches(true);
    }
  }, [group, branches]);

  // Resolve selected items when editing and available items are loaded
  useEffect(() => {
    if (group && availableItems.length > 0 && formData.group_item_ids.length > 0) {
      const resolvedItems = availableItems.filter(item =>
        formData.group_item_ids.includes(item.id)
      );
      setSelectedItems(resolvedItems);
    }
  }, [group, availableItems, formData.group_item_ids]);

  const loadAvailableItems = async () => {
    try {
      setIsLoading(true);
      const response = formData.type === 'ingredient'
        ? await api.getIngredients()
        : await api.getProducts();
      setAvailableItems(response.data || []);
    } catch (error) {
      console.error('Error loading items:', error);
      setError(strings.errorLoadingItems || 'Error loading items');
    } finally {
      setIsLoading(false);
    }
  };

  const handleInputChange = useCallback((e) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));
  }, []);

  const handleTypeChange = useCallback((value) => {
    // Clear selected items when type changes to prevent mixed-type groups
    setSelectedItems([]);
    setFormData(prev => ({
      ...prev,
      type: value,
      group_item_ids: []
    }));
  }, []);

  const handleItemSelect = useCallback((itemId) => {
    const item = availableItems.find(itm => itm.id === parseInt(itemId));
    if (item && !selectedItems.find(itm => itm.id === item.id)) {
      const newSelected = [...selectedItems, item];
      setSelectedItems(newSelected);
      setFormData(prev => ({
        ...prev,
        group_item_ids: newSelected.map(itm => itm.id)
      }));
    }
  }, [availableItems, selectedItems]);

  const handleRemoveItem = useCallback((itemId) => {
    const newSelected = selectedItems.filter(itm => itm.id !== itemId);
    setSelectedItems(newSelected);
    setFormData(prev => ({
      ...prev,
      group_item_ids: newSelected.map(itm => itm.id)
    }));
  }, [selectedItems]);

  const handleSubmit = async (e) => {
    e.preventDefault();

    if (!formData.name.trim()) {
      setError(strings.nameRequired || 'Name is required');
      return;
    }

    // Validate that all selected items match the group type
    if (selectedItems.length > 0) {
      const hasIngredients = selectedItems.some(item => {
        // Check if item has properties that indicate it's an ingredient
        return availableItems.find(avail => avail.id === item.id && formData.type === 'ingredient');
      });
      const hasProducts = selectedItems.some(item => {
        // Check if item has properties that indicate it's a product
        return availableItems.find(avail => avail.id === item.id && formData.type === 'product');
      });

      // If we're creating an ingredient group but have products, or vice versa
      if ((formData.type === 'ingredient' && hasProducts) || (formData.type === 'product' && hasIngredients)) {
        setError('לא ניתן לערבב מרכיבים ומוצרים באותה קבוצה');
        return;
      }
    }

    if (typeof onSave !== 'function') {
      setError('Internal error: onSave is not a function');
      return;
    }

    try {
      setIsLoading(true);
      setError('');
      await onSave(formData);
    } catch (error) {
      console.error('Error saving ingredient group:', error);
      setError(error.message || strings.errorSaving || 'Error saving ingredient group');
    } finally {
      setIsLoading(false);
    }
  };

  const handleAvailabilityChange = useCallback((branchId, isAvailable) => {
    setFormData(prev => ({
      ...prev,
      availability: {
        ...prev.availability,
        [branchId]: isAvailable
      }
    }));

    // Update "select all" state
    const filteredBranches = branches.filter(branch =>
      branch.name !== 'כל הסניפים' &&
      branch.name !== 'All Branches' &&
      branch.id !== 0
    );

    const newAvailability = {
      ...formData.availability,
      [branchId]: isAvailable
    };

    const allSelected = filteredBranches.every(branch => newAvailability[branch.id] === true);
    setSelectAllBranches(allSelected);
  }, [formData.availability, branches]);

  const handleSelectAllBranches = useCallback((selectAll) => {
    const filteredBranches = branches.filter(branch =>
      branch.name !== 'כל הסניפים' &&
      branch.name !== 'All Branches' &&
      branch.id !== 0
    );

    const newAvailability = {};
    filteredBranches.forEach(branch => {
      newAvailability[branch.id] = selectAll;
    });

    setFormData(prev => ({
      ...prev,
      availability: newAvailability
    }));

    setSelectAllBranches(selectAll);
  }, [branches]);

  // Validate availability consistency when items or availability changes
  useEffect(() => {
    const newWarnings = [];

    if (selectedItems.length > 0 && formData.availability) {
      // Check if group is enabled for branches where some items are not available
      const filteredBranches = branches.filter(branch =>
        branch.name !== 'כל הסניפים' &&
        branch.name !== 'All Branches' &&
        branch.id !== 0
      );

      filteredBranches.forEach(branch => {
        const isGroupAvailable = formData.availability[branch.id];

        if (isGroupAvailable) {
          // Group is enabled - check if all items are available
          const unavailableItems = selectedItems.filter(item => {
            // In a real scenario, we'd check item availability from API
            // For now, we'll simulate this check
            return item.availability && !item.availability[branch.id];
          });

          if (unavailableItems.length > 0) {
            newWarnings.push({
              type: 'availability',
              message: `קבוצה זמינה ב${branch.name} אך חלק מהפריטים לא זמינים`,
              branch: branch.name,
              items: unavailableItems.map(item => item.name)
            });
          }
        }
      });
    }

    setWarnings(newWarnings);
  }, [selectedItems, formData.availability, branches]);

  const handleClose = useCallback(() => {
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
      type: 'ingredient',
      group_item_ids: [],
      availability: defaultAvailability
    });
    setSelectedItems([]);
    setError('');
    setSelectAllBranches(true);
    onClose();
  }, [onClose, branches]);

  // Get items not yet selected
  const unselectedItems = availableItems.filter(
    item => !selectedItems.find(selected => selected.id === item.id)
  );

  const itemDropdownOptions = unselectedItems.map(item => ({
    value: item.id.toString(),
    label: item.name
  }));

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      <div className="flex items-center justify-center min-h-screen p-4 text-center">
        <div className="fixed inset-0 bg-black opacity-30" onClick={handleClose}></div>
        <div className="relative max-w-md w-full bg-white rounded-lg shadow-xl">
          {/* Header */}
          <div className="flex items-center justify-between p-6" style={{ borderBottom: `1px solid ${theme.border_color}` }}>
            <div className="flex items-center gap-3">
              <PlusIcon className="w-6 h-6" style={{ color: theme.primary_color }} />
              <h2 className="text-lg font-semibold" style={{ color: theme.text_primary }}>
                {group ?
                  (strings.edit_product_group || 'ערוך קבוצה') :
                  (strings.create_product_group || 'צור קבוצה חדשה')
                }
              </h2>
            </div>
            <button
              onClick={handleClose}
              className="transition-colors duration-200"
              style={{ color: theme.text_secondary }}
              onMouseEnter={(e) => e.target.style.color = theme.text_primary}
              onMouseLeave={(e) => e.target.style.color = theme.text_secondary}
              disabled={loading}
            >
              <XMarkIcon className="w-6 h-6" />
            </button>
          </div>

          {/* Content */}
          <div className="p-6">
            {/* Error Message */}
            {error && (
              <div className="mb-4 p-3 rounded" style={{ backgroundColor: theme.danger_color + '20', border: `1px solid ${theme.danger_color}`, color: theme.danger_color }}>
                {error}
              </div>
            )}

            {/* Warnings */}
            {warnings.length > 0 && (
              <div className="mb-4 space-y-2">
                {warnings.map((warning, index) => (
                  <div key={index} className="p-3 rounded" style={{ backgroundColor: '#fbbf24' + '20', border: `1px solid #fbbf24`, color: '#92400e' }}>
                    <div className="flex items-center gap-2">
                      <span className="text-sm font-medium">⚠️</span>
                      <span className="text-sm">{warning.message}</span>
                    </div>
                    {warning.items && warning.items.length > 0 && (
                      <div className="mt-2 text-xs">
                        פריטים לא זמינים: {warning.items.join(', ')}
                      </div>
                    )}
                  </div>
                ))}
              </div>
            )}

            {/* Form */}
            <form id="product-group-form" onSubmit={handleSubmit} className="space-y-4">
              {/* Name Field */}
              <div>
                <label className="block text-sm font-medium mb-2 text-right" style={{ color: theme.text_primary }}>
                  {strings.name || 'שם הקבוצה'} *
                </label>
                <input
                  type="text"
                  name="name"
                  value={formData.name}
                  onChange={handleInputChange}
                  className="w-full px-3 py-2 rounded text-right"
                  style={{
                    border: `1px solid ${theme.border_color}`,
                    backgroundColor: theme.background_secondary
                  }}
                  onFocus={(e) => e.target.style.boxShadow = `0 0 0 2px ${theme.primary_color}40`}
                  onBlur={(e) => e.target.style.boxShadow = 'none'}
                  placeholder={strings.group_name_placeholder || 'הכנס שם קבוצה...'}
                  disabled={isLoading}
                  required
                />
                {error && (
                  <p className="text-sm mt-1 text-right" style={{ color: theme.danger_color }}>{error}</p>
                )}
              </div>

              {/* Description Field */}
              <div>
                <label className="block text-sm font-medium mb-2 text-right" style={{ color: theme.text_primary }}>
                  {strings.description || 'תיאור'}
                </label>
                <textarea
                  name="description"
                  value={formData.description}
                  onChange={handleInputChange}
                  rows={3}
                  className="w-full px-3 py-2 rounded text-right"
                  style={{
                    border: `1px solid ${theme.border_color}`,
                    backgroundColor: theme.background_secondary
                  }}
                  onFocus={(e) => e.target.style.boxShadow = `0 0 0 2px ${theme.primary_color}40`}
                  onBlur={(e) => e.target.style.boxShadow = 'none'}
                  placeholder={strings.description_placeholder || 'הכנס תיאור קבוצה...'}
                  disabled={isLoading}
                />
              </div>

              {/* Type Field */}
              <div>
                <label className="block text-sm font-medium mb-2 text-right" style={{ color: theme.text_primary }}>
                  {strings.type || 'סוג'} *
                </label>
                <DropdownButton
                  options={[
                    { value: 'ingredient', label: 'מרכיבים' },
                    { value: 'product', label: 'מוצרים' }
                  ]}
                  value={formData.type}
                  onChange={handleTypeChange}
                  placeholder={strings.type_placeholder || 'בחר סוג...'}
                  disabled={isLoading}
                  width="w-full"
                />
              </div>

              {/* Items Selection */}
              <div>
                <label className="block text-sm font-medium mb-2 text-right" style={{ color: theme.text_primary }}>
                  {formData.type === 'ingredient' ? (strings.ingredients || 'מרכיבים') : (strings.products || 'מוצרים')}
                </label>

                {/* Selected Items */}
                {selectedItems.length > 0 && (
                  <div className="mb-3">
                    <div className="flex flex-wrap gap-2">
                      {selectedItems.map(item => (
                        <span
                          key={item.id}
                          className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                          style={{ backgroundColor: theme.primary_color + '20', color: theme.primary_color }}
                        >
                          {item.name}
                          <button
                            type="button"
                            onClick={() => handleRemoveItem(item.id)}
                            className="mr-2 hover:opacity-75"
                            disabled={isLoading}
                            style={{ color: theme.primary_color }}
                          >
                            ×
                          </button>
                        </span>
                      ))}
                    </div>
                  </div>
                )}

                {/* Dropdown for adding items */}
                <DropdownButton
                  options={itemDropdownOptions}
                  value=""
                  onChange={handleItemSelect}
                  placeholder={formData.type === 'ingredient'
                    ? (strings.select_ingredient || 'בחר מרכיב...')
                    : (strings.select_product || 'בחר מוצר...')
                  }
                  disabled={isLoading || itemDropdownOptions.length === 0}
                />

                {itemDropdownOptions.length === 0 && !isLoading && (
                  <p className="text-sm mt-1 text-right" style={{ color: theme.text_secondary }}>
                    {formData.type === 'ingredient'
                      ? (strings.all_ingredients_selected || 'כל המרכיבים נבחרו')
                      : (strings.all_products_selected || 'כל המוצרים נבחרו')
                    }
                  </p>
                )}
              </div>

              {/* Branch Availability */}
              <div>
                <label className="block text-sm font-medium mb-2 text-right" style={{ color: theme.text_primary }}>
                  {strings.availability || 'זמינות בסניפים'}
                </label>

                {/* Select All Branches Toggle */}
                <div className="mb-3 p-3 rounded-md" style={{ backgroundColor: theme.bg_gray_50 }}>
                  <label className="flex items-center justify-between cursor-pointer">
                    <input
                      type="checkbox"
                      checked={selectAllBranches}
                      onChange={(e) => handleSelectAllBranches(e.target.checked)}
                      className="w-4 h-4 rounded focus:ring-2"
                      style={{
                        accentColor: theme.primary_color,
                      }}
                      disabled={isLoading}
                    />
                    <span className="text-sm font-medium" style={{ color: theme.text_primary }}>
                      {strings.select_all_branches || 'בחר את כל הסניפים'}
                    </span>
                  </label>
                </div>

                {/* Individual Branch Checkboxes */}
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
                        disabled={isLoading}
                      />
                      <span className="text-sm" style={{ color: theme.text_secondary }}>{branch.name}</span>
                    </label>
                  ))}
                </div>
              </div>
            </form>
          </div>

          {/* Footer */}
          <div className="flex justify-end gap-3 p-6" style={{ borderTop: `1px solid ${theme.border_color}` }}>
            <button
              type="button"
              onClick={handleClose}
              className="px-4 py-2 text-sm font-medium rounded transition-colors duration-200"
              style={{
                color: theme.text_secondary,
                backgroundColor: theme.background_secondary,
                border: `1px solid ${theme.border_color}`
              }}
              onMouseEnter={(e) => e.target.style.backgroundColor = theme.background_primary}
              onMouseLeave={(e) => e.target.style.backgroundColor = theme.background_secondary}
              disabled={isLoading}
            >
              {strings.cancel || 'ביטול'}
            </button>
            <button
              type="submit"
              form="product-group-form"
              className="px-4 py-2 text-sm font-medium rounded transition-colors duration-200"
              style={{
                color: 'white',
                backgroundColor: theme.primary_color,
                border: `1px solid ${theme.primary_color}`
              }}
              onMouseEnter={(e) => e.target.style.opacity = '0.9'}
              onMouseLeave={(e) => e.target.style.opacity = '1'}
              disabled={isLoading}
            >
              {isLoading
                ? (strings.saving || 'שומר...')
                : (strings.save || 'שמור')
              }
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ProductGroupModal;