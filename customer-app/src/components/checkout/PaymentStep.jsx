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
      const taxRate = 0.17;
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
        <div
          style={{
            backgroundColor: '#F0FDF4',
            border: `1px solid ${theme.colors.success}`,
            borderRadius: theme.borderRadius.lg,
            padding: theme.spacing.xl,
            color: '#166534',
          }}
        >
          <h3
            style={{
              fontSize: theme.typography.desktop.h2,
              fontWeight: '700',
              marginBottom: theme.spacing.lg,
            }}
          >
            {t('orderConfirmed')}!
          </h3>
          <div style={{ display: 'flex', flexDirection: 'column', gap: theme.spacing.sm }}>
            <p style={{ margin: 0 }}>
              <strong>{t('orderNumber')}:</strong> #{orderResult.order_id}
            </p>
            <p style={{ margin: 0 }}>
              <strong>{t('trackingToken')}:</strong>{' '}
              <code
                style={{
                  backgroundColor: theme.colors.cardBg,
                  padding: `${theme.spacing.xs} ${theme.spacing.sm}`,
                  borderRadius: theme.borderRadius.md,
                  border: `1px solid ${theme.colors.border}`,
                  fontSize: theme.typography.mobile.small,
                }}
              >
                {orderResult.tracking_token}
              </code>
            </p>
            <p style={{ margin: 0 }}>
              <strong>{t('total')}:</strong> ₪{orderResult.total_price.toFixed(2)}
            </p>
            {orderResult.loyalty_discount > 0 && (
              <p style={{ margin: 0, color: theme.colors.success }}>
                <strong>{t('pointsDiscount')}:</strong> -₪{orderResult.loyalty_discount.toFixed(2)} ({orderResult.loyalty_points_used} {t('pointsRedeemed')})
              </p>
            )}
          </div>
          <div
            style={{
              marginTop: theme.spacing.lg,
              backgroundColor: theme.colors.cardBg,
              border: `1px solid ${theme.colors.border}`,
              borderRadius: theme.borderRadius.lg,
              padding: theme.spacing.md,
              color: theme.colors.text.secondary,
            }}
          >
            <p
              style={{
                fontWeight: '700',
                marginBottom: theme.spacing.xs,
                color: theme.colors.text.primary,
              }}
            >
              {t('trackYourOrder')}
            </p>
            <p style={{ margin: 0, fontSize: theme.typography.mobile.small }}>
              {t('useTokenToTrack')}
            </p>
            <p
              style={{
                margin: 0,
                marginTop: theme.spacing.sm,
                fontSize: theme.typography.mobile.small,
              }}
            >
              {t('confirmationSentTo', { phone: checkoutData.customerInfo.phone })}
            </p>
            {isAuthenticated && (
              <p
                style={{
                  margin: 0,
                  marginTop: theme.spacing.sm,
                  fontSize: theme.typography.mobile.small,
                  color: theme.colors.warning,
                  fontWeight: '600',
                }}
              >
                {t('pointsWillBeAwarded')}
              </p>
            )}
          </div>
        </div>
      )}

      {/* Redirecting State */}
      {orderResult && orderResult.payment_url && processingStage === 'redirecting' && (
        <div
          style={{
            backgroundColor: '#EFF6FF',
            border: `1px solid ${theme.colors.info}`,
            borderRadius: theme.borderRadius.lg,
            padding: theme.spacing.xl,
            textAlign: 'center',
            color: '#1E40AF',
          }}
        >
          <div style={spinnerStyle} />
          <h3
            style={{
              fontSize: theme.typography.desktop.h3,
              fontWeight: '700',
              marginBottom: theme.spacing.sm,
            }}
          >
            {t('redirectingToPayment')}...
          </h3>
          <p style={{ margin: 0, marginBottom: theme.spacing.lg }}>
            {t('doNotCloseWindow')}
          </p>
          <div
            style={{
              backgroundColor: theme.colors.cardBg,
              border: `1px solid ${theme.colors.border}`,
              borderRadius: theme.borderRadius.lg,
              padding: theme.spacing.md,
              textAlign: 'start',
              fontSize: theme.typography.mobile.small,
              color: theme.colors.text.secondary,
            }}
          >
            <p style={{ margin: 0 }}>
              <strong style={{ color: theme.colors.text.primary }}>{t('orderNumber')}:</strong> #{orderResult.order_id}
            </p>
            <p style={{ margin: 0, marginTop: theme.spacing.sm }}>
              <strong style={{ color: theme.colors.text.primary }}>{t('trackingToken')}:</strong>{' '}
              <code
                style={{
                  backgroundColor: theme.colors.background,
                  padding: `2px ${theme.spacing.xs}`,
                  borderRadius: theme.borderRadius.sm,
                }}
              >
                {orderResult.tracking_token}
              </code>
            </p>
            <p
              style={{
                margin: 0,
                marginTop: theme.spacing.md,
                fontSize: '0.75rem',
                color: theme.colors.text.muted,
              }}
            >
              {t('saveTrackingToken')}
            </p>
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
