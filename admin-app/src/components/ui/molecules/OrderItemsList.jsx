import React from 'react';

const OrderItemsList = ({ items = [] }) => {
  if (!items || items.length === 0) {
    return (
      <p className="text-sm text-gray-500">אין פריטים בהזמנה</p>
    );
  }

  return (
    <div className="space-y-2">
      {items.map((item, index) => (
        <div key={index} className="flex items-start justify-between text-sm">
          <div className="flex-1">
            <p className="text-gray-900">
              {item.product_name || item.display_string}
              {item.modifications && item.modifications.length > 0 && (
                <span className="text-gray-500"> ({item.modifications.join(', ')})</span>
              )}
            </p>
            {item.notes && (
              <p className="text-xs text-gray-500 mt-1">{item.notes}</p>
            )}
          </div>
          <span className="text-gray-600 font-medium ms-4">{item.quantity}x</span>
        </div>
      ))}
    </div>
  );
};

export default OrderItemsList;
