import React from 'react';
import theme from '../../config/theme';
import ProductCard from './ProductCard';

/**
 * ProductGrid - 2-column grid of product cards
 * Displays products with consistent spacing and layout
 */
export default function ProductGrid({ products, onAddToCart, loading = false }) {
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
        <div style={{ fontSize: '4rem', marginBottom: theme.spacing.md }}>🍽️</div>
        <p style={{ fontSize: '1.125rem', fontWeight: '500' }}>
          אין מוצרים זמינים כרגע
        </p>
      </div>
    );
  }

  return (
    <div
      style={{
        display: 'flex',
        flexDirection: 'column',
        gap: theme.spacing.md,
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
