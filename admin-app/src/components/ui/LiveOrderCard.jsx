import React, { useState } from 'react';
import { ChevronDownIcon, ChevronUpIcon, TruckIcon, ShoppingBagIcon } from '@heroicons/react/24/outline';
import { Card, Button } from './atoms';
import { TimeIndicator, OrderItemsList } from './molecules';

const LiveOrderCard = ({
  order,
  onAccept,
  onReject,
  onMarkReady,
  customer
}) => {
  const [isCustomerExpanded, setIsCustomerExpanded] = useState(false);
  const [isOrderExpanded, setIsOrderExpanded] = useState(false);
  const [isNotesExpanded, setIsNotesExpanded] = useState(false);

  const isDelivery = order.payment_method === 'online' || order.delivery_address;
  const isPending = order.status === 'pending';
  const isPreparing = order.status === 'preparing' || order.status === 'confirmed';

  return (
    <Card className="p-4 space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <h3 className="text-lg font-bold text-gray-900">
            הזמנה מס' {order.id}
          </h3>
          {isDelivery ? (
            <TruckIcon className="w-5 h-5 text-gray-500" />
          ) : (
            <ShoppingBagIcon className="w-5 h-5 text-gray-500" />
          )}
        </div>
        <TimeIndicator orderDate={order.order_date} />
      </div>

      {/* Customer Details - Expandable */}
      <div className="border-t pt-3">
        <button
          onClick={() => setIsCustomerExpanded(!isCustomerExpanded)}
          className="flex items-center justify-between w-full text-right"
        >
          <span className="text-sm font-medium text-gray-700">
            פרטי הלקוח: {customer?.name || 'לקוח'}
          </span>
          {isCustomerExpanded ? (
            <ChevronUpIcon className="w-4 h-4 text-gray-500" />
          ) : (
            <ChevronDownIcon className="w-4 h-4 text-gray-500" />
          )}
        </button>

        {isCustomerExpanded && customer && (
          <div className="mt-2 text-sm text-gray-600 space-y-1">
            {customer.phone && <p>טלפון: {customer.phone}</p>}
            {order.delivery_address && <p>כתובת: {order.delivery_address}</p>}
          </div>
        )}
      </div>

      {/* Order Details - Expandable */}
      <div className="border-t pt-3">
        <button
          onClick={() => setIsOrderExpanded(!isOrderExpanded)}
          className="flex items-center justify-between w-full text-right"
        >
          <span className="text-sm font-medium text-gray-700">פרטי ההזמנה</span>
          {isOrderExpanded ? (
            <ChevronUpIcon className="w-4 h-4 text-gray-500" />
          ) : (
            <ChevronDownIcon className="w-4 h-4 text-gray-500" />
          )}
        </button>

        {isOrderExpanded && (
          <div className="mt-3">
            <OrderItemsList items={order.order_items} />
          </div>
        )}
      </div>

      {/* Notes - Expandable */}
      {order.special_instructions && (
        <div className="border-t pt-3">
          <button
            onClick={() => setIsNotesExpanded(!isNotesExpanded)}
            className="flex items-center justify-between w-full text-right"
          >
            <span className="text-sm font-medium text-gray-700">הודעה:</span>
            {isNotesExpanded ? (
              <ChevronUpIcon className="w-4 h-4 text-gray-500" />
            ) : (
              <ChevronDownIcon className="w-4 h-4 text-gray-500" />
            )}
          </button>

          {isNotesExpanded && (
            <p className="mt-2 text-sm text-gray-600">{order.special_instructions}</p>
          )}
        </div>
      )}

      {/* Action Buttons */}
      <div className="border-t pt-3 space-y-2">
        {isPending && (
          <div className="flex gap-2">
            <Button
              variant="primary"
              onClick={() => onAccept(order.id)}
              className="flex-1"
            >
              קבל הזמנה
            </Button>
            <Button
              variant="secondary"
              onClick={() => onReject(order.id)}
              className="flex-1"
            >
              דחה הזמנה
            </Button>
          </div>
        )}

        {isPreparing && (
          <div className="space-y-2">
            <Button
              variant="primary"
              onClick={() => onMarkReady(order.id)}
              className="w-full"
            >
              הזמנה מוכנה
            </Button>
            {order.estimated_ready_time && (
              <div className="text-center">
                <span className="inline-block px-3 py-1 text-xs font-medium bg-red-500 text-white rounded-full">
                  זמן שמעריכי: {order.estimated_ready_time} דק'
                </span>
              </div>
            )}
          </div>
        )}
      </div>
    </Card>
  );
};

export default LiveOrderCard;
