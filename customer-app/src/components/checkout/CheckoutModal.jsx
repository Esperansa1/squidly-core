import React, { useState, useEffect, useCallback } from 'react';
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
  PencilSquareIcon,
} from '@heroicons/react/24/outline';

// ─── Constants ────────────────────────────────────────────────────────────────
const PHONE_REGEX = /^(\+972|0)[2-9]\d{7,8}$/;
const EMAIL_REGEX  = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const CUSTOMER_STORAGE_KEY = 'squidly_customer_info';

// ─── Helpers ──────────────────────────────────────────────────────────────────
function formatAddress(addr) {
  if (!addr) return '';
  if (typeof addr === 'string') return addr;
  const parts = [addr.street, addr.houseNumber, addr.city].filter(Boolean);
  return parts.join(' ');
}

function formatDatetime(value) {
  if (!value) return '';
  try {
    return new Date(value).toLocaleString(undefined, {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch {
    return value;
  }
}

// ─── Sub-components ───────────────────────────────────────────────────────────

function SectionHeading({ children }) {
  return (
    <p
      style={{
        fontSize: '0.75rem',
        fontWeight: '700',
        textTransform: 'uppercase',
        letterSpacing: '0.08em',
        color: theme.colors.text.muted,
        margin: `0 0 ${theme.spacing.sm}`,
      }}
    >
      {children}
    </p>
  );
}

function Field({ label, required, error, hint, children }) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '4px' }}>
      <label
        style={{
          fontSize: '0.875rem',
          fontWeight: '600',
          color: theme.colors.text.primary,
          display: 'block',
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

function inputStyle(hasError = false) {
  return {
    width: '100%',
    padding: `${theme.spacing.sm} ${theme.spacing.md}`,
    fontSize: '0.9375rem',
    fontFamily: theme.fonts.primary,
    color: theme.colors.text.primary,
    backgroundColor: theme.colors.cardBg,
    border: `1.5px solid ${hasError ? theme.colors.error : theme.colors.border}`,
    borderRadius: theme.borderRadius.md,
    outline: 'none',
    boxSizing: 'border-box',
    transition: 'border-color 0.15s ease',
  };
}

// Order context summary card (read-only)
function OrderContextCard({ selectedBranch, orderType, deliveryAddress, pickupTime, onEdit, isMobile }) {
  const isDelivery = orderType === 'delivery';
  const icon = isDelivery
    ? <TruckIcon style={{ width: '18px', height: '18px', flexShrink: 0 }} />
    : <ShoppingBagIcon style={{ width: '18px', height: '18px', flexShrink: 0 }} />;

  const methodLabel = isDelivery ? t('delivery') : t('pickup');
  const detail = isDelivery
    ? formatAddress(deliveryAddress)
    : pickupTime
      ? formatDatetime(pickupTime)
      : null;

  return (
    <div
      style={{
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        gap: theme.spacing.md,
        padding: `${theme.spacing.sm} ${theme.spacing.md}`,
        backgroundColor: theme.colors.background,
        borderRadius: theme.borderRadius.lg,
        border: `1px solid ${theme.colors.border}`,
      }}
    >
      <div style={{ display: 'flex', alignItems: 'flex-start', gap: theme.spacing.sm, flex: 1, minWidth: 0 }}>
        <div style={{ color: theme.colors.primary, marginTop: '1px' }}>{icon}</div>
        <div style={{ minWidth: 0 }}>
          <p style={{ margin: 0, fontSize: '0.9375rem', fontWeight: '600', color: theme.colors.text.primary }}>
            {methodLabel}
            {selectedBranch?.name && (
              <span style={{ fontWeight: '400', color: theme.colors.text.secondary, marginInlineStart: '6px' }}>
                — {selectedBranch.name}
              </span>
            )}
          </p>
          {detail && (
            <p
              style={{
                margin: `2px 0 0`,
                fontSize: '0.8125rem',
                color: theme.colors.text.secondary,
                whiteSpace: 'nowrap',
                overflow: 'hidden',
                textOverflow: 'ellipsis',
              }}
            >
              {detail}
            </p>
          )}
        </div>
      </div>
      <button
        type="button"
        onClick={onEdit}
        style={{
          display: 'flex',
          alignItems: 'center',
          gap: '4px',
          padding: `4px ${theme.spacing.sm}`,
          fontSize: '0.8125rem',
          fontWeight: '500',
          color: theme.colors.text.secondary,
          backgroundColor: 'transparent',
          border: `1px solid ${theme.colors.border}`,
          borderRadius: theme.borderRadius.md,
          cursor: 'pointer',
          flexShrink: 0,
          transition: 'all 0.15s ease',
          whiteSpace: 'nowrap',
        }}
        onMouseEnter={(e) => {
          e.currentTarget.style.borderColor = theme.colors.primary;
          e.currentTarget.style.color = theme.colors.primary;
        }}
        onMouseLeave={(e) => {
          e.currentTarget.style.borderColor = theme.colors.border;
          e.currentTarget.style.color = theme.colors.text.secondary;
        }}
      >
        <PencilSquareIcon style={{ width: '14px', height: '14px' }} />
        {t('edit')}
      </button>
    </div>
  );
}

// Cart item row
function CartRow({ item }) {
  const unitPrice = item.final_price || item.unit_price || 0;
  const total = unitPrice * (item.quantity || 1);
  return (
    <div
      style={{
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'flex-start',
        gap: theme.spacing.sm,
        padding: `10px 0`,
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
            display: 'flex',
            alignItems: 'center',
            gap: '6px',
          }}
        >
          {item.quantity > 1 && (
            <span
              style={{
                display: 'inline-flex',
                alignItems: 'center',
                justifyContent: 'center',
                minWidth: '22px',
                height: '22px',
                padding: '0 5px',
                backgroundColor: theme.colors.primary,
                color: '#fff',
                borderRadius: theme.borderRadius.full,
                fontSize: '0.75rem',
                fontWeight: '700',
                flexShrink: 0,
              }}
            >
              {item.quantity}
            </span>
          )}
          <span
            style={{
              whiteSpace: 'nowrap',
              overflow: 'hidden',
              textOverflow: 'ellipsis',
            }}
          >
            {item.product_name || item.name}
          </span>
        </p>
        {item.customizations && Object.keys(item.customizations).length > 0 && (
          <p
            style={{
              fontSize: '0.75rem',
              color: theme.colors.text.muted,
              margin: `3px 0 0`,
              paddingInlineStart: item.quantity > 1 ? '28px' : '0',
            }}
          >
            {Object.values(item.customizations)
              .flat()
              .map((c) => c.name)
              .filter(Boolean)
              .join(' · ')}
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
          paddingTop: '1px',
        }}
      >
        ₪{total.toFixed(2)}
      </span>
    </div>
  );
}

function PriceSummary({ subtotal, deliveryFee, taxAmount, totalAmount }) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '8px', marginTop: theme.spacing.sm }}>
      {[
        [t('subtotal'), `₪${subtotal.toFixed(2)}`],
        [t('deliveryFee'), deliveryFee > 0 ? `₪${deliveryFee.toFixed(2)}` : t('free')],
        [t('tax'), `₪${taxAmount.toFixed(2)}`],
      ].map(([label, value]) => (
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
      <div style={{ height: '1px', backgroundColor: theme.colors.divider, margin: `4px 0` }} />
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline' }}>
        <span style={{ fontSize: '1rem', fontWeight: '700', color: theme.colors.text.primary }}>{t('total')}</span>
        <span style={{ fontSize: '1.25rem', fontWeight: '700', color: theme.colors.primary, letterSpacing: '-0.02em' }}>
          ₪{totalAmount.toFixed(2)}
        </span>
      </div>
    </div>
  );
}

function ProcessingState({ stage, orderResult }) {
  if (stage === 'complete' && orderResult && !orderResult.payment_url) {
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
        <CheckCircleIcon style={{ width: '72px', height: '72px', color: theme.colors.success }} />
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
            padding: theme.spacing.lg,
            width: '100%',
            maxWidth: '320px',
          }}
        >
          <p style={{ fontSize: '0.875rem', color: theme.colors.text.secondary, margin: `0 0 6px` }}>
            {t('orderNumber')}: <strong>#{orderResult.order_id}</strong>
          </p>
          <p style={{ fontSize: '0.875rem', color: theme.colors.text.secondary, margin: `0 0 6px` }}>
            {t('total')}: <strong>₪{orderResult.total_price?.toFixed(2)}</strong>
          </p>
          <p style={{ fontSize: '0.8125rem', color: theme.colors.text.muted, margin: 0, fontFamily: 'monospace' }}>
            {orderResult.tracking_token}
          </p>
        </div>
      </div>
    );
  }

  const messages = {
    creating_customer: t('creatingCustomer'),
    creating_order: t('creatingOrder'),
    redirecting: t('redirectingToPayment'),
  };

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
          width: '52px',
          height: '52px',
          borderRadius: '50%',
          border: `4px solid ${theme.colors.primary}`,
          borderTopColor: 'transparent',
          animation: 'checkoutSpin 0.8s linear infinite',
        }}
      />
      <div>
        <p style={{ fontSize: '1.125rem', fontWeight: '600', color: theme.colors.text.primary, margin: `0 0 6px` }}>
          {messages[stage] || t('loading')}
        </p>
        {stage === 'redirecting' && (
          <p style={{ fontSize: '0.875rem', color: theme.colors.text.muted, margin: 0 }}>
            {t('doNotCloseWindow')}
          </p>
        )}
      </div>
    </div>
  );
}

