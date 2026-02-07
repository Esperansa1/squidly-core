import React, { useState, useRef, useEffect } from 'react';
import { useAuth } from '../../contexts/AuthContext';
import theme from '../../config/theme';

/**
 * UserMenu — Displays login button or user dropdown when authenticated
 * Used in both mobile header and desktop sidebar
 */
export default function UserMenu({ onLoginClick }) {
  const { customer, isAuthenticated, logout } = useAuth();
  const [dropdownOpen, setDropdownOpen] = useState(false);
  const dropdownRef = useRef(null);

  // Close dropdown on outside click
  useEffect(() => {
    if (!dropdownOpen) return;

    const handleClickOutside = (e) => {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target)) {
        setDropdownOpen(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [dropdownOpen]);

  const handleLogout = () => {
    setDropdownOpen(false);
    logout();
  };

  // User avatar icon
  const AvatarIcon = () => (
    <svg
      width="20"
      height="20"
      viewBox="0 0 24 24"
      fill="none"
      stroke={isAuthenticated ? '#FFFFFF' : theme.colors.text.secondary}
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
      <circle cx="12" cy="7" r="4" />
    </svg>
  );

  if (!isAuthenticated) {
    return (
      <button
        onClick={onLoginClick}
        style={{
          display: 'flex',
          alignItems: 'center',
          gap: theme.spacing.sm,
          background: 'none',
          border: 'none',
          cursor: 'pointer',
          padding: 0,
        }}
      >
        <span
          style={{
            fontSize: theme.typography.mobile.body,
            fontWeight: 400,
            color: theme.colors.primary,
          }}
        >
          התחברו
        </span>
        <div
          style={{
            width: '40px',
            height: '40px',
            borderRadius: '50%',
            backgroundColor: theme.colors.background,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            boxShadow: theme.shadows.sm,
          }}
        >
          <AvatarIcon />
        </div>
      </button>
    );
  }

  // Authenticated state — name + avatar with dropdown
  return (
    <div ref={dropdownRef} style={{ position: 'relative' }}>
      <button
        onClick={() => setDropdownOpen(!dropdownOpen)}
        style={{
          display: 'flex',
          alignItems: 'center',
          gap: theme.spacing.sm,
          background: 'none',
          border: 'none',
          cursor: 'pointer',
          padding: 0,
        }}
      >
        <span
          style={{
            fontSize: theme.typography.mobile.body,
            fontWeight: 500,
            color: theme.colors.text.primary,
          }}
        >
          שלום, {customer.first_name}
        </span>
        <div
          style={{
            width: '40px',
            height: '40px',
            borderRadius: '50%',
            backgroundColor: theme.colors.primary,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            boxShadow: theme.shadows.sm,
          }}
        >
          <AvatarIcon />
        </div>
      </button>

      {/* Dropdown menu */}
      {dropdownOpen && (
        <div
          style={{
            position: 'absolute',
            top: '48px',
            left: 0,
            backgroundColor: '#FFFFFF',
            borderRadius: theme.borderRadius.lg,
            boxShadow: theme.shadows.lg,
            minWidth: '180px',
            zIndex: 100,
            overflow: 'hidden',
            border: `1px solid ${theme.colors.border}`,
          }}
        >
          <button
            onClick={handleLogout}
            style={{
              width: '100%',
              display: 'flex',
              alignItems: 'center',
              gap: theme.spacing.sm,
              padding: `${theme.spacing.sm} ${theme.spacing.md}`,
              background: 'none',
              border: 'none',
              cursor: 'pointer',
              fontSize: '0.875rem',
              color: theme.colors.error,
              textAlign: 'right',
            }}
          >
            <svg
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
              <polyline points="16 17 21 12 16 7" />
              <line x1="21" y1="12" x2="9" y2="12" />
            </svg>
            התנתק
          </button>
        </div>
      )}
    </div>
  );
}
