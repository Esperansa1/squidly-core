import React, { useState } from 'react';
import theme from '../../config/theme';

/**
 * CouponSection - Coupon/discount code input section for cart
 * Displays ticket icon + header in black, then dashed line + input on same row
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
        direction: 'rtl',
      }}
    >
      {/* Header with ticket icon — black text, right-aligned (RTL start) */}
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
          stroke={theme.colors.text.primary}
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
            fontWeight: '700',
            color: theme.colors.text.primary,
          }}
        >
          יש לכם קופון?
        </span>
      </div>

      {/* Dashed line fills space, input at leftmost (end in RTL) */}
      <div
        style={{
          display: 'flex',
          alignItems: 'center',
          gap: theme.spacing.sm,
        }}
      >
        {/* Dashed line — fills remaining space */}
        <div
          style={{
            flex: 1,
            borderBottom: `2px dashed ${theme.colors.border}`,
          }}
        />

        {/* Input with red underline — at the left (end) side, text centered */}
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
            width: '120px',
            flexShrink: 0,
            padding: `4px 0`,
            fontSize: '0.8125rem',
            border: 'none',
            borderBottom: `2px solid ${theme.colors.primary}`,
            backgroundColor: 'transparent',
            textAlign: 'center',
            outline: 'none',
            color: theme.colors.text.primary,
          }}
        />
      </div>

      {/* Status message */}
      {message && (
        <p
          style={{
            fontSize: '0.75rem',
            color: message.type === 'error' ? '#DC2626' : message.type === 'success' ? '#16A34A' : theme.colors.text.secondary,
            margin: `${theme.spacing.xs} 0 0 0`,
          }}
        >
          {message.text}
        </p>
      )}
    </div>
  );
}
