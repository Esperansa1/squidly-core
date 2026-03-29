import React, { useState, useEffect, useCallback, useRef } from 'react';
import { useCart } from '../../contexts/CartContext';
import { useBranch } from '../../contexts/BranchContext';
import { t, getCurrentLanguage } from '../../i18n/translations';
import theme from '../../config/theme';
import { useIsMobile, useIsTablet } from '../../hooks/useMediaQuery';
import publicApi from '../../services/publicApi';
import {
  XMarkIcon,
  TruckIcon,
  ShoppingBagIcon,
  CheckCircleIcon,
  ArrowPathIcon,
} from '@heroicons/react/24/outline';

// ─── Constants ────────────────────────────────────────────────────────────────
const PHONE_REGEX = /^(\+972|0)[2-9]\d{7,8}$/;
const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

// ─── Helper: section heading style ────────────────────────────────────────────
function SectionHeading({ children }) {
  return (
    <p
      style={{
        fontSize: '0.8125rem',
        fontWeight: '700',
        textTransform: 'uppercase',
        letterSpacing: '0.07em',
        color: theme.colors.text.muted,
        margin: `0 0 ${theme.spacing.sm}`,
      }}
    >
      {children}
    </p>
  );
}

// ─── Helper: input field ──────────────────────────────────────────────────────
function Field({ label, required, error, hint, children }) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '4px' }}>
      <label
        style={{
          fontSize: '0.875rem',
          fontWeight: '600',
          color: theme.colors.text.primary,
        }}
      >
        {label}
        {required && (
          <span style={{ color: theme.colors.error, marginInlineStart: '3px' }}>*</span>
        )}
      </label>
      {children}
      {error && (
        <span style={{ fontSize: '0.75rem', color: theme.colors.error }}>{error}</span>
      )}
      {hint && !error && (
        <span style={{ fontSize: '0.75rem', color: theme.colors.text.muted }}>{hint}</span>
      )}
    </div>
  );
}

// ─── Shared input styles ──────────────────────────────────────────────────────
function inputStyle(hasError) {
  return {
    width: '100%',
    padding: `${theme.spacing.sm} ${theme.spacing.md}`,
    fontSize: '0.9375rem',
    fontFamily: theme.fonts.primary,
    color: theme.colors.text.primary,
    backgroundColor: theme.colors.background,
    border: `1.5px solid ${hasError ? theme.colors.error : theme.colors.border}`,
    borderRadius: theme.borderRadius.md,
    outline: 'none',
    boxSizing: 'border-box',
    transition: 'border-color 0.15s ease',
  };
}

// ─── Delivery type card ───────────────────────────────────────────────────────
function DeliveryCard({ icon, label, selected, onClick, isMobile }) {
  return (
    <button
      type="button"
      onClick={onClick}
      style={{
        flex: 1,
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        gap: theme.spacing.xs,
        padding: isMobile ? theme.spacing.md : theme.spacing.lg,
        backgroundColor: selected ? 'rgba(220, 38, 38, 0.05)' : theme.colors.cardBg,
        border: `2px solid ${selected ? theme.colors.primary : theme.colors.border}`,
        borderRadius: theme.borderRadius.lg,
        cursor: 'pointer',
        transition: 'all 0.2s ease',
        minHeight: isMobile ? '80px' : '96px',
      }}
      onMouseEnter={(e) => {
        if (!selected) {
          e.currentTarget.style.borderColor = theme.colors.primary;
          e.currentTarget.style.backgroundColor = 'rgba(220, 38, 38, 0.03)';
        }
      }}
      onMouseLeave={(e) => {
        if (!selected) {
          e.currentTarget.style.borderColor = theme.colors.border;
          e.currentTarget.style.backgroundColor = theme.colors.cardBg;
        }
      }}
    >
      <div style={{ color: selected ? theme.colors.primary : theme.colors.text.muted }}>
        {icon}
      </div>
      <span
        style={{
          fontSize: '0.875rem',
          fontWeight: '600',
          color: selected ? theme.colors.primary : theme.colors.text.primary,
        }}
      >
        {label}
      </span>
    </button>
  );
}

