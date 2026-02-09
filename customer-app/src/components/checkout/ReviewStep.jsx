import React from 'react';
import { useAuth } from '../../contexts/AuthContext';
import { useBranch } from '../../contexts/BranchContext';
import { t } from '../../i18n/translations';
import theme from '../../config/theme';
import publicApi from '../../services/publicApi';

/**
 * ReviewStep - Order summary and confirmation
 * Step 3 of checkout process
 */
export default function ReviewStep({ checkoutData, cartData, branchId, onEditStep, onUpdateLoyaltyPoints }) {
  const { customer, isAuthenticated } = useAuth();
  const { selectedBranch } = useBranch();
  const { customerInfo, deliveryType, deliveryAddress, deliveryTime, deliveryFee, loyaltyPointsToUse } = checkoutData;
  const { items, subtotal } = cartData;

  // Calculate tax using configured VAT rate
  const taxRate = publicApi.config?.taxes?.vat_rate ?? 0.18;
  const taxAmount = subtotal * taxRate;
  const loyaltyDiscount = loyaltyPointsToUse || 0;
  const totalAmount = subtotal + deliveryFee + taxAmount - loyaltyDiscount;

  // Loyalty points available
  const pointsBalance = isAuthenticated ? (customer?.loyalty_points_balance || 0) : 0;
  const maxRedeemable = Math.min(pointsBalance, subtotal);
  const cashbackRate = selectedBranch?.cashback_rate ?? 2.0;
  const pointsToEarn = subtotal * cashbackRate / 100;

  const sectionStyle = {
    backgroundColor: theme.colors.cardBg,
    border: `1px solid ${theme.colors.border}`,
    borderRadius: theme.borderRadius.lg,
    padding: theme.spacing.md,
  };

  const sectionHeaderStyle = {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: theme.spacing.md,
  };

  const sectionTitleStyle = {
    fontWeight: '700',
    fontSize: theme.typography.mobile.h3,
    color: theme.colors.text.primary,
  };

  const editBtnStyle = {
    color: theme.colors.primary,
    fontSize: theme.typography.mobile.small,
    background: 'none',
    border: 'none',
    cursor: 'pointer',
    fontWeight: '600',
    textDecoration: 'underline',
  };

  const rowStyle = {
    display: 'flex',
    justifyContent: 'space-between',
    fontSize: theme.typography.mobile.small,
    color: theme.colors.text.secondary,
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: theme.spacing.lg }}>
      <div>
        <h2
          style={{
            fontSize: theme.typography.desktop.h2,
            fontWeight: '700',
            color: theme.colors.text.primary,
            margin: `0 0 ${theme.spacing.xs} 0`,
          }}
        >
          {t('reviewOrder')}
        </h2>
        <p style={{ color: theme.colors.text.secondary, margin: 0 }}>
          {t('reviewBeforePayment')}
        </p>
      </div>

      {/* Customer Information */}
      <div style={sectionStyle}>
        <div style={sectionHeaderStyle}>
          <h3 style={sectionTitleStyle}>{t('customerInfo')}</h3>
          <button onClick={() => onEditStep(1)} style={editBtnStyle}>
            {t('edit')}
          </button>
        </div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: theme.spacing.xs }}>
          <div style={rowStyle}>
            <strong style={{ color: theme.colors.text.primary }}>{t('name')}:</strong>
            <span>{customerInfo.firstName} {customerInfo.lastName}</span>
          </div>
          <div style={rowStyle}>
            <strong style={{ color: theme.colors.text.primary }}>{t('phone')}:</strong>
            <span>{customerInfo.phone}</span>
          </div>
          {customerInfo.email && (
            <div style={rowStyle}>
              <strong style={{ color: theme.colors.text.primary }}>{t('email')}:</strong>
              <span>{customerInfo.email}</span>
            </div>
          )}
        </div>
      </div>

      {/* Delivery Information */}
      <div style={sectionStyle}>
        <div style={sectionHeaderStyle}>
          <h3 style={sectionTitleStyle}>
            {deliveryType === 'delivery' ? t('delivery') : t('pickup')}
          </h3>
          <button onClick={() => onEditStep(2)} style={editBtnStyle}>
            {t('edit')}
          </button>
        </div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: theme.spacing.xs }}>
          <div style={rowStyle}>
            <strong style={{ color: theme.colors.text.primary }}>{t('method')}:</strong>
            <span>{deliveryType === 'delivery' ? t('delivery') : t('pickup')}</span>
          </div>
          {deliveryType === 'delivery' && (
            <div style={rowStyle}>
              <strong style={{ color: theme.colors.text.primary }}>{t('address')}:</strong>
              <span>{deliveryAddress}</span>
            </div>
          )}
          <div style={rowStyle}>
            <strong style={{ color: theme.colors.text.primary }}>{t('time')}:</strong>
            <span>
              {new Date(deliveryTime).toLocaleString('he-IL', {
                dateStyle: 'medium',
                timeStyle: 'short',
              })}
            </span>
          </div>
          {deliveryType === 'delivery' && (
            <div style={rowStyle}>
              <strong style={{ color: theme.colors.text.primary }}>{t('deliveryFee')}:</strong>
              <span>{deliveryFee === 0 ? t('free') : `₪${deliveryFee.toFixed(2)}`}</span>
            </div>
          )}
        </div>
      </div>

      {/* Order Items */}
      <div style={sectionStyle}>
        <h3 style={{ ...sectionTitleStyle, marginBottom: theme.spacing.md }}>
          {t('orderItems')}
        </h3>
        <div style={{ display: 'flex', flexDirection: 'column', gap: theme.spacing.md }}>
          {items.map((item) => {
            const price = item.final_price || item.unit_price;
            const itemTotal = price * item.quantity;

            return (
              <div
                key={item.id}
                style={{
                  display: 'flex',
                  justifyContent: 'space-between',
                  borderBottom: `1px solid ${theme.colors.border}`,
                  paddingBottom: theme.spacing.sm,
                }}
              >
                <div style={{ flex: 1 }}>
                  <div
                    style={{
                      fontWeight: '700',
                      color: theme.colors.text.primary,
                      fontSize: theme.typography.mobile.body,
                    }}
                  >
                    {item.product_name}
                  </div>
                  <div
                    style={{
                      fontSize: theme.typography.mobile.small,
                      color: theme.colors.text.secondary,
                    }}
                  >
                    ₪{price.toFixed(2)} × {item.quantity}
                  </div>
                  {item.customizations && item.customizations.length > 0 && (
                    <div
                      style={{
                        fontSize: '0.75rem',
                        color: theme.colors.text.muted,
                        marginTop: theme.spacing.xs,
                      }}
                    >
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
                    <div
                      style={{
                        fontSize: '0.75rem',
                        color: theme.colors.text.muted,
                        marginTop: theme.spacing.xs,
                      }}
                    >
                      {t('notes')}: {item.special_instructions}
                    </div>
                  )}
                </div>
                <div
                  style={{
                    fontWeight: '700',
                    color: theme.colors.text.primary,
                    fontSize: theme.typography.mobile.body,
                  }}
                >
                  ₪{itemTotal.toFixed(2)}
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* Price Breakdown */}
      <div
        style={{
          ...sectionStyle,
          backgroundColor: theme.colors.background,
        }}
      >
        <h3 style={{ ...sectionTitleStyle, marginBottom: theme.spacing.md }}>
          {t('priceBreakdown')}
        </h3>
        <div style={{ display: 'flex', flexDirection: 'column', gap: theme.spacing.sm }}>
          <div style={rowStyle}>
            <span>{t('subtotal')}:</span>
            <span style={{ color: theme.colors.text.primary }}>₪{subtotal.toFixed(2)}</span>
          </div>
          {deliveryFee > 0 && (
            <div style={rowStyle}>
              <span>{t('deliveryFee')}:</span>
              <span style={{ color: theme.colors.text.primary }}>₪{deliveryFee.toFixed(2)}</span>
            </div>
          )}
          {taxRate > 0 && (
            <div
              style={{
                ...rowStyle,
                fontSize: theme.typography.mobile.small,
                color: theme.colors.text.muted,
              }}
            >
              <span>{t('tax')} ({(taxRate * 100).toFixed(0)}%):</span>
              <span>₪{taxAmount.toFixed(2)}</span>
            </div>
          )}

          {/* Loyalty Points Redemption */}
          {isAuthenticated && pointsBalance > 0 && (
            <div
              style={{
                borderTop: `1px solid ${theme.colors.border}`,
                paddingTop: theme.spacing.md,
                marginTop: theme.spacing.xs,
              }}
            >
              <div
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'space-between',
                  marginBottom: theme.spacing.sm,
                }}
              >
                <span
                  style={{
                    fontSize: theme.typography.mobile.small,
                    fontWeight: '700',
                    color: theme.colors.warning,
                  }}
                >
                  {t('redeemPoints')} ({t('pointsBalance', { points: pointsBalance })})
                </span>
              </div>
              <div
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  gap: theme.spacing.sm,
                }}
              >
                <input
                  type="number"
                  min="0"
                  max={maxRedeemable}
                  step="1"
                  value={loyaltyPointsToUse || ''}
                  onChange={(e) => {
                    const raw = parseFloat(e.target.value) || 0;
                    const val = Math.min(Math.max(0, raw), maxRedeemable);
                    onUpdateLoyaltyPoints(val);
                  }}
                  placeholder="0"
                  style={{
                    width: '80px',
                    padding: `${theme.spacing.xs} ${theme.spacing.sm}`,
                    border: `1px solid ${loyaltyPointsToUse > maxRedeemable ? theme.colors.error : theme.colors.border}`,
                    borderRadius: theme.borderRadius.md,
                    fontSize: theme.typography.mobile.small,
                    textAlign: 'center',
                    backgroundColor: loyaltyPointsToUse > maxRedeemable ? '#FEF2F2' : theme.colors.cardBg,
                    outline: 'none',
                  }}
                />
                <button
                  onClick={() => onUpdateLoyaltyPoints(maxRedeemable)}
                  style={{
                    padding: `${theme.spacing.xs} ${theme.spacing.sm}`,
                    backgroundColor: '#FEF3C7',
                    color: '#92400E',
                    fontSize: '0.75rem',
                    fontWeight: '700',
                    borderRadius: theme.borderRadius.md,
                    border: 'none',
                    cursor: 'pointer',
                    transition: 'background-color 0.2s ease',
                  }}
                >
                  {t('useAllPoints')}
                </button>
                {loyaltyPointsToUse > 0 && (
                  <button
                    onClick={() => onUpdateLoyaltyPoints(0)}
                    style={{
                      padding: `${theme.spacing.xs} ${theme.spacing.sm}`,
                      color: theme.colors.text.muted,
                      fontSize: '0.75rem',
                      background: 'none',
                      border: 'none',
                      cursor: 'pointer',
                    }}
                  >
                    ✕
                  </button>
                )}
              </div>
              <p
                style={{
                  fontSize: '0.75rem',
                  color: theme.colors.text.muted,
                  marginTop: theme.spacing.xs,
                }}
              >
                {t('maxPointsAvailable', { points: maxRedeemable.toFixed(0) })}
              </p>
            </div>
          )}

          {/* Loyalty Discount Line */}
          {loyaltyDiscount > 0 && (
            <div
              style={{
                ...rowStyle,
                fontWeight: '700',
                color: theme.colors.success,
              }}
            >
              <span>{t('pointsDiscount')}:</span>
              <span>-₪{loyaltyDiscount.toFixed(2)}</span>
            </div>
          )}

          {/* Total */}
          <div
            style={{
              borderTop: `1px solid ${theme.colors.border}`,
              paddingTop: theme.spacing.sm,
              display: 'flex',
              justifyContent: 'space-between',
              fontSize: theme.typography.desktop.h3,
              fontWeight: '700',
              color: theme.colors.text.primary,
            }}
          >
            <span>{t('total')}:</span>
            <span>₪{totalAmount.toFixed(2)}</span>
          </div>

          {/* Points you'll earn */}
          {isAuthenticated && pointsToEarn > 0 && (
            <div
              style={{
                display: 'flex',
                justifyContent: 'space-between',
                fontSize: '0.75rem',
                color: theme.colors.warning,
                marginTop: theme.spacing.xs,
              }}
            >
              <span>{t('pointsYouWillEarn')}:</span>
              <span>+{pointsToEarn.toFixed(1)}</span>
            </div>
          )}
        </div>
      </div>

      {/* Terms & Conditions Notice */}
      <div
        style={{
          backgroundColor: '#EFF6FF',
          border: `1px solid ${theme.colors.info}`,
          borderRadius: theme.borderRadius.lg,
          padding: theme.spacing.md,
          fontSize: theme.typography.mobile.small,
          color: theme.colors.text.secondary,
        }}
      >
        <p style={{ fontWeight: '700', marginBottom: theme.spacing.xs, color: theme.colors.text.primary }}>
          {t('beforeContinuing')}
        </p>
        <p style={{ margin: 0, lineHeight: '1.5' }}>{t('termsNotice')}</p>
      </div>
    </div>
  );
}
