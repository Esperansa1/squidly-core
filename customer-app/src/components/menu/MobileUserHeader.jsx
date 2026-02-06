import React, { useState } from 'react';
import theme from '../../config/theme';
import { useAuth } from '../../contexts/AuthContext';
import UserMenu from '../auth/UserMenu';
import AuthModal from '../auth/AuthModal';

/**
 * MobileUserHeader - User greeting section for mobile view
 * Shows login button when not authenticated, user name when authenticated
 * NOT sticky - scrolls with content
 */
export default function MobileUserHeader() {
  const { isAuthenticated, loading } = useAuth();
  const [authModalOpen, setAuthModalOpen] = useState(false);

  if (loading) {
    return (
      <div
        style={{
          display: 'flex',
          alignItems: 'center',
          gap: theme.spacing.sm,
          justifyContent: 'flex-end',
          padding: theme.spacing.mobile.md,
          paddingTop: theme.spacing.mobile.lg,
        }}
      >
        <div
          style={{
            width: '40px',
            height: '40px',
            borderRadius: '50%',
            backgroundColor: theme.colors.background,
          }}
        />
      </div>
    );
  }

  return (
    <>
      <div
        style={{
          display: 'flex',
          alignItems: 'center',
          gap: theme.spacing.sm,
          justifyContent: 'flex-end',
          padding: theme.spacing.mobile.md,
          paddingTop: theme.spacing.mobile.lg,
        }}
      >
        <UserMenu onLoginClick={() => setAuthModalOpen(true)} />
      </div>

      <AuthModal isOpen={authModalOpen} onClose={() => setAuthModalOpen(false)} />
    </>
  );
}
