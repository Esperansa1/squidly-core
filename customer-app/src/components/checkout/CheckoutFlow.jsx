import React, { useState, useEffect } from 'react';
import { useCart } from '../../contexts/CartContext';
import { useAuth } from '../../contexts/AuthContext';
import CustomerInfoStep from './CustomerInfoStep';
import DeliveryStep from './DeliveryStep';
import ReviewStep from './ReviewStep';
import PaymentStep from './PaymentStep';
import { t } from '../../i18n/translations';

/**
 * CheckoutFlow - Multi-step checkout container
 * Manages 4-step checkout process:
 * 1. Customer Info (guest customer)
 * 2. Delivery/Pickup + Timing
 * 3. Review Order
 * 4. Payment
 */
export default function CheckoutFlow({ onBack, branchId }) {
  const { getCartData } = useCart();
  const { customer, isAuthenticated } = useAuth();
  const [currentStep, setCurrentStep] = useState(1);
  const [checkoutData, setCheckoutData] = useState({
    // Step 1: Customer Info
    customerInfo: {
      firstName: '',
      lastName: '',
      phone: '',
      email: '',
    },
    // Step 2: Delivery
    deliveryType: 'pickup', // 'pickup' or 'delivery'
    deliveryAddress: '',
    deliveryTime: '',
    deliveryFee: 0,
    // Step 3: Review (no additional data)
    // Step 4: Payment
    paymentMethod: 'woocommerce',
    // Loyalty points
    loyaltyPointsToUse: 0,
  });

  // Pre-fill customer info from auth context
  useEffect(() => {
    if (isAuthenticated && customer) {
      setCheckoutData((prev) => ({
        ...prev,
        customerInfo: {
          firstName: customer.first_name || prev.customerInfo.firstName,
          lastName: customer.last_name || prev.customerInfo.lastName,
          phone: customer.phone || prev.customerInfo.phone,
          email: customer.email || prev.customerInfo.email,
        },
      }));
    }
  }, [isAuthenticated, customer]);

  // Order result (from checkout API)
  const [orderResult, setOrderResult] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  // Update checkout data for a specific step
  const updateCheckoutData = (stepData) => {
    setCheckoutData((prev) => ({ ...prev, ...stepData }));
  };

  // Navigate between steps
  const goToNextStep = () => {
    if (currentStep < 4) {
      setCurrentStep(currentStep + 1);
      setError(null);
    }
  };

  const goToPreviousStep = () => {
    if (currentStep > 1) {
      setCurrentStep(currentStep - 1);
      setError(null);
    }
  };

  // Step validation
  const canProceedToNextStep = () => {
    switch (currentStep) {
      case 1:
        return (
          checkoutData.customerInfo.firstName.trim() &&
          checkoutData.customerInfo.lastName.trim() &&
          checkoutData.customerInfo.phone.trim()
        );
      case 2:
        if (checkoutData.deliveryType === 'delivery') {
          return checkoutData.deliveryAddress.trim() && checkoutData.deliveryTime.trim();
        }
        return checkoutData.deliveryTime.trim();
      case 3:
        return true; // Review step, always can proceed
      default:
        return false;
    }
  };

  // Get cart data
  const cartData = getCartData();

  return (
    <div className="max-w-4xl mx-auto p-4">
      {/* Header */}
      <div className="mb-6">
        <button onClick={onBack} className="text-blue-600 mb-4 flex items-center gap-2">
          ← {t('backToCart')}
        </button>
        <h1 className="text-3xl font-bold mb-2">{t('checkout')}</h1>

        {/* Progress Indicator */}
        <div className="flex items-center justify-between mt-4">
          {[1, 2, 3, 4].map((step) => (
            <div key={step} className="flex items-center flex-1">
              <div
                className={`w-10 h-10 rounded-full flex items-center justify-center font-bold ${
                  step === currentStep
                    ? 'bg-blue-600 text-white'
                    : step < currentStep
                    ? 'bg-green-600 text-white'
                    : 'bg-gray-200 text-gray-500'
                }`}
              >
                {step < currentStep ? '✓' : step}
              </div>
              {step < 4 && (
                <div
                  className={`flex-1 h-1 mx-2 ${
                    step < currentStep ? 'bg-green-600' : 'bg-gray-200'
                  }`}
                />
              )}
            </div>
          ))}
        </div>

        {/* Step Labels */}
        <div className="flex justify-between mt-2 text-sm">
          <span className={currentStep === 1 ? 'font-bold' : 'text-gray-500'}>
            {t('customerInfo')}
          </span>
          <span className={currentStep === 2 ? 'font-bold' : 'text-gray-500'}>
            {t('delivery')}
          </span>
          <span className={currentStep === 3 ? 'font-bold' : 'text-gray-500'}>
            {t('review')}
          </span>
          <span className={currentStep === 4 ? 'font-bold' : 'text-gray-500'}>
            {t('payment')}
          </span>
        </div>
      </div>

      {/* Error Display */}
      {error && (
        <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 mb-4">
          {error}
        </div>
      )}

      {/* Step Content */}
      <div className="bg-white border p-6 min-h-[400px]">
        {currentStep === 1 && (
          <CustomerInfoStep
            data={checkoutData.customerInfo}
            onChange={(customerInfo) => updateCheckoutData({ customerInfo })}
          />
        )}

        {currentStep === 2 && (
          <DeliveryStep
            branchId={branchId}
            deliveryType={checkoutData.deliveryType}
            deliveryAddress={checkoutData.deliveryAddress}
            deliveryTime={checkoutData.deliveryTime}
            deliveryFee={checkoutData.deliveryFee}
            cartSubtotal={cartData.subtotal}
            onChange={(data) => updateCheckoutData(data)}
          />
        )}

        {currentStep === 3 && (
          <ReviewStep
            checkoutData={checkoutData}
            cartData={cartData}
            branchId={branchId}
            onEditStep={(step) => setCurrentStep(step)}
            onUpdateLoyaltyPoints={(points) => updateCheckoutData({ loyaltyPointsToUse: points })}
          />
        )}

        {currentStep === 4 && (
          <PaymentStep
            checkoutData={checkoutData}
            cartData={cartData}
            branchId={branchId}
            orderResult={orderResult}
            setOrderResult={setOrderResult}
            loading={loading}
            setLoading={setLoading}
            error={error}
            setError={setError}
          />
        )}
      </div>

      {/* Navigation Buttons */}
      {currentStep < 4 && (
        <div className="flex justify-between mt-6">
          <button
            onClick={goToPreviousStep}
            disabled={currentStep === 1}
            className="px-6 py-3 border text-gray-700 hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {t('back')}
          </button>

          <button
            onClick={goToNextStep}
            disabled={!canProceedToNextStep() || loading}
            className="px-6 py-3 bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {loading ? t('loading') : t('continue')}
          </button>
        </div>
      )}
    </div>
  );
}
