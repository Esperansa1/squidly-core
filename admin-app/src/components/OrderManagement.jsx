import React, { useState, useEffect } from 'react';
import api from '../services/api.js';
import { TabSelector, BranchSelector, OrderColumn, DeclineOrderModal, Toast, LoadingState } from './ui';
import DeliveryTypeSelector from './ui/DeliveryTypeSelector.jsx';

const OrderManagement = () => {
  // State management
  const [branches, setBranches] = useState([]);
  const [selectedBranch, setSelectedBranch] = useState({ id: 0, name: 'כל הסניפים' });
  const [activeTab, setActiveTab] = useState('הזמנות חיות');
  const [deliveryType, setDeliveryType] = useState(null);
  const [timeframe, setTimeframe] = useState('יום');

  // Orders data
  const [orders, setOrders] = useState([]);
  const [customers, setCustomers] = useState({});
  const [loading, setLoading] = useState(true);

  // Modal state
  const [declineModal, setDeclineModal] = useState({ show: false, orderId: null });
  const [isProcessing, setIsProcessing] = useState(false);

  // Toast state
  const [toast, setToast] = useState({ show: false, message: '', type: 'success' });

  // Tab options
  const mainTabs = ['הזמנות חיות', 'הזמנות קודמות'];
  const timeframeTabs = ['יום', 'שבוע', 'חודש', 'שנה', 'מותאם אישית'];

  // Initialize API and load branches
  useEffect(() => {
    initializeApp();
  }, []);

  // Fetch orders when tab, branch, or delivery type changes
  useEffect(() => {
    if (activeTab === 'הזמנות חיות') {
      fetchLiveOrders(false); // Initial load with loading spinner

      // Poll every 30 seconds for real-time updates (background refresh)
      const interval = setInterval(() => fetchLiveOrders(true), 30000);
      return () => clearInterval(interval);
    }
  }, [activeTab, selectedBranch, deliveryType]);

  const initializeApp = async () => {
    try {
      setLoading(true);
      await api.init();

      // Load branches
      const branchesData = await api.getBranches();
      setBranches(branchesData);

      setLoading(false);
    } catch (err) {
      console.error('Failed to initialize:', err);
      showToast('שגיאה באתחול המערכת', 'error');
      setLoading(false);
    }
  };

  const fetchLiveOrders = async (isBackgroundRefresh = false) => {
    try {
      // Only show loading spinner on initial load, not on background refresh
      if (!isBackgroundRefresh) {
        setLoading(true);
      }

      // Build filters for live orders
      const filters = {
        status: 'pending,confirmed,preparing,ready',
        per_page: 100 // Fetch up to 100 live orders (reasonable limit)
      };

      // Add branch filter if specific branch selected
      if (selectedBranch.id > 0) {
        filters.branch_id = selectedBranch.id;
      }

      // Add delivery type filter
      if (deliveryType === 'delivery') {
        filters.has_delivery = true;
      } else if (deliveryType === 'takeaway') {
        filters.has_delivery = false;
      }

      // Fetch orders with customer data inline
      const ordersData = await api.getOrders(filters);

      // Extract customer data from orders (now included inline)
      const customersMap = {};
      ordersData.forEach(order => {
        if (order.customer) {
          customersMap[order.customer_id] = order.customer;
        }
      });

      setCustomers(customersMap);
      setOrders(ordersData);

      if (!isBackgroundRefresh) {
        setLoading(false);
      }
    } catch (err) {
      console.error('Failed to fetch orders:', err);
      if (!isBackgroundRefresh) {
        showToast('שגיאה בטעינת הזמנות', 'error');
        setLoading(false);
      }
    }
  };

  // Categorize orders by status
  const newOrders = orders.filter(o => o.status === 'pending');
  const inProgressOrders = orders.filter(o => ['confirmed', 'preparing'].includes(o.status));
  const readyOrders = orders.filter(o => o.status === 'ready');

  // Action handlers with optimistic updates
  const handleAcceptOrder = async (orderId) => {
    try {
      setIsProcessing(true);

      // Optimistic update - update UI immediately
      setOrders(prevOrders =>
        prevOrders.map(order =>
          order.id === orderId ? { ...order, status: 'confirmed' } : order
        )
      );

      // Then update server
      await api.updateOrder(orderId, { status: 'confirmed' });
      showToast('ההזמנה התקבלה בהצלחה', 'success');

      // Background refresh to sync any server-side changes
      fetchLiveOrders(true);
    } catch (err) {
      console.error('Failed to accept order:', err);
      showToast('שגיאה בקבלת ההזמנה', 'error');
      // Revert on error
      fetchLiveOrders(true);
    } finally {
      setIsProcessing(false);
    }
  };

  const handleDeclineOrder = (orderId) => {
    setDeclineModal({ show: true, orderId });
  };

  const handleConfirmDecline = async () => {
    const orderId = declineModal.orderId;
    try {
      setIsProcessing(true);

      // Optimistic update - remove from UI immediately
      setOrders(prevOrders => prevOrders.filter(order => order.id !== orderId));

      // Then update server
      await api.updateOrder(orderId, { status: 'cancelled' });
      showToast('ההזמנה נדחתה', 'info');
      setDeclineModal({ show: false, orderId: null });

      // Background refresh to sync
      fetchLiveOrders(true);
    } catch (err) {
      console.error('Failed to decline order:', err);
      showToast('שגיאה בדחיית ההזמנה', 'error');
      // Revert on error
      fetchLiveOrders(true);
    } finally {
      setIsProcessing(false);
    }
  };

  const handleMarkReady = async (orderId) => {
    try {
      setIsProcessing(true);

      // Optimistic update
      setOrders(prevOrders =>
        prevOrders.map(order =>
          order.id === orderId ? { ...order, status: 'ready' } : order
        )
      );

      // Then update server
      await api.updateOrder(orderId, { status: 'ready' });
      showToast('ההזמנה מוכנה לאיסוף', 'success');

      // Background refresh to sync
      fetchLiveOrders(true);
    } catch (err) {
      console.error('Failed to mark order ready:', err);
      showToast('שגיאה בעדכון סטטוס', 'error');
      // Revert on error
      fetchLiveOrders(true);
    } finally {
      setIsProcessing(false);
    }
  };

  const handleMarkPickedUp = async (orderId) => {
    try {
      setIsProcessing(true);

      // Optimistic update - remove from live orders
      setOrders(prevOrders => prevOrders.filter(order => order.id !== orderId));

      // Then update server
      await api.updateOrder(orderId, { status: 'completed' });
      showToast('ההזמנה סומנה כנאספה', 'success');

      // Background refresh to sync
      fetchLiveOrders(true);
    } catch (err) {
      console.error('Failed to mark order as picked up:', err);
      showToast('שגיאה בעדכון סטטוס', 'error');
      // Revert on error
      fetchLiveOrders(true);
    } finally {
      setIsProcessing(false);
    }
  };

  const showToast = (message, type = 'success') => {
    setToast({ show: true, message, type });
  };

  return (
    <div className="h-full flex flex-col" dir="rtl">
      {/* Fixed Header */}
      <div className="flex-shrink-0 px-6 pt-6">
        {/* Single Row: Tabs + Filters */}
        <div className="flex justify-between items-center mb-6">
          {/* Left Side: Primary Navigation Tabs */}
          <TabSelector
            tabs={mainTabs}
            activeTab={activeTab}
            onTabChange={setActiveTab}
          />

          {/* Right Side: Branch + Conditional Dropdown */}
          <div className="flex items-center gap-4">
            {/* Branch Selector - Always visible */}
            <BranchSelector
              branches={branches}
              selectedBranchId={selectedBranch.id}
              selectedBranchName={selectedBranch.name}
              onBranchChange={setSelectedBranch}
              showAllBranches={true}
            />

            {/* Conditional: Delivery Type OR Timeframe */}
            {activeTab === 'הזמנות חיות' ? (
              <DeliveryTypeSelector
                value={deliveryType}
                onChange={setDeliveryType}
              />
            ) : (
              <TabSelector
                tabs={timeframeTabs}
                activeTab={timeframe}
                onTabChange={setTimeframe}
                className="w-auto"
              />
            )}
          </div>
        </div>
      </div>

      {/* Scrollable Content Area */}
      <div className="flex-1 px-6 pb-6 min-h-0">
        {loading ? (
          <LoadingState />
        ) : activeTab === 'הזמנות חיות' ? (
          /* Live Orders - 3 Column Layout */
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 h-full min-h-0">
            {/* Column 1: New Orders */}
            <OrderColumn
              title="הזמנות חדשות"
              orders={newOrders}
              customers={customers}
              branches={branches}
              emptyMessage="אין הזמנות חדשות"
              onAccept={handleAcceptOrder}
              onDecline={handleDeclineOrder}
            />

            {/* Column 2: In Progress */}
            <OrderColumn
              title="בהכנה"
              orders={inProgressOrders}
              customers={customers}
              branches={branches}
              emptyMessage="אין הזמנות בהכנה"
              onMarkReady={handleMarkReady}
            />

            {/* Column 3: Ready for Pickup */}
            <OrderColumn
              title="מוכן לאיסוף"
              orders={readyOrders}
              customers={customers}
              branches={branches}
              emptyMessage="אין הזמנות מוכנות"
              onMarkPickedUp={handleMarkPickedUp}
            />
          </div>
        ) : (
          /* Previous Orders - Placeholder */
          <div className="text-center mt-20">
            <h2 className="text-2xl font-bold text-gray-900 mb-4">הזמנות קודמות</h2>
            <p className="text-gray-600">תכונה זו תהיה זמינה בקרוב</p>
            <p className="text-sm text-gray-500 mt-2">טווח זמן נבחר: {timeframe}</p>
          </div>
        )}
      </div>

      {/* Decline Order Modal */}
      <DeclineOrderModal
        isOpen={declineModal.show}
        orderId={declineModal.orderId}
        onClose={() => setDeclineModal({ show: false, orderId: null })}
        onConfirm={handleConfirmDecline}
        loading={isProcessing}
      />

      {/* Toast Notifications */}
      <Toast
        message={toast.message}
        type={toast.type}
        isVisible={toast.show}
        onClose={() => setToast({ ...toast, show: false })}
        duration={3000}
        position="top-right"
      />
    </div>
  );
};

export default OrderManagement;
