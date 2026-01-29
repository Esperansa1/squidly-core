import React from 'react';
import theme from '../../config/theme';
import CartItemDisplay from '../cart/CartItemDisplay';

/**
 * CartPanel - Order summary section (leftmost panel)
 * Shows order summary header, cart items, price details, and order button
 */
export default function CartPanel({ cart, onCheckout, onClearCart, onItemClick, onDeleteItem }) {
  const isEmpty = !cart || !cart.items || cart.items.length === 0;
  const subtotal = cart?.subtotal || 0;
  const deliveryFee = cart?.deliveryFee || 25.0;
  const total = subtotal + deliveryFee;

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
      {/* Header: סיכום ההזמנה */}
      <div
        style={{
          padding: theme.spacing.md,
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
      </div>

      {/* Cart Items Area - Takes all available vertical space */}
      <div
        style={{
          flex: '1 1 0',
          overflowY: 'auto',
          padding: theme.spacing.md,
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
                color: theme.colors.text.primary,
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
              <CartItemDisplay key={index} item={item} compact={false} onItemClick={onItemClick} onDelete={onDeleteItem} />
            ))}
          </div>
        )}
      </div>

      {/* Order Summary - Complete Transparent Breakdown */}
      <div
        style={{
          padding: `${theme.spacing.lg} ${theme.spacing.lg} ${theme.spacing.md}`,
          backgroundColor: theme.colors.cardBg,
          borderRadius: theme.borderRadius.xl,
          boxShadow: theme.shadows.card,
        }}
      >
        {/* Price Breakdown - Low Emphasis (Explanation) */}
        <div style={{ marginBottom: theme.spacing.md }}>
          {/* Items Subtotal */}
          <div
            style={{
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'baseline',
              marginBottom: theme.spacing.sm,
            }}
          >
            <span
              style={{
                fontSize: '0.875rem',
                fontWeight: 400,
                color: theme.colors.text.secondary,
                opacity: 0.9,
              }}
            >
              מחיר פריטים
            </span>
            <span
              style={{
                fontSize: '0.875rem',
                fontWeight: 400,
                color: theme.colors.text.secondary,
                opacity: 0.9,
              }}
            >
              ₪{subtotal.toFixed(2)}
            </span>
          </div>

          {/* Delivery Fee */}
          <div
            style={{
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'baseline',
            }}
          >
            <span
              style={{
                fontSize: '0.875rem',
                fontWeight: 400,
                color: theme.colors.text.secondary,
                opacity: 0.9,
              }}
            >
              דמי משלוח
            </span>
            <span
              style={{
                fontSize: '0.875rem',
                fontWeight: 400,
                color: theme.colors.text.secondary,
                opacity: 0.9,
              }}
            >
              ₪{deliveryFee.toFixed(2)}
            </span>
          </div>
        </div>

        {/* Divider With Meaning - Separates thinking from deciding */}
        <div
          style={{
            height: '1px',
            backgroundColor: theme.colors.border,
            margin: `${theme.spacing.lg} 0`,
            opacity: 0.5,
          }}
        />

        {/* Total to Pay - High Emphasis (Conclusion) */}
        <div
          style={{
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'baseline',
            paddingTop: theme.spacing.sm,
          }}
        >
          {/* Total Label - More Explicit */}
          <span
            style={{
              fontSize: '1rem',
              fontWeight: 500,
              color: theme.colors.text.secondary,
              letterSpacing: '0.01em',
            }}
          >
            סה״כ לתשלום
          </span>

          {/* Total Price - Visual Hero */}
          <span
            style={{
              fontSize: '1.75rem',
              fontWeight: 700,
              color: theme.colors.text.primary,
              letterSpacing: '-0.02em',
              lineHeight: '1.2',
            }}
          >
            ₪{total.toFixed(2)}
          </span>
        </div>
      </div>

      {/* Order Now Button - Action comes after psychological closure */}
      <button
        onClick={onCheckout}
        disabled={isEmpty}
        style={{
          width: '100%',
          padding: `${theme.spacing.md} ${theme.spacing.lg}`,
          marginTop: theme.spacing.lg, // Clear separation from summary
          backgroundColor: isEmpty ? '#EF444480' : theme.colors.primary,
          color: theme.colors.text.white,
          border: 'none',
          borderRadius: theme.borderRadius.md,
          fontSize: '1.0625rem',
          fontWeight: 600,
          cursor: isEmpty ? 'not-allowed' : 'pointer',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          gap: theme.spacing.sm,
          transition: 'all 0.2s ease',
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
  );
}
