import React, { useState, useEffect } from 'react';
import { useCart } from '../../contexts/CartContext';
import { useAuth } from '../../contexts/AuthContext';
import CustomerInfoStep from './CustomerInfoStep';
import DeliveryStep from './DeliveryStep';
import ReviewStep from './ReviewStep';
import PaymentStep from './PaymentStep';
import { t } from '../../i18n/translations';
import theme from '../../config/theme';

/**
 * CheckoutFlow - Modal-based multi-step checkout
 * Renders as a large modal overlay covering ~80% of the screen
 */
export default function CheckoutFlow({ onBack, branchId }) {
  const { getCartData } = useCart();
  const { customer, isAuthenticated } = useAuth();
  const [currentStep, setCurrentStep] = useState(1);
  const [checkoutData, setCheckoutData] = useState({
    customerInfo: {
      firstName: '',
      lastName: '',
      phone: '',
      email: '',
    },
    deliveryType: 'pickup',
    deliveryAddress: '',
    deliveryTime: '',
    deliveryFee: 0,
    paymentMethod: 'woocommerce',
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

  const [orderResult, setOrderResult] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const updateCheckoutData = (stepData) => {
    setCheckoutData((prev) => ({ ...prev, ...stepData }));
  };

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
        return true;
      default:
        return false;
    }
  };

  const cartData = getCartData();
  const stepLabels = [t('customerInfo'), t('delivery'), t('review'), t('payment')];
  const isDisabled = !canProceedToNextStep() || loading;

  return (
    <div
      style={{
        position: 'fixed',
        inset: 0,
        zIndex: 1000,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        backgroundColor: 'rgba(0, 0, 0, 0.5)',
        backdropFilter: 'blur(4px)',
      }}
      onClick={(e) => {
        if (e.target === e.currentTarget) onBack();
      }}
    >
      {/* Modal Container */}
      <div
        style={{
          width: '90%',
          maxWidth: '900px',
          height: '85vh',
          backgroundColor: theme.colors.cardBg,
          borderRadius: theme.borderRadius.xl,
          boxShadow: theme.shadows.lg,
          display: 'flex',
          flexDirection: 'column',
          overflow: 'hidden',
        }}
        dir="rtl"
      >
        {/* Modal Header */}
        <div
          style={{
            padding: `${theme.spacing.md} ${theme.spacing.xl}`,
            borderBottom: `1px solid ${theme.colors.border}`,
            flexShrink: 0,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
          }}
        >
          <h1
            style={{
              fontSize: theme.typography.desktop.h2,
              fontWeight: '700',
              color: theme.colors.text.primary,
              margin: 0,
            }}
          >
            {t('checkout')}
          </h1>

          {/* Close Button */}
          <button
            onClick={onBack}
            style={{
              background: 'none',
              border: 'none',
              cursor: 'pointer',
              padding: theme.spacing.xs,
              color: theme.colors.text.muted,
              fontSize: '1.5rem',
              lineHeight: 1,
              transition: 'color 0.2s ease',
            }}
            onMouseEnter={(e) => { e.currentTarget.style.color = theme.colors.text.primary; }}
            onMouseLeave={(e) => { e.currentTarget.style.color = theme.colors.text.muted; }}
          >
            ✕
          </button>
        </div>

        {/* Progress Bar */}
        <div
          style={{
            padding: `${theme.spacing.md} ${theme.spacing.xl}`,
            borderBottom: `1px solid ${theme.colors.border}`,
            flexShrink: 0,
            backgroundColor: theme.colors.background,
          }}
        >
          <div
            style={{
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'space-between',
            }}
          >
            {[1, 2, 3, 4].map((step) => (
              <div
                key={step}
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  flex: 1,
                }}
              >
                <div
                  style={{
                    width: '36px',
                    height: '36px',
                    borderRadius: theme.borderRadius.full,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    fontWeight: '700',
                    fontSize: theme.typography.mobile.small,
                    backgroundColor:
                      step === currentStep
                        ? theme.colors.primary
                        : step < currentStep
                        ? theme.colors.success
                        : theme.colors.border,
                    color:
                      step <= currentStep
                        ? theme.colors.text.white
                        : theme.colors.text.muted,
                    transition: 'all 0.3s ease',
                    flexShrink: 0,
                  }}
                >
                  {step < currentStep ? '✓' : step}
                </div>
                {step < 4 && (
                  <div
                    style={{
                      flex: 1,
                      height: '3px',
                      margin: `0 ${theme.spacing.sm}`,
                      borderRadius: theme.borderRadius.full,
                      backgroundColor:
                        step < currentStep ? theme.colors.success : theme.colors.border,
                      transition: 'background-color 0.3s ease',
                    }}
                  />
                )}
              </div>
            ))}
          </div>

          {/* Step Labels */}
          <div
            style={{
              display: 'flex',
              justifyContent: 'space-between',
              marginTop: theme.spacing.xs,
            }}
          >
            {stepLabels.map((label, idx) => (
              <span
                key={idx}
                style={{
                  fontSize: '0.75rem',
                  fontWeight: currentStep === idx + 1 ? '700' : '400',
                  color:
                    currentStep === idx + 1
                      ? theme.colors.text.primary
                      : theme.colors.text.muted,
                  transition: 'all 0.2s ease',
                }}
              >
                {label}
              </span>
            ))}
          </div>
        </div>

        {/* Error Display */}
        {error && (
          <div
            style={{
              margin: `${theme.spacing.md} ${theme.spacing.xl} 0`,
              backgroundColor: '#FEF2F2',
              border: `1px solid ${theme.colors.error}`,
              borderRadius: theme.borderRadius.lg,
              padding: theme.spacing.md,
              color: theme.colors.error,
              fontSize: theme.typography.mobile.small,
              flexShrink: 0,
            }}
          >
            {error}
          </div>
        )}

        {/* Scrollable Step Content */}
        <div
          style={{
            flex: 1,
            overflowY: 'auto',
            padding: theme.spacing.xl,
          }}
        >
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

        {/* Fixed Footer with Navigation */}
        {currentStep < 4 && (
          <div
            style={{
              padding: `${theme.spacing.md} ${theme.spacing.xl}`,
              borderTop: `1px solid ${theme.colors.border}`,
              flexShrink: 0,
              display: 'flex',
              justifyContent: 'space-between',
              gap: theme.spacing.md,
              backgroundColor: theme.colors.cardBg,
            }}
          >
            <button
              onClick={goToPreviousStep}
              disabled={currentStep === 1}
              style={{
                padding: `${theme.spacing.sm} ${theme.spacing.xl}`,
                border: `1px solid ${theme.colors.border}`,
                borderRadius: theme.borderRadius.lg,
                backgroundColor: theme.colors.cardBg,
                color: currentStep === 1 ? theme.colors.text.muted : theme.colors.text.primary,
                fontWeight: '600',
                fontSize: theme.typography.mobile.body,
                cursor: currentStep === 1 ? 'not-allowed' : 'pointer',
                opacity: currentStep === 1 ? 0.5 : 1,
                transition: 'all 0.2s ease',
              }}
            >
              {t('back')}
            </button>

            <button
              onClick={goToNextStep}
              disabled={isDisabled}
              style={{
                padding: `${theme.spacing.sm} ${theme.spacing.xl}`,
                border: 'none',
                borderRadius: theme.borderRadius.lg,
                backgroundColor: isDisabled ? theme.colors.border : theme.colors.primary,
                color: theme.colors.text.white,
                fontWeight: '700',
                fontSize: theme.typography.mobile.body,
                cursor: isDisabled ? 'not-allowed' : 'pointer',
                opacity: isDisabled ? 0.6 : 1,
                transition: 'all 0.2s ease',
                minWidth: '140px',
              }}
            >
              {loading ? t('loading') : t('continue')}
            </button>
          </div>
        )}
      </div>
    </div>
  );
}
