import React from 'react';
import { useOrderPolling } from '../../hooks/useOrderPolling';
import { t } from '../../i18n/translations';

/**
 * OrderTracking - Real-time order status display
 * Shows order timeline with automatic polling every 10 seconds
 */
export default function OrderTracking({ orderId, trackingToken, onBack }) {
  const { order, loading, error, isPolling, refresh } = useOrderPolling(
    orderId,
    trackingToken,
    true // auto-start polling
  );

  // Order status progression
  const statusSteps = [
    { key: 'pending', label: t('statusPending'), icon: '📝' },
    { key: 'confirmed', label: t('statusConfirmed'), icon: '✅' },
    { key: 'preparing', label: t('statusPreparing'), icon: '👨‍🍳' },
    { key: 'ready', label: t('statusReady'), icon: '📦' },
    { key: 'completed', label: t('statusCompleted'), icon: '🎉' },
  ];

  // Get current step index
  const getCurrentStepIndex = (status) => {
    if (status === 'cancelled') return -1;
    return statusSteps.findIndex((step) => step.key === status);
  };

  const currentStepIndex = order ? getCurrentStepIndex(order.status) : -1;

  // Status colors
  const getStatusColor = (status) => {
    switch (status) {
      case 'completed':
        return 'bg-green-600';
      case 'cancelled':
        return 'bg-red-600';
      case 'preparing':
      case 'ready':
        return 'bg-blue-600';
      default:
        return 'bg-yellow-600';
    }
  };

  return (
    <div className="max-w-4xl mx-auto p-4">
      {/* Header */}
      <div className="mb-6">
        {onBack && (
          <button onClick={onBack} className="text-blue-600 mb-4 flex items-center gap-2">
            ← {t('enterDifferentOrder')}
          </button>
        )}
        <div className="flex justify-between items-start">
          <div>
            <h1 className="text-3xl font-bold mb-2">{t('orderTracking')}</h1>
            <p className="text-gray-600">
              {t('order')} #{orderId}
            </p>
          </div>
          <button
            onClick={refresh}
            disabled={loading}
            className="px-4 py-2 border text-gray-700 hover:bg-gray-100 disabled:opacity-50"
          >
            {loading ? t('loading') : t('refresh')}
          </button>
        </div>
      </div>

      {/* Loading State (initial) */}
      {loading && !order && (
        <div className="text-center py-12">
          <div className="inline-block animate-spin w-12 h-12 border-4 border-blue-600 border-t-transparent rounded-full mb-4"></div>
          <p>{t('loadingOrder')}</p>
        </div>
      )}

      {/* Error State */}
      {error && !order && (
        <div className="bg-red-100 border border-red-400 text-red-700 p-6">
          <h3 className="font-bold mb-2">{t('error')}</h3>
          <p>{error}</p>
          <button
            onClick={refresh}
            className="mt-4 px-6 py-2 bg-red-600 text-white hover:bg-red-700"
          >
            {t('tryAgain')}
          </button>
        </div>
      )}

      {/* Order Content */}
      {order && (
        <>
          {/* Polling Indicator */}
          {isPolling && (
            <div className="mb-4 bg-blue-50 border border-blue-200 p-3 flex items-center gap-3 text-sm">
              <div className="animate-pulse w-2 h-2 bg-blue-600 rounded-full"></div>
              <span>{t('autoUpdating')}</span>
            </div>
          )}

          {/* Current Status Banner */}
          <div
            className={`${getStatusColor(
              order.status
            )} text-white p-6 mb-6 flex items-center justify-between`}
          >
            <div>
              <h2 className="text-2xl font-bold mb-1">
                {order.status === 'cancelled' ? t('statusCancelled') : statusSteps[currentStepIndex]?.label}
              </h2>
              {order.estimated_time && order.status !== 'completed' && order.status !== 'cancelled' && (
                <p className="text-sm opacity-90">
                  {t('estimatedTime')}: {new Date(order.estimated_time).toLocaleTimeString('he-IL', { hour: '2-digit', minute: '2-digit' })}
                </p>
              )}
            </div>
            <div className="text-5xl">
              {order.status === 'cancelled' ? '❌' : statusSteps[currentStepIndex]?.icon}
            </div>
          </div>

          {/* Status Timeline (only if not cancelled) */}
          {order.status !== 'cancelled' && (
            <div className="bg-white border p-6 mb-6">
              <h3 className="font-bold text-lg mb-4">{t('orderProgress')}</h3>
              <div className="space-y-4">
                {statusSteps.map((step, index) => {
                  const isCompleted = index <= currentStepIndex;
                  const isCurrent = index === currentStepIndex;

                  return (
                    <div key={step.key} className="flex items-center gap-4">
                      {/* Icon/Checkpoint */}
                      <div
                        className={`w-12 h-12 rounded-full flex items-center justify-center text-2xl ${
                          isCompleted ? getStatusColor(step.key) : 'bg-gray-200'
                        }`}
                      >
                        {isCompleted ? '✓' : step.icon}
                      </div>

                      {/* Label */}
                      <div className="flex-1">
                        <div
                          className={`font-bold ${
                            isCurrent ? 'text-blue-600' : isCompleted ? 'text-gray-700' : 'text-gray-400'
                          }`}
                        >
                          {step.label}
                        </div>
                        {isCurrent && (
                          <div className="text-sm text-gray-600">{t('currentStatus')}</div>
                        )}
                      </div>

                      {/* Completion Time */}
                      {isCompleted && order[`${step.key}_at`] && (
                        <div className="text-sm text-gray-500">
                          {new Date(order[`${step.key}_at`]).toLocaleTimeString('he-IL', {
                            hour: '2-digit',
                            minute: '2-digit',
                          })}
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            </div>
          )}

          {/* Order Details */}
          <div className="bg-white border p-6 mb-6">
            <h3 className="font-bold text-lg mb-4">{t('orderDetails')}</h3>
            <div className="space-y-3">
              <div className="flex justify-between">
                <span className="text-gray-600">{t('orderNumber')}:</span>
                <span className="font-bold">#{order.id}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-gray-600">{t('orderDate')}:</span>
                <span className="font-bold">
                  {new Date(order.order_date).toLocaleString('he-IL', {
                    dateStyle: 'medium',
                    timeStyle: 'short',
                  })}
                </span>
              </div>
              <div className="flex justify-between">
                <span className="text-gray-600">{t('deliveryType')}:</span>
                <span className="font-bold">
                  {order.delivery_type === 'delivery' ? t('delivery') : t('pickup')}
                </span>
              </div>
              {order.delivery_type === 'delivery' && order.delivery_address && (
                <div className="flex justify-between">
                  <span className="text-gray-600">{t('address')}:</span>
                  <span className="font-bold text-left max-w-xs">{order.delivery_address}</span>
                </div>
              )}
              <div className="flex justify-between border-t pt-3">
                <span className="text-gray-600">{t('total')}:</span>
                <span className="font-bold text-lg">₪{order.total_amount.toFixed(2)}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-gray-600">{t('paymentStatus')}:</span>
                <span
                  className={`font-bold ${
                    order.payment_status === 'paid' ? 'text-green-600' : 'text-yellow-600'
                  }`}
                >
                  {t(`paymentStatus_${order.payment_status}`)}
                </span>
              </div>
            </div>
          </div>

          {/* Order Items */}
          {order.items && order.items.length > 0 && (
            <div className="bg-white border p-6 mb-6">
              <h3 className="font-bold text-lg mb-4">{t('orderItems')}</h3>
              <div className="space-y-3">
                {order.items.map((item, index) => (
                  <div key={index} className="flex justify-between border-b pb-2">
                    <div className="flex-1">
                      <div className="font-bold">{item.product_name}</div>
                      <div className="text-sm text-gray-600">
                        ₪{item.unit_price.toFixed(2)} × {item.quantity}
                      </div>
                      {item.special_instructions && (
                        <div className="text-xs text-gray-500 mt-1">
                          {t('notes')}: {item.special_instructions}
                        </div>
                      )}
                    </div>
                    <div className="font-bold">
                      ₪{(item.unit_price * item.quantity).toFixed(2)}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Cancelled Message */}
          {order.status === 'cancelled' && (
            <div className="bg-red-100 border border-red-400 text-red-700 p-6">
              <h3 className="font-bold text-lg mb-2">{t('orderCancelled')}</h3>
              <p>{t('orderCancelledMessage')}</p>
              {order.cancelled_at && (
                <p className="mt-2 text-sm">
                  {t('cancelledAt')}:{' '}
                  {new Date(order.cancelled_at).toLocaleString('he-IL', {
                    dateStyle: 'medium',
                    timeStyle: 'short',
                  })}
                </p>
              )}
            </div>
          )}
        </>
      )}
    </div>
  );
}