// ─── Cart item row ────────────────────────────────────────────────────────────
function CartRow({ item }) {
  const price = ((item.final_price || item.unit_price || 0) * (item.quantity || 1));
  return (
    <div
      style={{
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'flex-start',
        gap: theme.spacing.sm,
        padding: `${theme.spacing.sm} 0`,
        borderBottom: `1px solid ${theme.colors.border}`,
      }}
    >
      <div style={{ flex: 1, minWidth: 0 }}>
        <p
          style={{
            fontSize: '0.9375rem',
            fontWeight: '600',
            color: theme.colors.text.primary,
            margin: 0,
            whiteSpace: 'nowrap',
            overflow: 'hidden',
            textOverflow: 'ellipsis',
          }}
        >
          {item.product_name || item.name}
          {item.quantity > 1 && (
            <span
              style={{
                marginInlineStart: '6px',
                fontSize: '0.8125rem',
                fontWeight: '700',
                color: theme.colors.primary,
              }}
            >
              ×{item.quantity}
            </span>
          )}
        </p>
        {item.customizations && Object.keys(item.customizations).length > 0 && (
          <p
            style={{
              fontSize: '0.75rem',
              color: theme.colors.text.muted,
              margin: `2px 0 0`,
            }}
          >
            {Object.values(item.customizations)
              .flat()
              .map((c) => c.name)
              .filter(Boolean)
              .join(', ')}
          </p>
        )}
        {(item.special_instructions || item.notes) && (
          <p
            style={{
              fontSize: '0.75rem',
              color: theme.colors.text.secondary,
              margin: `2px 0 0`,
              fontStyle: 'italic',
            }}
          >
            {item.special_instructions || item.notes}
          </p>
        )}
      </div>
      <span
        style={{
          fontSize: '0.9375rem',
          fontWeight: '700',
          color: theme.colors.text.primary,
          whiteSpace: 'nowrap',
          flexShrink: 0,
        }}
      >
        ₪{price.toFixed(2)}
      </span>
    </div>
  );
}

// ─── Processing overlay ───────────────────────────────────────────────────────
function ProcessingState({ stage, orderResult }) {
  const stageMessages = {
    creating_customer: t('creatingCustomer'),
    creating_order: t('creatingOrder'),
    redirecting: t('redirectingToPayment'),
  };

  if (stage === 'complete' && orderResult && !orderResult.payment_url) {
    return (
      <div
        style={{
          display: 'flex',
          flexDirection: 'column',
          alignItems: 'center',
          justifyContent: 'center',
          padding: theme.spacing.xl,
          gap: theme.spacing.md,
          textAlign: 'center',
        }}
      >
        <CheckCircleIcon style={{ width: '64px', height: '64px', color: theme.colors.success }} />
        <h3
          style={{
            fontSize: '1.5rem',
            fontWeight: '700',
            color: theme.colors.text.primary,
            margin: 0,
          }}
        >
          {t('orderConfirmed')}!
        </h3>
        <div
          style={{
            backgroundColor: theme.colors.background,
            borderRadius: theme.borderRadius.lg,
            padding: theme.spacing.md,
            width: '100%',
            maxWidth: '320px',
          }}
        >
          <p style={{ fontSize: '0.875rem', color: theme.colors.text.secondary, margin: `0 0 ${theme.spacing.xs}` }}>
            {t('orderNumber')}: <strong>#{orderResult.order_id}</strong>
          </p>
          <p style={{ fontSize: '0.875rem', color: theme.colors.text.secondary, margin: `0 0 ${theme.spacing.xs}` }}>
            {t('total')}: <strong>₪{orderResult.total_price?.toFixed(2)}</strong>
          </p>
          <p style={{ fontSize: '0.75rem', color: theme.colors.text.muted, margin: 0 }}>
            {t('trackingToken')}: <code>{orderResult.tracking_token}</code>
          </p>
        </div>
      </div>
    );
  }

  return (
    <div
      style={{
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        padding: theme.spacing['2xl'],
        gap: theme.spacing.lg,
        textAlign: 'center',
      }}
    >
      <div
        style={{
          width: '56px',
          height: '56px',
          borderRadius: '50%',
          border: `4px solid ${theme.colors.primary}`,
          borderTopColor: 'transparent',
          animation: 'spin 0.8s linear infinite',
        }}
      />
      <div>
        <p
          style={{
            fontSize: '1.125rem',
            fontWeight: '600',
            color: theme.colors.text.primary,
            margin: `0 0 ${theme.spacing.xs}`,
          }}
        >
          {stageMessages[stage] || t('loading')}
        </p>
        <p style={{ fontSize: '0.875rem', color: theme.colors.text.muted, margin: 0 }}>
          {stage === 'redirecting' ? t('doNotCloseWindow') : ''}
        </p>
      </div>
    </div>
  );
}

