import React, { useState } from 'react';
import theme from '../../config/theme';

/**
 * CouponSection - Coupon/discount code input section for cart
 * Displays ticket icon, header, and input field
 */
export default function CouponSection() {
  const [couponCode, setCouponCode] = useState('');
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState(null);

  const handleApplyCoupon = async () => {
    if (!couponCode.trim()) return;

    setLoading(true);
    setMessage(null);

    // Placeholder - will be wired to backend coupon validation later
    setTimeout(() => {
      setLoading(false);
      setMessage({ type: 'info', text: 'מימוש קופונים יהיה זמין בקרוב' });
    }, 500);
  };

  return (
    <div
      style={{
        padding: `${theme.spacing.md}`,
        borderTop: `1px solid ${theme.colors.border}`,
        borderBottom: `1px solid ${theme.colors.border}`,
      }}
    >
      {/* Header with ticket icon */}
      <div
        style={{
          display: 'flex',
          alignItems: 'center',
          gap: theme.spacing.sm,
          marginBottom: theme.spacing.sm,
        }}
      >
        {/* Ticket/coupon icon */}
        <svg
          width="18"
          height="18"
          viewBox="0 0 24 24"
          fill="none"
          stroke={theme.colors.text.secondary}
          strokeWidth="1.5"
          strokeLinecap="round"
          strokeLinejoin="round"
        >
          <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z" />
          <path d="M13 5v2" />
          <path d="M13 17v2" />
          <path d="M13 11v2" />
        </svg>
        <span
          style={{
            fontSize: '0.875rem',
            fontWeight: '500',
            color: theme.colors.text.secondary,
          }}
        >
          ?יש לכם קופון
        </span>
      </div>

      {/* Input field */}
      <div style={{ display: 'flex', gap: theme.spacing.sm }}>
        <input
          type="text"
          value={couponCode}
          onChange={(e) => {
            setCouponCode(e.target.value);
            setMessage(null);
          }}
          onKeyDown={(e) => e.key === 'Enter' && handleApplyCoupon()}
          placeholder="ממשו קופון"
          disabled={loading}
          style={{
            flex: 1,
            padding: `${theme.spacing.sm}`,
            fontSize: '0.875rem',
            border: 'none',
            borderBottom: `1px solid ${theme.colors.border}`,
            backgroundColor: 'transparent',
            textAlign: 'right',
            outline: 'none',
            color: theme.colors.text.primary,
          }}
        />
        <button
          onClick={handleApplyCoupon}
          disabled={loading || !couponCode.trim()}
          style={{
            padding: `${theme.spacing.sm} ${theme.spacing.md}`,
            fontSize: '0.8rem',
            fontWeight: '600',
            color: loading || !couponCode.trim() ? theme.colors.text.muted : theme.colors.primary,
            backgroundColor: 'transparent',
            border: 'none',
            cursor: loading || !couponCode.trim() ? 'not-allowed' : 'pointer',
            whiteSpace: 'nowrap',
          }}
        >
          {loading ? '...' : 'החל'}
        </button>
      </div>

      {/* Status message */}
      {message && (
        <p
          style={{
            fontSize: '0.75rem',
            color: message.type === 'error' ? '#DC2626' : message.type === 'success' ? '#16A34A' : theme.colors.text.secondary,
            marginTop: theme.spacing.xs,
            margin: `${theme.spacing.xs} 0 0 0`,
          }}
        >
          {message.text}
        </p>
      )}
    </div>
  );
}
