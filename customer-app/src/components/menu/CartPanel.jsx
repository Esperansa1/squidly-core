import React from 'react';
import theme from '../../config/theme';
import CartItemDisplay from '../cart/CartItemDisplay';
import CouponSection from '../cart/CouponSection';

/**
 * CartPanel - Order summary section (leftmost panel)
 * Shows order summary header, cart items, coupon section, price details, and order button
 */
export default function CartPanel({ cart, onCheckout, onClearCart, onItemClick, onDeleteItem }) {
  const isEmpty = !cart || !cart.items || cart.items.length === 0;
  const subtotal = cart?.subtotal || 0;
  const deliveryFee = cart?.deliveryFee || 25.0;
  const vat = subtotal * 0.17;
  const total = subtotal + deliveryFee + vat;

  const handleCancelOrder = () => {
    if (window.confirm('האם אתם בטוחים שברצונכם לבטל את ההזמנה?')) {
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
          padding: theme.spacing.md,
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
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

        {/* Cancel Order Button (#6) */}
        {!isEmpty && (
          <button
            onClick={handleCancelOrder}
            style={{
              display: 'flex',
              alignItems: 'center',
              gap: '4px',
              padding: `4px ${theme.spacing.sm}`,
              fontSize: '0.75rem',
              fontWeight: '500',
              color: theme.colors.text.secondary,
              backgroundColor: 'transparent',
              border: `1px solid ${theme.colors.border}`,
              borderRadius: theme.borderRadius.md,
              cursor: 'pointer',
              transition: 'all 0.2s ease',
            }}
            onMouseEnter={(e) => {
              e.currentTarget.style.color = '#DC2626';
              e.currentTarget.style.borderColor = '#DC2626';
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.color = theme.colors.text.secondary;
              e.currentTarget.style.borderColor = theme.colors.border;
            }}
          >
            {/* Trash icon */}
            <svg
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <polyline points="3 6 5 6 21 6" />
              <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
            </svg>
            <span>ביטול הזמנה</span>
          </button>
        )}
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
          // Empty cart placeholder (#7 icon + #8 text)
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
              {/* Clipboard/receipt icon (#7) */}
              <svg
                width="40"
                height="40"
                viewBox="0 0 24 24"
                fill="none"
                stroke={theme.colors.text.muted}
                strokeWidth="1.5"
                strokeLinecap="round"
                strokeLinejoin="round"
              >
                <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
                <rect x="8" y="2" width="8" height="4" rx="1" ry="1" />
                <line x1="9" y1="10" x2="15" y2="10" />
                <line x1="9" y1="14" x2="15" y2="14" />
                <line x1="9" y1="18" x2="12" y2="18" />
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
              העגלה שלכם ריקה
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

      {/* Coupon Section (#9) */}
      <CouponSection />

      {/* Order Summary - Price Breakdown (#10) */}
      <div
        style={{
          padding: `${theme.spacing.lg} ${theme.spacing.lg} ${theme.spacing.md}`,
          backgroundColor: theme.colors.cardBg,
          borderRadius: theme.borderRadius.xl,
          boxShadow: theme.shadows.card,
        }}
      >
        {/* Price Breakdown */}
        <div style={{ marginBottom: theme.spacing.md }}>
          {/* Delivery Fee (first) */}
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

          {/* Subtotal (renamed to סכום כולל) */}
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
              סכום כולל
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

          {/* VAT Line (new) */}
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
              מע״מ (17%)
            </span>
            <span
              style={{
                fontSize: '0.875rem',
                fontWeight: 400,
                color: theme.colors.text.secondary,
                opacity: 0.9,
              }}
            >
              ₪{vat.toFixed(2)}
            </span>
          </div>
        </div>

        {/* Divider */}
        <div
          style={{
            height: '1px',
            backgroundColor: theme.colors.border,
            margin: `${theme.spacing.lg} 0`,
            opacity: 0.5,
          }}
        />

        {/* Total (#10 renamed to סה״כ) */}
        <div
          style={{
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'baseline',
            paddingTop: theme.spacing.sm,
          }}
        >
          <span
            style={{
              fontSize: '1rem',
              fontWeight: 500,
              color: theme.colors.text.secondary,
              letterSpacing: '0.01em',
            }}
          >
            סה״כ
          </span>

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

      {/* Order Now Button (#11 - text + lock icon) */}
      <button
        onClick={onCheckout}
        disabled={isEmpty}
        style={{
          width: '100%',
          padding: `${theme.spacing.md} ${theme.spacing.lg}`,
          marginTop: theme.spacing.lg,
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
        <span>הזמינו עכשיו</span>
        {/* Lock/padlock icon (#11) */}
        <svg
          width="18"
          height="18"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
          strokeLinecap="round"
          strokeLinejoin="round"
        >
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
          <path d="M7 11V7a5 5 0 0 1 10 0v4" />
        </svg>
      </button>
    </div>
  );
}
