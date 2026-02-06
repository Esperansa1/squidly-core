import React from 'react';
import theme from '../../config/theme';

/**
 * CartItemDisplay - Cart item summary component
 * Information hierarchy: Name (primary) → Customizations → Total Price → Quantity → Unit Price
 * Design principle: This is a confirmation component, not a sales component
 *
 * @param {object} item - Cart item object
 * @param {boolean} compact - Use compact mobile styling (optional)
 * @param {function} onItemClick - Callback when item is clicked to review/edit
 * @param {function} onDelete - Callback when delete icon is clicked
 */
export default function CartItemDisplay({ item, compact = false, onItemClick, onDelete }) {
  const imageSize = compact ? '56px' : '64px'; // Square image as visual anchor
  const unitPrice = item.price;
  const totalPrice = item.price * item.quantity;

  // Detect RTL mode to ensure image stays on the right
  const isRTL = document.dir === 'rtl' || document.documentElement.dir === 'rtl';

  const handleClick = (e) => {
    // Don't trigger if clicking delete button
    if (e.target.closest('[data-delete-button]')) {
      return;
    }
    if (onItemClick) {
      onItemClick(item);
    }
  };

  const handleDelete = (e) => {
    e.stopPropagation();
    if (onDelete) {
      onDelete(item);
    }
  };

  return (
    <div
      onClick={handleClick}
      style={{
        position: 'relative', // For absolute positioned delete button
        display: 'flex',
        flexDirection: isRTL ? 'row-reverse' : 'row',
        alignItems: 'center',
        gap: '8px',
        padding: compact ? '8px' : '12px',
        backgroundColor: '#FFFFFF',
        borderRadius: compact ? '12px' : '14px', // Soft, not bubbly
        boxShadow: '0 1px 3px rgba(0, 0, 0, 0.06)', // Subtle, touchable
        width: '100%',
        minHeight: 'fit-content',
        cursor: onItemClick ? 'pointer' : 'default',
        transition: 'all 0.15s ease',
      }}
      onMouseEnter={(e) => {
        if (onItemClick) {
          e.currentTarget.style.boxShadow = '0 2px 8px rgba(0, 0, 0, 0.1)';
          e.currentTarget.style.transform = 'translateY(-1px)';
        }
      }}
      onMouseLeave={(e) => {
        if (onItemClick) {
          e.currentTarget.style.boxShadow = '0 1px 3px rgba(0, 0, 0, 0.06)';
          e.currentTarget.style.transform = 'translateY(0)';
        }
      }}
    >
      {/* Content Stack - Takes available space */}
      <div
        style={{
          flex: 1,
          display: 'flex',
          flexDirection: 'column',
          gap: compact ? '6px' : '8px',
          minWidth: 0, // Prevent text overflow
          textAlign: isRTL ? 'right' : 'left',
        }}
      >
        {/* 1. Product Name - Visual Hero (only loud element) */}
        <div
          style={{
            fontSize: compact ? '1.0625rem' : '1.125rem', // 17px / 18px
            fontWeight: 600,
            color: theme.colors.text.primary,
            lineHeight: '1.3',
            letterSpacing: '-0.01em',
          }}
        >
          {item.name}
        </div>

        {/* 2. Customizations - Context, secondary */}
        {/* Handles both array format (from backend) and object format (legacy local) */}
        {item.customizations && (
          Array.isArray(item.customizations) ? (
            // Array format from backend: [{name, price_modifier}, ...]
            item.customizations.length > 0 && (
              <div
                style={{
                  fontSize: compact ? '0.8125rem' : '0.875rem',
                  color: theme.colors.text.secondary,
                  lineHeight: '1.4',
                  opacity: 0.85,
                }}
              >
                {item.customizations.map(customItem => customItem.name).join(', ')}
              </div>
            )
          ) : (
            // Object format (legacy): {groupId: [{name, price}, ...], ...}
            Object.keys(item.customizations).length > 0 && (
              <div
                style={{
                  fontSize: compact ? '0.8125rem' : '0.875rem',
                  color: theme.colors.text.secondary,
                  lineHeight: '1.4',
                  opacity: 0.85,
                }}
              >
                {Object.values(item.customizations).map((groupItems, idx) => (
                  <div key={idx} style={{ marginBottom: '2px' }}>
                    {groupItems.map(customItem => customItem.name).join(', ')}
                  </div>
                ))}
              </div>
            )
          )
        )}

        {/* 3. Price Logic: Quantity × Unit = Total (visual equation) */}
        <div
          style={{
            display: 'flex',
            alignItems: 'baseline',
            gap: '8px',
            marginTop: 'auto',
            flexWrap: 'wrap',
          }}
        >
          {/* Total Price - Bold, resolved */}
          <div
            style={{
              fontSize: compact ? '1rem' : '1.0625rem', // 16px / 17px
              fontWeight: 700,
              color: theme.colors.text.primary,
              letterSpacing: '-0.01em',
            }}
          >
            ₪{totalPrice.toFixed(2)}
          </div>

          {/* Quantity × Unit Price - Informational, not dominant */}
          <div
            style={{
              fontSize: compact ? '0.8125rem' : '0.875rem', // 13px / 14px
              fontWeight: 400,
              color: theme.colors.text.secondary,
              display: 'flex',
              alignItems: 'baseline',
              gap: '4px',
            }}
          >
            <span>({item.quantity}</span>
            <span>×</span>
            <span>₪{unitPrice.toFixed(2)})</span>
          </div>
        </div>
      </div>

      {/* Image - Anchor, not hero */}
      <div
        style={{
          width: imageSize,
          height: imageSize,
          flexShrink: 0,
          borderRadius: compact ? '8px' : '10px',
          overflow: 'hidden',
          backgroundColor: theme.colors.background,
          opacity: 0.95, // Slightly muted presence
        }}
      >
        {item.image_url ? (
          <img
            src={item.image_url}
            alt={item.name}
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
              fontSize: compact ? '1.5rem' : '1.75rem',
            }}
          >
            🍽️
          </div>
        )}
      </div>

      {/* Delete Button - Left side (44x44px touch target) */}
      {onDelete && (
        <button
          data-delete-button
          onClick={handleDelete}
          aria-label={`הסר ${item.name} מהעגלה`}
          style={{
            position: 'absolute',
            [isRTL ? 'left' : 'right']: '8px',
            top: '50%',
            transform: 'translateY(-50%)',
            width: '44px',
            height: '44px',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            backgroundColor: 'transparent',
            border: 'none',
            borderRadius: '50%',
            cursor: 'pointer',
            transition: 'all 0.2s ease',
            padding: 0,
          }}
          onMouseEnter={(e) => {
            e.currentTarget.style.backgroundColor = '#FEE2E2';
          }}
          onMouseLeave={(e) => {
            e.currentTarget.style.backgroundColor = 'transparent';
          }}
        >
          {/* Trash Icon SVG */}
          <svg
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            style={{
              color: '#6B7280',
              transition: 'color 0.2s ease',
            }}
            onMouseEnter={(e) => {
              e.currentTarget.style.color = '#DC2626';
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.color = '#6B7280';
            }}
          >
            <polyline points="3 6 5 6 21 6" />
            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
            <line x1="10" y1="11" x2="10" y2="17" />
            <line x1="14" y1="11" x2="14" y2="17" />
          </svg>
        </button>
      )}
    </div>
  );
}
