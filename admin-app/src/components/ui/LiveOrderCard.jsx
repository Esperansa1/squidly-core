import React, { useState } from 'react';
import ChevronDownIcon from '@heroicons/react/24/outline/ChevronDownIcon';
import ChevronUpIcon from '@heroicons/react/24/outline/ChevronUpIcon';
import TruckIcon from '@heroicons/react/24/outline/TruckIcon';
import ShoppingBagIcon from '@heroicons/react/24/outline/ShoppingBagIcon';
import { Card, Button } from './atoms';
import { TimeIndicator, OrderItemsList } from './molecules';

const LiveOrderCard = ({
  order,
  customer,
  branches = [],
  onAccept,
  onDecline,
  onMarkReady,
  onMarkPickedUp
}) => {
  const [isCustomerInfoExpanded, setIsCustomerInfoExpanded] = useState(false);
  const [isCustomerMessageExpanded, setIsCustomerMessageExpanded] = useState(false);
  const [isOrderExpanded, setIsOrderExpanded] = useState(true); // Default open for order items

  // Determine order status and type
  const isDelivery = order.payment_method === 'online' || order.delivery_address;
  const isPending = order.status === 'pending';
  const isPreparing = order.status === 'preparing' || order.status === 'confirmed';
  const isReady = order.status === 'ready';

  // Get branch name
  const getBranchName = () => {
    if (!order.branch_id) return null;
    const branch = branches.find(b => b.id === order.branch_id);
    return branch ? branch.name : null;
  };

  const branchName = getBranchName();

  // Format order time from order_date
  const formatOrderTime = (dateString) => {
    const date = new Date(dateString);
    const hours = date.getHours().toString().padStart(2, '0');
    const minutes = date.getMinutes().toString().padStart(2, '0');
    return `${hours}:${minutes}`;
  };

  // Calculate remaining time for in-progress orders
  const getRemainingTime = () => {
    if (!order.estimated_ready_time) return null;

    const now = new Date();
    const readyTime = new Date(order.estimated_ready_time);
    const diffMs = readyTime - now;
    const diffMins = Math.floor(diffMs / 60000);

    return diffMins;
  };

  const getTimeColor = (minutes) => {
    if (minutes === null) return 'bg-gray-100 text-gray-800';
    if (minutes < 0) return 'bg-red-100 text-red-800'; // Overdue
    if (minutes <= 5) return 'bg-red-100 text-red-800'; // Urgent
    if (minutes <= 10) return 'bg-yellow-100 text-yellow-800'; // Warning
    return 'bg-green-100 text-green-800'; // Good
  };

  const formatRemainingTime = (minutes) => {
    if (minutes === null) return '';
    if (minutes < 0) return `איחור: ${Math.abs(minutes)} דק'`;
    return `זמן נותר: ${minutes} דק'`;
  };

  const remainingMinutes = isPreparing ? getRemainingTime() : null;

  const formatPrice = (price) => {
    return `₪${parseFloat(price).toFixed(2)}`;
  };

  return (
    <Card className="p-4 space-y-3">
      {/* Header - Order ID, Branch & Time */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <h3 className="text-lg font-bold text-gray-900">
            הזמנה #{order.id}
          </h3>
          {isDelivery ? (
            <TruckIcon className="w-5 h-5 text-gray-500" />
          ) : (
            <ShoppingBagIcon className="w-5 h-5 text-gray-500" />
          )}
          {branchName && (
            <span className="text-sm text-gray-500">• {branchName}</span>
          )}
        </div>
        <span className="text-sm font-medium text-gray-600">
          {formatOrderTime(order.order_date)}
        </span>
      </div>

      {/* Grey Background Container - Customer Info & Message */}
      <div className="border-t pt-3">
        <div className="bg-gray-50 rounded-lg p-3 space-y-3">
          {/* Customer Details Section */}
          <div>
            <button
              onClick={() => setIsCustomerInfoExpanded(!isCustomerInfoExpanded)}
              className="flex items-center justify-between w-full text-right p-2 -m-2 focus:outline-none"
            >
              <span className="text-sm font-medium text-gray-700">
                פרטי הלקוח: {customer?.name || 'לקוח לא ידוע'}
              </span>
              {isCustomerInfoExpanded ? (
                <ChevronUpIcon className="w-4 h-4 text-gray-500" />
              ) : (
                <ChevronDownIcon className="w-4 h-4 text-gray-500" />
              )}
            </button>

            {isCustomerInfoExpanded && customer && (
              <div className="mt-2 text-sm text-gray-600 space-y-1 pe-2">
                {customer.phone && <p>טלפון: {customer.phone}</p>}
                {customer.email && <p>אימייל: {customer.email}</p>}
                {order.delivery_address && <p>כתובת משלוח: {order.delivery_address}</p>}
              </div>
            )}
          </div>

          {/* Customer Message/Special Instructions */}
          {order.special_instructions && (
            <div className="pt-2 border-t border-gray-200">
              <button
                onClick={() => setIsCustomerMessageExpanded(!isCustomerMessageExpanded)}
                className="flex items-center justify-between w-full text-right p-2 -m-2 focus:outline-none"
              >
                <span className="text-sm font-medium text-gray-700">הודעת לקוח:</span>
                {isCustomerMessageExpanded ? (
                  <ChevronUpIcon className="w-4 h-4 text-gray-500" />
                ) : (
                  <ChevronDownIcon className="w-4 h-4 text-gray-500" />
                )}
              </button>

              {isCustomerMessageExpanded && (
                <p className="mt-2 text-sm text-gray-600 bg-gray-50 p-3 rounded">
                  {order.special_instructions}
                </p>
              )}
            </div>
          )}
        </div>
      </div>

      {/* Order Items - Always visible by default */}
      <div className="mt-4">
        <button
          onClick={() => setIsOrderExpanded(!isOrderExpanded)}
          className="flex items-center justify-between w-full text-right p-2 -m-2 focus:outline-none"
        >
          <span className="text-sm font-medium text-gray-700">
            פרטי ההזמנה ({order.order_items?.length || 0} פריטים)
          </span>
          <div className="flex items-center gap-2">
            <span className="text-sm font-bold text-gray-900">
              {formatPrice(order.total_amount)}
            </span>
            {isOrderExpanded ? (
              <ChevronUpIcon className="w-4 h-4 text-gray-500" />
            ) : (
              <ChevronDownIcon className="w-4 h-4 text-gray-500" />
            )}
          </div>
        </button>

        {isOrderExpanded && (
          <div className="mt-3 space-y-3">
            <OrderItemsList items={order.order_items} showPrices={true} />

            {/* Price Breakdown */}
            <div className="border-t pt-3 space-y-1 text-sm">
              <div className="flex justify-between text-gray-600">
                <span>סכום ביניים:</span>
                <span>{formatPrice(order.subtotal)}</span>
              </div>
              {order.tax_amount > 0 && (
                <div className="flex justify-between text-gray-600">
                  <span>מע"מ:</span>
                  <span>{formatPrice(order.tax_amount)}</span>
                </div>
              )}
              {order.delivery_fee > 0 && (
                <div className="flex justify-between text-gray-600">
                  <span>דמי משלוח:</span>
                  <span>{formatPrice(order.delivery_fee)}</span>
                </div>
              )}
              <div className="flex justify-between font-bold text-gray-900 pt-1 border-t">
                <span>סה"כ:</span>
                <span>{formatPrice(order.total_amount)}</span>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Action Buttons Based on Status */}
      <div className="space-y-2 pt-4">
        {/* Pending Orders - Accept/Decline */}
        {isPending && (
          <div className="flex gap-2">
            <Button
              variant="primary"
              onClick={() => onAccept && onAccept(order.id)}
              className="flex-1 bg-green-600 hover:bg-green-700"
            >
              קבל הזמנה
            </Button>
            <Button
              variant="secondary"
              onClick={() => onDecline && onDecline(order.id)}
              className="flex-1 border-red-600 text-red-600 hover:bg-red-50"
            >
              דחה הזמנה
            </Button>
          </div>
        )}

        {/* In Progress Orders - Mark Ready + Time Remaining */}
        {isPreparing && (
          <div className="space-y-2">
            <Button
              variant="primary"
              onClick={() => onMarkReady && onMarkReady(order.id)}
              className="w-full"
            >
              הזמנה מוכנה
            </Button>
            {remainingMinutes !== null && (
              <div className="text-center">
                <span className={`inline-block px-3 py-1 text-xs font-medium rounded-full ${getTimeColor(remainingMinutes)}`}>
                  {formatRemainingTime(remainingMinutes)}
                </span>
              </div>
            )}
          </div>
        )}

        {/* Ready Orders - Mark as Picked Up */}
        {isReady && (
          <Button
            variant="primary"
            onClick={() => onMarkPickedUp && onMarkPickedUp(order.id)}
            className="w-full bg-blue-600 hover:bg-blue-700"
          >
            סומן כנאסף
          </Button>
        )}
      </div>
    </Card>
  );
};

export default LiveOrderCard;
