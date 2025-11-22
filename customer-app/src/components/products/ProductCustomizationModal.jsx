import React, { useState, useEffect } from 'react';
import publicApi from '../../services/publicApi';
import { t } from '../../i18n/translations';

/**
 * ProductCustomizationModal - Wolt-style product customization
 * Allows selecting items from product groups before adding to cart
 */
export default function ProductCustomizationModal({ product, isOpen, onClose, onConfirm }) {
  const [productData, setProductData] = useState(null);
  const [selections, setSelections] = useState({});
  const [loading, setLoading] = useState(true);

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
        initialSelections[group.group_id] = [];
      });
      setSelections(initialSelections);
      console.log('✅ Product data loaded:', data);
    } catch (error) {
      console.error('❌ Failed to load product groups:', error);
    } finally {
      setLoading(false);
    }
  };

  // Toggle item selection in a group
  const toggleItem = (groupId, item) => {
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
        // Add item
        return {
          ...prev,
          [groupId]: [...groupSelections, item]
        };
      }
    });
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
    const customizedProduct = {
      ...product,
      customizations: selections,
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
                productData.groups_product_data.map(group => (
                  <div key={group.group_id} className="mb-6 border-t pt-4">
                    <h3 className="font-bold text-lg mb-2">{group.group_name}</h3>
                    {group.description && (
                      <p className="text-sm text-gray-600 mb-3">{group.description}</p>
                    )}

                    {/* Group Items */}
                    <div className="space-y-2">
                      {group.items.map(item => {
                        const isSelected = selections[group.group_id]?.some(s => s.id === item.id);

                        return (
                          <label
                            key={item.id}
                            className={`flex items-center justify-between p-3 border cursor-pointer hover:bg-gray-50 ${
                              isSelected ? 'border-blue-600 bg-blue-50' : 'border-gray-200'
                            }`}
                          >
                            <div className="flex items-center gap-3">
                              <input
                                type="checkbox"
                                checked={isSelected}
                                onChange={() => toggleItem(group.group_id, item)}
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
                ))
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
          <button
            onClick={handleConfirm}
            disabled={loading}
            className="w-full bg-blue-600 text-white py-3 font-bold hover:bg-blue-700 disabled:bg-gray-300"
          >
            {t('addToCart')}
          </button>
        </div>
      </div>
    </div>
  );
}