// ─── Main component ────────────────────────────────────────────────────────────
export default function CheckoutModal({ isOpen, onClose }) {
  const isMobile = useIsMobile();
  const isTablet = useIsTablet();
  const { cart, getTotal, clearCart } = useCart();
  const { selectedBranch } = useBranch();
  const branchId = selectedBranch?.id;

  // Direction detection
  const [direction, setDirection] = useState('rtl');
  useEffect(() => {
    if (isOpen) {
      const lang = getCurrentLanguage();
      setDirection(lang === 'he' || lang === 'ar' ? 'rtl' : 'ltr');
    }
  }, [isOpen]);

  // Form data
  const [form, setForm] = useState({
    firstName: '',
    lastName: '',
    phone: '',
    email: '',
    deliveryType: 'pickup',
    deliveryAddress: '',
    deliveryTime: '',
    notes: '',
  });
  const [errors, setErrors] = useState({});

  // Delivery fee state
  const [deliveryFee, setDeliveryFee] = useState(0);
  const [feeInfo, setFeeInfo] = useState(null);
  const [feeError, setFeeError] = useState(null);
  const [calculatingFee, setCalculatingFee] = useState(false);

  // Checkout process state
  const [processingStage, setProcessingStage] = useState('idle');
  const [orderResult, setOrderResult] = useState(null);
  const [submitError, setSubmitError] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Delivery fee debounce ref
  const feeDebounceRef = useRef(null);

  // Reset on open
  useEffect(() => {
    if (isOpen) {
      setForm({
        firstName: '',
        lastName: '',
        phone: '',
        email: '',
        deliveryType: 'pickup',
        deliveryAddress: '',
        deliveryTime: '',
        notes: '',
      });
      setErrors({});
      setDeliveryFee(0);
      setFeeInfo(null);
      setFeeError(null);
      setProcessingStage('idle');
      setOrderResult(null);
      setSubmitError(null);
      setIsSubmitting(false);
    }
  }, [isOpen]);

  // Auto-calculate delivery fee when address changes
  useEffect(() => {
    if (form.deliveryType !== 'delivery' || !form.deliveryAddress.trim()) {
      setDeliveryFee(0);
      setFeeInfo(null);
      setFeeError(null);
      return;
    }

    if (feeDebounceRef.current) clearTimeout(feeDebounceRef.current);

    feeDebounceRef.current = setTimeout(async () => {
      try {
        setCalculatingFee(true);
        setFeeError(null);
        const subtotal = getTotal();
        const result = await publicApi.getDeliveryFee(branchId, form.deliveryAddress, subtotal);
        if (!result.is_deliverable) {
          setFeeError(t('addressNotDeliverable'));
          setDeliveryFee(0);
          setFeeInfo(null);
        } else {
          setFeeInfo(result);
          setDeliveryFee(result.delivery_fee || 0);
        }
      } catch {
        setFeeError(t('failedToCalculateFee'));
        setDeliveryFee(0);
        setFeeInfo(null);
      } finally {
        setCalculatingFee(false);
      }
    }, 700);

    return () => {
      if (feeDebounceRef.current) clearTimeout(feeDebounceRef.current);
    };
  }, [form.deliveryAddress, form.deliveryType, branchId]);

  // Field change handler
  const handleChange = useCallback((field, value) => {
    setForm((prev) => ({ ...prev, [field]: value }));
    if (errors[field]) {
      setErrors((prev) => {
        const next = { ...prev };
        delete next[field];
        return next;
      });
    }
  }, [errors]);

  // Delivery type toggle
  const handleDeliveryTypeChange = useCallback((type) => {
    setForm((prev) => ({
      ...prev,
      deliveryType: type,
      deliveryAddress: type === 'pickup' ? '' : prev.deliveryAddress,
    }));
    setDeliveryFee(0);
    setFeeInfo(null);
    setFeeError(null);
  }, []);

  // Validate form
  const validate = () => {
    const next = {};
    if (!form.firstName.trim()) next.firstName = t('requiredField');
    if (!form.lastName.trim()) next.lastName = t('requiredField');
    if (!form.phone.trim()) {
      next.phone = t('requiredField');
    } else {
      const cleaned = form.phone.replace(/[-\s]/g, '');
      if (!PHONE_REGEX.test(cleaned)) next.phone = t('invalidPhone');
    }
    if (form.email && !EMAIL_REGEX.test(form.email)) next.email = t('invalidEmail');
    if (form.deliveryType === 'delivery' && !form.deliveryAddress.trim()) {
      next.deliveryAddress = t('requiredField');
    }
    if (!form.deliveryTime) next.deliveryTime = t('requiredField');
    return next;
  };

  // Submit checkout
  const handleSubmit = async () => {
    const validationErrors = validate();
    if (Object.keys(validationErrors).length > 0) {
      setErrors(validationErrors);
      return;
    }

    try {
      setIsSubmitting(true);
      setSubmitError(null);
      const cartData = cart;

      // Step 1: Create guest customer
      setProcessingStage('creating_customer');
      const customerResponse = await publicApi.createGuestCustomer({
        first_name: form.firstName.trim(),
        last_name: form.lastName.trim(),
        phone: form.phone.replace(/[-\s]/g, '').trim(),
        email: form.email?.trim() || null,
      });
      const customerId = customerResponse.customer_id;

      // Step 2: Create order via cart checkout
      setProcessingStage('creating_order');
      const orderResponse = await publicApi.checkoutCart(cartData.token, {
        customer_id: customerId,
        delivery_type: form.deliveryType,
        delivery_address: form.deliveryType === 'delivery' ? form.deliveryAddress.trim() : '',
        delivery_time: form.deliveryTime,
        payment_method: 'woocommerce',
        delivery_fee: deliveryFee,
        notes: form.notes.trim(),
      });

      setOrderResult(orderResponse);

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
      console.error('Checkout failed:', err);
      setSubmitError(err.message || t('checkoutFailed'));
      setProcessingStage('idle');
    } finally {
      setIsSubmitting(false);
    }
  };

  if (!isOpen) return null;

  // Cart calculations
  const items = cart?.items || [];
  const subtotal = getTotal();
  const taxAmount = subtotal * 0.17;
  const totalAmount = subtotal + deliveryFee + taxAmount;
  const isEmpty = items.length === 0;

  const isProcessing = isSubmitting || (processingStage !== 'idle' && processingStage !== 'complete');
  const isDone = processingStage === 'complete' || (orderResult && orderResult.payment_url && processingStage === 'redirecting');

  // Min datetime = now
  const minDatetime = new Date().toISOString().slice(0, 16);

  // Sizes
  const modalWidth = isMobile ? '100%' : isTablet ? '90%' : '880px';
  const modalHeight = isMobile ? '100%' : '90vh';
  const sidebarWidth = isMobile ? '100%' : '280px';

  return (
    <>
      {/* Spin keyframes */}
      <style>{`@keyframes spin { to { transform: rotate(360deg); } }`}</style>

      {/* Overlay */}
      <div
        style={{
          position: 'fixed',
          inset: 0,
          backgroundColor: 'rgba(0,0,0,0.55)',
          zIndex: 10000,
          display: 'flex',
          alignItems: isMobile ? 'flex-end' : 'center',
          justifyContent: 'center',
          padding: isMobile ? 0 : theme.spacing.md,
        }}
        onClick={(e) => {
          if (e.target === e.currentTarget && !isProcessing) onClose();
        }}
      >
        {/* Modal container */}
        <div
          style={{
            backgroundColor: theme.colors.cardBg,
            borderRadius: isMobile ? `${theme.borderRadius.xl} ${theme.borderRadius.xl} 0 0` : theme.borderRadius.xl,
            width: modalWidth,
            maxWidth: isMobile ? '100%' : '880px',
            height: modalHeight,
            maxHeight: isMobile ? '96vh' : '90vh',
            display: 'flex',
            flexDirection: 'column',
            overflow: 'hidden',
            boxShadow: '0 25px 60px rgba(0,0,0,0.3)',
            direction: direction,
            fontFamily: theme.fonts.primary,
            ...(isMobile && { position: 'fixed', bottom: 0, left: 0, right: 0, borderRadius: `${theme.borderRadius.xl} ${theme.borderRadius.xl} 0 0` }),
          }}
        >
          {/* ── Header ── */}
          <div
            style={{
              padding: `${theme.spacing.md} ${theme.spacing.lg}`,
              borderBottom: `1px solid ${theme.colors.border}`,
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'space-between',
              flexShrink: 0,
            }}
          >
            <h2
              style={{
                margin: 0,
                fontSize: isMobile ? '1.125rem' : '1.375rem',
                fontWeight: '700',
                color: theme.colors.text.primary,
              }}
            >
              {t('checkoutTitle')}
            </h2>
            {!isProcessing && (
              <button
                onClick={onClose}
                style={{
                  background: 'none',
                  border: 'none',
                  cursor: 'pointer',
                  padding: theme.spacing.xs,
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  borderRadius: theme.borderRadius.full,
                  color: theme.colors.text.muted,
                  transition: 'all 0.2s ease',
                }}
                onMouseEnter={(e) => {
                  e.currentTarget.style.backgroundColor = theme.colors.background;
                  e.currentTarget.style.color = theme.colors.text.primary;
                }}
                onMouseLeave={(e) => {
                  e.currentTarget.style.backgroundColor = 'transparent';
                  e.currentTarget.style.color = theme.colors.text.muted;
                }}
              >
                <XMarkIcon style={{ width: '22px', height: '22px' }} />
              </button>
            )}
          </div>

          {/* ── Body ── */}
          {isProcessing || isDone ? (
            <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <ProcessingState stage={processingStage} orderResult={orderResult} />
            </div>
          ) : (
            <div
              style={{
                flex: 1,
                display: 'flex',
                flexDirection: isMobile ? 'column' : 'row',
                overflow: 'hidden',
              }}
            >
              {/* ── Form column ── */}
              <div
                style={{
                  flex: 1,
                  overflowY: 'auto',
                  padding: isMobile ? theme.spacing.lg : theme.spacing.xl,
                  display: 'flex',
                  flexDirection: 'column',
                  gap: theme.spacing.xl,
                  scrollbarWidth: 'none',
                  msOverflowStyle: 'none',
                }}
              >
                {/* Section: Contact Details */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: theme.spacing.md }}>
                  <SectionHeading>{t('contactDetails')}</SectionHeading>

                  {/* Name row */}
                  <div
                    style={{
                      display: 'grid',
                      gridTemplateColumns: '1fr 1fr',
                      gap: theme.spacing.md,
                    }}
                  >
                    <Field label={t('firstName')} required error={errors.firstName}>
                      <input
                        type="text"
                        value={form.firstName}
                        onChange={(e) => handleChange('firstName', e.target.value)}
                        placeholder={t('enterFirstName')}
                        style={inputStyle(!!errors.firstName)}
                        onFocus={(e) => { e.target.style.borderColor = theme.colors.primary; }}
                        onBlur={(e) => { e.target.style.borderColor = errors.firstName ? theme.colors.error : theme.colors.border; }}
                      />
                    </Field>
                    <Field label={t('lastName')} required error={errors.lastName}>
                      <input
                        type="text"
                        value={form.lastName}
                        onChange={(e) => handleChange('lastName', e.target.value)}
                        placeholder={t('enterLastName')}
                        style={inputStyle(!!errors.lastName)}
                        onFocus={(e) => { e.target.style.borderColor = theme.colors.primary; }}
                        onBlur={(e) => { e.target.style.borderColor = errors.lastName ? theme.colors.error : theme.colors.border; }}
                      />
                    </Field>
                  </div>

                  {/* Phone */}
                  <Field
                    label={t('phone')}
                    required
                    error={errors.phone}
                    hint={t('phoneUsedForOrderUpdates')}
                  >
                    <input
                      type="tel"
                      value={form.phone}
                      onChange={(e) => handleChange('phone', e.target.value)}
                      placeholder="050-000-0000"
                      style={inputStyle(!!errors.phone)}
                      onFocus={(e) => { e.target.style.borderColor = theme.colors.primary; }}
                      onBlur={(e) => { e.target.style.borderColor = errors.phone ? theme.colors.error : theme.colors.border; }}
                    />
                  </Field>

                  {/* Email */}
                  <Field
                    label={`${t('email')} (${t('optional')})`}
                    error={errors.email}
                    hint={t('emailForReceipt')}
                  >
                    <input
                      type="email"
                      value={form.email}
                      onChange={(e) => handleChange('email', e.target.value)}
                      placeholder="email@example.com"
                      style={inputStyle(!!errors.email)}
                      onFocus={(e) => { e.target.style.borderColor = theme.colors.primary; }}
                      onBlur={(e) => { e.target.style.borderColor = errors.email ? theme.colors.error : theme.colors.border; }}
                    />
                  </Field>
                </div>

                {/* Section: Delivery Method */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: theme.spacing.md }}>
                  <SectionHeading>{t('deliveryMethod')}</SectionHeading>

                  <div style={{ display: 'flex', gap: theme.spacing.md }}>
                    <DeliveryCard
                      icon={<ShoppingBagIcon style={{ width: '28px', height: '28px' }} />}
                      label={t('pickup')}
                      selected={form.deliveryType === 'pickup'}
                      onClick={() => handleDeliveryTypeChange('pickup')}
                      isMobile={isMobile}
                    />
                    <DeliveryCard
                      icon={<TruckIcon style={{ width: '28px', height: '28px' }} />}
                      label={t('delivery')}
                      selected={form.deliveryType === 'delivery'}
                      onClick={() => handleDeliveryTypeChange('delivery')}
                      isMobile={isMobile}
                    />
                  </div>

                  {/* Delivery address (only for delivery) */}
                  {form.deliveryType === 'delivery' && (
                    <Field
                      label={t('deliveryAddress')}
                      required
                      error={errors.deliveryAddress || feeError}
                      hint={t('includeStreetCityApt')}
                    >
                      <textarea
                        value={form.deliveryAddress}
                        onChange={(e) => handleChange('deliveryAddress', e.target.value)}
                        placeholder={t('enterFullAddress')}
                        rows={3}
                        style={{
                          ...inputStyle(!!errors.deliveryAddress || !!feeError),
                          resize: 'vertical',
                          lineHeight: '1.5',
                        }}
                        onFocus={(e) => { e.target.style.borderColor = theme.colors.primary; }}
                        onBlur={(e) => { e.target.style.borderColor = (errors.deliveryAddress || feeError) ? theme.colors.error : theme.colors.border; }}
                      />
                      {/* Fee calculation status */}
                      {calculatingFee && (
                        <div style={{ display: 'flex', alignItems: 'center', gap: '6px', fontSize: '0.8125rem', color: theme.colors.text.muted }}>
                          <ArrowPathIcon style={{ width: '14px', height: '14px', animation: 'spin 0.8s linear infinite' }} />
                          {t('calculatingDeliveryFee')}
                        </div>
                      )}
                      {feeInfo && !feeError && !calculatingFee && (
                        <div
                          style={{
                            padding: `${theme.spacing.xs} ${theme.spacing.sm}`,
                            backgroundColor: 'rgba(16, 185, 129, 0.08)',
                            border: `1px solid rgba(16, 185, 129, 0.3)`,
                            borderRadius: theme.borderRadius.md,
                            fontSize: '0.8125rem',
                            color: '#065f46',
                          }}
                        >
                          {feeInfo.is_free_delivery
                            ? t('freeDelivery')
                            : `${t('deliveryFee')}: ₪${feeInfo.delivery_fee.toFixed(2)}`}
                        </div>
                      )}
                    </Field>
                  )}
                </div>

                {/* Section: Scheduled Time */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: theme.spacing.md }}>
                  <SectionHeading>{t('scheduledTime')}</SectionHeading>
                  <Field
                    label={form.deliveryType === 'delivery' ? t('deliveryTime') : t('pickupTime')}
                    required
                    error={errors.deliveryTime}
                    hint={form.deliveryType === 'delivery' ? t('selectPreferredDeliveryTime') : t('selectPreferredPickupTime')}
                  >
                    <input
                      type="datetime-local"
                      value={form.deliveryTime}
                      onChange={(e) => handleChange('deliveryTime', e.target.value)}
                      min={minDatetime}
                      style={{
                        ...inputStyle(!!errors.deliveryTime),
                        colorScheme: 'light',
                      }}
                      onFocus={(e) => { e.target.style.borderColor = theme.colors.primary; }}
                      onBlur={(e) => { e.target.style.borderColor = errors.deliveryTime ? theme.colors.error : theme.colors.border; }}
                    />
                  </Field>
                </div>

                {/* Section: Notes */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: theme.spacing.md }}>
                  <SectionHeading>{`${t('orderNotes')} (${t('optional')})`}</SectionHeading>
                  <textarea
                    value={form.notes}
                    onChange={(e) => handleChange('notes', e.target.value)}
                    placeholder={t('orderNotesPlaceholder')}
                    rows={3}
                    style={{
                      ...inputStyle(false),
                      resize: 'vertical',
                      lineHeight: '1.5',
                    }}
                    onFocus={(e) => { e.target.style.borderColor = theme.colors.primary; }}
                    onBlur={(e) => { e.target.style.borderColor = theme.colors.border; }}
                  />
                </div>

                {/* Mobile: cart summary inline */}
                {isMobile && (
                  <div style={{ display: 'flex', flexDirection: 'column', gap: theme.spacing.md }}>
                    <SectionHeading>{t('orderSummary')}</SectionHeading>
                    <div
                      style={{
                        backgroundColor: theme.colors.background,
                        borderRadius: theme.borderRadius.lg,
                        padding: theme.spacing.md,
                      }}
                    >
                      {isEmpty ? (
                        <p style={{ textAlign: 'center', color: theme.colors.text.muted, fontSize: '0.875rem', margin: 0 }}>
                          {t('emptyCart')}
                        </p>
                      ) : (
                        items.map((item, i) => <CartRow key={i} item={item} />)
                      )}
                      <PriceSummary subtotal={subtotal} deliveryFee={deliveryFee} taxAmount={taxAmount} totalAmount={totalAmount} />
                    </div>
                  </div>
                )}
              </div>

              {/* ── Cart sidebar (desktop/tablet only) ── */}
              {!isMobile && (
                <div
                  style={{
                    width: sidebarWidth,
                    flexShrink: 0,
                    borderInlineStart: `1px solid ${theme.colors.border}`,
                    display: 'flex',
                    flexDirection: 'column',
                    overflow: 'hidden',
                    backgroundColor: theme.colors.background,
                  }}
                >
                  {/* Cart header */}
                  <div
                    style={{
                      padding: `${theme.spacing.lg} ${theme.spacing.lg} ${theme.spacing.md}`,
                      flexShrink: 0,
                    }}
                  >
                    <SectionHeading>{t('orderSummary')}</SectionHeading>
                  </div>

                  {/* Cart items - scrollable */}
                  <div
                    style={{
                      flex: 1,
                      overflowY: 'auto',
                      padding: `0 ${theme.spacing.lg}`,
                      scrollbarWidth: 'none',
                    }}
                  >
                    {isEmpty ? (
                      <p style={{ textAlign: 'center', color: theme.colors.text.muted, fontSize: '0.875rem' }}>
                        {t('emptyCart')}
                      </p>
                    ) : (
                      items.map((item, i) => <CartRow key={i} item={item} />)
                    )}
                  </div>

                  {/* Price breakdown */}
                  <div
                    style={{
                      padding: theme.spacing.lg,
                      flexShrink: 0,
                      borderTop: `1px solid ${theme.colors.border}`,
                    }}
                  >
                    <PriceSummary subtotal={subtotal} deliveryFee={deliveryFee} taxAmount={taxAmount} totalAmount={totalAmount} />
                  </div>
                </div>
              )}
            </div>
          )}

          {/* ── Footer ── */}
          {!isProcessing && !isDone && (
            <div
              style={{
                padding: `${theme.spacing.md} ${theme.spacing.lg}`,
                borderTop: `1px solid ${theme.colors.border}`,
                flexShrink: 0,
                display: 'flex',
                flexDirection: 'column',
                gap: theme.spacing.sm,
                backgroundColor: theme.colors.cardBg,
              }}
            >
              {/* Error message */}
              {submitError && (
                <div
                  style={{
                    padding: `${theme.spacing.sm} ${theme.spacing.md}`,
                    backgroundColor: 'rgba(239,68,68,0.08)',
                    border: `1px solid rgba(239,68,68,0.3)`,
                    borderRadius: theme.borderRadius.md,
                    fontSize: '0.875rem',
                    color: theme.colors.error,
                  }}
                >
                  {submitError}
                </div>
              )}

              {/* Place Order button */}
              <button
                type="button"
                onClick={handleSubmit}
                disabled={isEmpty || isSubmitting}
                style={{
                  width: '100%',
                  padding: `${theme.spacing.md} ${theme.spacing.lg}`,
                  backgroundColor: isEmpty ? `${theme.colors.primary}55` : theme.colors.primary,
                  color: '#fff',
                  border: 'none',
                  borderRadius: theme.borderRadius.md,
                  fontSize: '1rem',
                  fontWeight: '700',
                  fontFamily: theme.fonts.primary,
                  cursor: isEmpty ? 'not-allowed' : 'pointer',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  gap: theme.spacing.sm,
                  transition: 'background-color 0.2s ease',
                  letterSpacing: '-0.01em',
                }}
                onMouseEnter={(e) => {
                  if (!isEmpty && !isSubmitting) e.currentTarget.style.backgroundColor = theme.colors.primaryHover;
                }}
                onMouseLeave={(e) => {
                  if (!isEmpty && !isSubmitting) e.currentTarget.style.backgroundColor = theme.colors.primary;
                }}
              >
                {isSubmitting ? (
                  <>
                    <div
                      style={{
                        width: '16px',
                        height: '16px',
                        borderRadius: '50%',
                        border: '2px solid rgba(255,255,255,0.4)',
                        borderTopColor: '#fff',
                        animation: 'spin 0.8s linear infinite',
                      }}
                    />
                    {t('loading')}
                  </>
                ) : (
                  <>
                    <span>{t('placeOrder')}</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                      <path d="M5 12h14M12 5l7 7-7 7" style={{ transform: direction === 'rtl' ? 'scaleX(-1)' : 'none', transformOrigin: 'center' }} />
                    </svg>
                  </>
                )}
              </button>

              {/* Terms notice */}
              <p
                style={{
                  fontSize: '0.75rem',
                  color: theme.colors.text.muted,
                  textAlign: 'center',
                  margin: 0,
                }}
              >
                {t('termsNotice')}
              </p>
            </div>
          )}

          {/* Done footer */}
          {isDone && processingStage === 'complete' && (
            <div
              style={{
                padding: `${theme.spacing.md} ${theme.spacing.lg}`,
                borderTop: `1px solid ${theme.colors.border}`,
                flexShrink: 0,
              }}
            >
              <button
                type="button"
                onClick={onClose}
                style={{
                  width: '100%',
                  padding: `${theme.spacing.sm} ${theme.spacing.lg}`,
                  backgroundColor: theme.colors.background,
                  color: theme.colors.text.primary,
                  border: `1.5px solid ${theme.colors.border}`,
                  borderRadius: theme.borderRadius.md,
                  fontSize: '0.9375rem',
                  fontWeight: '600',
                  fontFamily: theme.fonts.primary,
                  cursor: 'pointer',
                }}
              >
                {t('continueShopping')}
              </button>
            </div>
          )}
        </div>
      </div>
    </>
  );
}

