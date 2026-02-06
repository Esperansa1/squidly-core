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
        overflow: 'hidden',
      }}
    >
      {/* Header: סיכום ההזמנה + Cancel Button */}
      <div
        style={{
          padding: `${theme.spacing.md} ${theme.spacing.md} ${theme.spacing.sm}`,
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          flexShrink: 0,
        }}
      >
        <h2
          style={{
            fontSize: '1.25rem',
            fontWeight: '700',
            color: theme.colors.text.primary,
            margin: 0,
          }}
        >
          סיכום ההזמנה
        </h2>

        {/* Cancel Order Button */}
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
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <polyline points="3 6 5 6 21 6" />
              <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
            </svg>
            <span>ביטול הזמנה</span>
          </button>
        )}
      </div>

      {/* Scrollable middle section: cart items OR empty state */}
      <div
        style={{
          flex: 1,
          overflowY: 'auto',
          display: 'flex',
          flexDirection: 'column',
        }}
      >
        {isEmpty ? (
          /* Empty state - white card spanning full available area */
          <div
            style={{
              flex: 1,
              display: 'flex',
              flexDirection: 'column',
              padding: theme.spacing.md,
              minHeight: '160px',
            }}
          >
            <div
              style={{
                flex: 1,
                backgroundColor: theme.colors.cardBg,
                borderRadius: theme.borderRadius.xl,
                boxShadow: theme.shadows.card,
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                justifyContent: 'center',
              }}
            >
              <div
                style={{
                  width: '72px',
                  height: '72px',
                  backgroundColor: theme.colors.background,
                  borderRadius: theme.borderRadius.full,
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  marginBottom: theme.spacing.sm,
                }}
              >
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke={theme.colors.text.muted} strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
                  <rect x="8" y="2" width="8" height="4" rx="1" ry="1" />
                  <line x1="9" y1="10" x2="15" y2="10" />
                  <line x1="9" y1="14" x2="15" y2="14" />
                  <line x1="9" y1="18" x2="12" y2="18" />
                </svg>
              </div>
              <p
                style={{
                  fontSize: '0.9375rem',
                  fontWeight: '500',
                  color: theme.colors.text.secondary,
                  textAlign: 'center',
                  margin: 0,
                }}
              >
                העגלה שלכם ריקה
              </p>
            </div>
          </div>
        ) : (
          /* Cart items list */
          <div style={{ padding: theme.spacing.md, display: 'flex', flexDirection: 'column', gap: theme.spacing.sm }}>
            {cart.items.map((item, index) => (
              <CartItemDisplay key={index} item={item} compact={false} onItemClick={onItemClick} onDelete={onDeleteItem} />
            ))}
          </div>
        )}
      </div>

      {/* Bottom fixed section: Coupon + Price breakdown + Order button */}
      <div style={{ flexShrink: 0, padding: `0 ${theme.spacing.md} ${theme.spacing.md}` }}>
        {/* Price Breakdown Card (includes coupon section) */}
        <div
          style={{
            backgroundColor: theme.colors.cardBg,
            borderRadius: theme.borderRadius.xl,
            boxShadow: theme.shadows.card,
          }}
        >
          {/* Coupon Section - inside the price card */}
          <CouponSection />

          {/* Divider between coupon and prices */}
          <div style={{ height: '1px', backgroundColor: theme.colors.border, opacity: 0.5 }} />

          {/* Price lines */}
          <div style={{ padding: theme.spacing.md }}>
            {/* Delivery Fee */}
            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: theme.spacing.xs }}>
              <span style={{ fontSize: '0.9375rem', color: theme.colors.text.primary }}>דמי משלוח</span>
              <span style={{ fontSize: '0.9375rem', color: theme.colors.text.primary }}>₪{deliveryFee.toFixed(2)}</span>
            </div>

            {/* Subtotal */}
            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: theme.spacing.xs }}>
              <span style={{ fontSize: '0.9375rem', color: theme.colors.text.primary }}>סכום כולל</span>
              <span style={{ fontSize: '0.9375rem', color: theme.colors.text.primary }}>₪{subtotal.toFixed(2)}</span>
            </div>

            {/* VAT */}
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <span style={{ fontSize: '0.9375rem', color: theme.colors.text.primary }}>מע״מ (17%)</span>
              <span style={{ fontSize: '0.9375rem', color: theme.colors.text.primary }}>₪{vat.toFixed(2)}</span>
            </div>

            {/* Divider */}
            <div style={{ height: '1px', backgroundColor: theme.colors.border, margin: `${theme.spacing.md} 0`, opacity: 0.5 }} />

            {/* Total */}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline' }}>
              <span style={{ fontSize: '1rem', fontWeight: 600, color: theme.colors.text.primary }}>סה״כ</span>
              <span style={{ fontSize: '1.25rem', fontWeight: 700, color: theme.colors.text.primary, letterSpacing: '-0.02em' }}>₪{total.toFixed(2)}</span>
            </div>
          </div>
        </div>

        {/* Order Now Button */}
        <button
          onClick={onCheckout}
          disabled={isEmpty}
          style={{
            width: '100%',
            padding: `${theme.spacing.sm} ${theme.spacing.lg}`,
            marginTop: theme.spacing.sm,
            backgroundColor: isEmpty ? '#EF444480' : theme.colors.primary,
            color: theme.colors.text.white,
            border: 'none',
            borderRadius: theme.borderRadius.md,
            fontSize: '1rem',
            fontWeight: 700,
            cursor: isEmpty ? 'not-allowed' : 'pointer',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            gap: theme.spacing.sm,
            transition: 'all 0.2s ease',
          }}
          onMouseEnter={(e) => {
            if (!isEmpty) e.currentTarget.style.backgroundColor = theme.colors.primaryHover;
          }}
          onMouseLeave={(e) => {
            if (!isEmpty) e.currentTarget.style.backgroundColor = theme.colors.primary;
          }}
        >
          <span>הזמינו עכשיו</span>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
          </svg>
        </button>
      </div>
    </div>
  );
}
