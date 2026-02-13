import ArrowTrendingUpIcon from '@heroicons/react/24/outline/ArrowTrendingUpIcon';
import ArrowTrendingDownIcon from '@heroicons/react/24/outline/ArrowTrendingDownIcon';
import Card from '../atoms/Card';

/**
 * Statistics Cards Component
 *
 * Displays key metrics with percentage change indicators
 * Used in the Previous Orders page
 */
const StatisticsCards = ({ statistics }) => {
  const {
    total_revenue = 0,
    total_orders = 0,
    cancellation_count = 0,
    refund_count = 0,
    percentage_change = {},
  } = statistics;

  /**
   * Format currency value
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
   * Format percentage change
   */
  const formatPercentage = (value) => {
    const abs = Math.abs(value);
    return `${abs.toFixed(1)}%`;
  };

  /**
   * Render percentage change indicator
   */
  const renderChangeIndicator = (value) => {
    if (!value || value === 0) {
      return null;
    }

    const isPositive = value > 0;
    const Icon = isPositive ? ArrowTrendingUpIcon : ArrowTrendingDownIcon;
    const colorClass = isPositive ? 'text-green-600' : 'text-red-600';
    const bgColorClass = isPositive ? 'bg-green-50' : 'bg-red-50';

    return (
      <div className={`flex items-center gap-1 px-2 py-1 rounded-full ${bgColorClass}`}>
        <Icon className={`w-4 h-4 ${colorClass}`} />
        <span className={`text-sm font-medium ${colorClass}`}>
          {formatPercentage(value)}
        </span>
      </div>
    );
  };

  const cards = [
    {
      title: 'סך ההכנסות',
      value: formatCurrency(total_revenue),
      change: percentage_change.total_revenue,
      colorClass: 'text-blue-600',
      bgColorClass: 'bg-blue-50',
    },
    {
      title: 'מספר הזמנות',
      value: total_orders.toLocaleString('he-IL'),
      change: percentage_change.total_orders,
      colorClass: 'text-purple-600',
      bgColorClass: 'bg-purple-50',
    },
    {
      title: 'ביטולים',
      value: cancellation_count.toLocaleString('he-IL'),
      change: percentage_change.cancellation_count,
      colorClass: 'text-orange-600',
      bgColorClass: 'bg-orange-50',
      invertColors: true, // For cancellations, down is good
    },
    {
      title: 'החזרים',
      value: refund_count.toLocaleString('he-IL'),
      change: percentage_change.refund_count,
      colorClass: 'text-red-600',
      bgColorClass: 'bg-red-50',
      invertColors: true, // For refunds, down is good
    },
  ];

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      {cards.map((card, index) => (
        <Card key={index} className="p-4">
          <div className="flex flex-col gap-3">
            {/* Title */}
            <h3 className="text-sm font-medium text-gray-500">{card.title}</h3>

            {/* Value and Change */}
            <div className="flex items-center justify-between">
              <div className={`text-2xl font-bold ${card.colorClass}`}>
                {card.value}
              </div>
              {card.change !== undefined && renderChangeIndicator(
                card.invertColors ? -card.change : card.change
              )}
            </div>
          </div>
        </Card>
      ))}
    </div>
  );
};

export default StatisticsCards;
