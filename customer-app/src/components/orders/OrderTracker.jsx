import React, { useState, useEffect } from 'react';
import { t } from '../../i18n/translations';
import theme from '../../config/theme.js';
import OrderTracking from './OrderTracking';

/**
 * OrderTracker - Token input view for order tracking
 * Allows users to enter order ID + tracking token to track their order
 * Also auto-loads from sessionStorage if available
 */
export default function OrderTracker({ onBack }) {
  const [orderId, setOrderId] = useState('');
  const [trackingToken, setTrackingToken] = useState('');
  const [isTracking, setIsTracking] = useState(false);
  const [error, setError] = useState(null);

  // Load from sessionStorage on mount
  useEffect(() => {
    const savedOrderId = sessionStorage.getItem('squidly_order_id');
    const savedToken = sessionStorage.getItem('squidly_tracking_token');

    if (savedOrderId && savedToken) {
      setOrderId(savedOrderId);
      setTrackingToken(savedToken);
      // Auto-start tracking if both values present
      setIsTracking(true);
    }
  }, []);

  const handleTrackOrder = (e) => {
    e.preventDefault();
    setError(null);

    if (!orderId.trim() || !trackingToken.trim()) {
      setError(t('enterOrderIdAndToken'));
      return;
    }

    // Save to sessionStorage
    sessionStorage.setItem('squidly_order_id', orderId.trim());
    sessionStorage.setItem('squidly_tracking_token', trackingToken.trim());

    // Start tracking
    setIsTracking(true);
  };

  const handleBackToInput = () => {
    setIsTracking(false);
    sessionStorage.removeItem('squidly_order_id');
    sessionStorage.removeItem('squidly_tracking_token');
  };

  if (isTracking) {
    return (
      <OrderTracking
        orderId={parseInt(orderId)}
        trackingToken={trackingToken}
        onBack={handleBackToInput}
      />
    );
  }

  return (
    <div className="max-w-2xl mx-auto p-4">
      {/* Header */}
      <div className="mb-6">
        {onBack && (
          <button
            onClick={onBack}
            className="mb-4 flex items-center gap-2"
            style={{ color: theme.colors.primary }}
          >
            ← {t('back')}
          </button>
        )}
        <h1 className="text-3xl font-bold mb-2">{t('trackOrder')}</h1>
        <p className="text-gray-600">{t('enterTrackingDetails')}</p>
      </div>

      {/* Tracking Form */}
      <div className="bg-white border p-6">
        <form onSubmit={handleTrackOrder} className="space-y-6">
          {/* Order ID */}
          <div>
            <label className="block font-bold mb-2">
              {t('orderNumber')} <span style={{ color: theme.colors.primary }}>*</span>
            </label>
            <input
              type="number"
              value={orderId}
              onChange={(e) => setOrderId(e.target.value)}
              className="w-full border px-4 py-3 text-lg outline-none"
              onFocus={(e) => (e.target.style.borderColor = theme.colors.primary)}
              onBlur={(e) => (e.target.style.borderColor = theme.colors.border)}
              placeholder="12345"
              required
            />
            <p className="text-sm text-gray-500 mt-1">{t('orderNumberHelp')}</p>
          </div>

          {/* Tracking Token */}
          <div>
            <label className="block font-bold mb-2">
              {t('trackingToken')} <span style={{ color: theme.colors.primary }}>*</span>
            </label>
            <input
              type="text"
              value={trackingToken}
              onChange={(e) => setTrackingToken(e.target.value)}
              className="w-full border px-4 py-3 font-mono text-sm outline-none"
              onFocus={(e) => (e.target.style.borderColor = theme.colors.primary)}
              onBlur={(e) => (e.target.style.borderColor = theme.colors.border)}
              placeholder="tk_xxxxxxxxxxxxxxxx"
              required
            />
            <p className="text-sm text-gray-500 mt-1">{t('trackingTokenHelp')}</p>
          </div>

          {/* Error Message */}
          {error && (
            <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3">
              {error}
            </div>
          )}

          {/* Submit Button */}
          <button
            type="submit"
            className="w-full text-white py-3 font-bold text-lg"
            style={{ backgroundColor: theme.colors.primary }}
            onMouseEnter={(e) => (e.currentTarget.style.backgroundColor = theme.colors.primaryHover)}
            onMouseLeave={(e) => (e.currentTarget.style.backgroundColor = theme.colors.primary)}
          >
            {t('trackMyOrder')}
          </button>
        </form>

        {/* Help Section */}
        <div className="mt-6 pt-6 border-t">
          <h3 className="font-bold mb-2">{t('whereToFind')}</h3>
          <ul className="text-sm text-gray-700 space-y-2">
            <li>• {t('confirmationEmail')}</li>
            <li>• {t('confirmationSMS')}</li>
            <li>• {t('checkoutConfirmation')}</li>
          </ul>
        </div>
      </div>

      {/* Example Section */}
      <div className="mt-6 bg-gray-50 border p-4 text-sm">
        <h3 className="font-bold mb-2">{t('example')}</h3>
        <div className="space-y-1">
          <p>
            <strong>{t('orderNumber')}:</strong> 12345
          </p>
          <p>
            <strong>{t('trackingToken')}:</strong>{' '}
            <code className="bg-white px-2 py-1 border">tk_abc123def456ghi789</code>
          </p>
        </div>
      </div>
    </div>
  );
}
