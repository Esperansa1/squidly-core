import React from 'react';
import theme from '../../config/theme';
import ProductCard from './ProductCard';
import { useIsMobile } from '../../hooks/useMediaQuery';

/**
 * ProductGrid - Responsive product list
 * Mobile: Tighter spacing for compact display
 * Desktop: More spacing for comfortable reading
 */
export default function ProductGrid({ products, onAddToCart, loading = false, hasActiveFilters = false, onClearFilters }) {
  const isMobile = useIsMobile();
  if (loading) {
    return (
      <div
        style={{
          display: 'flex',
          justifyContent: 'center',
          alignItems: 'center',
          padding: theme.spacing['2xl'],
          color: theme.colors.text.secondary,
        }}
      >
        <div
          style={{
            width: '48px',
            height: '48px',
            border: `4px solid ${theme.colors.border}`,
            borderTopColor: theme.colors.primary,
            borderRadius: theme.borderRadius.full,
            animation: 'spin 1s linear infinite',
          }}
        />
      </div>
    );
  }

  if (!products || products.length === 0) {
    return (
      <div
        style={{
          textAlign: 'center',
          padding: theme.spacing['2xl'],
          color: theme.colors.text.muted,
        }}
      >
        <div style={{ fontSize: '4rem', marginBottom: theme.spacing.md }}>
          {hasActiveFilters ? '🔍' : '🍽️'}
        </div>
        <p style={{ fontSize: '1.125rem', fontWeight: '500', marginBottom: theme.spacing.md }}>
          {hasActiveFilters ? 'לא נמצאו מוצרים מתאימים' : 'אין מוצרים זמינים כרגע'}
        </p>
        {hasActiveFilters && onClearFilters && (
          <>
            <p style={{ fontSize: '0.875rem', color: theme.colors.text.secondary, marginBottom: theme.spacing.md }}>
              נסה להרחיב את הסינון או לחפש משהו אחר
            </p>
            <button
              onClick={onClearFilters}
              style={{
                padding: `${theme.spacing.sm} ${theme.spacing.lg}`,
                backgroundColor: theme.colors.primary,
                color: '#FFFFFF',
                border: 'none',
                borderRadius: theme.borderRadius.md,
                fontSize: '1rem',
                fontWeight: 600,
                cursor: 'pointer',
              }}
            >
              נקה את כל הסינונים
            </button>
          </>
        )}
      </div>
    );
  }

  return (
    <div
      style={{
        display: 'flex',
        flexDirection: 'column',
        gap: isMobile ? theme.spacing.mobile.sm : theme.spacing.md,
      }}
    >
      {products.map((product) => (
        <ProductCard
          key={product.id}
          product={product}
          onAddToCart={onAddToCart}
        />
      ))}
    </div>
  );
}
