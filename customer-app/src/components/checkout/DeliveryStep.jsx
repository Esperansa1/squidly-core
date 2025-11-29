import React, { useState, useEffect } from 'react';
import publicApi from '../../services/publicApi';
import { t } from '../../i18n/translations';

/**
 * DeliveryStep - Delivery/Pickup selection with timing
 * Step 2 of checkout process
 */
export default function DeliveryStep({
  branchId,
  deliveryType,
  deliveryAddress,
  deliveryTime,
  deliveryFee,
  cartSubtotal,
  onChange,
}) {
  const [calculatingFee, setCalculatingFee] = useState(false);
  const [feeError, setFeeError] = useState(null);
  const [feeInfo, setFeeInfo] = useState(null);

  // Calculate delivery fee when address changes
  useEffect(() => {
    if (deliveryType === 'delivery' && deliveryAddress.trim()) {
      calculateDeliveryFee();
    } else {
      setFeeInfo(null);
      onChange({ deliveryFee: 0 });
    }
  }, [deliveryAddress, deliveryType, cartSubtotal]);

  const calculateDeliveryFee = async () => {
    try {
      setCalculatingFee(true);
      setFeeError(null);

      const result = await publicApi.getDeliveryFee(branchId, deliveryAddress, cartSubtotal);

      if (!result.is_deliverable) {
        setFeeError(t('addressNotDeliverable'));
        setFeeInfo(null);
        onChange({ deliveryFee: 0 });
        return;
      }

      setFeeInfo(result);
      onChange({ deliveryFee: result.delivery_fee });
    } catch (error) {
      console.error('Failed to calculate delivery fee:', error);
      setFeeError(t('failedToCalculateFee'));
      setFeeInfo(null);
      onChange({ deliveryFee: 0 });
    } finally {
      setCalculatingFee(false);
    }
  };

  const handleDeliveryTypeChange = (type) => {
    onChange({
      deliveryType: type,
      deliveryAddress: type === 'pickup' ? '' : deliveryAddress,
      deliveryFee: type === 'pickup' ? 0 : deliveryFee,
    });
    setFeeError(null);
    setFeeInfo(null);
  };

  return (
    <div className="space-y-6">
      <h2 className="text-2xl font-bold mb-4">{t('deliveryOptions')}</h2>

      {/* Delivery Type Toggle */}
      <div>
        <label className="block font-bold mb-3">{t('selectDeliveryMethod')}</label>
        <div className="flex gap-4">
          {/* Pickup */}
          <button
            onClick={() => handleDeliveryTypeChange('pickup')}
            className={`flex-1 border-2 p-4 text-center font-bold transition ${
              deliveryType === 'pickup'
                ? 'border-blue-600 bg-blue-50 text-blue-600'
                : 'border-gray-300 hover:border-gray-400'
            }`}
          >
            <div className="text-2xl mb-2">🏪</div>
            <div>{t('pickup')}</div>
            <div className="text-sm font-normal text-gray-600 mt-1">{t('pickupAtBranch')}</div>
          </button>

          {/* Delivery */}
          <button
            onClick={() => handleDeliveryTypeChange('delivery')}
            className={`flex-1 border-2 p-4 text-center font-bold transition ${
              deliveryType === 'delivery'
                ? 'border-blue-600 bg-blue-50 text-blue-600'
                : 'border-gray-300 hover:border-gray-400'
            }`}
          >
            <div className="text-2xl mb-2">🚚</div>
            <div>{t('delivery')}</div>
            <div className="text-sm font-normal text-gray-600 mt-1">{t('deliverToAddress')}</div>
          </button>
        </div>
      </div>

      {/* Delivery Address (only for delivery) */}
      {deliveryType === 'delivery' && (
        <div>
          <label className="block font-bold mb-2">
            {t('deliveryAddress')} <span className="text-red-600">*</span>
          </label>
          <textarea
            value={deliveryAddress}
            onChange={(e) => onChange({ deliveryAddress: e.target.value })}
            className="w-full border px-4 py-2 focus:outline-none focus:border-blue-500"
            placeholder={t('enterFullAddress')}
            rows={3}
            required
          />
          <p className="text-gray-500 text-sm mt-1">{t('includeStreetCityApt')}</p>

          {/* Delivery Fee Calculation */}
          {calculatingFee && (
            <div className="mt-3 text-blue-600 flex items-center gap-2">
              <div className="animate-spin w-4 h-4 border-2 border-blue-600 border-t-transparent rounded-full"></div>
              {t('calculatingDeliveryFee')}
            </div>
          )}

          {feeError && (
            <div className="mt-3 bg-red-100 border border-red-400 text-red-700 px-4 py-2">
              {feeError}
            </div>
          )}

          {feeInfo && !feeError && (
            <div className="mt-3 bg-green-100 border border-green-400 text-green-700 px-4 py-2">
              {feeInfo.is_free_delivery ? (
                <div>
                  <strong>{t('freeDelivery')}!</strong>
                  <p className="text-sm mt-1">
                    {t('orderAboveFreeThreshold', { threshold: feeInfo.free_delivery_threshold })}
                  </p>
                </div>
              ) : (
                <div>
                  <strong>{t('deliveryFee')}: ₪{feeInfo.delivery_fee.toFixed(2)}</strong>
                  {feeInfo.free_delivery_threshold > 0 && (
                    <p className="text-sm mt-1">
                      {t('freeDeliveryAt', { threshold: feeInfo.free_delivery_threshold })}
                    </p>
                  )}
                </div>
              )}
            </div>
          )}
        </div>
      )}

      {/* Delivery/Pickup Time */}
      <div>
        <label className="block font-bold mb-2">
          {deliveryType === 'delivery' ? t('deliveryTime') : t('pickupTime')}{' '}
          <span className="text-red-600">*</span>
        </label>
        <input
          type="datetime-local"
          value={deliveryTime}
          onChange={(e) => onChange({ deliveryTime: e.target.value })}
          className="w-full border px-4 py-2 focus:outline-none focus:border-blue-500"
          min={new Date().toISOString().slice(0, 16)}
          required
        />
        <p className="text-gray-500 text-sm mt-1">
          {deliveryType === 'delivery'
            ? t('selectPreferredDeliveryTime')
            : t('selectPreferredPickupTime')}
        </p>
      </div>

      {/* Summary */}
      <div className="bg-gray-50 border p-4">
        <h3 className="font-bold mb-2">{t('summary')}</h3>
        <div className="space-y-1 text-sm">
          <div className="flex justify-between">
            <span>{t('method')}:</span>
            <span className="font-bold">
              {deliveryType === 'delivery' ? t('delivery') : t('pickup')}
            </span>
          </div>
          {deliveryType === 'delivery' && deliveryAddress && (
            <div className="flex justify-between">
              <span>{t('address')}:</span>
              <span className="font-bold text-left max-w-xs">{deliveryAddress}</span>
            </div>
          )}
          {deliveryTime && (
            <div className="flex justify-between">
              <span>{t('time')}:</span>
              <span className="font-bold">
                {new Date(deliveryTime).toLocaleString('he-IL', {
                  dateStyle: 'short',
                  timeStyle: 'short',
                })}
              </span>
            </div>
          )}
          {deliveryType === 'delivery' && feeInfo && (
            <div className="flex justify-between border-t pt-2 mt-2">
              <span className="font-bold">{t('deliveryFee')}:</span>
              <span className="font-bold">
                {feeInfo.is_free_delivery ? t('free') : `₪${feeInfo.delivery_fee.toFixed(2)}`}
              </span>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