// ─── Main component ────────────────────────────────────────────────────────────
export default function CheckoutModal({ isOpen, onClose, onEditOrderDetails }) {
  const isMobile = useIsMobile();
  const isTablet = useIsTablet();
  const { cart, getTotal } = useCart();
  const {
    selectedBranch,
    orderType,
    deliveryAddress,
    pickupTime,
  } = useBranch();

  const [direction, setDirection] = useState('rtl');
  useEffect(() => {
    if (isOpen) {
      const lang = getCurrentLanguage();
      setDirection(lang === 'he' || lang === 'ar' ? 'rtl' : 'ltr');
    }
  }, [isOpen]);

  // Form state — only what's still needed
  const [form, setForm] = useState({ firstName: '', lastName: '', phone: '', email: '', notes: '' });
  const [errors, setErrors] = useState({});

  // Time override — editable even if pre-filled
  const [scheduledTime, setScheduledTime] = useState('');

  // Delivery fee
  const [deliveryFee, setDeliveryFee] = useState(0);
  const [feeLoading, setFeeLoading] = useState(false);

  // Checkout state
  const [processingStage, setProcessingStage] = useState('idle');
  const [orderResult, setOrderResult] = useState(null);
  const [submitError, setSubmitError] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Reset and pre-fill on open
  useEffect(() => {
    if (!isOpen) return;

    // Restore saved customer info
    const saved = (() => {
      try { return JSON.parse(sessionStorage.getItem(CUSTOMER_STORAGE_KEY) || '{}'); }
      catch { return {}; }
    })();

    setForm({
      firstName: saved.firstName || '',
      lastName: saved.lastName || '',
      phone: saved.phone || '',
      email: saved.email || '',
      notes: '',
    });

    // Pre-fill time from BranchContext
    setScheduledTime(pickupTime || '');

    setErrors({});
    setProcessingStage('idle');
    setOrderResult(null);
    setSubmitError(null);
    setIsSubmitting(false);
  }, [isOpen, pickupTime]);

  // Calculate delivery fee when context address is set
  useEffect(() => {
    if (!isOpen || orderType !== 'delivery') {
      setDeliveryFee(0);
      return;
    }
    const addr = formatAddress(deliveryAddress);
    if (!addr) return;

    let cancelled = false;
    setFeeLoading(true);

    publicApi.getDeliveryFee(selectedBranch?.id, addr, getTotal())
      .then((result) => {
        if (cancelled) return;
        setDeliveryFee(result.is_deliverable ? (result.delivery_fee || 0) : 0);
      })
      .catch(() => {
        if (!cancelled) setDeliveryFee(0);
      })
      .finally(() => {
        if (!cancelled) setFeeLoading(false);
      });

    return () => { cancelled = true; };
  }, [isOpen, orderType, deliveryAddress, selectedBranch?.id]);

  const handleChange = useCallback((field, value) => {
    setForm((prev) => ({ ...prev, [field]: value }));
    if (errors[field]) setErrors((prev) => { const n = { ...prev }; delete n[field]; return n; });
  }, [errors]);

  const validate = () => {
    const next = {};
    if (!form.firstName.trim()) next.firstName = t('requiredField');
    if (!form.lastName.trim()) next.lastName = t('requiredField');
    if (!form.phone.trim()) {
      next.phone = t('requiredField');
    } else if (!PHONE_REGEX.test(form.phone.replace(/[-\s]/g, ''))) {
      next.phone = t('invalidPhone');
    }
    if (form.email && !EMAIL_REGEX.test(form.email)) next.email = t('invalidEmail');
    if (!scheduledTime) next.scheduledTime = t('requiredField');
    return next;
  };

  const handleSubmit = async () => {
    const errs = validate();
    if (Object.keys(errs).length > 0) { setErrors(errs); return; }

    // Persist customer info for next time
    sessionStorage.setItem(CUSTOMER_STORAGE_KEY, JSON.stringify({
      firstName: form.firstName.trim(),
      lastName: form.lastName.trim(),
      phone: form.phone.replace(/[-\s]/g, '').trim(),
      email: form.email?.trim() || '',
    }));

    try {
      setIsSubmitting(true);
      setSubmitError(null);

      setProcessingStage('creating_customer');
      const customerResponse = await publicApi.createGuestCustomer({
        first_name: form.firstName.trim(),
        last_name: form.lastName.trim(),
        phone: form.phone.replace(/[-\s]/g, '').trim(),
        email: form.email?.trim() || null,
      });

      setProcessingStage('creating_order');
      const formattedAddress = formatAddress(deliveryAddress);
      const orderResponse = await publicApi.checkoutCart(cart.token, {
        customer_id: customerResponse.customer_id,
        delivery_type: orderType || 'pickup',
        delivery_address: orderType === 'delivery' ? formattedAddress : '',
        delivery_time: scheduledTime,
        payment_method: 'woocommerce',
        delivery_fee: deliveryFee,
        notes: form.notes.trim(),
      });

      setOrderResult(orderResponse);

      if (orderResponse.payment_url) {
        setProcessingStage('redirecting');
        sessionStorage.setItem('squidly_tracking_token', orderResponse.tracking_token);
        sessionStorage.setItem('squidly_order_id', orderResponse.order_id.toString());
        setTimeout(() => { window.location.href = orderResponse.payment_url; }, 1500);
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

  const items = cart?.items || [];
  const subtotal = getTotal();
  const taxAmount = subtotal * 0.17;
  const totalAmount = subtotal + deliveryFee + taxAmount;
  const isEmpty = items.length === 0;

  const isProcessing = isSubmitting || (processingStage !== 'idle' && processingStage !== 'complete');
  const isDone = processingStage === 'complete' || (orderResult?.payment_url && processingStage === 'redirecting');

  const sidebarWidth = '280px';

  const inputFocus = (e) => { e.target.style.borderColor = theme.colors.primary; };
  const inputBlur = (hasErr) => (e) => {
    e.target.style.borderColor = hasErr ? theme.colors.error : theme.colors.border;
  };

  return (
    <>
      <style>{`@keyframes checkoutSpin { to { transform: rotate(360deg); } }`}</style>

      {/* Backdrop */}
      <div
        style={{
          position: 'fixed',
          inset: 0,
          backgroundColor: 'rgba(0,0,0,0.5)',
          zIndex: 10000,
          display: 'flex',
          alignItems: isMobile ? 'flex-end' : 'center',
          justifyContent: 'center',
          padding: isMobile ? 0 : theme.spacing.md,
        }}
        onClick={(e) => { if (e.target === e.currentTarget && !isProcessing) onClose(); }}
      >
        {/* Modal */}
        <div
          style={{
            backgroundColor: theme.colors.cardBg,
            borderRadius: isMobile
              ? `${theme.borderRadius.xl} ${theme.borderRadius.xl} 0 0`
              : theme.borderRadius.xl,
            width: isMobile ? '100%' : isTablet ? '92%' : '820px',
            maxWidth: isMobile ? '100%' : '820px',
            height: isMobile ? '96vh' : '88vh',
            maxHeight: isMobile ? '96vh' : '88vh',
            display: 'flex',
            flexDirection: 'column',
            overflow: 'hidden',
            boxShadow: '0 24px 56px rgba(0,0,0,0.28)',
            direction: direction,
            fontFamily: theme.fonts.primary,
            ...(isMobile && { position: 'fixed', bottom: 0, left: 0, right: 0 }),
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
                fontSize: isMobile ? '1.0625rem' : '1.25rem',
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
                  padding: '6px',
                  display: 'flex',
                  alignItems: 'center',
                  borderRadius: theme.borderRadius.full,
                  color: theme.colors.text.muted,
                  transition: 'all 0.15s ease',
                }}
                onMouseEnter={(e) => { e.currentTarget.style.backgroundColor = theme.colors.background; e.currentTarget.style.color = theme.colors.text.primary; }}
                onMouseLeave={(e) => { e.currentTarget.style.backgroundColor = 'transparent'; e.currentTarget.style.color = theme.colors.text.muted; }}
              >
                <XMarkIcon style={{ width: '20px', height: '20px' }} />
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
              {/* ── Form ── */}
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
                {/* Order context: method + branch + address/time — read-only */}
                <OrderContextCard
                  selectedBranch={selectedBranch}
                  orderType={orderType}
                  deliveryAddress={deliveryAddress}
                  pickupTime={pickupTime}
                  onEdit={() => { onClose(); if (onEditOrderDetails) onEditOrderDetails(); }}
                  isMobile={isMobile}
                />

                {/* Contact Details */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: theme.spacing.md }}>
                  <SectionHeading>{t('contactDetails')}</SectionHeading>

                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: theme.spacing.md }}>
                    <Field label={t('firstName')} required error={errors.firstName}>
                      <input
                        type="text"
                        value={form.firstName}
                        onChange={(e) => handleChange('firstName', e.target.value)}
                        placeholder={t('enterFirstName')}
                        style={inputStyle(!!errors.firstName)}
                        onFocus={inputFocus}
                        onBlur={inputBlur(!!errors.firstName)}
                      />
                    </Field>
                    <Field label={t('lastName')} required error={errors.lastName}>
                      <input
                        type="text"
                        value={form.lastName}
                        onChange={(e) => handleChange('lastName', e.target.value)}
                        placeholder={t('enterLastName')}
                        style={inputStyle(!!errors.lastName)}
                        onFocus={inputFocus}
                        onBlur={inputBlur(!!errors.lastName)}
                      />
                    </Field>
                  </div>

                  <Field label={t('phone')} required error={errors.phone} hint={t('phoneUsedForOrderUpdates')}>
                    <input
                      type="tel"
                      value={form.phone}
                      onChange={(e) => handleChange('phone', e.target.value)}
                      placeholder="050-000-0000"
                      style={inputStyle(!!errors.phone)}
                      onFocus={inputFocus}
                      onBlur={inputBlur(!!errors.phone)}
                    />
                  </Field>

                  <Field label={`${t('email')} (${t('optional')})`} error={errors.email} hint={t('emailForReceipt')}>
                    <input
                      type="email"
                      value={form.email}
                      onChange={(e) => handleChange('email', e.target.value)}
                      placeholder="email@example.com"
                      style={inputStyle(!!errors.email)}
                      onFocus={inputFocus}
                      onBlur={inputBlur(!!errors.email)}
                    />
                  </Field>
                </div>

                {/* Scheduled Time — pre-filled, editable */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: theme.spacing.md }}>
                  <SectionHeading>{t('scheduledTime')}</SectionHeading>
                  <Field
                    label={orderType === 'delivery' ? t('deliveryTime') : t('pickupTime')}
                    required
                    error={errors.scheduledTime}
                    hint={orderType === 'delivery' ? t('selectPreferredDeliveryTime') : t('selectPreferredPickupTime')}
                  >
                    <input
                      type="datetime-local"
                      value={scheduledTime}
                      onChange={(e) => {
                        setScheduledTime(e.target.value);
                        if (errors.scheduledTime) setErrors((p) => { const n = { ...p }; delete n.scheduledTime; return n; });
                      }}
                      min={new Date().toISOString().slice(0, 16)}
                      style={{ ...inputStyle(!!errors.scheduledTime), colorScheme: 'light' }}
                      onFocus={inputFocus}
                      onBlur={inputBlur(!!errors.scheduledTime)}
                    />
                  </Field>
                </div>

                {/* Notes */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: theme.spacing.sm }}>
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
                    onFocus={inputFocus}
                    onBlur={(e) => { e.target.style.borderColor = theme.colors.border; }}
                  />
                </div>

                {/* Mobile: cart summary */}
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
                      <PriceSummary
                        subtotal={subtotal}
                        deliveryFee={deliveryFee}
                        taxAmount={taxAmount}
                        totalAmount={totalAmount}
                      />
                    </div>
                  </div>
                )}
              </div>

              {/* ── Cart sidebar (desktop / tablet) ── */}
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
                  <div style={{ padding: `${theme.spacing.lg} ${theme.spacing.lg} ${theme.spacing.sm}`, flexShrink: 0 }}>
                    <SectionHeading>{t('orderSummary')}</SectionHeading>
                  </div>

                  {/* Items */}
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
                      backgroundColor: theme.colors.cardBg,
                    }}
                  >
                    {feeLoading && (
                      <div
                        style={{
                          display: 'flex',
                          alignItems: 'center',
                          gap: '6px',
                          fontSize: '0.75rem',
                          color: theme.colors.text.muted,
                          marginBottom: theme.spacing.xs,
                        }}
                      >
                        <ArrowPathIcon style={{ width: '12px', height: '12px', animation: 'checkoutSpin 0.8s linear infinite' }} />
                        {t('calculatingDeliveryFee')}
                      </div>
                    )}
                    <PriceSummary
                      subtotal={subtotal}
                      deliveryFee={deliveryFee}
                      taxAmount={taxAmount}
                      totalAmount={totalAmount}
                    />
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
                backgroundColor: theme.colors.cardBg,
                display: 'flex',
                flexDirection: 'column',
                gap: theme.spacing.sm,
              }}
            >
              {submitError && (
                <div
                  style={{
                    padding: `${theme.spacing.sm} ${theme.spacing.md}`,
                    backgroundColor: 'rgba(239,68,68,0.07)',
                    border: `1px solid rgba(239,68,68,0.25)`,
                    borderRadius: theme.borderRadius.md,
                    fontSize: '0.875rem',
                    color: theme.colors.error,
                  }}
                >
                  {submitError}
                </div>
              )}
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
                onMouseEnter={(e) => { if (!isEmpty && !isSubmitting) e.currentTarget.style.backgroundColor = theme.colors.primaryHover; }}
                onMouseLeave={(e) => { if (!isEmpty && !isSubmitting) e.currentTarget.style.backgroundColor = theme.colors.primary; }}
              >
                {isSubmitting ? (
                  <>
                    <div
                      style={{
                        width: '16px', height: '16px',
                        borderRadius: '50%',
                        border: '2px solid rgba(255,255,255,0.4)',
                        borderTopColor: '#fff',
                        animation: 'checkoutSpin 0.8s linear infinite',
                      }}
                    />
                    {t('loading')}
                  </>
                ) : (
                  <>
                    <span>{t('placeOrder')}</span>
                    <svg
                      width="16" height="16" viewBox="0 0 24 24"
                      fill="none" stroke="currentColor" strokeWidth="2.5"
                      strokeLinecap="round" strokeLinejoin="round"
                      style={{ transform: direction === 'rtl' ? 'scaleX(-1)' : 'none' }}
                    >
                      <path d="M5 12h14M12 5l7 7-7 7" />
                    </svg>
                  </>
                )}
              </button>
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
            <div style={{ padding: `${theme.spacing.md} ${theme.spacing.lg}`, borderTop: `1px solid ${theme.colors.border}`, flexShrink: 0 }}>
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
