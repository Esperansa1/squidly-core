import React from 'react';
import { t } from '../../i18n/translations';

/**
 * CartItem - Display single cart item with quantity controls
 */
export default function CartItem({ item, onUpdateQuantity, onRemove }) {
  const { product, quantity } = item;
  const price = product.discounted_price || product.price;
  const itemTotal = price * quantity;

  return (
    <div className="border-b py-4">
      {/* Product Info */}
      <div className="flex justify-between items-start mb-2">
        <div className="flex-1">
          <h4 className="font-bold">{product.name}</h4>
          {product.category && (
            <p className="text-xs text-gray-500">{product.category}</p>
          )}
        </div>
        <div className="text-left ml-4">
          <p className="font-bold">₪{itemTotal.toFixed(2)}</p>
          <p className="text-xs text-gray-500">₪{price} × {quantity}</p>
        </div>
      </div>

      {/* Quantity Controls + Remove */}
      <div className="flex items-center justify-between">
        {/* Quantity Controls */}
        <div className="flex items-center gap-2">
          <button
            onClick={() => onUpdateQuantity(product.id, quantity - 1)}
            className="w-8 h-8 border flex items-center justify-center hover:bg-gray-100"
            disabled={quantity <= 1}
          >
            -
          </button>
          <span className="w-8 text-center font-bold">{quantity}</span>
          <button
            onClick={() => onUpdateQuantity(product.id, quantity + 1)}
            className="w-8 h-8 border flex items-center justify-center hover:bg-gray-100"
          >
            +
          </button>
        </div>

        {/* Remove Button */}
        <button
          onClick={() => onRemove(product.id)}
          className="text-sm text-red-600 hover:text-red-800"
        >
          {t('removeFromCart')}
        </button>
      </div>
    </div>
  );
}
