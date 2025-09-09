/**
 * Generic Modal Component
 * 
 * Base modal component with overlay, animations, and accessibility features
 * Supports different modal types: form, confirm, custom
 */

import React, { useEffect } from 'react';
import { XMarkIcon } from '@heroicons/react/24/outline';
import { DEFAULT_THEME } from '../../../config/theme.js';

const Modal = ({ 
  isOpen = false,
  onClose = () => {},
  title = '',
  size = 'md', // 'sm' | 'md' | 'lg' | 'xl'
  showCloseButton = true,
  className = '',
  children,
  ...props 
}) => {
  const theme = DEFAULT_THEME;

  // Handle ESC key
  useEffect(() => {
    const handleEsc = (event) => {
      if (event.keyCode === 27 && isOpen) {
        onClose();
      }
    };
    document.addEventListener('keydown', handleEsc);
    return () => document.removeEventListener('keydown', handleEsc);
  }, [isOpen, onClose]);

  // Prevent body scroll when modal is open
  useEffect(() => {
    if (isOpen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = 'unset';
    }
    return () => {
      document.body.style.overflow = 'unset';
    };
  }, [isOpen]);

  // Focus trap and focus management
  useEffect(() => {
    if (isOpen) {
      const modalElement = document.querySelector('[data-modal]');
      if (modalElement) {
        const focusableElements = modalElement.querySelectorAll(
          'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        // Focus first element
        if (firstElement) {
          firstElement.focus();
        }

        // Handle tab key for focus trap
        const handleTab = (event) => {
          if (event.key === 'Tab') {
            if (event.shiftKey) {
              if (document.activeElement === firstElement) {
                lastElement?.focus();
                event.preventDefault();
              }
            } else {
              if (document.activeElement === lastElement) {
                firstElement?.focus();
                event.preventDefault();
              }
            }
          }
        };

        document.addEventListener('keydown', handleTab);
        return () => document.removeEventListener('keydown', handleTab);
      }
    }
  }, [isOpen]);

  const sizeClasses = {
    sm: 'max-w-md',
    md: 'max-w-lg md:max-w-xl',
    lg: 'max-w-lg md:max-w-xl lg:max-w-2xl',
    xl: 'max-w-lg md:max-w-2xl lg:max-w-3xl'
  };

  if (!isOpen) return null;

  return (
    <div 
      className="fixed inset-0 z-50 flex items-center justify-center p-4"
      onClick={(e) => {
        // Close modal when clicking overlay
        if (e.target === e.currentTarget) {
          onClose();
        }
      }}
    >
      {/* Overlay */}
      <div 
        className="absolute inset-0 bg-black bg-opacity-50 transition-opacity"
        style={{ 
          animation: isOpen ? 'fadeIn 0.2s ease-out' : 'fadeOut 0.2s ease-out'
        }}
      />
      
      {/* Modal Content */}
      <div 
        data-modal
        className={`
          relative w-full bg-white rounded-lg shadow-xl transform transition-all
          flex flex-col
          ${sizeClasses[size]}
          ${className}
        `}
        style={{ 
          animation: isOpen ? 'modalSlideIn 0.3s ease-out' : 'modalSlideOut 0.3s ease-out',
          direction: 'rtl', // RTL support
          maxHeight: 'calc(100vh - 6rem)', // More compact height
          minHeight: '400px'
        }}
        {...props}
      >
        {/* Header */}
        {(title || showCloseButton) && (
          <div className="flex-shrink-0 flex items-center justify-between p-6 border-b" style={{ borderColor: theme.border_color }}>
            <h3 className="text-lg font-semibold text-right" style={{ color: theme.text_primary }}>
              {title}
            </h3>
            {showCloseButton && (
              <button
                onClick={onClose}
                className="p-1 hover:bg-gray-100 rounded-full transition-colors"
                style={{ color: theme.text_secondary }}
              >
                <XMarkIcon className="w-5 h-5" />
              </button>
            )}
          </div>
        )}
        
        {/* Content - Scrollable */}
        <div className="flex-1 overflow-y-auto p-6">
          {children}
        </div>
      </div>
      
      {/* CSS Animations */}
      <style jsx>{`
        @keyframes fadeIn {
          from { opacity: 0; }
          to { opacity: 1; }
        }
        
        @keyframes fadeOut {
          from { opacity: 1; }
          to { opacity: 0; }
        }
        
        @keyframes modalSlideIn {
          from { 
            opacity: 0; 
            transform: translateY(-50px) scale(0.95); 
          }
          to { 
            opacity: 1; 
            transform: translateY(0) scale(1); 
          }
        }
        
        @keyframes modalSlideOut {
          from { 
            opacity: 1; 
            transform: translateY(0) scale(1); 
          }
          to { 
            opacity: 0; 
            transform: translateY(-50px) scale(0.95); 
          }
        }
      `}</style>
    </div>
  );
};

export default Modal;