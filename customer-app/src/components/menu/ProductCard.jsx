import React from 'react';
import theme from '../../config/theme';
import AddToCartButton from '../ui/AddToCartButton';

/**
 * ProductCard - Individual product display card
 * RTL layout with image on right, details on left, + button at bottom
 */
export default function ProductCard({ product, onAddToCart }) {
  const hasDiscount = product.regular_price && product.regular_price > product.price;

  return (
    <div
      style={{
        backgroundColor: theme.colors.cardBg,
        borderRadius: theme.borderRadius.lg,
        padding: theme.spacing.md,
        boxShadow: theme.shadows.card,
        display: 'flex',
        flexDirection: 'row-reverse', // RTL: image on right
        gap: theme.spacing.md,
        height: '100%',
        transition: 'transform 0.2s ease, box-shadow 0.2s ease',
      }}
      onMouseEnter={(e) => {
        e.currentTarget.style.transform = 'translateY(-2px)';
        e.currentTarget.style.boxShadow = theme.shadows.md;
      }}
      onMouseLeave={(e) => {
        e.currentTarget.style.transform = 'translateY(0)';
        e.currentTarget.style.boxShadow = theme.shadows.card;
      }}
    >
      {/* Product Image - Right side */}
      <div
        style={{
          width: '120px',
          height: '120px',
          flexShrink: 0,
          borderRadius: theme.borderRadius.lg,
          overflow: 'hidden',
          backgroundColor: theme.colors.background,
        }}
      >
        {product.image_url ? (
          <img
            src={product.image_url}
            alt={product.name}
            style={{
              width: '100%',
              height: '100%',
              objectFit: 'cover',
            }}
          />
        ) : (
          <div
            style={{
              width: '100%',
              height: '100%',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              color: theme.colors.text.muted,
              fontSize: '3rem',
            }}
          >
            🍽️
          </div>
        )}
      </div>

      {/* Product Details - Left side */}
      <div
        style={{
          flex: 1,
          display: 'flex',
          flexDirection: 'column',
          justifyContent: 'space-between',
          minWidth: 0, // Allow text truncation
        }}
      >
        {/* Top section: Name and Description */}
        <div>
          <h3
            style={{
              fontSize: '1.125rem',
              fontWeight: 'bold',
              color: theme.colors.text.primary,
              margin: 0,
              marginBottom: theme.spacing.xs,
              lineHeight: '1.4',
            }}
          >
            {product.name}
          </h3>
          <p
            style={{
              fontSize: '0.875rem',
              color: theme.colors.text.secondary,
              margin: 0,
              lineHeight: '1.5',
              overflow: 'hidden',
              textOverflow: 'ellipsis',
              display: '-webkit-box',
              WebkitLineClamp: 2,
              WebkitBoxOrient: 'vertical',
            }}
          >
            {product.description || 'אין תיאור זמין'}
          </p>
        </div>

        {/* Bottom section: Price and Add Button */}
        <div
          style={{
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            marginTop: theme.spacing.sm,
          }}
        >
          {/* Price */}
          <div style={{ display: 'flex', alignItems: 'center', gap: theme.spacing.xs }}>
            <span
              style={{
                fontSize: '1.125rem',
                fontWeight: 'bold',
                color: theme.colors.text.primary,
              }}
            >
              ₪{product.price.toFixed(2)}
            </span>
            {hasDiscount && (
              <span
                style={{
                  fontSize: '0.875rem',
                  color: theme.colors.text.muted,
                  textDecoration: 'line-through',
                }}
              >
                ₪{product.regular_price.toFixed(2)}
              </span>
            )}
          </div>

          {/* Add to Cart Button */}
          <AddToCartButton onClick={() => onAddToCart(product)} />
        </div>
      </div>
    </div>
  );
}
