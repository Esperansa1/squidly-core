import React from 'react';
import theme from '../../config/theme';
import { useIsMobile } from '../../hooks/useMediaQuery';

/**
 * ProductCard - Responsive product card
 * Mobile: Compact layout without description (56px image, smaller padding)
 * Desktop: Full layout with description (100px image, more spacing)
 * Layout: [Product Image (left)] [Text Content (middle)] [+ Button (right)]
 */
export default function ProductCard({ product, onAddToCart }) {
  const isMobile = useIsMobile();
  const hasDiscount = product.discounted_price && product.discounted_price < product.price;
  const displayPrice = hasDiscount ? product.discounted_price : product.price;
  const originalPrice = product.price;

  // Get badges from product tags
  const badges = (product.tags || []).map((tag) => ({
    text: tag,
    color: theme.colors.primary, // Red color for all tags
  }));

  // Responsive sizing
  const imageSize = isMobile ? '56px' : '100px';
  const buttonSize = isMobile ? '44px' : '48px';
  const cardPadding = isMobile ? theme.spacing.mobile.sm : theme.spacing.lg;
  const gap = isMobile ? theme.spacing.mobile.sm : theme.spacing.lg;

  return (
    <div
      style={{
        backgroundColor: theme.colors.cardBg,
        borderRadius: theme.borderRadius.xl,
        padding: cardPadding,
        boxShadow: theme.shadows.card,
        display: 'flex',
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        gap: gap,
        transition: 'box-shadow 0.2s ease',
        width: '100%',
        minHeight: isMobile ? '72px' : 'auto',
      }}
      onMouseEnter={(e) => {
        if (!isMobile) {
          e.currentTarget.style.boxShadow = theme.shadows.lg;
        }
      }}
      onMouseLeave={(e) => {
        if (!isMobile) {
          e.currentTarget.style.boxShadow = theme.shadows.card;
        }
      }}
    >
      {/* Left: Product Image */}
      <div
        style={{
          width: imageSize,
          height: imageSize,
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
              fontSize: isMobile ? '2rem' : '3rem',
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
          gap: isMobile ? '2px' : theme.spacing.xs,
          minWidth: 0, // Allow text truncation
        }}
      >
        {/* Badges (if any) - Only on desktop */}
        {!isMobile && badges.length > 0 && (
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
            fontSize: isMobile ? theme.typography.mobile.body : '1.125rem',
            fontWeight: isMobile ? 600 : 'bold',
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

        {/* Description - ONLY ON DESKTOP */}
        {!isMobile && (
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
        )}

        {/* Price Row */}
        <div style={{ display: 'flex', alignItems: 'center', gap: theme.spacing.xs }}>
          <span
            style={{
              fontSize: isMobile ? theme.typography.mobile.body : '1.125rem',
              fontWeight: isMobile ? 600 : 'bold',
              color: theme.colors.text.primary,
            }}
          >
            {displayPrice.toFixed(2)} ₪
          </span>
          {hasDiscount && (
            <span
              style={{
                fontSize: isMobile ? theme.typography.mobile.small : '0.875rem',
                color: theme.colors.text.muted,
                textDecoration: 'line-through',
              }}
            >
              {originalPrice.toFixed(2)} ₪
            </span>
          )}
        </div>
      </div>

      {/* Right: Add (+) Button */}
      <button
        onClick={() => onAddToCart(product)}
        style={{
          width: buttonSize,
          height: buttonSize,
          borderRadius: theme.borderRadius.lg,
          backgroundColor: '#FEF3C7', // Light yellow
          border: 'none',
          cursor: 'pointer',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          fontSize: isMobile ? '1.25rem' : '1.5rem',
          fontWeight: 'bold',
          color: '#F59E0B', // Dark yellow/orange
          flexShrink: 0,
          transition: 'background-color 0.2s ease',
        }}
        onMouseEnter={(e) => {
          if (!isMobile) {
            e.currentTarget.style.backgroundColor = '#FDE68A';
          }
        }}
        onMouseLeave={(e) => {
          if (!isMobile) {
            e.currentTarget.style.backgroundColor = '#FEF3C7';
          }
        }}
      >
        +
      </button>
    </div>
  );
}
