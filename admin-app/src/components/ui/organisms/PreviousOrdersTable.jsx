import { useState } from 'react';
import {
  CheckCircleIcon,
  XCircleIcon,
  ArrowPathIcon,
  ChevronUpIcon,
  ChevronDownIcon,
  TruckIcon,
  ShoppingBagIcon,
} from '@heroicons/react/24/outline';
import Card from '../atoms/Card';
import Pagination from '../molecules/Pagination';

/**
 * Previous Orders Table Component
 *
 * Sortable table displaying historical orders with status badges and backend pagination
 */
const PreviousOrdersTable = ({
  orders = [],
  customers = {},
  onOrderClick,
  currentPage = 1,
  itemsPerPage = 10,
  totalItems = 0,
  onPageChange,
  onItemsPerPageChange,
  loading = false
}) => {
  const [sortColumn, setSortColumn] = useState('date');
  const [sortDirection, setSortDirection] = useState('desc');

  /**
   * Handle column header click for sorting
   */
  const handleSort = (column) => {
    if (sortColumn === column) {
      // Toggle direction if same column
      setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
    } else {
      // New column, default to descending
      setSortColumn(column);
      setSortDirection('desc');
    }
  };

  /**
   * Sort orders based on current sort column and direction (client-side sorting on current page)
   */
  const sortedOrders = [...orders].sort((a, b) => {
    let aValue, bValue;

    switch (sortColumn) {
      case 'id':
        aValue = a.id;
        bValue = b.id;
        break;
      case 'date':
        aValue = new Date(a.order_date).getTime();
        bValue = new Date(b.order_date).getTime();
        break;
      case 'customer':
        aValue = customers[a.customer_id]?.name || '';
        bValue = customers[b.customer_id]?.name || '';
        break;
      case 'type':
        aValue = a.delivery_address || a.payment_method === 'online' ? 'delivery' : 'pickup';
        bValue = b.delivery_address || b.payment_method === 'online' ? 'delivery' : 'pickup';
        break;
      case 'amount':
        aValue = a.total_amount;
        bValue = b.total_amount;
        break;
      case 'status':
        aValue = a.status;
        bValue = b.status;
        break;
      default:
        aValue = a.id;
        bValue = b.id;
    }

    if (sortDirection === 'asc') {
      return aValue > bValue ? 1 : -1;
    } else {
      return aValue < bValue ? 1 : -1;
    }
  });

  /**
   * Render sort indicator
   */
  const renderSortIndicator = (column) => {
    if (sortColumn !== column) {
      return null;
    }

    const Icon = sortDirection === 'asc' ? ChevronUpIcon : ChevronDownIcon;
    return <Icon className="w-4 h-4 inline-block mr-1" />;
  };

  /**
   * Render status badge
   */
  const renderStatusBadge = (order) => {
    const statusConfig = {
      completed: {
        icon: CheckCircleIcon,
        text: 'הושלם',
        className: 'bg-green-100 text-green-800',
      },
      cancelled: {
        icon: XCircleIcon,
        text: 'בוטל',
        className: 'bg-red-100 text-red-800',
      },
      refunded: {
        icon: ArrowPathIcon,
        text: 'הוחזר',
        className: 'bg-yellow-100 text-yellow-800',
      },
    };

    // Determine which badge to show
    let config;
    if (order.payment_status === 'refunded') {
      config = statusConfig.refunded;
    } else if (order.status === 'cancelled') {
      config = statusConfig.cancelled;
    } else if (order.status === 'completed') {
      config = statusConfig.completed;
    } else {
      // Default for other statuses
      return (
        <span className="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800">
          {order.status}
        </span>
      );
    }

    const Icon = config.icon;

    return (
      <span className={`inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full ${config.className}`}>
        <Icon className="w-4 h-4" />
        {config.text}
      </span>
    );
  };

  /**
   * Format currency
   */
  const formatCurrency = (value) => {
    return new Intl.NumberFormat('he-IL', {
      style: 'currency',
      currency: 'ILS',
      minimumFractionDigits: 0,
      maximumFractionDigits: 2,
    }).format(value);
  };

  /**
   * Format date and time
   */
  const formatDateTime = (dateString) => {
    const date = new Date(dateString);
    const dateStr = date.toLocaleDateString('he-IL');
    const timeStr = date.toLocaleTimeString('he-IL', {
      hour: '2-digit',
      minute: '2-digit',
    });
    return { date: dateStr, time: timeStr };
  };

  /**
   * Get order type icon and text
   */
  const getOrderType = (order) => {
    const isDelivery = order.delivery_address || order.payment_method === 'online';

    return {
      icon: isDelivery ? TruckIcon : ShoppingBagIcon,
      text: isDelivery ? 'משלוח' : 'איסוף',
    };
  };

  if (orders.length === 0) {
    return (
      <Card className="p-8 text-center">
        <p className="text-gray-500">אין הזמנות קודמות להצגה</p>
      </Card>
    );
  }

  return (
    <Card className="overflow-hidden relative">
      {/* Loading overlay for table only */}
      {loading && (
        <div className="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center z-10">
          <div className="flex flex-col items-center gap-2">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
            <span className="text-sm text-gray-600">טוען...</span>
          </div>
        </div>
      )}

      <div className="overflow-x-auto">
        <table className="min-w-full divide-y divide-gray-200">
          <thead className="bg-gray-50">
            <tr>
              <th
                onClick={() => handleSort('id')}
                className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
              >
                {renderSortIndicator('id')}
                מספר הזמנה
              </th>
              <th
                onClick={() => handleSort('date')}
                className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
              >
                {renderSortIndicator('date')}
                תאריך
              </th>
              <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                שעה
              </th>
              <th
                onClick={() => handleSort('customer')}
                className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
              >
                {renderSortIndicator('customer')}
                לקוח
              </th>
              <th
                onClick={() => handleSort('type')}
                className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
              >
                {renderSortIndicator('type')}
                סוג
              </th>
              <th
                onClick={() => handleSort('amount')}
                className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
              >
                {renderSortIndicator('amount')}
                סכום
              </th>
              <th
                onClick={() => handleSort('status')}
                className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
              >
                {renderSortIndicator('status')}
                סטטוס
              </th>
            </tr>
          </thead>
          <tbody className="bg-white divide-y divide-gray-200">
            {sortedOrders.map((order) => {
              const { date, time } = formatDateTime(order.order_date);
              const orderType = getOrderType(order);
              const TypeIcon = orderType.icon;
              const customer = customers[order.customer_id];

              return (
                <tr
                  key={order.id}
                  onClick={() => onOrderClick && onOrderClick(order)}
                  className="hover:bg-gray-50 cursor-pointer"
                >
                  <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    #{order.id}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {date}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {time}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {customer?.name || '-'}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <div className="flex items-center gap-2">
                      <TypeIcon className="w-4 h-4" />
                      {orderType.text}
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    {formatCurrency(order.total_amount)}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm">
                    {renderStatusBadge(order)}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {/* Pagination - Using backend pagination props */}
      {onPageChange && onItemsPerPageChange && (
        <Pagination
          currentPage={currentPage}
          totalItems={totalItems}
          itemsPerPage={itemsPerPage}
          onPageChange={onPageChange}
          onItemsPerPageChange={onItemsPerPageChange}
        />
      )}
    </Card>
  );
};

export default PreviousOrdersTable;
