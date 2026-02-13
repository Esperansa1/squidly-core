import Modal from './Modal';
import UserIcon from '@heroicons/react/24/outline/UserIcon';
import PhoneIcon from '@heroicons/react/24/outline/PhoneIcon';
import MapPinIcon from '@heroicons/react/24/outline/MapPinIcon';
import CreditCardIcon from '@heroicons/react/24/outline/CreditCardIcon';
import BanknotesIcon from '@heroicons/react/24/outline/BanknotesIcon';
import ClockIcon from '@heroicons/react/24/outline/ClockIcon';
import TruckIcon from '@heroicons/react/24/outline/TruckIcon';
import ShoppingBagIcon from '@heroicons/react/24/outline/ShoppingBagIcon';

/**
 * Order Details Modal Component
 *
 * Displays full details of a previous order
 */
const OrderDetailsModal = ({ isOpen, onClose, order, customer }) => {
  if (!order) return null;

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
    return date.toLocaleString('he-IL', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  /**
   * Get order type
   */
  const isDelivery = order.delivery_address || order.payment_method === 'online';
  const TypeIcon = isDelivery ? TruckIcon : ShoppingBagIcon;
  const typeText = isDelivery ? 'משלוח' : 'איסוף';

  /**
   * Get payment method icon and text
   */
  const getPaymentMethod = () => {
    const methods = {
      cash: { icon: BanknotesIcon, text: 'מזומן' },
      card: { icon: CreditCardIcon, text: 'כרטיס אשראי' },
      online: { icon: CreditCardIcon, text: 'תשלום אונליין' },
    };

    return methods[order.payment_method] || methods.cash;
  };

  const paymentMethod = getPaymentMethod();
  const PaymentIcon = paymentMethod.icon;

  /**
   * Get status color
   */
  const getStatusColor = (status) => {
    const colors = {
      pending: 'text-yellow-600 bg-yellow-50',
      confirmed: 'text-blue-600 bg-blue-50',
      preparing: 'text-purple-600 bg-purple-50',
      ready: 'text-green-600 bg-green-50',
      completed: 'text-green-700 bg-green-100',
      cancelled: 'text-red-600 bg-red-50',
    };

    return colors[status] || 'text-gray-600 bg-gray-50';
  };

  /**
   * Get payment status color
   */
  const getPaymentStatusColor = (status) => {
    const colors = {
      pending: 'text-yellow-600 bg-yellow-50',
      paid: 'text-green-600 bg-green-50',
      failed: 'text-red-600 bg-red-50',
      refunded: 'text-orange-600 bg-orange-50',
    };

    return colors[status] || 'text-gray-600 bg-gray-50';
  };

  /**
   * Translate status
   */
  const translateStatus = (status) => {
    const translations = {
      pending: 'ממתין',
      confirmed: 'אושר',
      preparing: 'בהכנה',
      ready: 'מוכן',
      completed: 'הושלם',
      cancelled: 'בוטל',
    };

    return translations[status] || status;
  };

  /**
   * Translate payment status
   */
  const translatePaymentStatus = (status) => {
    const translations = {
      pending: 'ממתין לתשלום',
      paid: 'שולם',
      failed: 'נכשל',
      refunded: 'הוחזר',
    };

    return translations[status] || status;
  };

  /**
   * Extract modification names from various formats:
   * - Object format (from cart): { groupId: [{ id, name, price }, ...], ... }
   * - Array of objects: [{ name, price }, ...]
   * - Array of strings (legacy): ["Extra Cheese", "Mushrooms"]
   * Returns array of modification name strings
   */
  const getModificationNames = (modifications) => {
    if (!modifications) return [];

    // If it's an array
    if (Array.isArray(modifications)) {
      if (modifications.length === 0) return [];
      // Check if array of strings or array of objects
      if (typeof modifications[0] === 'string') {
        return modifications; // Already array of strings
      }
      // Array of objects with name property
      return modifications.map(mod => mod.name).filter(Boolean);
    }

    // If it's an object with group IDs as keys
    if (typeof modifications === 'object') {
      const names = [];
      Object.values(modifications).forEach(groupItems => {
        if (Array.isArray(groupItems)) {
          groupItems.forEach(item => {
            if (item.name) {
              names.push(item.name);
            }
          });
        }
      });
      return names;
    }

    return [];
  };

  return (
    <Modal isOpen={isOpen} onClose={onClose} title={`הזמנה #${order.id}`} size="lg">
      <div className="space-y-6">
        {/* Order Info Section */}
        <div className="grid grid-cols-2 gap-4">
          <div>
            <div className="text-sm text-gray-500 mb-1">תאריך ושעה</div>
            <div className="flex items-center gap-2 text-gray-900">
              <ClockIcon className="w-5 h-5 text-gray-400" />
              {formatDateTime(order.order_date)}
            </div>
          </div>

          <div>
            <div className="text-sm text-gray-500 mb-1">סוג הזמנה</div>
            <div className="flex items-center gap-2 text-gray-900">
              <TypeIcon className="w-5 h-5 text-gray-400" />
              {typeText}
            </div>
          </div>
        </div>

        {/* Customer Info Section */}
        {customer && (
          <div className="border-t pt-4">
            <h4 className="font-semibold text-gray-900 mb-3">פרטי לקוח</h4>
            <div className="space-y-2">
              <div className="flex items-center gap-2 text-gray-700">
                <UserIcon className="w-5 h-5 text-gray-400" />
                {customer.name}
              </div>
              {customer.phone && (
                <div className="flex items-center gap-2 text-gray-700">
                  <PhoneIcon className="w-5 h-5 text-gray-400" />
                  {customer.phone}
                </div>
              )}
              {order.delivery_address && (
                <div className="flex items-center gap-2 text-gray-700">
                  <MapPinIcon className="w-5 h-5 text-gray-400" />
                  {order.delivery_address}
                </div>
              )}
            </div>
          </div>
        )}

        {/* Order Items Section */}
        <div className="border-t pt-4">
          <h4 className="font-semibold text-gray-900 mb-3">פריטים בהזמנה</h4>
          <div className="space-y-3">
            {order.order_items && order.order_items.map((item, index) => {
              const modificationNames = getModificationNames(item.modifications);

              return (
                <div key={index} className="flex justify-between items-start bg-gray-50 p-3 rounded-lg">
                  <div className="flex-1">
                    <div className="font-medium text-gray-900">{item.product_name}</div>
                    {modificationNames.length > 0 && (
                      <div className="text-sm text-gray-600 mt-1">
                        {modificationNames.join(', ')}
                      </div>
                    )}
                    {item.notes && (
                      <div className="text-sm text-amber-600 mt-1 italic">
                        הערה: {item.notes}
                      </div>
                    )}
                  </div>
                  <div className="text-left mr-4">
                    <div className="text-gray-600">
                      {item.quantity} × {formatCurrency(item.unit_price)}
                    </div>
                    <div className="font-semibold text-gray-900">
                      {formatCurrency(item.total_price || item.quantity * item.unit_price)}
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>

        {/* Special Instructions */}
        {order.special_instructions && (
          <div className="border-t pt-4">
            <h4 className="font-semibold text-gray-900 mb-2">הוראות מיוחדות</h4>
            <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-gray-700">
              {order.special_instructions}
            </div>
          </div>
        )}

        {/* Notes */}
        {order.notes && (
          <div className="border-t pt-4">
            <h4 className="font-semibold text-gray-900 mb-2">הערות</h4>
            <div className="bg-gray-50 rounded-lg p-3 text-gray-700">
              {order.notes}
            </div>
          </div>
        )}

        {/* Payment & Status Section */}
        <div className="border-t pt-4">
          <div className="grid grid-cols-2 gap-4 mb-4">
            <div>
              <div className="text-sm text-gray-500 mb-1">אמצעי תשלום</div>
              <div className="flex items-center gap-2 text-gray-900">
                <PaymentIcon className="w-5 h-5 text-gray-400" />
                {paymentMethod.text}
              </div>
            </div>

            <div>
              <div className="text-sm text-gray-500 mb-1">סטטוס תשלום</div>
              <span className={`inline-block px-3 py-1 text-sm font-medium rounded-full ${getPaymentStatusColor(order.payment_status)}`}>
                {translatePaymentStatus(order.payment_status)}
              </span>
            </div>
          </div>

          <div>
            <div className="text-sm text-gray-500 mb-1">סטטוס הזמנה</div>
            <span className={`inline-block px-3 py-1 text-sm font-medium rounded-full ${getStatusColor(order.status)}`}>
              {translateStatus(order.status)}
            </span>
          </div>
        </div>

        {/* Price Breakdown Section */}
        <div className="border-t pt-4">
          <h4 className="font-semibold text-gray-900 mb-3">פירוט מחיר</h4>
          <div className="space-y-2">
            <div className="flex justify-between text-gray-700">
              <span>סכום ביניים</span>
              <span>{formatCurrency(order.subtotal)}</span>
            </div>
            {order.tax_amount > 0 && (
              <div className="flex justify-between text-gray-700">
                <span>מע"ם</span>
                <span>{formatCurrency(order.tax_amount)}</span>
              </div>
            )}
            {order.delivery_fee > 0 && (
              <div className="flex justify-between text-gray-700">
                <span>דמי משלוח</span>
                <span>{formatCurrency(order.delivery_fee)}</span>
              </div>
            )}
            <div className="flex justify-between font-bold text-lg text-gray-900 border-t pt-2 mt-2">
              <span>סה"כ</span>
              <span>{formatCurrency(order.total_amount)}</span>
            </div>
          </div>
        </div>
      </div>
    </Modal>
  );
};

export default OrderDetailsModal;
