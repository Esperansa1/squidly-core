import React, { useRef } from 'react';
import theme from '../../config/theme';

/**
 * MobileCheckoutBar - Two-row fixed bottom section for mobile
 * Row 1: Total price display
 * Row 2: Full-width checkout button
 *
 * ALWAYS visible on mobile (static)
 * Pull-up gesture opens MobileCartSheet (tap or swipe up)
 * Button disabled when cart is empty
 *
 * @param {object} cart - Cart object with items, subtotal, tax, deliveryFee
 * @param {function} onOpenCart - Callback to open the cart sheet
 * @param {function} onCheckout - Callback for checkout action
 */
export default function MobileCheckoutBar({ cart, onOpenCart, onCheckout }) {
  // Calculate total
  const total = cart?.subtotal + cart?.tax + cart?.deliveryFee || 0;
  const isEmpty = !cart || !cart.items || cart.items.length === 0;

  // Touch gesture tracking
  const touchStartY = useRef(null);
  const touchStartTime = useRef(null);

  const handleTouchStart = (e) => {
    touchStartY.current = e.touches[0].clientY;
    touchStartTime.current = Date.now();
  };

  const handleTouchEnd = (e) => {
    if (touchStartY.current === null) return;

    const touchEndY = e.changedTouches[0].clientY;
    const deltaY = touchStartY.current - touchEndY;
    const deltaTime = Date.now() - touchStartTime.current;

    // Detect upward swipe: moved up at least 50px within 300ms
    const isUpwardSwipe = deltaY > 50 && deltaTime < 300;

    // Detect tap: small movement (less than 10px) and quick (less than 200ms)
    const isTap = Math.abs(deltaY) < 10 && deltaTime < 200;

    if (isUpwardSwipe || isTap) {
      e.preventDefault(); // Prevent click event from also firing
      onOpenCart();
    }

    // Reset
    touchStartY.current = null;
    touchStartTime.current = null;
  };

  return (
    <div
      style={{
        position: 'fixed',
        bottom: 0,
        left: 0,
        right: 0,
        backgroundColor: theme.colors.cardBg,
        borderTop: `1px solid ${theme.colors.border}`,
        padding: theme.spacing.md,
        paddingBottom: 'calc(1rem + env(safe-area-inset-bottom))', // iOS safe area
        display: 'flex',
        flexDirection: 'column',
        gap: theme.spacing.sm,
        zIndex: 50,
        boxShadow: '0 -2px 8px rgba(0, 0, 0, 0.1)',
        touchAction: 'pan-y', // Allow vertical gestures
      }}
      onClick={onOpenCart} // Pull up cart on tap
      onTouchStart={handleTouchStart}
      onTouchEnd={handleTouchEnd}
      role="button"
      tabIndex={0}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          onOpenCart();
        }
      }}
    >
      {/* Row 1: Total price - Centered (clickable to open cart) */}
      <div
        style={{
          display: 'flex',
          justifyContent: 'center',
          alignItems: 'center',
          gap: theme.spacing.xs,
          fontSize: theme.typography.mobile.h3,
          fontWeight: 600,
          color: theme.colors.text.primary,
          cursor: 'pointer',
        }}
      >
        <span>סה"כ: {total.toFixed(2)} ₪</span>
        {/* Up arrow icon to indicate pull-up action */}
        <svg
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke={theme.colors.text.secondary}
          strokeWidth="2"
          strokeLinecap="round"
          strokeLinejoin="round"
        >
          <path d="M18 15l-6-6-6 6" />
        </svg>
      </div>

      {/* Row 2: Checkout button - Full width */}
      <button
        type="button"
        onClick={(e) => {
          e.stopPropagation(); // Don't trigger cart open
          if (!isEmpty) {
            onCheckout();
          }
        }}
        onTouchStart={(e) => {
          e.stopPropagation(); // Prevent parent touch handler
        }}
        onTouchEnd={(e) => {
          e.stopPropagation(); // Prevent parent touch handler
          if (!isEmpty) {
            onCheckout();
          }
        }}
        disabled={isEmpty}
        style={{
          width: '100%',
          minHeight: theme.layout.minTouchTarget, // 44px touch target
          backgroundColor: isEmpty ? theme.colors.background : theme.colors.primary,
          color: isEmpty ? theme.colors.text.muted : theme.colors.text.white,
          border: 'none',
          borderRadius: theme.borderRadius.lg,
          fontSize: theme.typography.mobile.body,
          fontWeight: 600,
          cursor: isEmpty ? 'not-allowed' : 'pointer',
          boxShadow: isEmpty ? 'none' : theme.shadows.md,
          transition: 'background-color 0.2s ease',
          opacity: isEmpty ? 0.6 : 1,
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
        {isEmpty ? 'העגלה ריקה' : 'הזמינו עכשיו'}
      </button>
    </div>
  );
}
