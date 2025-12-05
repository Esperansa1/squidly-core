import React from 'react';
import theme from '../../config/theme';

/**
 * CartPanel - Order summary section (leftmost panel)
 * Shows order summary header, cart items, price details, and order button
 */
export default function CartPanel({ cart, onCheckout, onClearCart }) {
  const isEmpty = !cart || !cart.items || cart.items.length === 0;
  const subtotal = cart?.subtotal || 0;
  const deliveryFee = cart?.deliveryFee || 25.0;
  const total = subtotal + deliveryFee;

  const handleClearCart = () => {
    if (onClearCart && !isEmpty) {
      onClearCart();
    }
  };

  return (
    <div
      style={{
        display: 'flex',
        flexDirection: 'column',
        height: '100%',
        gap: theme.spacing.md,
        overflow: 'hidden',
      }}
    >
      {/* Header: סיכום ההזמנה + Cancel Button */}
      <div
        style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          padding: theme.spacing.lg,
          backgroundColor: theme.colors.cardBg,
          borderRadius: theme.borderRadius.xl,
          boxShadow: theme.shadows.card,
        }}
      >
        <h2
          style={{
            fontSize: '1.25rem',
            fontWeight: 'bold',
            color: theme.colors.text.primary,
            margin: 0,
          }}
        >
          סיכום ההזמנה
        </h2>
        <button
          onClick={handleClearCart}
          disabled={isEmpty}
          style={{
            display: 'flex',
            alignItems: 'center',
            gap: theme.spacing.xs,
            padding: `${theme.spacing.xs} ${theme.spacing.sm}`,
            backgroundColor: isEmpty ? theme.colors.background : '#9CA3AF',
            color: isEmpty ? theme.colors.text.muted : theme.colors.text.white,
            border: 'none',
            borderRadius: theme.borderRadius.md,
            fontSize: '0.875rem',
            cursor: isEmpty ? 'not-allowed' : 'pointer',
            transition: 'background-color 0.2s ease',
          }}
          onMouseEnter={(e) => {
            if (!isEmpty) {
              e.currentTarget.style.backgroundColor = '#6B7280';
            }
          }}
          onMouseLeave={(e) => {
            if (!isEmpty) {
              e.currentTarget.style.backgroundColor = '#9CA3AF';
            }
          }}
        >
          {/* Trash Icon */}
          <svg
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
          >
            <polyline points="3 6 5 6 21 6" />
            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
            <line x1="10" y1="11" x2="10" y2="17" />
            <line x1="14" y1="11" x2="14" y2="17" />
          </svg>
          <span>ביטול הזמנה</span>
        </button>
      </div>

      {/* Cart Items Area - Takes all available vertical space */}
      <div
        style={{
          flex: '1 1 0',
          overflowY: 'auto',
          padding: theme.spacing.lg,
          backgroundColor: theme.colors.cardBg,
          borderRadius: theme.borderRadius.xl,
          boxShadow: theme.shadows.card,
        }}
      >
        {isEmpty ? (
          // Empty cart placeholder
          <div
            style={{
              display: 'flex',
              flexDirection: 'column',
              alignItems: 'center',
              justifyContent: 'center',
              height: '100%',
            }}
          >
            <div
              style={{
                width: '80px',
                height: '80px',
                backgroundColor: theme.colors.background,
                borderRadius: theme.borderRadius.full,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                marginBottom: theme.spacing.md,
              }}
            >
              {/* Placeholder for logo - will be replaced */}
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
                color: theme.colors.text.secondary,
                textAlign: 'center',
                margin: 0,
              }}
            >
              העגלה שלך ריקה
            </p>
          </div>
        ) : (
          // Cart items list
          <div style={{ display: 'flex', flexDirection: 'column', gap: theme.spacing.sm }}>
            {cart.items.map((item, index) => (
              <div
                key={index}
                style={{
                  padding: theme.spacing.md,
                  backgroundColor: theme.colors.background,
                  borderRadius: theme.borderRadius.md,
                  display: 'flex',
                  justifyContent: 'space-between',
                  alignItems: 'center',
                }}
              >
                <div style={{ flex: 1 }}>
                  <div style={{ fontWeight: '500', color: theme.colors.text.primary }}>
                    {item.name}
                  </div>
                  <div style={{ fontSize: '0.875rem', color: theme.colors.text.secondary }}>
                    כמות: {item.quantity}
                  </div>
                </div>
                <div style={{ fontWeight: 'bold', color: theme.colors.text.primary }}>
                  ₪{(item.price * item.quantity).toFixed(2)}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Order Details - Delivery + Total */}
      <div
        style={{
          padding: theme.spacing.lg,
          backgroundColor: theme.colors.cardBg,
          borderRadius: theme.borderRadius.xl,
          boxShadow: theme.shadows.card,
        }}
      >
        <div
          style={{
            display: 'flex',
            justifyContent: 'space-between',
            marginBottom: theme.spacing.sm,
            color: theme.colors.text.secondary,
          }}
        >
          <span>דמי משלוח</span>
          <span>₪{deliveryFee.toFixed(2)}</span>
        </div>
        <div
          style={{
            display: 'flex',
            justifyContent: 'space-between',
            paddingTop: theme.spacing.sm,
            borderTop: `1px solid ${theme.colors.border}`,
            fontSize: '1.125rem',
            fontWeight: 'bold',
            color: theme.colors.text.primary,
          }}
        >
          <span>סך הכל</span>
          <span>₪{total.toFixed(2)}</span>
        </div>
      </div>

      {/* Order Now Button Container */}
      <div
        style={{
          padding: theme.spacing.lg,
          backgroundColor: theme.colors.cardBg,
          borderRadius: theme.borderRadius.xl,
          boxShadow: theme.shadows.card,
        }}
      >
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
            fontSize: '1.125rem',
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
          <span>הזמן עכשיו</span>
          {/* Mouse/Click Icon */}
          <svg
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
          >
            <path d="M9 9V5a3 3 0 0 1 6 0v4" />
            <path d="M12 13v8" />
            <path d="M9 9h6l-2.5 8h-1L9 9z" />
            <rect x="7" y="9" width="10" height="14" rx="2" />
          </svg>
        </button>
      </div>
    </div>
  );
}
