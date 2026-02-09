import React, { useState, useEffect } from 'react';
import publicApi from '../../services/publicApi';
import { useAuth } from '../../contexts/AuthContext';
import { t } from '../../i18n/translations';
import theme from '../../config/theme';

/**
 * PaymentStep - Order creation and payment redirect
 * Step 4 of checkout process
 */
export default function PaymentStep({
  checkoutData,
  cartData,
  branchId,
  orderResult,
  setOrderResult,
  loading,
  setLoading,
  error,
  setError,
}) {
  const { customer: authCustomer, isAuthenticated, refreshCustomer } = useAuth();
  const [processingStage, setProcessingStage] = useState('idle');

  // Auto-initiate checkout when component mounts
  useEffect(() => {
    if (!orderResult && processingStage === 'idle') {
      handleCheckout();
    }
  }, []);

  const handleCheckout = async () => {
    try {
      setLoading(true);
      setError(null);

      // Step 1: Get or create customer
      let customerId;
      if (isAuthenticated && authCustomer) {
        customerId = authCustomer.id;
        setProcessingStage('creating_order');
      } else {
        setProcessingStage('creating_customer');
        const customerResponse = await publicApi.createGuestCustomer({
          first_name: checkoutData.customerInfo.firstName,
          last_name: checkoutData.customerInfo.lastName,
          phone: checkoutData.customerInfo.phone,
          email: checkoutData.customerInfo.email || '',
        });
        customerId = customerResponse.customer_id;
      }

      // Step 2: Calculate tax and total
      const taxRate = publicApi.config?.taxes?.vat_rate ?? 0.18;
      const subtotal = cartData.subtotal;
      const deliveryFee = checkoutData.deliveryFee;
      const taxAmount = subtotal * taxRate;
      const totalAmount = subtotal + deliveryFee + taxAmount;

      // Step 3: Checkout cart
      setProcessingStage('creating_order');
      const orderResponse = await publicApi.checkoutCart(cartData.token, {
        customer_id: customerId,
        delivery_type: checkoutData.deliveryType,
        delivery_address: checkoutData.deliveryType === 'delivery' ? checkoutData.deliveryAddress : '',
        delivery_time: checkoutData.deliveryTime,
        payment_method: 'woocommerce',
        delivery_fee: deliveryFee,
        notes: '',
        loyalty_points_to_use: checkoutData.loyaltyPointsToUse || 0,
      });

      setOrderResult(orderResponse);

      // Refresh customer data to sync loyalty points
      if (isAuthenticated) {
        refreshCustomer().catch(() => {});
      }

      if (orderResponse.payment_url) {
        setProcessingStage('redirecting');

        sessionStorage.setItem('squidly_tracking_token', orderResponse.tracking_token);
        sessionStorage.setItem('squidly_order_id', orderResponse.order_id.toString());

        setTimeout(() => {
          window.location.href = orderResponse.payment_url;
        }, 1500);
      } else {
        setProcessingStage('complete');
      }
    } catch (err) {
      setError(err.message || t('checkoutFailed'));
      setProcessingStage('idle');
    } finally {
      setLoading(false);
    }
  };

  const spinnerStyle = {
    display: 'inline-block',
    width: '48px',
    height: '48px',
    border: `4px solid ${theme.colors.border}`,
    borderTopColor: theme.colors.primary,
    borderRadius: theme.borderRadius.full,
    animation: 'spin 1s linear infinite',
    marginBottom: theme.spacing.lg,
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: theme.spacing.lg }}>
      <h2
        style={{
          fontSize: theme.typography.desktop.h2,
          fontWeight: '700',
          color: theme.colors.text.primary,
          margin: 0,
        }}
      >
        {t('payment')}
      </h2>

      {/* Loading State */}
      {loading && (
        <div
          style={{
            textAlign: 'center',
            padding: `${theme.spacing['2xl']} 0`,
          }}
        >
          <div style={spinnerStyle} />
          <div style={{ display: 'flex', flexDirection: 'column', gap: theme.spacing.xs }}>
            {processingStage === 'creating_customer' && (
              <p
                style={{
                  fontSize: theme.typography.desktop.h3,
                  color: theme.colors.text.secondary,
                }}
              >
                {t('creatingCustomer')}...
              </p>
            )}
            {processingStage === 'creating_order' && (
              <p
                style={{
                  fontSize: theme.typography.desktop.h3,
                  color: theme.colors.text.secondary,
                }}
              >
                {t('creatingOrder')}...
              </p>
            )}
            {processingStage === 'redirecting' && (
              <p
                style={{
                  fontSize: theme.typography.desktop.h3,
                  color: theme.colors.text.secondary,
                }}
              >
                {t('redirectingToPayment')}...
              </p>
            )}
          </div>
        </div>
      )}

      {/* Error State */}
      {error && !loading && (
        <div
          style={{
            backgroundColor: '#FEF2F2',
            border: `1px solid ${theme.colors.error}`,
            borderRadius: theme.borderRadius.lg,
            padding: theme.spacing.lg,
            color: theme.colors.error,
          }}
        >
          <h3
            style={{
              fontWeight: '700',
              marginBottom: theme.spacing.sm,
              fontSize: theme.typography.mobile.h3,
            }}
          >
            {t('error')}
          </h3>
          <p style={{ margin: 0, marginBottom: theme.spacing.md }}>{error}</p>
          <button
            onClick={handleCheckout}
            style={{
              padding: `${theme.spacing.sm} ${theme.spacing.xl}`,
              backgroundColor: theme.colors.error,
              color: theme.colors.text.white,
              border: 'none',
              borderRadius: theme.borderRadius.lg,
              fontWeight: '700',
              fontSize: theme.typography.mobile.body,
              cursor: 'pointer',
              transition: 'opacity 0.2s ease',
            }}
          >
            {t('tryAgain')}
          </button>
        </div>
      )}

      {/* Success State (No Payment URL) */}
      {orderResult && !orderResult.payment_url && processingStage === 'complete' && (
        <div style={{ display: 'flex', flexDirection: 'column', gap: theme.spacing.lg }}>
          {/* Success Icon & Message */}
          <div style={{ textAlign: 'center' }}>
            <div
              style={{
                width: '80px',
                height: '80px',
                borderRadius: theme.borderRadius.full,
                backgroundColor: '#F0FDF4',
                border: `3px solid ${theme.colors.success}`,
                display: 'inline-flex',
                alignItems: 'center',
                justifyContent: 'center',
                marginBottom: theme.spacing.md,
              }}
            >
              <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke={theme.colors.success} strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
                <polyline points="20 6 9 17 4 12" />
              </svg>
            </div>
            <h3
              style={{
                fontSize: theme.typography.desktop.h2,
                fontWeight: '700',
                color: theme.colors.success,
                margin: `0 0 ${theme.spacing.xs} 0`,
              }}
            >
              {t('orderConfirmed')}!
            </h3>
            <p style={{ color: theme.colors.text.secondary, margin: 0 }}>
              הזמנתך התקבלה בהצלחה ותתחיל להתכונן בקרוב
            </p>
          </div>

          {/* Order Details Card */}
          <div
            style={{
              backgroundColor: theme.colors.cardBg,
              border: `1px solid ${theme.colors.border}`,
              borderRadius: theme.borderRadius.xl,
              padding: theme.spacing.lg,
              boxShadow: theme.shadows.card,
            }}
          >
            <div style={{ display: 'flex', flexDirection: 'column', gap: theme.spacing.md }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <span style={{ fontSize: theme.typography.mobile.small, color: theme.colors.text.secondary }}>
                  {t('orderNumber')}
                </span>
                <span style={{ fontSize: theme.typography.mobile.h3, fontWeight: '700', color: theme.colors.text.primary }}>
                  #{orderResult.order_id}
                </span>
              </div>

              <div style={{ height: '1px', backgroundColor: theme.colors.border }} />

              <div>
                <div style={{ fontSize: theme.typography.mobile.small, color: theme.colors.text.secondary, marginBottom: theme.spacing.xs }}>
                  {t('trackingToken')}
                </div>
                <div
                  style={{
                    backgroundColor: theme.colors.background,
                    padding: theme.spacing.md,
                    borderRadius: theme.borderRadius.lg,
                    border: `2px dashed ${theme.colors.border}`,
                    fontFamily: 'monospace',
                    fontSize: theme.typography.mobile.body,
                    fontWeight: '700',
                    color: theme.colors.text.primary,
                    textAlign: 'center',
                    letterSpacing: '0.05em',
                  }}
                >
                  {orderResult.tracking_token}
                </div>
                <p style={{ fontSize: '0.75rem', color: theme.colors.text.muted, margin: `${theme.spacing.xs} 0 0 0`, textAlign: 'center' }}>
                  שמור את הקוד לעקוב אחר ההזמנה
                </p>
              </div>

              <div style={{ height: '1px', backgroundColor: theme.colors.border }} />

              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline' }}>
                <span style={{ fontSize: theme.typography.mobile.body, color: theme.colors.text.secondary }}>
                  {t('total')}
                </span>
                <span style={{ fontSize: theme.typography.desktop.h3, fontWeight: '700', color: theme.colors.text.primary }}>
                  ₪{orderResult.total_price.toFixed(2)}
                </span>
              </div>

              {orderResult.loyalty_discount > 0 && (
                <div
                  style={{
                    backgroundColor: '#FEF3C7',
                    padding: theme.spacing.sm,
                    borderRadius: theme.borderRadius.md,
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                  }}
                >
                  <span style={{ fontSize: theme.typography.mobile.small, color: '#92400E', fontWeight: '600' }}>
                    {t('pointsDiscount')}
                  </span>
                  <span style={{ fontSize: theme.typography.mobile.body, color: '#92400E', fontWeight: '700' }}>
                    -₪{orderResult.loyalty_discount.toFixed(2)} ({orderResult.loyalty_points_used} נק')
                  </span>
                </div>
              )}

              {isAuthenticated && (
                <div
                  style={{
                    backgroundColor: '#FEF3C7',
                    padding: theme.spacing.md,
                    borderRadius: theme.borderRadius.lg,
                    textAlign: 'center',
                  }}
                >
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="#F59E0B" stroke="none" style={{ marginBottom: theme.spacing.xs }}>
                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                  </svg>
                  <p style={{ fontSize: theme.typography.mobile.small, color: '#92400E', fontWeight: '600', margin: 0 }}>
                    הנקודות שלך יזוכו לאחר השלמת ההזמנה
                  </p>
                </div>
              )}
            </div>
          </div>

          {/* Contact Info */}
          <div
            style={{
              backgroundColor: '#EFF6FF',
              border: `1px solid ${theme.colors.info}`,
              borderRadius: theme.borderRadius.lg,
              padding: theme.spacing.md,
              fontSize: theme.typography.mobile.small,
              color: '#1E40AF',
            }}
          >
            <p style={{ margin: 0, fontWeight: '600' }}>
              📱 {t('confirmationSentTo', { phone: checkoutData.customerInfo.phone })}
            </p>
          </div>
        </div>
      )}

      {/* Redirecting State */}
      {orderResult && orderResult.payment_url && processingStage === 'redirecting' && (
        <div style={{ display: 'flex', flexDirection: 'column', gap: theme.spacing.lg, alignItems: 'center', textAlign: 'center' }}>
          <div style={spinnerStyle} />

          <div>
            <h3
              style={{
                fontSize: theme.typography.desktop.h2,
                fontWeight: '700',
                margin: `0 0 ${theme.spacing.sm} 0`,
                color: theme.colors.info,
              }}
            >
              {t('redirectingToPayment')}...
            </h3>
            <p style={{ margin: 0, color: theme.colors.text.secondary, fontSize: theme.typography.mobile.body }}>
              {t('doNotCloseWindow')}
            </p>
          </div>

          <div
            style={{
              backgroundColor: theme.colors.cardBg,
              border: `1px solid ${theme.colors.border}`,
              borderRadius: theme.borderRadius.xl,
              padding: theme.spacing.lg,
              boxShadow: theme.shadows.card,
              width: '100%',
              maxWidth: '400px',
            }}
          >
            <div style={{ display: 'flex', flexDirection: 'column', gap: theme.spacing.md, textAlign: 'start' }}>
              <div>
                <div style={{ fontSize: theme.typography.mobile.small, color: theme.colors.text.secondary, marginBottom: theme.spacing.xs }}>
                  {t('orderNumber')}
                </div>
                <div style={{ fontSize: theme.typography.mobile.h3, fontWeight: '700', color: theme.colors.text.primary }}>
                  #{orderResult.order_id}
                </div>
              </div>

              <div style={{ height: '1px', backgroundColor: theme.colors.border }} />

              <div>
                <div style={{ fontSize: theme.typography.mobile.small, color: theme.colors.text.secondary, marginBottom: theme.spacing.xs }}>
                  {t('trackingToken')}
                </div>
                <div
                  style={{
                    backgroundColor: theme.colors.background,
                    padding: `${theme.spacing.sm} ${theme.spacing.md}`,
                    borderRadius: theme.borderRadius.md,
                    border: `1px solid ${theme.colors.border}`,
                    fontFamily: 'monospace',
                    fontSize: theme.typography.mobile.body,
                    fontWeight: '700',
                    color: theme.colors.text.primary,
                    textAlign: 'center',
                    letterSpacing: '0.05em',
                  }}
                >
                  {orderResult.tracking_token}
                </div>
                <p
                  style={{
                    margin: `${theme.spacing.xs} 0 0 0`,
                    fontSize: '0.75rem',
                    color: theme.colors.text.muted,
                    textAlign: 'center',
                  }}
                >
                  {t('saveTrackingToken')}
                </p>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Initial State (fallback) */}
      {!loading && !error && !orderResult && processingStage === 'idle' && (
        <div
          style={{
            textAlign: 'center',
            padding: `${theme.spacing['2xl']} 0`,
          }}
        >
          <p
            style={{
              marginBottom: theme.spacing.lg,
              color: theme.colors.text.secondary,
            }}
          >
            {t('readyToComplete')}
          </p>
          <button
            onClick={handleCheckout}
            style={{
              padding: `${theme.spacing.md} ${theme.spacing['2xl']}`,
              border: 'none',
              borderRadius: theme.borderRadius.lg,
              backgroundColor: theme.colors.primary,
              color: theme.colors.text.white,
              fontSize: theme.typography.desktop.h3,
              fontWeight: '700',
              cursor: 'pointer',
              transition: 'opacity 0.2s ease',
            }}
          >
            {t('completeOrder')}
          </button>
        </div>
      )}
    </div>
  );
}
