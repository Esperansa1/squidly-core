import React from 'react';
import { Card } from './atoms';
import LiveOrderCard from './LiveOrderCard.jsx';

const OrderColumn = ({
  title,
  orders = [],
  customers = {},
  branches = [],
  emptyMessage = 'אין הזמנות',
  onAccept,
  onDecline,
  onMarkReady,
  onMarkPickedUp,
}) => {
  return (
    <div className="flex flex-col h-full min-h-0">
      {/* Column Header */}
      <div className="mb-4 flex-shrink-0">
        <h2 className="text-lg font-bold text-gray-900">
          {title}
          <span className="text-sm font-normal text-gray-600 ms-2">
            ({orders.length})
          </span>
        </h2>
      </div>

      {/* Scrollable Order Cards */}
      <div className="flex-1 overflow-y-auto space-y-4 scrollbar-hide">
        {orders.length === 0 ? (
          <Card className="p-8 text-center">
            <p className="text-gray-500">{emptyMessage}</p>
          </Card>
        ) : (
          orders.map((order) => (
            <LiveOrderCard
              key={order.id}
              order={order}
              customer={customers[order.customer_id]}
              branches={branches}
              onAccept={onAccept}
              onDecline={onDecline}
              onMarkReady={onMarkReady}
              onMarkPickedUp={onMarkPickedUp}
            />
          ))
        )}
      </div>
    </div>
  );
};

export default OrderColumn;
