import React, { useState, useEffect } from 'react';
import XMarkIcon from '@heroicons/react/24/outline/XMarkIcon';
import ExclamationTriangleIcon from '@heroicons/react/24/outline/ExclamationTriangleIcon';
import CheckCircleIcon from '@heroicons/react/24/outline/CheckCircleIcon';
import InformationCircleIcon from '@heroicons/react/24/outline/InformationCircleIcon';
import { DEFAULT_THEME } from '../../config/theme.js';

const Toast = ({
  message = '',
  type = 'error', // 'error', 'success', 'info', 'warning'
  isVisible = false,
  onClose = () => {},
  duration = 3000, // 3 seconds default
  position = 'top-right' // 'top-right', 'top-left', 'bottom-right', 'bottom-left', 'top-center', 'bottom-center'
}) => {
  const [show, setShow] = useState(false);
  const theme = DEFAULT_THEME;

  useEffect(() => {
    if (isVisible) {
      setShow(true);

      // Auto-hide after duration
      const timer = setTimeout(() => {
        handleClose();
      }, duration);

      return () => clearTimeout(timer);
    } else {
      setShow(false);
    }
  }, [isVisible, duration]);

  const handleClose = () => {
    setShow(false);
    onClose();
  };

  if (!show) return null;

  // Icon based on type
  const getIcon = () => {
    switch (type) {
      case 'success':
        return <CheckCircleIcon className="w-5 h-5 flex-shrink-0" />;
      case 'warning':
        return <ExclamationTriangleIcon className="w-5 h-5 flex-shrink-0" />;
      case 'info':
        return <InformationCircleIcon className="w-5 h-5 flex-shrink-0" />;
      case 'error':
      default:
        return <ExclamationTriangleIcon className="w-5 h-5 flex-shrink-0" />;
    }
  };

  // Colors based on type
  const getColors = () => {
    switch (type) {
      case 'success':
        return {
          bg: 'bg-green-50',
          border: 'border-green-200',
          text: 'text-green-800',
          icon: 'text-green-600',
          closeHover: 'hover:text-green-900'
        };
      case 'warning':
        return {
          bg: 'bg-yellow-50',
          border: 'border-yellow-200',
          text: 'text-yellow-800',
          icon: 'text-yellow-600',
          closeHover: 'hover:text-yellow-900'
        };
      case 'info':
        return {
          bg: 'bg-blue-50',
          border: 'border-blue-200',
          text: 'text-blue-800',
          icon: 'text-blue-600',
          closeHover: 'hover:text-blue-900'
        };
      case 'error':
      default:
        return {
          bg: 'bg-red-50',
          border: 'border-red-200',
          text: 'text-red-800',
          icon: 'text-red-600',
          closeHover: 'hover:text-red-900'
        };
    }
  };

  // Position classes
  const getPositionClasses = () => {
    switch (position) {
      case 'top-left':
        return 'top-4 left-4';
      case 'top-center':
        return 'top-4 left-1/2 transform -translate-x-1/2';
      case 'top-right':
        return 'top-4 right-4';
      case 'bottom-left':
        return 'bottom-4 left-4';
      case 'bottom-center':
        return 'bottom-4 left-1/2 transform -translate-x-1/2';
      case 'bottom-right':
        return 'bottom-4 right-4';
      default:
        return 'top-4 right-4';
    }
  };

  const colors = getColors();

  return (
    <div
      className={`fixed z-50 ${getPositionClasses()} max-w-sm w-full mx-auto`}
      style={{ zIndex: 9999 }}
    >
      <div
        className={`${colors.bg} ${colors.border} border rounded-lg shadow-lg p-4 animate-in slide-in-from-top-2 duration-300`}
        role="alert"
        aria-live="polite"
      >
        <div className="flex items-start gap-3">
          {/* Icon */}
          <div className={colors.icon}>
            {getIcon()}
          </div>

          {/* Message */}
          <div className="flex-1 min-w-0">
            <p className={`text-sm ${colors.text} text-right leading-relaxed`}>
              {message}
            </p>
          </div>

          {/* Close Button */}
          <button
            onClick={handleClose}
            className={`flex-shrink-0 ${colors.icon} ${colors.closeHover} transition-colors rounded-md p-1 hover:bg-white hover:bg-opacity-20`}
            aria-label="סגור הודעה"
          >
            <XMarkIcon className="w-4 h-4" />
          </button>
        </div>

        {/* Progress bar */}
        <div className="mt-3 w-full bg-gray-200 bg-opacity-50 rounded-full h-1">
          <div
            className={`h-1 rounded-full transition-all ease-linear ${
              type === 'success' ? 'bg-green-500' :
              type === 'warning' ? 'bg-yellow-500' :
              type === 'info' ? 'bg-blue-500' :
              'bg-red-500'
            }`}
            style={{
              width: '100%',
              animation: `shrink ${duration}ms linear forwards`
            }}
          />
        </div>
      </div>

      <style jsx>{`
        @keyframes shrink {
          from { width: 100%; }
          to { width: 0%; }
        }

        @keyframes slide-in-from-top-2 {
          from {
            opacity: 0;
            transform: translateY(-8px);
          }
          to {
            opacity: 1;
            transform: translateY(0);
          }
        }

        .animate-in {
          animation-fill-mode: both;
        }

        .slide-in-from-top-2 {
          animation-name: slide-in-from-top-2;
        }

        .duration-300 {
          animation-duration: 300ms;
        }
      `}</style>
    </div>
  );
};

export default Toast;