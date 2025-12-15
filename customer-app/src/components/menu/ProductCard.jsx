import React from 'react';
import theme from '../../config/theme';

/**
 * ProductCard - Full-width horizontal product card
 * Layout: [Product Image (left)] [Text Content (middle)] [+ Button (right)]
 */
export default function ProductCard({ product, onAddToCart }) {
  const hasDiscount = product.discounted_price && product.discounted_price < product.price;
  const displayPrice = hasDiscount ? product.discounted_price : product.price;
  const originalPrice = product.price;

  // Get badges from product tags
  const badges = (product.tags || []).map((tag) => ({
    text: tag,
    color: theme.colors.primary, // Red color for all tags
  }))

  return (
    <div
      style={{
        backgroundColor: theme.colors.cardBg,
        borderRadius: theme.borderRadius.xl,
        padding: theme.spacing.lg,
        boxShadow: theme.shadows.card,
        display: 'flex',
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        gap: theme.spacing.lg,
        transition: 'box-shadow 0.2s ease',
        width: '100%',
      }}
      onMouseEnter={(e) => {
        e.currentTarget.style.boxShadow = theme.shadows.lg;
      }}
      onMouseLeave={(e) => {
        e.currentTarget.style.boxShadow = theme.shadows.card;
      }}
    >
      {/* Left: Product Image */}
      <div
        style={{
          width: '100px',
          height: '100px',
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

      {/* Middle: Text Content (grows to fill space) */}
      <div
        style={{
          flex: 1,
          display: 'flex',
          flexDirection: 'column',
          gap: theme.spacing.xs,
          minWidth: 0, // Allow text truncation
        }}
      >
        {/* Badges (if any) */}
        {badges.length > 0 && (
          <div style={{ display: 'flex', gap: theme.spacing.xs, marginBottom: theme.spacing.xs }}>
            {badges.map((badge, index) => (
              <span
                key={index}
                style={{
                  fontSize: '0.75rem',
                  fontWeight: '600',
                  color: 'white',
                  backgroundColor: badge.color,
                  padding: '2px 8px',
                  borderRadius: theme.borderRadius.full,
                }}
              >
                {badge.text}
              </span>
            ))}
          </div>
        )}

        {/* Title */}
        <h3
          style={{
            fontSize: '1.125rem',
            fontWeight: 'bold',
            color: theme.colors.text.primary,
            margin: 0,
            lineHeight: '1.4',
            whiteSpace: 'nowrap',
            overflow: 'hidden',
            textOverflow: 'ellipsis',
          }}
        >
          {product.name}
        </h3>

        {/* Description */}
        <p
          style={{
            fontSize: '0.875rem',
            color: theme.colors.text.secondary,
            margin: 0,
            lineHeight: '1.5',
            whiteSpace: 'nowrap',
            overflow: 'hidden',
            textOverflow: 'ellipsis',
          }}
        >
          {product.description || 'פלאפל טרי, חם וקריספי עם כל התוספות.'}
        </p>

        {/* Price Row */}
        <div style={{ display: 'flex', alignItems: 'center', gap: theme.spacing.sm }}>
          <span
            style={{
              fontSize: '1.125rem',
              fontWeight: 'bold',
              color: theme.colors.text.primary,
            }}
          >
            ₪{displayPrice.toFixed(2)}
          </span>
          {hasDiscount && (
            <span
              style={{
                fontSize: '0.875rem',
                color: theme.colors.text.muted,
                textDecoration: 'line-through',
              }}
            >
              ₪{originalPrice.toFixed(2)}
            </span>
          )}
        </div>
      </div>

      {/* Right: Add (+) Button */}
      <button
        onClick={() => onAddToCart(product)}
        style={{
          width: '48px',
          height: '48px',
          borderRadius: theme.borderRadius.lg,
          backgroundColor: '#FEF3C7', // Light yellow
          border: 'none',
          cursor: 'pointer',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          fontSize: '1.5rem',
          fontWeight: 'bold',
          color: '#F59E0B', // Dark yellow/orange
          flexShrink: 0,
          transition: 'background-color 0.2s ease',
        }}
        onMouseEnter={(e) => {
          e.currentTarget.style.backgroundColor = '#FDE68A';
        }}
        onMouseLeave={(e) => {
          e.currentTarget.style.backgroundColor = '#FEF3C7';
        }}
      >
        +
      </button>
    </div>
  );
}
