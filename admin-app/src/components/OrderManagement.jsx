import React, { useState, useEffect, useCallback } from 'react';
import api from '../services/api.js';
import { TabSelector, BranchSelector, OrderColumn, DeclineOrderModal, Toast, LoadingState } from './ui';
import DeliveryTypeSelector from './ui/DeliveryTypeSelector.jsx';
import StatisticsCards from './ui/organisms/StatisticsCards.jsx';
import PreviousOrdersTable from './ui/organisms/PreviousOrdersTable.jsx';
import OrderDetailsModal from './ui/organisms/OrderDetailsModal.jsx';
import DownloadButton from './ui/molecules/DownloadButton.jsx';
import { getDateRange } from '../utils/dateRangeCalculator.js';

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

  // Previous orders state
  const [statistics, setStatistics] = useState(null);
  const [previousOrders, setPreviousOrders] = useState([]);
  const [totalOrders, setTotalOrders] = useState(0);
  const [currentPage, setCurrentPage] = useState(1);
  const [itemsPerPage, setItemsPerPage] = useState(10);
  const [dateRange, setDateRange] = useState(null);
  const [selectedOrder, setSelectedOrder] = useState(null);
  const [orderDetailsModal, setOrderDetailsModal] = useState(false);
  const [tableLoading, setTableLoading] = useState(false);
  const [hasInitiallyLoaded, setHasInitiallyLoaded] = useState(false);

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

  // Reset to page 1 when filters change
  useEffect(() => {
    if (activeTab === 'הזמנות קודמות') {
      setCurrentPage(1);
      setHasInitiallyLoaded(false); // Reset on filter change
    }
  }, [activeTab, timeframe, selectedBranch, deliveryType]);

  // Fetch orders when tab, branch, delivery type changes (full reload)
  useEffect(() => {
    if (activeTab === 'הזמנות חיות') {
      fetchLiveOrders(false); // Initial load with loading spinner

      // Poll every 30 seconds for real-time updates (background refresh)
      const interval = setInterval(() => fetchLiveOrders(true), 30000);
      return () => clearInterval(interval);
    } else if (activeTab === 'הזמנות קודמות') {
      fetchPreviousOrders(false).then(() => {
        setHasInitiallyLoaded(true); // Mark as loaded after initial fetch
      });
    }
  }, [activeTab, timeframe, selectedBranch, deliveryType]);

  // Fetch orders when only pagination changes (table reload only)
  useEffect(() => {
    if (activeTab === 'הזמנות קודמות' && hasInitiallyLoaded) {
      // After initial load, any pagination change should reload table
      fetchPreviousOrders(true); // Table only reload
    }
  }, [currentPage, itemsPerPage, hasInitiallyLoaded]);

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
        per_page: 100 // Maximum allowed by backend (typically < 100 live orders at once)
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

  const fetchPreviousOrders = async (isTableOnly = false) => {
    try {
      // Table-only reload: only show table spinner
      // Full reload: show full page spinner
      if (isTableOnly) {
        setTableLoading(true);
      } else {
        setLoading(true);
      }

      // Calculate date range based on timeframe
      const dateFilters = calculateDateFilters(timeframe);

      // Build filters for orders with pagination
      // Backend uses offset, not page number
      const offset = (currentPage - 1) * itemsPerPage;
      const orderFilters = {
        ...dateFilters,
        status: 'completed,cancelled', // Only show finished orders
        offset: offset,
        per_page: itemsPerPage
      };

      // Add branch filter
      if (selectedBranch.id > 0) {
        orderFilters.branch_id = selectedBranch.id;
      }

      // Add delivery type filter
      if (deliveryType === 'delivery') {
        orderFilters.has_delivery = true;
      } else if (deliveryType === 'takeaway') {
        orderFilters.has_delivery = false;
      }

      // For table-only reload, only fetch orders (not statistics)
      if (isTableOnly) {
        const ordersResponse = await api.getOrders(orderFilters, true);

        // Extract customers from orders
        const customersMap = {};
        ordersResponse.data.forEach(order => {
          if (order.customer) {
            customersMap[order.customer_id] = order.customer;
          }
        });

        setPreviousOrders(ordersResponse.data);
        setTotalOrders(ordersResponse.total);
        setCustomers(customersMap);
        setTableLoading(false);
      } else {
        // Full reload: fetch both statistics and orders
        const statsFilters = {
          ...dateFilters,
          compare_previous: true
        };
        if (selectedBranch.id > 0) {
          statsFilters.branch_id = selectedBranch.id;
        }
        // Add delivery type filter to stats as well
        if (deliveryType === 'delivery') {
          statsFilters.has_delivery = true;
        } else if (deliveryType === 'takeaway') {
          statsFilters.has_delivery = false;
        }

        // Fetch both in parallel
        const [statsData, ordersResponse] = await Promise.all([
          api.getOrderStatistics(statsFilters),
          api.getOrders(orderFilters, true) // Include pagination headers
        ]);

        // Extract customers from orders
        const customersMap = {};
        ordersResponse.data.forEach(order => {
          if (order.customer) {
            customersMap[order.customer_id] = order.customer;
          }
        });

        setStatistics(statsData);
        setPreviousOrders(ordersResponse.data);
        setTotalOrders(ordersResponse.total);
        setCustomers(customersMap);
        setDateRange(dateFilters);
        setLoading(false);
      }
    } catch (err) {
      console.error('Failed to fetch previous orders:', err);
      showToast('שגיאה בטעינת הזמנות קודמות', 'error');
      if (isTableOnly) {
        setTableLoading(false);
      } else {
        setLoading(false);
      }
    }
  };

  const calculateDateFilters = (timeframe) => {
    // Map Hebrew timeframe to English for dateRangeCalculator
    const timeframeMap = {
      'יום': 'today',
      'שבוע': 'week',
      'חודש': 'month',
      'שנה': 'year'
    };

    const englishTimeframe = timeframeMap[timeframe] || 'month';
    return getDateRange(englishTimeframe);
  };

  const handleOrderClick = (order) => {
    setSelectedOrder(order);
    setOrderDetailsModal(true);
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
          /* Previous Orders - Full Implementation */
          <div className="flex flex-col h-full gap-6">
            {/* Statistics Cards */}
            {statistics && (
              <StatisticsCards statistics={statistics} />
            )}

            {/* Action Bar */}
            <div className="flex justify-between items-center">
              <h2 className="text-xl font-semibold text-gray-900">
                רשימת הזמנות
              </h2>
              <DownloadButton
                filters={dateRange}
                format="csv"
                disabled={previousOrders.length === 0}
              />
            </div>

            {/* Orders Table */}
            <div className="flex-1 min-h-0">
              <PreviousOrdersTable
                orders={previousOrders}
                customers={customers}
                onOrderClick={handleOrderClick}
                currentPage={currentPage}
                itemsPerPage={itemsPerPage}
                totalItems={totalOrders}
                onPageChange={setCurrentPage}
                onItemsPerPageChange={setItemsPerPage}
                loading={tableLoading}
              />
            </div>
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

      {/* Order Details Modal */}
      <OrderDetailsModal
        isOpen={orderDetailsModal}
        onClose={() => {
          setOrderDetailsModal(false);
          setSelectedOrder(null);
        }}
        order={selectedOrder}
        customer={selectedOrder ? customers[selectedOrder.customer_id] : null}
      />
    </div>
  );
};

export default OrderManagement;
