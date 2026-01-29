import React from 'react';

const OrderItemsList = ({ items = [], showPrices = false }) => {
  if (!items || items.length === 0) {
    return (
      <p className="text-sm text-gray-500">אין פריטים בהזמנה</p>
    );
  }

  const formatPrice = (price) => {
    return `₪${parseFloat(price).toFixed(2)}`;
  };

  /**
   * Extract modification names from various formats:
   * - Object format (from cart): { groupId: [{ id, name, price }, ...], ... }
   * - Array of objects: [{ name, price }, ...]
   * - Array of strings (legacy): ["Extra Cheese", "Mushrooms"]
   * Returns array of modification name strings
   */
  const getModificationNames = (modifications) => {
    if (!modifications) return [];

    // If it's an array
    if (Array.isArray(modifications)) {
      if (modifications.length === 0) return [];
      // Check if array of strings or array of objects
      if (typeof modifications[0] === 'string') {
        return modifications; // Already array of strings
      }
      // Array of objects with name property
      return modifications.map(mod => mod.name).filter(Boolean);
    }

    // If it's an object with group IDs as keys
    if (typeof modifications === 'object') {
      const names = [];
      Object.values(modifications).forEach(groupItems => {
        if (Array.isArray(groupItems)) {
          groupItems.forEach(item => {
            if (item.name) {
              names.push(item.name);
            }
          });
        }
      });
      return names;
    }

    return [];
  };

  return (
    <div className="space-y-2">
      {items.map((item, index) => {
        const modificationNames = getModificationNames(item.modifications);

        return (
          <div key={index} className="flex items-start justify-between text-sm">
            <div className="flex-1">
              <p className="text-gray-900 font-medium">
                {item.product_name || item.display_string}
              </p>
              {modificationNames.length > 0 && (
                <p className="text-gray-600 text-xs mt-1">
                  {modificationNames.join(', ')}
                </p>
              )}
              {item.notes && (
                <p className="text-xs text-amber-600 mt-1 italic">הערה: {item.notes}</p>
              )}
            </div>
            <span className="text-gray-600 font-medium ms-4 whitespace-nowrap">
              {item.quantity}x
              {showPrices && item.unit_price && (
                <span className="ms-1">{formatPrice(item.unit_price)}</span>
              )}
            </span>
          </div>
        );
      })}
    </div>
  );
};

export default OrderItemsList;
