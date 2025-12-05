import React from 'react';
import theme from '../../config/theme';

/**
 * CartPanel - Fixed left sidebar showing cart contents and checkout
 * Displays cart items, price breakdown, and checkout button
 */
export default function CartPanel({ cart, onCheckout }) {
  const isEmpty = !cart || !cart.items || cart.items.length === 0;
  const subtotal = cart?.subtotal || 0;
  const deliveryFee = cart?.deliveryFee || 25.0;
  const tax = cart?.tax || 0;
  const total = subtotal + deliveryFee + tax;

  return (
    <div
      style={{
        backgroundColor: theme.colors.cardBg,
        borderRadius: theme.borderRadius.xl,
        padding: theme.spacing.lg,
        boxShadow: theme.shadows.card,
        display: 'flex',
        flexDirection: 'column',
        gap: theme.spacing.lg,
        height: '100%',
        overflow: 'hidden',
      }}
    >
      {/* Empty Cart State */}
      {isEmpty && (
        <>
          <div
            style={{
              display: 'flex',
              flexDirection: 'column',
              alignItems: 'center',
              padding: theme.spacing.xl,
            }}
          >
            <div
              style={{
                width: '80px',
                height: '80px',
                backgroundColor: theme.colors.background,
                borderRadius: theme.borderRadius.md,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                marginBottom: theme.spacing.md,
              }}
            >
              <svg
                width="40"
                height="40"
                viewBox="0 0 24 24"
                fill="none"
                stroke={theme.colors.text.muted}
                strokeWidth="2"
              >
                <path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
            </div>
            <p
              style={{
                fontSize: '1rem',
                fontWeight: '500',
                color: theme.colors.text.primary,
                textAlign: 'center',
              }}
            >
              העגלה שלכם ריקה
            </p>
          </div>

          {/* Coupon Section */}
          <div
            style={{
              borderTop: `1px solid ${theme.colors.border}`,
              paddingTop: theme.spacing.md,
            }}
          >
            <div
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: theme.spacing.sm,
                marginBottom: theme.spacing.sm,
              }}
            >
              <svg
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke={theme.colors.text.secondary}
                strokeWidth="2"
              >
                <path d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
              </svg>
              <span
                style={{
                  fontSize: '0.875rem',
                  color: theme.colors.text.secondary,
                  fontWeight: '500',
                }}
              >
                יש לכם קופון
              </span>
            </div>
          </div>
        </>
      )}

      {/* Cart Items - Show when not empty */}
      {!isEmpty && (
        <div style={{ flex: 1, overflowY: 'auto', maxHeight: '400px' }}>
          {cart.items.map((item, index) => (
            <div
              key={index}
              style={{
                padding: `${theme.spacing.sm} 0`,
                borderBottom: `1px solid ${theme.colors.border}`,
              }}
            >
              <div
                style={{
                  display: 'flex',
                  justifyContent: 'space-between',
                  alignItems: 'center',
                }}
              >
                <span style={{ fontWeight: '500' }}>{item.name}</span>
                <span>₪{item.price.toFixed(2)}</span>
              </div>
              <div style={{ fontSize: '0.875rem', color: theme.colors.text.muted }}>
                כמות: {item.quantity}
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Price Breakdown */}
      <div
        style={{
          borderTop: `1px solid ${theme.colors.border}`,
          paddingTop: theme.spacing.md,
          display: 'flex',
          flexDirection: 'column',
          gap: theme.spacing.sm,
        }}
      >
        <div style={{ display: 'flex', justifyContent: 'space-between' }}>
          <span style={{ color: theme.colors.text.secondary }}>דמי משלוח</span>
          <span>₪{deliveryFee.toFixed(2)}</span>
        </div>
        <div style={{ display: 'flex', justifyContent: 'space-between' }}>
          <span style={{ color: theme.colors.text.secondary }}>סכום כולל</span>
          <span>₪{subtotal.toFixed(2)}</span>
        </div>
        <div style={{ display: 'flex', justifyContent: 'space-between' }}>
          <span style={{ color: theme.colors.text.secondary }}>מע״מ</span>
          <span>₪{tax.toFixed(2)}</span>
        </div>
        <div
          style={{
            display: 'flex',
            justifyContent: 'space-between',
            paddingTop: theme.spacing.sm,
            borderTop: `1px solid ${theme.colors.border}`,
            fontSize: '1.125rem',
            fontWeight: 'bold',
          }}
        >
          <span>סך הכל</span>
          <span>₪{total.toFixed(2)}</span>
        </div>
      </div>

      {/* Checkout Button */}
      <button
        onClick={onCheckout}
        disabled={isEmpty}
        style={{
          width: '100%',
          padding: `${theme.spacing.md} ${theme.spacing.lg}`,
          backgroundColor: isEmpty ? theme.colors.text.muted : theme.colors.primary,
          color: theme.colors.text.white,
          border: 'none',
          borderRadius: theme.borderRadius.md,
          fontSize: '1rem',
          fontWeight: 'bold',
          cursor: isEmpty ? 'not-allowed' : 'pointer',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          gap: theme.spacing.sm,
          transition: 'background-color 0.2s ease',
        }}
        onMouseEnter={(e) => {
          if (!isEmpty) {
            e.currentTarget.style.backgroundColor = theme.colors.primaryHover;
          }
        }}
        onMouseLeave={(e) => {
          if (!isEmpty) {
            e.currentTarget.style.backgroundColor = theme.colors.primary;
          }
        }}
      >
        <span>המשך עכשיו</span>
        <svg
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
          style={{ transform: 'scaleX(-1)' }}
        >
          <path d="M9 5l7 7-7 7" />
        </svg>
      </button>
    </div>
  );
}
