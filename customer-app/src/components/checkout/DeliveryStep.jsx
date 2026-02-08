import React, { useState, useEffect } from 'react';
import publicApi from '../../services/publicApi';
import { t } from '../../i18n/translations';
import theme from '../../config/theme';

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

  const inputStyle = {
    width: '100%',
    border: `1px solid ${theme.colors.border}`,
    borderRadius: theme.borderRadius.lg,
    padding: `${theme.spacing.md} ${theme.spacing.md}`,
    fontSize: theme.typography.mobile.body,
    color: theme.colors.text.primary,
    backgroundColor: theme.colors.cardBg,
    outline: 'none',
    transition: 'border-color 0.2s ease',
    boxSizing: 'border-box',
  };

  const labelStyle = {
    display: 'block',
    fontWeight: '600',
    fontSize: theme.typography.mobile.body,
    color: theme.colors.text.primary,
    marginBottom: theme.spacing.sm,
  };

  const typeCardStyle = (isSelected) => ({
    flex: 1,
    border: `2px solid ${isSelected ? theme.colors.primary : theme.colors.border}`,
    borderRadius: theme.borderRadius.xl,
    padding: theme.spacing.lg,
    textAlign: 'center',
    fontWeight: '700',
    fontSize: theme.typography.mobile.body,
    backgroundColor: isSelected ? '#FEF2F2' : theme.colors.cardBg,
    color: isSelected ? theme.colors.primary : theme.colors.text.primary,
    cursor: 'pointer',
    transition: 'all 0.2s ease',
  });

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: theme.spacing.lg }}>
      <h2
        style={{
          fontSize: theme.typography.desktop.h2,
          fontWeight: '700',
          color: theme.colors.text.primary,
          margin: `0 0 ${theme.spacing.xs} 0`,
        }}
      >
        {t('deliveryOptions')}
      </h2>

      {/* Delivery Type Toggle */}
      <div>
        <label style={labelStyle}>{t('selectDeliveryMethod')}</label>
        <div style={{ display: 'flex', gap: theme.spacing.md }}>
          {/* Pickup */}
          <button
            onClick={() => handleDeliveryTypeChange('pickup')}
            style={typeCardStyle(deliveryType === 'pickup')}
          >
            <div style={{ fontSize: '2rem', marginBottom: theme.spacing.sm }}>🏪</div>
            <div>{t('pickup')}</div>
            <div
              style={{
                fontSize: theme.typography.mobile.small,
                fontWeight: '400',
                color: theme.colors.text.secondary,
                marginTop: theme.spacing.xs,
              }}
            >
              {t('pickupAtBranch')}
            </div>
          </button>

          {/* Delivery */}
          <button
            onClick={() => handleDeliveryTypeChange('delivery')}
            style={typeCardStyle(deliveryType === 'delivery')}
          >
            <div style={{ fontSize: '2rem', marginBottom: theme.spacing.sm }}>🚚</div>
            <div>{t('delivery')}</div>
            <div
              style={{
                fontSize: theme.typography.mobile.small,
                fontWeight: '400',
                color: theme.colors.text.secondary,
                marginTop: theme.spacing.xs,
              }}
            >
              {t('deliverToAddress')}
            </div>
          </button>
        </div>
      </div>

      {/* Delivery Address (only for delivery) */}
      {deliveryType === 'delivery' && (
        <div>
          <label style={labelStyle}>
            {t('deliveryAddress')} <span style={{ color: theme.colors.error }}>*</span>
          </label>
          <textarea
            value={deliveryAddress}
            onChange={(e) => onChange({ deliveryAddress: e.target.value })}
            style={{
              ...inputStyle,
              resize: 'vertical',
              minHeight: '80px',
              fontFamily: 'inherit',
            }}
            placeholder={t('enterFullAddress')}
            rows={3}
            required
          />
          <p
            style={{
              color: theme.colors.text.muted,
              fontSize: theme.typography.mobile.small,
              marginTop: theme.spacing.xs,
            }}
          >
            {t('includeStreetCityApt')}
          </p>

          {/* Delivery Fee Calculation */}
          {calculatingFee && (
            <div
              style={{
                marginTop: theme.spacing.md,
                color: theme.colors.info,
                display: 'flex',
                alignItems: 'center',
                gap: theme.spacing.sm,
                fontSize: theme.typography.mobile.small,
              }}
            >
              <div
                style={{
                  width: '16px',
                  height: '16px',
                  border: `2px solid ${theme.colors.info}`,
                  borderTopColor: 'transparent',
                  borderRadius: theme.borderRadius.full,
                  animation: 'spin 1s linear infinite',
                }}
              />
              {t('calculatingDeliveryFee')}
            </div>
          )}

          {feeError && (
            <div
              style={{
                marginTop: theme.spacing.md,
                backgroundColor: '#FEF2F2',
                border: `1px solid ${theme.colors.error}`,
                borderRadius: theme.borderRadius.lg,
                padding: theme.spacing.md,
                color: theme.colors.error,
                fontSize: theme.typography.mobile.small,
              }}
            >
              {feeError}
            </div>
          )}

          {feeInfo && !feeError && (
            <div
              style={{
                marginTop: theme.spacing.md,
                backgroundColor: '#F0FDF4',
                border: `1px solid ${theme.colors.success}`,
                borderRadius: theme.borderRadius.lg,
                padding: theme.spacing.md,
                color: '#166534',
                fontSize: theme.typography.mobile.small,
              }}
            >
              {feeInfo.is_free_delivery ? (
                <div>
                  <strong>{t('freeDelivery')}!</strong>
                  <p style={{ marginTop: theme.spacing.xs, margin: 0 }}>
                    {t('orderAboveFreeThreshold', { threshold: feeInfo.free_delivery_threshold })}
                  </p>
                </div>
              ) : (
                <div>
                  <strong>{t('deliveryFee')}: ₪{feeInfo.delivery_fee.toFixed(2)}</strong>
                  {feeInfo.free_delivery_threshold > 0 && (
                    <p style={{ marginTop: theme.spacing.xs, margin: 0 }}>
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
        <label style={labelStyle}>
          {deliveryType === 'delivery' ? t('deliveryTime') : t('pickupTime')}{' '}
          <span style={{ color: theme.colors.error }}>*</span>
        </label>
        <input
          type="datetime-local"
          value={deliveryTime}
          onChange={(e) => onChange({ deliveryTime: e.target.value })}
          style={inputStyle}
          min={new Date().toISOString().slice(0, 16)}
          required
        />
        <p
          style={{
            color: theme.colors.text.muted,
            fontSize: theme.typography.mobile.small,
            marginTop: theme.spacing.xs,
          }}
        >
          {deliveryType === 'delivery'
            ? t('selectPreferredDeliveryTime')
            : t('selectPreferredPickupTime')}
        </p>
      </div>

      {/* Summary */}
      <div
        style={{
          backgroundColor: theme.colors.background,
          border: `1px solid ${theme.colors.border}`,
          borderRadius: theme.borderRadius.lg,
          padding: theme.spacing.md,
        }}
      >
        <h3
          style={{
            fontWeight: '700',
            color: theme.colors.text.primary,
            marginBottom: theme.spacing.sm,
          }}
        >
          {t('summary')}
        </h3>
        <div style={{ display: 'flex', flexDirection: 'column', gap: theme.spacing.xs }}>
          <div
            style={{
              display: 'flex',
              justifyContent: 'space-between',
              fontSize: theme.typography.mobile.small,
              color: theme.colors.text.secondary,
            }}
          >
            <span>{t('method')}:</span>
            <span style={{ fontWeight: '700', color: theme.colors.text.primary }}>
              {deliveryType === 'delivery' ? t('delivery') : t('pickup')}
            </span>
          </div>
          {deliveryType === 'delivery' && deliveryAddress && (
            <div
              style={{
                display: 'flex',
                justifyContent: 'space-between',
                fontSize: theme.typography.mobile.small,
                color: theme.colors.text.secondary,
              }}
            >
              <span>{t('address')}:</span>
              <span
                style={{
                  fontWeight: '700',
                  color: theme.colors.text.primary,
                  textAlign: 'start',
                  maxWidth: '250px',
                }}
              >
                {deliveryAddress}
              </span>
            </div>
          )}
          {deliveryTime && (
            <div
              style={{
                display: 'flex',
                justifyContent: 'space-between',
                fontSize: theme.typography.mobile.small,
                color: theme.colors.text.secondary,
              }}
            >
              <span>{t('time')}:</span>
              <span style={{ fontWeight: '700', color: theme.colors.text.primary }}>
                {new Date(deliveryTime).toLocaleString('he-IL', {
                  dateStyle: 'short',
                  timeStyle: 'short',
                })}
              </span>
            </div>
          )}
          {deliveryType === 'delivery' && feeInfo && (
            <div
              style={{
                display: 'flex',
                justifyContent: 'space-between',
                fontSize: theme.typography.mobile.small,
                borderTop: `1px solid ${theme.colors.border}`,
                paddingTop: theme.spacing.sm,
                marginTop: theme.spacing.xs,
              }}
            >
              <span style={{ fontWeight: '700', color: theme.colors.text.primary }}>
                {t('deliveryFee')}:
              </span>
              <span style={{ fontWeight: '700', color: theme.colors.text.primary }}>
                {feeInfo.is_free_delivery ? t('free') : `₪${feeInfo.delivery_fee.toFixed(2)}`}
              </span>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
