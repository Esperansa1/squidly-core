import React from 'react';
import theme from '../../config/theme';

/**
 * MobileUserHeader - User greeting section for mobile view
 * Displays user avatar and greeting at the top right (RTL)
 * NOT sticky - scrolls with content
 *
 * @param {string} username - User's name for the greeting
 */
export default function MobileUserHeader({ username = 'משתמש' }) {
  return (
    <div
      style={{
        display: 'flex',
        alignItems: 'center',
        gap: theme.spacing.sm,
        justifyContent: 'flex-end', // Right side in RTL
        padding: theme.spacing.mobile.md,
        paddingTop: theme.spacing.mobile.lg,
      }}
    >
      {/* Greeting text - Right side */}
      <span
        style={{
          fontSize: theme.typography.mobile.body,
          fontWeight: 400,
          color: theme.colors.text.secondary,
        }}
      >
        שלום, {username}
      </span>

      {/* User avatar icon - Far right */}
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
        <svg
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke={theme.colors.text.secondary}
          strokeWidth="2"
          strokeLinecap="round"
          strokeLinejoin="round"
        >
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
          <circle cx="12" cy="7" r="4" />
        </svg>
      </div>
    </div>
  );
}
