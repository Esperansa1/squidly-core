import React, { useCallback } from 'react';
import theme from '../../config/theme';
import { useAuth } from '../../contexts/AuthContext';

/**
 * MenuSidebar - Navigation sidebar matching admin-app style
 * Dynamically populated with product categories
 * Features: Logo, search, category navigation, toggle functionality
 */
const MenuSidebar = React.memo(function MenuSidebar({ activeCategory, onCategoryChange, categories, isExpanded = true, onToggle, onLoginClick }) {
  const { customer, isAuthenticated, logout } = useAuth();
  const toggleSidebar = useCallback(() => {
    if (onToggle) {
      onToggle(!isExpanded);
    }
  }, [onToggle, isExpanded]);

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
        {category.icon && <span style={{ width: '22px', height: '22px', flexShrink: 0, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>{category.icon}</span>}
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
              <div className="relative w-full">
                <div className="absolute right-3 top-1/2 transform -translate-y-1/2 pointer-events-none">
                  <svg className="w-4 h-4" style={{ color: theme.colors.text.muted }} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <circle cx="11" cy="11" r="8" />
                    <path d="M21 21l-4.35-4.35" />
                  </svg>
                </div>
                <input
                  type="text"
                  placeholder="חיפוש"
                  className="w-full border rounded-lg transition-all duration-200 outline-none px-3 py-1.5 text-sm pr-9 focus:ring-2 focus:border-transparent"
                  style={{
                    borderColor: theme.colors.border,
                    color: theme.colors.text.primary,
                    '--tw-ring-color': theme.colors.primary,
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
                    fontWeight: '700',
                    margin: 0,
                  }}
                >
                  התפריט שלנו
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

          {/* Bottom Section - Auth / User Info */}
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
              {isAuthenticated ? (
                /* === Logged-in state === */
                isExpanded ? (
                  <div
                    style={{
                      width: '100%',
                      display: 'flex',
                      flexDirection: 'row',
                      alignItems: 'center',
                      gap: theme.spacing.md,
                      padding: theme.spacing.md,
                      direction: 'rtl',
                    }}
                  >
                    {/* User avatar */}
                    <div
                      style={{
                        width: '48px',
                        height: '48px',
                        borderRadius: '50%',
                        backgroundColor: theme.colors.primary,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        flexShrink: 0,
                      }}
                    >
                      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                        <circle cx="12" cy="7" r="4" />
                      </svg>
                    </div>

                    {/* User info + logout */}
                    <div
                      style={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: theme.spacing.xs,
                        flex: 1,
                      }}
                    >
                      <p
                        style={{
                          fontSize: '0.8rem',
                          color: theme.colors.text.primary,
                          margin: 0,
                          lineHeight: '1.5',
                          fontWeight: '600',
                        }}
                      >
                        שלום, {customer?.first_name}
                      </p>
                      <button
                        style={{
                          backgroundColor: 'transparent',
                          border: 'none',
                          borderBottom: `2px solid ${theme.colors.error || '#DC2626'}`,
                          color: theme.colors.error || '#DC2626',
                          fontSize: '0.75rem',
                          fontWeight: '700',
                          padding: '0 0 2px 0',
                          cursor: 'pointer',
                          textAlign: 'right',
                          alignSelf: 'flex-start',
                        }}
                        onClick={logout}
                      >
                        התנתק
                      </button>
                    </div>
                  </div>
                ) : (
                  <div
                    style={{
                      width: '32px',
                      height: '32px',
                      borderRadius: '50%',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      backgroundColor: theme.colors.primary,
                      cursor: 'pointer',
                    }}
                    title={`שלום, ${customer?.first_name}`}
                    onClick={logout}
                  >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                      <circle cx="12" cy="7" r="4" />
                    </svg>
                  </div>
                )
              ) : (
                /* === Not logged-in state === */
                isExpanded ? (
                  <div
                    style={{
                      width: '100%',
                      display: 'flex',
                      flexDirection: 'row',
                      alignItems: 'center',
                      gap: theme.spacing.md,
                      padding: theme.spacing.md,
                      direction: 'rtl',
                    }}
                  >
                    {/* Mascot / food illustration */}
                    <div
                      style={{
                        width: '48px',
                        height: '48px',
                        borderRadius: '50%',
                        backgroundColor: '#FEF3C7',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        flexShrink: 0,
                      }}
                    >
                      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
                        <path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2" />
                        <path d="M7 2v20" />
                        <path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3zm0 0v7" />
                      </svg>
                    </div>

                    {/* CTA text + button */}
                    <div
                      style={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: theme.spacing.xs,
                        flex: 1,
                      }}
                    >
                      <p
                        style={{
                          fontSize: '0.8rem',
                          color: theme.colors.text.primary,
                          margin: 0,
                          lineHeight: '1.5',
                          fontWeight: '600',
                        }}
                      >
                        עדיין לא הצטרפתם למועדון?
                      </p>
                      <button
                        style={{
                          backgroundColor: 'transparent',
                          border: 'none',
                          borderBottom: `2px solid ${theme.colors.primary}`,
                          color: theme.colors.primary,
                          fontSize: '0.75rem',
                          fontWeight: '700',
                          padding: '0 0 2px 0',
                          cursor: 'pointer',
                          textAlign: 'right',
                          alignSelf: 'flex-start',
                        }}
                        onClick={onLoginClick}
                      >
                        להצטרפות בחינם
                      </button>
                    </div>
                  </div>
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
                      cursor: 'pointer',
                    }}
                    title="הצטרפות למועדון"
                    onClick={onLoginClick}
                  >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke={theme.colors.primary} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                      <circle cx="8.5" cy="7" r="4" />
                      <line x1="20" y1="8" x2="20" y2="14" />
                      <line x1="23" y1="11" x2="17" y2="11" />
                    </svg>
                  </div>
                )
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
});

export default MenuSidebar;
