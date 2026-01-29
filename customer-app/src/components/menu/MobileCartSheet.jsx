import React, { useEffect } from 'react';
import theme from '../../config/theme';
import CartItemDisplay from '../cart/CartItemDisplay';

/**
 * MobileCartSheet - Full-screen cart overlay for mobile
 * Slides up from bottom, shows cart items, summary, and actions
 *
 * @param {boolean} isOpen - Whether the sheet is open
 * @param {function} onClose - Callback to close the sheet
 * @param {object} cart - Cart object with items, subtotal, tax, deliveryFee
 * @param {function} onClearCart - Callback to clear the cart
 * @param {function} onItemClick - Callback when cart item is clicked to review/edit
 * @param {function} onDeleteItem - Callback when delete icon is clicked on an item
 */
export default function MobileCartSheet({ isOpen, onClose, cart, onClearCart, onItemClick, onDeleteItem }) {
  // Lock body scroll when sheet is open
  useEffect(() => {
    if (isOpen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
    return () => {
      document.body.style.overflow = '';
    };
  }, [isOpen]);

  if (!isOpen) return null;

  const total = cart.subtotal + cart.tax + cart.deliveryFee;

  return (
    <>
      {/* Backdrop - Dark overlay */}
      <div
        style={{
          position: 'fixed',
          inset: 0,
          backgroundColor: 'rgba(0, 0, 0, 0.5)',
          zIndex: 100,
          animation: 'fadeIn 0.2s ease-out',
        }}
        onClick={onClose}
      />

      {/* Cart Sheet */}
      <div
        style={{
          position: 'fixed',
          bottom: 0,
          left: 0,
          right: 0,
          maxHeight: '85vh',
          backgroundColor: theme.colors.cardBg,
          borderTopLeftRadius: theme.borderRadius.xl,
          borderTopRightRadius: theme.borderRadius.xl,
          zIndex: 101,
          display: 'flex',
          flexDirection: 'column',
          animation: 'slideUp 0.3s ease-out',
          boxShadow: '0 -4px 20px rgba(0, 0, 0, 0.15)',
        }}
      >
        {/* Header */}
        <div
          style={{
            padding: theme.spacing.md,
            borderBottom: `1px solid ${theme.colors.border}`,
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center',
            flexShrink: 0,
          }}
        >
          <h2
            style={{
              fontSize: theme.typography.mobile.h2,
              fontWeight: 600,
              color: theme.colors.text.primary,
              margin: 0,
            }}
          >
            העגלה שלי
          </h2>
          <button
            onClick={onClose}
            style={{
              width: '36px',
              height: '36px',
              backgroundColor: 'transparent',
              border: 'none',
              borderRadius: '50%',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              cursor: 'pointer',
            }}
            aria-label="סגור עגלה"
          >
            <svg
              width="24"
              height="24"
              viewBox="0 0 24 24"
              fill="none"
              stroke={theme.colors.text.secondary}
              strokeWidth="2"
            >
              <path d="M18 6L6 18M6 6l12 12" />
            </svg>
          </button>
        </div>

        {/* Cart Items - Scrollable */}
        <div
          style={{
            flex: 1,
            overflowY: 'auto',
            padding: theme.spacing.md,
            paddingBottom: theme.spacing.xl,
          }}
        >
          {cart.items.length === 0 ? (
            <div
              style={{
                textAlign: 'center',
                padding: theme.spacing.xl,
                color: theme.colors.text.muted,
              }}
            >
              העגלה ריקה
            </div>
          ) : (
            cart.items.map((item, index) => (
              <div key={`${item.id}-${index}`} style={{ marginBottom: theme.spacing.sm }}>
                <CartItemDisplay item={item} compact={true} onItemClick={onItemClick} onDelete={onDeleteItem} />
              </div>
            ))
          )}
        </div>

        {/* Summary Footer - Complete Transparent Breakdown */}
        <div
          style={{
            padding: theme.spacing.md,
            paddingBottom: 'calc(1rem + env(safe-area-inset-bottom))',
            borderTop: `1px solid ${theme.colors.border}`,
            backgroundColor: theme.colors.cardBg,
            flexShrink: 0,
          }}
        >
          {/* Price Breakdown - Low Emphasis (Explanation) */}
          <div style={{ marginBottom: theme.spacing.sm }}>
            {/* Items Subtotal */}
            <div
              style={{
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'baseline',
                marginBottom: theme.spacing.xs,
              }}
            >
              <span
                style={{
                  fontSize: '0.8125rem',
                  fontWeight: 400,
                  color: theme.colors.text.secondary,
                  opacity: 0.9,
                }}
              >
                מחיר פריטים
              </span>
              <span
                style={{
                  fontSize: '0.8125rem',
                  fontWeight: 400,
                  color: theme.colors.text.secondary,
                  opacity: 0.9,
                }}
              >
                ₪{cart.subtotal.toFixed(2)}
              </span>
            </div>

            {/* Delivery Fee */}
            <div
              style={{
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'baseline',
                marginBottom: theme.spacing.xs,
              }}
            >
              <span
                style={{
                  fontSize: '0.8125rem',
                  fontWeight: 400,
                  color: theme.colors.text.secondary,
                  opacity: 0.9,
                }}
              >
                דמי משלוח
              </span>
              <span
                style={{
                  fontSize: '0.8125rem',
                  fontWeight: 400,
                  color: theme.colors.text.secondary,
                  opacity: 0.9,
                }}
              >
                ₪{cart.deliveryFee.toFixed(2)}
              </span>
            </div>

            {/* Tax */}
            <div
              style={{
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'baseline',
              }}
            >
              <span
                style={{
                  fontSize: '0.8125rem',
                  fontWeight: 400,
                  color: theme.colors.text.secondary,
                  opacity: 0.9,
                }}
              >
                מע״מ
              </span>
              <span
                style={{
                  fontSize: '0.8125rem',
                  fontWeight: 400,
                  color: theme.colors.text.secondary,
                  opacity: 0.9,
                }}
              >
                ₪{cart.tax.toFixed(2)}
              </span>
            </div>
          </div>

          {/* Divider With Meaning - Separates thinking from deciding */}
          <div
            style={{
              height: '1px',
              backgroundColor: theme.colors.border,
              margin: `${theme.spacing.md} 0`,
              opacity: 0.5,
            }}
          />

          {/* Total to Pay - High Emphasis (Conclusion) */}
          <div
            style={{
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'baseline',
              paddingTop: theme.spacing.xs,
              marginBottom: theme.spacing.md,
            }}
          >
            {/* Total Label - More Explicit */}
            <span
              style={{
                fontSize: '0.9375rem',
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
                fontSize: '1.5rem',
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
      </div>
    </>
  );
}
