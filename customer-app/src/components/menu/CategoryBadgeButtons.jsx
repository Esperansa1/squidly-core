import React from 'react';
import theme from '../../config/theme';

/**
 * CategoryBadgeButtons - Inline horizontally scrollable category badges for mobile
 * Badge-shaped buttons (NOT tabs), scrolls with content (NOT sticky)
 *
 * Active state: filled background
 * Inactive state: outline/border style
 *
 * @param {array} categories - Array of category objects with id, name, icon
 * @param {string} activeCategory - Currently active category ID
 * @param {function} onCategoryChange - Callback when category is selected
 */
export default function CategoryBadgeButtons({ categories, activeCategory, onCategoryChange }) {
  if (!categories || categories.length === 0) return null;

  return (
    <div
      style={{
        padding: theme.spacing.mobile.md,
        paddingTop: theme.spacing.mobile.sm,
        paddingBottom: theme.spacing.mobile.md,
      }}
    >
      {/* Horizontally scrollable container */}
      <div
        className="hide-scrollbar"
        style={{
          display: 'flex',
          gap: theme.spacing.mobile.sm,
          overflowX: 'auto',
          WebkitOverflowScrolling: 'touch', // iOS momentum scrolling
          scrollBehavior: 'smooth',
        }}
      >
        {categories.map((category) => {
          const isActive = activeCategory === category.id;

          return (
            <button
              key={category.id}
              onClick={() => onCategoryChange(category.id)}
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: theme.spacing.xs,
                padding: `${theme.spacing.mobile.sm} ${theme.spacing.md}`,
                backgroundColor: isActive ? theme.colors.primary : '#FFFFFF',
                color: isActive ? theme.colors.text.white : theme.colors.text.primary,
                border: `1px solid ${isActive ? theme.colors.primary : theme.colors.border}`,
                borderRadius: theme.borderRadius.full, // Pill shape
                fontSize: theme.typography.mobile.small,
                fontWeight: isActive ? 600 : 400,
                cursor: 'pointer',
                whiteSpace: 'nowrap', // Keep text on one line
                flexShrink: 0, // Don't shrink badges
                minHeight: theme.layout.minTouchTarget, // 44px touch target
                transition: 'all 0.2s ease',
                boxShadow: isActive ? theme.shadows.sm : theme.shadows.sm,
              }}
              onMouseEnter={(e) => {
                if (!isActive) {
                  e.currentTarget.style.borderColor = theme.colors.primary;
                  e.currentTarget.style.color = theme.colors.primary;
                }
              }}
              onMouseLeave={(e) => {
                if (!isActive) {
                  e.currentTarget.style.borderColor = theme.colors.border;
                  e.currentTarget.style.color = theme.colors.text.primary;
                }
              }}
            >
              {/* Category icon */}
              {category.icon && <span style={{ fontSize: '1.125rem' }}>{category.icon}</span>}

              {/* Category name */}
              <span>{category.name}</span>
            </button>
          );
        })}
      </div>
    </div>
  );
}
