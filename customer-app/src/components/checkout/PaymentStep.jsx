import React, { useState, useEffect } from 'react';
import publicApi from '../../services/publicApi';
import { useAuth } from '../../contexts/AuthContext';
import { t } from '../../i18n/translations';

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
  const { customer: authCustomer, isAuthenticated } = useAuth();
  const [processingStage, setProcessingStage] = useState('idle'); // 'idle', 'creating_customer', 'creating_order', 'redirecting', 'complete'

  // Auto-initiate checkout when component mounts (if not already processed)
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
        // Authenticated user — use existing customer ID
        customerId = authCustomer.id;
        setProcessingStage('creating_order');
      } else {
        // Guest — create guest customer
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

      // Step 3: Checkout cart (creates order)
      setProcessingStage('creating_order');
      const orderResponse = await publicApi.checkoutCart(cartData.token, {
        customer_id: customerId,
        delivery_type: checkoutData.deliveryType,
        delivery_address: checkoutData.deliveryType === 'delivery' ? checkoutData.deliveryAddress : '',
        delivery_time: checkoutData.deliveryTime,
        payment_method: 'woocommerce',
        delivery_fee: deliveryFee,
        notes: '',
      });

      // Store order result
      setOrderResult(orderResponse);

      // Step 4: Redirect to payment if payment URL provided
      if (orderResponse.payment_url) {
        setProcessingStage('redirecting');

        // Save tracking token to sessionStorage for post-payment tracking
        sessionStorage.setItem('squidly_tracking_token', orderResponse.tracking_token);
        sessionStorage.setItem('squidly_order_id', orderResponse.order_id.toString());

        // Small delay before redirect
        setTimeout(() => {
          window.location.href = orderResponse.payment_url;
        }, 1500);
      } else {
        // No payment needed (cash on delivery, etc.)
        setProcessingStage('complete');
      }
    } catch (err) {
      console.error('Checkout failed:', err);
      setError(err.message || t('checkoutFailed'));
      setProcessingStage('idle');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="space-y-6">
      <h2 className="text-2xl font-bold mb-4">{t('payment')}</h2>

      {/* Loading State */}
      {loading && (
        <div className="text-center py-12">
          <div className="inline-block animate-spin w-16 h-16 border-4 border-blue-600 border-t-transparent rounded-full mb-4"></div>
          <div className="space-y-2">
            {processingStage === 'creating_customer' && (
              <p className="text-lg">{t('creatingCustomer')}...</p>
            )}
            {processingStage === 'creating_order' && (
              <p className="text-lg">{t('creatingOrder')}...</p>
            )}
            {processingStage === 'redirecting' && (
              <p className="text-lg">{t('redirectingToPayment')}...</p>
            )}
          </div>
        </div>
      )}

      {/* Error State */}
      {error && !loading && (
        <div className="bg-red-100 border border-red-400 text-red-700 p-4">
          <h3 className="font-bold mb-2">{t('error')}</h3>
          <p>{error}</p>
          <button
            onClick={handleCheckout}
            className="mt-4 px-6 py-2 bg-red-600 text-white hover:bg-red-700"
          >
            {t('tryAgain')}
          </button>
        </div>
      )}

      {/* Success State (No Payment URL) */}
      {orderResult && !orderResult.payment_url && processingStage === 'complete' && (
        <div className="bg-green-100 border border-green-400 text-green-700 p-6">
          <h3 className="text-2xl font-bold mb-4">{t('orderConfirmed')}!</h3>
          <div className="space-y-2">
            <p>
              <strong>{t('orderNumber')}:</strong> #{orderResult.order_id}
            </p>
            <p>
              <strong>{t('trackingToken')}:</strong>{' '}
              <code className="bg-white px-2 py-1 border">{orderResult.tracking_token}</code>
            </p>
            <p>
              <strong>{t('total')}:</strong> ₪{orderResult.total_price.toFixed(2)}
            </p>
          </div>
          <div className="mt-6 bg-white border p-4">
            <p className="font-bold mb-2">{t('trackYourOrder')}</p>
            <p className="text-sm">{t('useTokenToTrack')}</p>
            <p className="text-sm mt-2">{t('confirmationSentTo', { phone: checkoutData.customerInfo.phone })}</p>
          </div>
        </div>
      )}

      {/* Redirecting State */}
      {orderResult && orderResult.payment_url && processingStage === 'redirecting' && (
        <div className="bg-blue-100 border border-blue-400 text-blue-700 p-6 text-center">
          <div className="inline-block animate-spin w-12 h-12 border-4 border-blue-600 border-t-transparent rounded-full mb-4"></div>
          <h3 className="text-xl font-bold mb-2">{t('redirectingToPayment')}...</h3>
          <p>{t('doNotCloseWindow')}</p>
          <div className="mt-6 bg-white border p-4 text-sm text-left">
            <p>
              <strong>{t('orderNumber')}:</strong> #{orderResult.order_id}
            </p>
            <p className="mt-2">
              <strong>{t('trackingToken')}:</strong>{' '}
              <code className="bg-gray-100 px-2 py-1">{orderResult.tracking_token}</code>
            </p>
            <p className="text-xs text-gray-600 mt-3">{t('saveTrackingToken')}</p>
          </div>
        </div>
      )}

      {/* Initial State (shouldn't normally see this due to auto-trigger) */}
      {!loading && !error && !orderResult && processingStage === 'idle' && (
        <div className="text-center py-12">
          <p className="mb-4">{t('readyToComplete')}</p>
          <button
            onClick={handleCheckout}
            className="px-8 py-3 bg-blue-600 text-white text-lg font-bold hover:bg-blue-700"
          >
            {t('completeOrder')}
          </button>
        </div>
      )}
    </div>
  );
}
