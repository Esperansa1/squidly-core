import React from 'react';
import theme from '../../config/theme';

/**
 * MenuSidebar - Navigation sidebar matching admin-app style
 * Dynamically populated with product categories
 * Features: Logo, search, category navigation, toggle functionality
 */
export default function MenuSidebar({ activeCategory, onCategoryChange, categories, isExpanded = true, onToggle }) {
  const toggleSidebar = () => {
    if (onToggle) {
      onToggle(!isExpanded);
    }
  };

  const NavItem = ({ category, isActive }) => {
    return (
      <button
        onClick={() => onCategoryChange(category.id)}
        style={{
          width: '100%',
          display: 'flex',
          alignItems: 'center',
          gap: theme.spacing.sm,
          padding: `${theme.spacing.sm} ${theme.spacing.md}`,
          backgroundColor: isActive ? theme.colors.primary : 'transparent',
          color: isActive ? theme.colors.text.white : theme.colors.text.primary,
          border: 'none',
          borderRadius: theme.borderRadius.md,
          textAlign: 'right',
          cursor: 'pointer',
          justifyContent: isExpanded ? 'flex-start' : 'center',
          transition: 'all 0.2s ease',
          fontWeight: isActive ? '600' : '400',
          fontSize: '0.875rem',
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
        {category.icon && <span style={{ fontSize: '1.25rem', flexShrink: 0 }}>{category.icon}</span>}
        {isExpanded && <span>{category.name}</span>}
      </button>
    );
  };

  const SectionDivider = () => (
    <div style={{ borderTop: `1px solid ${theme.colors.border}`, margin: `${theme.spacing.sm} 0` }} />
  );

  return (
    <div style={{ position: 'relative' }}>
      <div
        style={{
          width: isExpanded ? '280px' : '70px',
          backgroundColor: theme.colors.cardBg,
          border: `1px solid ${theme.colors.border}`,
          boxShadow: theme.shadows.sm,
          transition: 'all 0.3s ease-out',
          height: '100%',
          borderRadius: theme.borderRadius.xl,
          position: 'relative',
        }}
      >
        {/* Toggle Button */}
        <button
          onClick={toggleSidebar}
          style={{
            position: 'absolute',
            top: theme.spacing.md,
            left: theme.spacing.md,
            width: '32px',
            height: '32px',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            background: 'transparent',
            border: 'none',
            cursor: 'pointer',
            color: theme.colors.text.secondary,
            transition: 'color 0.2s ease',
          }}
          onMouseEnter={(e) => {
            e.currentTarget.style.color = theme.colors.text.primary;
          }}
          onMouseLeave={(e) => {
            e.currentTarget.style.color = theme.colors.text.secondary;
          }}
        >
          {isExpanded ? (
            // Chevron Right Icon
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path strokeLinecap="round" strokeLinejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
          ) : (
            // Bars Icon
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
          )}
        </button>

        <div
          style={{
            paddingTop: '4rem',
            padding: theme.spacing.md,
            height: '100%',
            display: 'flex',
            flexDirection: 'column',
          }}
        >
          {/* Logo Area */}
          <div
            style={{
              display: 'flex',
              alignItems: 'center',
              gap: theme.spacing.sm,
              marginBottom: theme.spacing.lg,
              justifyContent: isExpanded ? 'flex-start' : 'center',
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
                flexShrink: 0,
              }}
            >
              <div
                style={{
                  width: '24px',
                  height: '2px',
                  backgroundColor: 'white',
                  transform: 'rotate(45deg)',
                }}
              />
              <div
                style={{
                  width: '24px',
                  height: '2px',
                  backgroundColor: 'white',
                  transform: 'rotate(-45deg)',
                  marginLeft: '-24px',
                }}
              />
            </div>
            {isExpanded && (
              <span
                style={{
                  fontSize: '1.25rem',
                  fontWeight: '800',
                  color: theme.colors.text.primary,
                }}
              >
                DeliGO
              </span>
            )}
          </div>

          {/* Search Bar */}
          <div
            style={{
              marginBottom: theme.spacing.lg,
              display: 'flex',
              justifyContent: isExpanded ? 'flex-start' : 'center',
            }}
          >
            {isExpanded ? (
              <div style={{ position: 'relative', width: '100%' }}>
                <svg
                  style={{
                    position: 'absolute',
                    right: theme.spacing.sm,
                    top: '50%',
                    transform: 'translateY(-50%)',
                    width: '16px',
                    height: '16px',
                    color: theme.colors.text.muted,
                  }}
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2"
                >
                  <circle cx="11" cy="11" r="8" />
                  <path d="M21 21l-4.35-4.35" />
                </svg>
                <input
                  type="text"
                  placeholder="חיפוש"
                  style={{
                    width: '100%',
                    paddingLeft: theme.spacing.md,
                    paddingRight: '2.5rem',
                    paddingTop: theme.spacing.sm,
                    paddingBottom: theme.spacing.sm,
                    backgroundColor: theme.colors.background,
                    borderRadius: theme.borderRadius.lg,
                    border: 'none',
                    fontSize: '0.875rem',
                    color: theme.colors.text.primary,
                    outline: 'none',
                  }}
                  onFocus={(e) => {
                    e.currentTarget.style.backgroundColor = theme.colors.cardBg;
                    e.currentTarget.style.boxShadow = `0 0 0 2px ${theme.colors.primary}`;
                  }}
                  onBlur={(e) => {
                    e.currentTarget.style.backgroundColor = theme.colors.background;
                    e.currentTarget.style.boxShadow = 'none';
                  }}
                />
              </div>
            ) : (
              <div
                style={{
                  width: '40px',
                  height: '40px',
                  backgroundColor: theme.colors.background,
                  borderRadius: theme.borderRadius.lg,
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                }}
              >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke={theme.colors.text.muted} strokeWidth="2">
                  <circle cx="11" cy="11" r="8" />
                  <path d="M21 21l-4.35-4.35" />
                </svg>
              </div>
            )}
          </div>

          {/* Main Content - Takes remaining space */}
          <div style={{ flexGrow: 1 }}>
            {/* Section Title */}
            {isExpanded && (
              <div style={{ padding: `${theme.spacing.sm} ${theme.spacing.md}` }}>
                <h3
                  style={{
                    fontSize: '0.75rem',
                    color: theme.colors.text.secondary,
                    textTransform: 'uppercase',
                    letterSpacing: '0.05em',
                    fontWeight: '600',
                    margin: 0,
                  }}
                >
                  קטגוריות התפריט
                </h3>
              </div>
            )}

            {/* Category Navigation */}
            <nav style={{ display: 'flex', flexDirection: 'column', gap: theme.spacing.xs }}>
              {categories.map((category) => (
                <NavItem key={category.id} category={category} isActive={activeCategory === category.id} />
              ))}
            </nav>
          </div>

          {/* Bottom Section */}
          <div style={{ flexShrink: 0 }}>
            <SectionDivider />
            <div
              style={{
                paddingTop: theme.spacing.md,
                paddingBottom: theme.spacing.md,
                display: 'flex',
                justifyContent: isExpanded ? 'flex-start' : 'center',
              }}
            >
              {isExpanded ? (
                <button
                  style={{
                    width: '100%',
                    display: 'flex',
                    alignItems: 'center',
                    gap: theme.spacing.sm,
                    padding: `${theme.spacing.sm} ${theme.spacing.md}`,
                    color: theme.colors.text.primary,
                    background: 'transparent',
                    border: 'none',
                    borderRadius: theme.borderRadius.lg,
                    cursor: 'pointer',
                    transition: 'background-color 0.2s ease',
                  }}
                  onMouseEnter={(e) => {
                    e.currentTarget.style.backgroundColor = theme.colors.background;
                  }}
                  onMouseLeave={(e) => {
                    e.currentTarget.style.backgroundColor = 'transparent';
                  }}
                >
                  <div
                    style={{
                      width: '32px',
                      height: '32px',
                      borderRadius: '50%',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      flexShrink: 0,
                      backgroundColor: `${theme.colors.primary}33`,
                    }}
                  >
                    <span style={{ fontSize: '0.875rem', fontWeight: '800', color: theme.colors.primary }}>א</span>
                  </div>
                  <div style={{ flex: 1, textAlign: 'right' }}>
                    <div style={{ fontSize: '0.875rem', color: theme.colors.text.primary, fontWeight: '800' }}>
                      אורח
                    </div>
                    <div style={{ fontSize: '0.75rem', color: theme.colors.text.secondary, fontWeight: '400' }}>
                      לקוח
                    </div>
                  </div>
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke={theme.colors.text.muted} strokeWidth="2">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                  </svg>
                </button>
              ) : (
                <div
                  style={{
                    width: '32px',
                    height: '32px',
                    borderRadius: '50%',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    backgroundColor: `${theme.colors.primary}33`,
                  }}
                >
                  <span style={{ fontSize: '0.875rem', fontWeight: '800', color: theme.colors.primary }}>א</span>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
