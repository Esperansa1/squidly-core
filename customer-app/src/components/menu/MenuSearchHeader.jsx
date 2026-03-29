import React from 'react';
import theme from '../../config/theme';

/**
 * MenuSearchHeader
 * "התפריט שלנו" title with an inline expanding search bar.
 * The search button sits at the far left; the input expands rightward.
 */
const MenuSearchHeader = ({
  showSearch,
  onToggleSearch,
  searchQuery,
  onSearch,
  isMobile = false,
}) => {
  const spacing   = isMobile ? theme.spacing.mobile : theme.spacing;
  const typography = isMobile ? theme.typography.mobile : theme.typography.desktop;
  const searchMaxWidth = isMobile ? '90%' : '20vw';
  const clearBtnLeft   = isMobile ? '6px' : '8px';
  const h2FontSize     = isMobile ? theme.typography.mobile.h2 : '1.5rem';
  const h2FontWeight   = isMobile ? 600 : 'bold';

  const containerStyle = isMobile
    ? {
        display: 'flex',
        alignItems: 'center',
        gap: theme.spacing.mobile.sm,
        padding: theme.spacing.mobile.md,
        paddingTop: theme.spacing.mobile.lg,
        paddingBottom: theme.spacing.mobile.sm,
      }
    : {
        display: 'flex',
        alignItems: 'center',
        gap: theme.spacing.sm,
        marginBottom: theme.spacing.md,
        flexShrink: 0,
      };

  return (
    <div style={containerStyle}>
      {/* Title fades when search is open */}
      <h2
        style={{
          fontSize: h2FontSize,
          fontWeight: h2FontWeight,
          color: theme.colors.text.primary,
          margin: 0,
          flex: 1,
          transition: 'opacity 0.25s ease',
          opacity: showSearch ? 0.4 : 1,
        }}
      >
        התפריט שלנו
      </h2>

      {/* Expanding input */}
      <div
        style={{
          flex: showSearch ? 1 : 0,
          maxWidth: showSearch ? searchMaxWidth : 0,
          overflow: 'hidden',
          transition: 'flex 0.3s ease, max-width 0.3s ease, opacity 0.3s ease',
          opacity: showSearch ? 1 : 0,
        }}
      >
        <div style={{ position: 'relative' }}>
          <input
            type="text"
            placeholder="חפש מוצרים..."
            value={searchQuery}
            onChange={(e) => onSearch(e.target.value)}
            autoFocus={showSearch}
            className="focus:ring-0 focus:ring-offset-0"
            style={{
              width: '100%',
              padding: `${spacing.xs} ${spacing.md}`,
              paddingLeft: '2rem',
              fontSize: typography.small,
              border: `1.5px solid ${theme.colors.border}`,
              borderRadius: theme.borderRadius.full,
              backgroundColor: theme.colors.cardBg,
              color: theme.colors.text.primary,
              textAlign: 'right',
              outline: 'none',
              boxShadow: 'none',
              boxSizing: 'border-box',
              transition: 'border-color 0.2s ease',
            }}
            onFocus={(e) => {
              e.target.style.borderColor = theme.colors.primary;
              e.target.style.boxShadow = 'none';
            }}
            onBlur={(e) => {
              e.target.style.borderColor = theme.colors.border;
            }}
          />
          {searchQuery && (
            <button
              onClick={() => onSearch('')}
              style={{
                position: 'absolute',
                left: clearBtnLeft,
                top: '50%',
                transform: 'translateY(-50%)',
                background: 'none',
                border: 'none',
                cursor: 'pointer',
                color: theme.colors.text.muted,
                padding: '2px',
                lineHeight: 1,
                fontSize: '0.75rem',
              }}
            >
              ✕
            </button>
          )}
        </div>
      </div>

      {/* Search toggle button (always at far left) */}
      <button
        onClick={onToggleSearch}
        style={{
          width: '36px',
          height: '36px',
          backgroundColor: showSearch ? theme.colors.primary : theme.colors.cardBg,
          border: 'none',
          borderRadius: '50%',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          cursor: 'pointer',
          boxShadow: theme.shadows.sm,
          transition: 'background-color 0.2s ease',
          flexShrink: 0,
        }}
      >
        <svg
          width="16"
          height="16"
          viewBox="0 0 24 24"
          fill="none"
          stroke={showSearch ? '#FFFFFF' : theme.colors.text.secondary}
          strokeWidth="2"
        >
          <circle cx="11" cy="11" r="8" />
          <path d="M21 21l-4.35-4.35" />
        </svg>
      </button>
    </div>
  );
};

export default MenuSearchHeader;
