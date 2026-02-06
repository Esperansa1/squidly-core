import React, { createContext, useContext, useState, useCallback } from 'react';
import Toast from '../components/ui/Toast';

/**
 * Toast Context
 *
 * Provides toast notification functionality throughout the app.
 *
 * Usage:
 *   const { showToast } = useToast();
 *   showToast('Message here', 'error');  // type: 'success' | 'error' | 'warning' | 'info'
 */

const ToastContext = createContext(null);

export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([]);

  /**
   * Show a toast notification
   * @param {string} message - The message to display
   * @param {string} type - Toast type: 'success' | 'error' | 'warning' | 'info'
   * @param {number} duration - Duration in ms (default: 4000)
   */
  const showToast = useCallback((message, type = 'info', duration = 4000) => {
    const id = Date.now() + Math.random();

    setToasts((prev) => [...prev, { id, message, type, duration }]);

    return id;
  }, []);

  /**
   * Remove a toast by ID
   */
  const removeToast = useCallback((id) => {
    setToasts((prev) => prev.filter((toast) => toast.id !== id));
  }, []);

  /**
   * Clear all toasts
   */
  const clearToasts = useCallback(() => {
    setToasts([]);
  }, []);

  const value = {
    showToast,
    removeToast,
    clearToasts,
  };

  return (
    <ToastContext.Provider value={value}>
      {children}
      {/* Render toasts - stacked from top-left */}
      {toasts.map((toast, index) => (
        <div
          key={toast.id}
          style={{
            position: 'fixed',
            top: `${24 + index * 80}px`,
            left: '24px',
            zIndex: 10000 + index,
          }}
        >
          <Toast
            message={toast.message}
            type={toast.type}
            duration={toast.duration}
            onClose={() => removeToast(toast.id)}
          />
        </div>
      ))}
    </ToastContext.Provider>
  );
}

/**
 * Custom hook to use the Toast context
 */
export function useToast() {
  const context = useContext(ToastContext);

  if (!context) {
    throw new Error('useToast must be used within a ToastProvider');
  }

  return context;
}

export default ToastContext;
