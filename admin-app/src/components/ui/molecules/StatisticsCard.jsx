import React from 'react';
import { ArrowUpIcon, ArrowDownIcon } from '@heroicons/react/24/outline';
import { Card } from '../atoms';

const StatisticsCard = ({
  title,
  value,
  trend,
  icon: Icon,
  iconBgColor = 'bg-red-50',
  iconColor = 'text-red-500'
}) => {
  const trendValue = parseFloat(trend);
  const isPositive = trendValue > 0;
  const isNegative = trendValue < 0;

  return (
    <Card className="p-6">
      <div className="flex items-center justify-between">
        <div className="flex-1">
          <p className="text-sm text-gray-600 mb-2">{title}</p>
          <p className="text-2xl font-bold text-gray-900">{value}</p>

          {trend !== undefined && (
            <div className={`flex items-center mt-2 text-sm ${
              isPositive ? 'text-green-600' : isNegative ? 'text-red-600' : 'text-gray-600'
            }`}>
              {isPositive && <ArrowUpIcon className="w-4 h-4 ms-1" />}
              {isNegative && <ArrowDownIcon className="w-4 h-4 ms-1" />}
              <span>{Math.abs(trendValue)}%</span>
            </div>
          )}
        </div>

        {Icon && (
          <div className={`p-3 rounded-lg ${iconBgColor}`}>
            <Icon className={`w-6 h-6 ${iconColor}`} />
          </div>
        )}
      </div>
    </Card>
  );
};

export default StatisticsCard;