// ─── Price summary sub-component ──────────────────────────────────────────────
function PriceSummary({ subtotal, deliveryFee, taxAmount, totalAmount }) {
  const rows = [
    { label: t('subtotal'), value: `₪${subtotal.toFixed(2)}` },
    { label: t('deliveryFee'), value: deliveryFee > 0 ? `₪${deliveryFee.toFixed(2)}` : t('free') },
    { label: t('tax'), value: `₪${taxAmount.toFixed(2)}` },
  ];

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '6px', marginTop: theme.spacing.sm }}>
      {rows.map(({ label, value }) => (
        <div
          key={label}
          style={{
            display: 'flex',
            justifyContent: 'space-between',
            fontSize: '0.875rem',
            color: theme.colors.text.secondary,
          }}
        >
          <span>{label}</span>
          <span>{value}</span>
        </div>
      ))}
      <div
        style={{
          height: '1px',
          backgroundColor: theme.colors.border,
          margin: `${theme.spacing.xs} 0`,
        }}
      />
      <div
        style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'baseline',
        }}
      >
        <span style={{ fontSize: '1rem', fontWeight: '700', color: theme.colors.text.primary }}>
          {t('total')}
        </span>
        <span style={{ fontSize: '1.25rem', fontWeight: '700', color: theme.colors.primary, letterSpacing: '-0.02em' }}>
          ₪{totalAmount.toFixed(2)}
        </span>
      </div>
    </div>
  );
}
