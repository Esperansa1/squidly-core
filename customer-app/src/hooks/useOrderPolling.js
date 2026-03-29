import { useState, useEffect, useRef } from 'react';
import publicApi from '../services/publicApi';

/**
 * Custom hook for real-time order polling
 * Polls order status every 10 seconds until order is completed/cancelled
 *
 * @param {number} orderId - Order ID to track
 * @param {string} trackingToken - Tracking token for authentication
 * @param {boolean} autoStart - Start polling immediately (default: true)
 * @returns {object} { order, loading, error, startPolling, stopPolling, refresh }
 */
export function useOrderPolling(orderId, trackingToken, autoStart = true) {
  const [order, setOrder] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [isPolling, setIsPolling] = useState(false);

  const pollingIntervalRef = useRef(null);
  const isMountedRef = useRef(true);

  // Fetch order status
  const fetchOrderStatus = async () => {
    if (!orderId || !trackingToken) {
      setError('Order ID and tracking token are required');
      return;
    }

    try {
      setLoading(true);
      setError(null);

      const response = await publicApi.getOrderStatus(orderId, trackingToken);

      if (isMountedRef.current) {
        setOrder(response.order);

        // Stop polling if order is in terminal state
        if (isTerminalStatus(response.order.status)) {
          stopPolling();
        }
      }
    } catch (err) {
      console.error('Failed to fetch order status:', err);
      if (isMountedRef.current) {
        setError(err.message || 'Failed to fetch order status');
      }
    } finally {
      if (isMountedRef.current) {
        setLoading(false);
      }
    }
  };

  // Check if status is terminal (stop polling)
  const isTerminalStatus = (status) => {
    return status === 'completed' || status === 'cancelled';
  };

  // Start polling
  const startPolling = () => {
    if (isPolling) return; // Already polling

    setIsPolling(true);

    // Fetch immediately
    fetchOrderStatus();

    // Then poll every 10 seconds
    pollingIntervalRef.current = setInterval(() => {
      fetchOrderStatus();
    }, 15000); // 15 seconds
  };

  // Stop polling
  const stopPolling = () => {
    if (pollingIntervalRef.current) {
      clearInterval(pollingIntervalRef.current);
      pollingIntervalRef.current = null;
    }
    setIsPolling(false);
  };

  // Manual refresh
  const refresh = () => {
    fetchOrderStatus();
  };

  // Auto-start polling on mount if enabled
  useEffect(() => {
    if (autoStart && orderId && trackingToken) {
      startPolling();
    }

    return () => {
      isMountedRef.current = false;
      stopPolling();
    };
  }, [orderId, trackingToken, autoStart]);

  return {
    order,
    loading,
    error,
    isPolling,
    startPolling,
    stopPolling,
    refresh,
  };
}

export default useOrderPolling;
