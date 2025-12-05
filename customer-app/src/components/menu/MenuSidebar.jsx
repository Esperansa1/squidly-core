import React from 'react';
import theme from '../../config/theme';

/**
 * MenuSidebar - Right navigation sidebar with logo and menu categories
 * Shows active category and character illustration at bottom
 */
export default function MenuSidebar({ activeCategory, onCategoryChange, categories }) {
  return (
    <div
      style={{
        width: theme.layout.sidebarWidth,
        backgroundColor: theme.colors.cardBg,
        borderRadius: theme.borderRadius.xl,
        padding: theme.spacing.lg,
        boxShadow: theme.shadows.card,
        display: 'flex',
        flexDirection: 'column',
        gap: theme.spacing.md,
        height: 'fit-content',
        position: 'sticky',
        top: theme.spacing.lg,
      }}
    >
      {/* Logo */}
      <div
        style={{
          display: 'flex',
          alignItems: 'center',
          gap: theme.spacing.sm,
          marginBottom: theme.spacing.md,
          paddingBottom: theme.spacing.md,
          borderBottom: `1px solid ${theme.colors.border}`,
        }}
      >
        <div
          style={{
            width: '40px',
            height: '40px',
            backgroundColor: theme.colors.primary,
            borderRadius: theme.borderRadius.md,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            color: 'white',
            fontSize: '1.5rem',
            transform: 'rotate(-15deg)',
          }}
        >
          🍕
        </div>
        <span
          style={{
            fontSize: '1.5rem',
            fontWeight: 'bold',
            color: theme.colors.text.primary,
          }}
        >
          DeliGO
        </span>
      </div>

      {/* "Our Menu" Header */}
      <div
        style={{
          padding: `${theme.spacing.sm} ${theme.spacing.md}`,
          backgroundColor: theme.colors.primary,
          color: theme.colors.text.white,
          borderRadius: theme.borderRadius.md,
          fontWeight: 'bold',
          textAlign: 'center',
        }}
      >
        המפריט שלנו
      </div>

      {/* Category Navigation */}
      <nav style={{ display: 'flex', flexDirection: 'column', gap: theme.spacing.xs }}>
        {categories.map((category) => {
          const isActive = activeCategory === category.id;
          return (
            <button
              key={category.id}
              onClick={() => onCategoryChange(category.id)}
              style={{
                padding: `${theme.spacing.sm} ${theme.spacing.md}`,
                backgroundColor: isActive ? theme.colors.primary : 'transparent',
                color: isActive ? theme.colors.text.white : theme.colors.text.primary,
                border: 'none',
                borderRadius: theme.borderRadius.md,
                textAlign: 'right',
                cursor: 'pointer',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'flex-end',
                gap: theme.spacing.sm,
                transition: 'all 0.2s ease',
                fontWeight: isActive ? 'bold' : 'normal',
              }}
              onMouseEnter={(e) => {
                if (!isActive) {
                  e.currentTarget.style.backgroundColor = theme.colors.background;
                }
              }}
              onMouseLeave={(e) => {
                if (!isActive) {
                  e.currentTarget.style.backgroundColor = 'transparent';
                }
              }}
            >
              <span>{category.name}</span>
              {category.icon && <span>{category.icon}</span>}
            </button>
          );
        })}
      </nav>

      {/* Character Illustration Placeholder */}
      <div
        style={{
          marginTop: 'auto',
          paddingTop: theme.spacing.lg,
          borderTop: `1px solid ${theme.colors.border}`,
        }}
      >
        <div
          style={{
            width: '100%',
            height: '150px',
            backgroundColor: theme.colors.background,
            borderRadius: theme.borderRadius.lg,
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            justifyContent: 'center',
            padding: theme.spacing.md,
            textAlign: 'center',
          }}
        >
          <div style={{ fontSize: '3rem', marginBottom: theme.spacing.sm }}>
            👩‍🍳
          </div>
          <p
            style={{
              fontSize: '0.75rem',
              color: theme.colors.text.secondary,
              margin: 0,
            }}
          >
            אנחנו לא מתאימים למישהו?
            <br />
            לחצטרט בחינם
          </p>
        </div>
      </div>
    </div>
  );
}
