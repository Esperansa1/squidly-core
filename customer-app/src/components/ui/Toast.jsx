import React, { useEffect, useState } from 'react';
import theme from '../../config/theme';
import { getCurrentLanguage } from '../../i18n/translations';

/**
 * Toast Notification Component
 *
 * Fixed position at top-left
 * Supports success/error/warning types
 * Auto-dismiss after 4 seconds
 * RTL support
 * Responsive sizing (bigger on desktop)
 */
export default function Toast({ message, type = 'info', onClose, duration = 4000 }) {
  const [isVisible, setIsVisible] = useState(false);
  const [isExiting, setIsExiting] = useState(false);
  const direction = (getCurrentLanguage() === 'he' || getCurrentLanguage() === 'ar') ? 'rtl' : 'ltr';

  // Get colors and icon based on type
  const getTypeStyles = () => {
    switch (type) {
      case 'success':
        return {
          background: '#10B981',
          icon: '✓',
        };
      case 'error':
        return {
          background: '#EF4444',
          icon: '!',
        };
      case 'warning':
        return {
          background: '#F59E0B',
          icon: '!',
        };
      default:
        return {
          background: '#3B82F6',
          icon: 'i',
        };
    }
  };

  const typeStyles = getTypeStyles();

  // Animate in on mount
  useEffect(() => {
    // Small delay to trigger animation
    const showTimer = setTimeout(() => setIsVisible(true), 10);

    // Auto dismiss
    const hideTimer = setTimeout(() => {
      setIsExiting(true);
      setTimeout(() => {
        onClose?.();
      }, 300);
    }, duration);

    return () => {
      clearTimeout(showTimer);
      clearTimeout(hideTimer);
    };
  }, [duration, onClose]);

  const handleClose = () => {
    setIsExiting(true);
    setTimeout(() => {
      onClose?.();
    }, 300);
  };

  return (
    <div
      style={{
        display: 'flex',
        alignItems: 'center',
        gap: theme.spacing.md,
        padding: `${theme.spacing.md} ${theme.spacing.lg}`,
        backgroundColor: typeStyles.background,
        color: theme.colors.text.white,
        borderRadius: theme.borderRadius.lg,
        boxShadow: '0 10px 25px rgba(0, 0, 0, 0.2)',
        direction: direction,
        opacity: isVisible && !isExiting ? 1 : 0,
        transform: `translateY(${isVisible && !isExiting ? '0' : '-20px'})`,
        transition: 'all 0.3s ease',
        maxWidth: 'calc(100vw - 48px)',
        minWidth: '280px',
      }}
      onClick={handleClose}
      role="alert"
      aria-live="assertive"
    >
      {/* Icon */}
      <div
        style={{
          width: '32px',
          height: '32px',
          borderRadius: theme.borderRadius.full,
          backgroundColor: 'rgba(255, 255, 255, 0.2)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          fontSize: '1.125rem',
          fontWeight: 'bold',
          flexShrink: 0,
        }}
      >
        {typeStyles.icon}
      </div>

      {/* Message */}
      <span
        style={{
          fontSize: '1.0625rem',
          fontWeight: '500',
          flex: 1,
          lineHeight: 1.4,
        }}
      >
        {message}
      </span>

      {/* Close button */}
      <button
        onClick={(e) => {
          e.stopPropagation();
          handleClose();
        }}
        style={{
          background: 'rgba(255, 255, 255, 0.15)',
          border: 'none',
          color: 'rgba(255, 255, 255, 0.9)',
          cursor: 'pointer',
          width: '28px',
          height: '28px',
          borderRadius: theme.borderRadius.full,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          fontSize: '1.25rem',
          fontWeight: '300',
          flexShrink: 0,
          transition: 'background-color 0.2s ease',
        }}
        onMouseEnter={(e) => {
          e.currentTarget.style.backgroundColor = 'rgba(255, 255, 255, 0.25)';
        }}
        onMouseLeave={(e) => {
          e.currentTarget.style.backgroundColor = 'rgba(255, 255, 255, 0.15)';
        }}
        aria-label="Close notification"
      >
        ×
      </button>
    </div>
  );
}
