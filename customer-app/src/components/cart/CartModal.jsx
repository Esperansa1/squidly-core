import React from 'react';
import { useCart } from '../../contexts/CartContext';
import CartItem from './CartItem';
import { t } from '../../i18n/translations';

/**
 * CartModal - Full-screen cart overlay
 */
export default function CartModal({ isOpen, onClose, onCheckout }) {
  const { cart, updateQuantity, removeFromCart, getTotal, clearCart } = useCart();

  if (!isOpen) return null;

  const total = getTotal();
  const isEmpty = cart.items.length === 0;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-end md:items-center justify-center">
      <div className="bg-white w-full md:w-2/3 lg:w-1/2 max-h-[90vh] flex flex-col">
        {/* Header */}
        <div className="border-b p-4 flex items-center justify-between">
          <h2 className="text-2xl font-bold">{t('cart')}</h2>
          <button
            onClick={onClose}
            className="text-2xl px-3 hover:bg-gray-100"
          >
            ×
          </button>
        </div>

        {/* Cart Items */}
        <div className="flex-1 overflow-y-auto p-4">
          {isEmpty ? (
            <div className="text-center py-12 text-gray-500">
              <p className="mb-4">{t('emptyCart')}</p>
              <button
                onClick={onClose}
                className="px-6 py-2 bg-blue-600 text-white hover:bg-blue-700"
              >
                {t('continueShopping')}
              </button>
            </div>
          ) : (
            <div>
              {cart.items.map((item) => (
                <CartItem
                  key={item.id}
                  item={item}
                  onUpdateQuantity={updateQuantity}
                  onRemove={removeFromCart}
                />
              ))}
            </div>
          )}
        </div>

        {/* Footer - Total & Actions */}
        {!isEmpty && (
          <div className="border-t p-4">
            {/* Total */}
            <div className="flex justify-between items-center mb-4">
              <span className="text-xl font-bold">{t('total')}:</span>
              <span className="text-2xl font-bold">₪{total.toFixed(2)}</span>
            </div>

            {/* Actions */}
            <div className="flex gap-3">
              <button
                onClick={onCheckout}
                className="flex-1 bg-blue-600 text-white py-3 font-bold hover:bg-blue-700"
              >
                {t('proceedToCheckout')}
              </button>
              <button
                onClick={() => {
                  if (confirm(t('confirm') + ' ' + t('emptyCart') + '?')) {
                    clearCart();
                  }
                }}
                className="px-6 py-3 border text-gray-700 hover:bg-gray-100"
              >
                {t('emptyCart')}
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
