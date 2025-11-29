import React, { useState, useEffect } from 'react';
import publicApi from '../../services/publicApi';
import { t } from '../../i18n/translations';

/**
 * ProductCustomizationModal - Wolt-style product customization
 * Allows selecting items from product groups before adding to cart
 * Enforces min/max selection constraints
 */
export default function ProductCustomizationModal({ product, isOpen, onClose, onConfirm }) {
  const [productData, setProductData] = useState(null);
  const [selections, setSelections] = useState({});
  const [loading, setLoading] = useState(true);
  const [validationErrors, setValidationErrors] = useState({});

  // Fetch product with groups when modal opens
  useEffect(() => {
    if (isOpen && product) {
      fetchProductWithGroups();
    }
  }, [isOpen, product]);

  const fetchProductWithGroups = async () => {
    setLoading(true);
    try {
      const data = await publicApi.getProductWithGroups(product.id);
      setProductData(data);
      // Initialize empty selections
      const initialSelections = {};
      data.groups_product_data?.forEach(group => {
        console.log('🔍 Initializing group:', group.group_id, group.group_name);
        initialSelections[group.group_id] = [];
      });
      setSelections(initialSelections);
      setValidationErrors({});
      console.log('✅ Product data loaded:', data);
      console.log('🔍 Initial selections object:', initialSelections);
    } catch (error) {
      console.error('❌ Failed to load product groups:', error);
    } finally {
      setLoading(false);
    }
  };

  // Get constraint label for a group
  const getConstraintLabel = (group) => {
    const min = group.min_selections || 0;
    const max = group.max_selections || 0;

    if (min === 0 && max === 0) {
      return 'אופציונלי'; // Optional
    } else if (min === 0 && max > 0) {
      return `בחר עד ${max} ${max === 1 ? 'פריט' : 'פריטים'}`; // Choose up to X items
    } else if (min > 0 && max === 0) {
      return `בחר לפחות ${min} ${min === 1 ? 'פריט' : 'פריטים'}`; // Choose at least X items
    } else if (min === max) {
      return `בחר בדיוק ${min} ${min === 1 ? 'פריט' : 'פריטים'} *`; // Choose exactly X items *
    } else {
      return `בחר בין ${min}-${max} פריטים *`; // Choose between X-Y items *
    }
  };

  // Check if a group is required
  const isGroupRequired = (group) => {
    return (group.min_selections || 0) > 0;
  };

  // Toggle item selection in a group (for checkboxes)
  const toggleItem = (groupId, item, group) => {
    setSelections(prev => {
      const groupSelections = prev[groupId] || [];
      const isSelected = groupSelections.some(s => s.id === item.id);

      if (isSelected) {
        // Remove item
        return {
          ...prev,
          [groupId]: groupSelections.filter(s => s.id !== item.id)
        };
      } else {
        // Check max constraint before adding
        const maxSelections = group.max_selections || 0;
        if (maxSelections > 0 && groupSelections.length >= maxSelections) {
          // Already at max - don't add
          return prev;
        }

        // Add item
        return {
          ...prev,
          [groupId]: [...groupSelections, item]
        };
      }
    });

    // Clear validation error for this group
    setValidationErrors(prev => {
      const newErrors = { ...prev };
      delete newErrors[groupId];
      return newErrors;
    });
  };

  // Select single item (for radio buttons)
  const selectSingleItem = (groupId, item) => {
    setSelections(prev => ({
      ...prev,
      [groupId]: [item]
    }));

    // Clear validation error for this group
    setValidationErrors(prev => {
      const newErrors = { ...prev };
      delete newErrors[groupId];
      return newErrors;
    });
  };

  // Validate all selections against constraints
  const validateSelections = () => {
    const errors = {};

    productData?.groups_product_data?.forEach(group => {
      const selectedCount = (selections[group.group_id] || []).length;
      const min = group.min_selections || 0;
      const max = group.max_selections || 0;

      // Check minimum constraint
      if (selectedCount < min) {
        errors[group.group_id] = `יש לבחור לפחות ${min} ${min === 1 ? 'פריט' : 'פריטים'}`;
      }

      // Check maximum constraint
      if (max > 0 && selectedCount > max) {
        errors[group.group_id] = `ניתן לבחור עד ${max} ${max === 1 ? 'פריט' : 'פריטים'}`;
      }
    });

    return errors;
  };

  // Check if can add to cart
  const canAddToCart = () => {
    const errors = validateSelections();
    return Object.keys(errors).length === 0;
  };

  // Calculate total price including selections
  const calculateTotalPrice = () => {
    if (!productData) return 0;

    const basePrice = productData.discounted_price || productData.price;
    const addonsPrice = Object.values(selections)
      .flat()
      .reduce((sum, item) => sum + (item.price || 0), 0);

    return basePrice + addonsPrice;
  };

  // Handle confirm and add to cart
  const handleConfirm = () => {
    const errors = validateSelections();

    if (Object.keys(errors).length > 0) {
      setValidationErrors(errors);
      return;
    }

    // Filter out empty groups (groups with no selections)
    // IMPORTANT: Only send groups with valid IDs (non-zero integers) and items
    // CRITICAL: Keep keys as STRINGS to prevent JSON.stringify from converting to array
    const customizationsToSend = {};
    Object.keys(selections).forEach(groupId => {
      const numericGroupId = parseInt(groupId);
      // Only include if:
      // 1. Group ID is a valid number
      // 2. Group ID is greater than 0
      // 3. Group has at least one selected item
      if (!isNaN(numericGroupId) && numericGroupId > 0 && selections[groupId] && selections[groupId].length > 0) {
        // IMPORTANT: Use groupId (string) as key, NOT numericGroupId (integer)
        // Using integer keys causes JSON.stringify to convert object to array
        customizationsToSend[groupId] = selections[groupId];
      }
    });

    console.log('🔍 All selections:', selections);
    console.log('🔍 Filtered customizations to send:', customizationsToSend);

    const customizedProduct = {
      ...product,
      customizations: customizationsToSend,
      final_price: calculateTotalPrice()
    };
    onConfirm(customizedProduct);
    onClose();
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-end md:items-center justify-center">
      <div className="bg-white w-full md:w-2/3 lg:w-1/2 max-h-[90vh] flex flex-col">
        {/* Header */}
        <div className="border-b p-4 flex items-center justify-between">
          <h2 className="text-2xl font-bold">{product?.name}</h2>
          <button
            onClick={onClose}
            className="text-2xl px-3 hover:bg-gray-100"
          >
            ×
          </button>
        </div>

        {/* Content */}
        <div className="flex-1 overflow-y-auto p-4">
          {loading ? (
            <div className="text-center py-12">{t('loading')}</div>
          ) : (
            <div>
              {/* Base Product Info */}
              <div className="mb-6">
                <p className="text-gray-600">{productData?.description}</p>
                <p className="text-lg font-bold mt-2">
                  ₪{(productData?.discounted_price || productData?.price).toFixed(2)}
                </p>
              </div>

              {/* Product Groups */}
              {productData?.groups_product_data?.length > 0 ? (
                productData.groups_product_data.map(group => {
                  const isSingleChoice = group.max_selections === 1;
                  const selectedCount = (selections[group.group_id] || []).length;
                  const maxSelections = group.max_selections || 0;
                  const hasError = validationErrors[group.group_id];

                  return (
                    <div key={group.group_id} className="mb-6 border-t pt-4">
                      {/* Group Header */}
                      <div className="flex items-start justify-between mb-2">
                        <div>
                          <h3 className="font-bold text-lg">
                            {group.group_name}
                            {isGroupRequired(group) && <span className="text-red-600 mr-1">*</span>}
                          </h3>
                          {group.description && (
                            <p className="text-sm text-gray-600 mt-1">{group.description}</p>
                          )}
                          <p className="text-sm text-blue-600 mt-1">
                            {getConstraintLabel(group)}
                          </p>
                        </div>
                        {maxSelections > 0 && !isSingleChoice && (
                          <span className={`text-sm font-medium ${
                            selectedCount >= maxSelections ? 'text-red-600' : 'text-gray-600'
                          }`}>
                            {selectedCount}/{maxSelections}
                          </span>
                        )}
                      </div>

                      {/* Validation Error */}
                      {hasError && (
                        <div className="mb-3 p-2 bg-red-50 border border-red-200 text-red-700 text-sm rounded">
                          {hasError}
                        </div>
                      )}

                      {/* Group Items */}
                      <div className="space-y-2">
                        {group.items.map(item => {
                          const isSelected = selections[group.group_id]?.some(s => s.id === item.id);
                          const isAtMax = maxSelections > 0 && selectedCount >= maxSelections && !isSelected;

                          return (
                            <label
                              key={item.id}
                              className={`flex items-center justify-between p-3 border cursor-pointer transition-colors ${
                                isSelected
                                  ? 'border-blue-600 bg-blue-50'
                                  : isAtMax
                                    ? 'border-gray-200 bg-gray-100 cursor-not-allowed opacity-60'
                                    : 'border-gray-200 hover:bg-gray-50'
                              }`}
                            >
                              <div className="flex items-center gap-3">
                                <input
                                  type={isSingleChoice ? 'radio' : 'checkbox'}
                                  name={isSingleChoice ? `group_${group.group_id}` : undefined}
                                  checked={isSelected}
                                  onChange={() => {
                                    if (isAtMax) return; // Prevent selection if at max
                                    if (isSingleChoice) {
                                      selectSingleItem(group.group_id, item);
                                    } else {
                                      toggleItem(group.group_id, item, group);
                                    }
                                  }}
                                  disabled={isAtMax}
                                  className="w-5 h-5"
                                />
                                <span className="font-medium">{item.name}</span>
                              </div>
                              {item.price > 0 && (
                                <span className="text-gray-600">+₪{item.price.toFixed(2)}</span>
                              )}
                            </label>
                          );
                        })}
                      </div>
                    </div>
                  );
                })
              ) : (
                <p className="text-gray-500 text-center py-6">
                  No customization options available
                </p>
              )}
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="border-t p-4">
          <div className="flex items-center justify-between mb-4">
            <span className="text-lg font-bold">Total:</span>
            <span className="text-2xl font-bold">₪{calculateTotalPrice().toFixed(2)}</span>
          </div>

          {/* Overall validation message */}
          {!canAddToCart() && Object.keys(validationErrors).length === 0 && (
            <div className="mb-3 p-2 bg-yellow-50 border border-yellow-200 text-yellow-800 text-sm rounded text-center">
              אנא השלם את כל הבחירות הנדרשות
            </div>
          )}

          <button
            onClick={handleConfirm}
            disabled={loading || !canAddToCart()}
            className={`w-full py-3 font-bold transition-colors ${
              loading || !canAddToCart()
                ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                : 'bg-blue-600 text-white hover:bg-blue-700'
            }`}
          >
            {t('addToCart')}
          </button>
        </div>
      </div>
    </div>
  );
}
