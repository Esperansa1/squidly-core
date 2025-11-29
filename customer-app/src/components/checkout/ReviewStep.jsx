import React from 'react';
import { t } from '../../i18n/translations';

/**
 * ReviewStep - Order summary and confirmation
 * Step 3 of checkout process
 */
export default function ReviewStep({ checkoutData, cartData, branchId, onEditStep }) {
  const { customerInfo, deliveryType, deliveryAddress, deliveryTime, deliveryFee } = checkoutData;
  const { items, subtotal } = cartData;

  // Calculate tax (17% VAT in Israel)
  const taxRate = 0.17;
  const taxAmount = subtotal * taxRate;
  const totalAmount = subtotal + deliveryFee + taxAmount;

  return (
    <div className="space-y-6">
      <h2 className="text-2xl font-bold mb-4">{t('reviewOrder')}</h2>

      <p className="text-gray-600">{t('reviewBeforePayment')}</p>

      {/* Customer Information */}
      <div className="border p-4">
        <div className="flex justify-between items-start mb-3">
          <h3 className="font-bold text-lg">{t('customerInfo')}</h3>
          <button
            onClick={() => onEditStep(1)}
            className="text-blue-600 text-sm hover:underline"
          >
            {t('edit')}
          </button>
        </div>
        <div className="space-y-1 text-sm">
          <div>
            <strong>{t('name')}:</strong> {customerInfo.firstName} {customerInfo.lastName}
          </div>
          <div>
            <strong>{t('phone')}:</strong> {customerInfo.phone}
          </div>
          {customerInfo.email && (
            <div>
              <strong>{t('email')}:</strong> {customerInfo.email}
            </div>
          )}
        </div>
      </div>

      {/* Delivery Information */}
      <div className="border p-4">
        <div className="flex justify-between items-start mb-3">
          <h3 className="font-bold text-lg">
            {deliveryType === 'delivery' ? t('delivery') : t('pickup')}
          </h3>
          <button
            onClick={() => onEditStep(2)}
            className="text-blue-600 text-sm hover:underline"
          >
            {t('edit')}
          </button>
        </div>
        <div className="space-y-1 text-sm">
          <div>
            <strong>{t('method')}:</strong>{' '}
            {deliveryType === 'delivery' ? t('delivery') : t('pickup')}
          </div>
          {deliveryType === 'delivery' && (
            <div>
              <strong>{t('address')}:</strong> {deliveryAddress}
            </div>
          )}
          <div>
            <strong>{t('time')}:</strong>{' '}
            {new Date(deliveryTime).toLocaleString('he-IL', {
              dateStyle: 'medium',
              timeStyle: 'short',
            })}
          </div>
          {deliveryType === 'delivery' && (
            <div>
              <strong>{t('deliveryFee')}:</strong>{' '}
              {deliveryFee === 0 ? t('free') : `₪${deliveryFee.toFixed(2)}`}
            </div>
          )}
        </div>
      </div>

      {/* Order Items */}
      <div className="border p-4">
        <h3 className="font-bold text-lg mb-3">{t('orderItems')}</h3>
        <div className="space-y-3">
          {items.map((item) => {
            const price = item.final_price || item.unit_price;
            const itemTotal = price * item.quantity;

            return (
              <div key={item.id} className="flex justify-between border-b pb-2">
                <div className="flex-1">
                  <div className="font-bold">{item.product_name}</div>
                  <div className="text-sm text-gray-600">
                    ₪{price.toFixed(2)} × {item.quantity}
                  </div>
                  {/* Show customizations */}
                  {item.customizations && item.customizations.length > 0 && (
                    <div className="text-xs text-gray-500 mt-1">
                      {item.customizations.map((custom, idx) => (
                        <span key={idx}>
                          • {custom.name}
                          {custom.price > 0 && ` (+₪${custom.price})`}
                          {idx < item.customizations.length - 1 && ', '}
                        </span>
                      ))}
                    </div>
                  )}
                  {item.special_instructions && (
                    <div className="text-xs text-gray-500 mt-1">
                      {t('notes')}: {item.special_instructions}
                    </div>
                  )}
                </div>
                <div className="font-bold">₪{itemTotal.toFixed(2)}</div>
              </div>
            );
          })}
        </div>
      </div>

      {/* Price Breakdown */}
      <div className="border p-4 bg-gray-50">
        <h3 className="font-bold text-lg mb-3">{t('priceBreakdown')}</h3>
        <div className="space-y-2">
          <div className="flex justify-between">
            <span>{t('subtotal')}:</span>
            <span>₪{subtotal.toFixed(2)}</span>
          </div>
          {deliveryFee > 0 && (
            <div className="flex justify-between">
              <span>{t('deliveryFee')}:</span>
              <span>₪{deliveryFee.toFixed(2)}</span>
            </div>
          )}
          <div className="flex justify-between text-sm text-gray-600">
            <span>{t('tax')} (17%):</span>
            <span>₪{taxAmount.toFixed(2)}</span>
          </div>
          <div className="border-t pt-2 flex justify-between text-xl font-bold">
            <span>{t('total')}:</span>
            <span>₪{totalAmount.toFixed(2)}</span>
          </div>
        </div>
      </div>

      {/* Terms & Conditions Notice */}
      <div className="bg-blue-50 border border-blue-200 p-4 text-sm">
        <p className="font-bold mb-2">{t('beforeContinuing')}</p>
        <p>{t('termsNotice')}</p>
      </div>
    </div>
  );
}
