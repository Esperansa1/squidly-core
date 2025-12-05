import React from 'react';
import theme from '../../config/theme';

/**
 * AddToCartButton - Orange circular + button for adding products
 * Matches the design from the reference image
 */
export default function AddToCartButton({ onClick, disabled = false }) {
  return (
    <button
      onClick={onClick}
      disabled={disabled}
      style={{
        width: '32px',
        height: '32px',
        borderRadius: theme.borderRadius.full,
        backgroundColor: disabled ? theme.colors.text.muted : theme.colors.secondary,
        color: theme.colors.text.white,
        border: 'none',
        cursor: disabled ? 'not-allowed' : 'pointer',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        fontSize: '20px',
        fontWeight: 'bold',
        transition: 'all 0.2s ease',
        flexShrink: 0,
      }}
      onMouseEnter={(e) => {
        if (!disabled) {
          e.currentTarget.style.backgroundColor = theme.colors.secondaryHover;
          e.currentTarget.style.transform = 'scale(1.05)';
        }
      }}
      onMouseLeave={(e) => {
        if (!disabled) {
          e.currentTarget.style.backgroundColor = theme.colors.secondary;
          e.currentTarget.style.transform = 'scale(1)';
        }
      }}
    >
      +
    </button>
  );
}
