import React, { useState, useEffect } from 'react';
import { Card, Button } from './ui/atoms';
import TabSelector from './ui/TabSelector';
import {
  ArrowTrendingUpIcon,
  ArrowTrendingDownIcon,
  CalendarIcon,
  ShoppingCartIcon,
  UserPlusIcon,
  BanknotesIcon,
} from '@heroicons/react/24/outline';
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  Legend,
} from 'recharts';
import api from '../services/api';

const ManagementDashboard = () => {
  const [period, setPeriod] = useState('this_month');
  const [customDateFrom, setCustomDateFrom] = useState('');
  const [customDateTo, setCustomDateTo] = useState('');
  const [showCustomDate, setShowCustomDate] = useState(false);
  const [analytics, setAnalytics] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    fetchAnalytics();
  }, [period, customDateFrom, customDateTo]);

  const fetchAnalytics = async () => {
    try {
      setLoading(true);
      setError(null);

      const params = { period };
      if (period === 'custom' && customDateFrom && customDateTo) {
        params.date_from = customDateFrom;
        params.date_to = customDateTo;
      }

      const response = await api.fetch('dashboard/analytics', { params });
      setAnalytics(response);
    } catch (err) {
      setError(err.message || 'Failed to fetch analytics');
      console.error('Analytics error:', err);
    } finally {
      setLoading(false);
    }
  };

  const handlePeriodChange = (newPeriod) => {
    setPeriod(newPeriod);
    setShowCustomDate(newPeriod === 'custom');
  };

  const formatCurrency = (value) => {
    return `₪${parseFloat(value).toLocaleString('he-IL', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}`;
  };

  const formatPercent = (value) => {
    const absValue = Math.abs(value);
    return `${absValue.toFixed(2)}%`;
  };

  const formatDate = (dateStr) => {
    const date = new Date(dateStr);
    return date.toLocaleDateString('he-IL', { day: 'numeric', month: 'short' });
  };

  // Period label mappings for TabSelector
  const periodLabels = {
    'today': 'היום',
    'yesterday': 'אתמול',
    'this_week': 'השבוע',
    'last_week': 'שבוע שעבר',
    'this_month': 'החודש',
    'last_month': 'חודש שעבר',
    'custom': 'מותאם אישית',
  };

  const periodIds = {
    'היום': 'today',
    'אתמול': 'yesterday',
    'השבוע': 'this_week',
    'שבוע שעבר': 'last_week',
    'החודש': 'this_month',
    'חודש שעבר': 'last_month',
    'מותאם אישית': 'custom',
  };

  const getActivePeriodLabel = (periodId) => periodLabels[periodId];

  const handleTabChange = (label) => {
    const periodId = periodIds[label];
    handlePeriodChange(periodId);
  };

  const kpis = analytics?.kpis;
  const charts = analytics?.charts;

  return (
    <div className="p-6">
      <div className="space-y-6">
        {/* Header with Period Filter */}
        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
          <h1 className="text-2xl font-bold text-gray-900">ניהול ודוחות</h1>

          <TabSelector
            tabs={['היום', 'אתמול', 'השבוע', 'שבוע שעבר', 'החודש', 'חודש שעבר', 'מותאם אישית']}
            activeTab={getActivePeriodLabel(period)}
            onTabChange={handleTabChange}
          />
        </div>

        {/* Custom Date Range Picker */}
        {showCustomDate && (
          <Card className="p-4">
            <div className="flex items-center gap-4">
              <div className="flex items-center gap-2">
                <CalendarIcon className="w-5 h-5 text-gray-500" />
                <label className="text-sm font-medium text-gray-700">מתאריך:</label>
                <input
                  type="date"
                  value={customDateFrom}
                  onChange={(e) => setCustomDateFrom(e.target.value)}
                  className="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500"
                />
              </div>
              <div className="flex items-center gap-2">
                <label className="text-sm font-medium text-gray-700">עד תאריך:</label>
                <input
                  type="date"
                  value={customDateTo}
                  onChange={(e) => setCustomDateTo(e.target.value)}
                  className="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500"
                />
              </div>
              <Button
                variant="primary"
                onClick={fetchAnalytics}
                disabled={!customDateFrom || !customDateTo}
              >
                הצג
              </Button>
            </div>
          </Card>
        )}

        {/* KPI Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          {loading || error ? (
            // Loading/Error state for KPI cards
            <>
              {[1, 2, 3, 4].map((i) => (
                <Card key={i} className="p-6">
                  <div className="flex items-center justify-center h-24">
                    {loading && <div className="text-sm text-gray-500">טוען...</div>}
                    {error && <div className="text-sm text-red-500">שגיאה בטעינה</div>}
                  </div>
                </Card>
              ))}
            </>
          ) : (
            <>
              {/* Total Revenue */}
              <KPICard
                title="הכנסות כוללות"
                value={formatCurrency(kpis.total_revenue.value)}
                change={kpis.total_revenue.change}
                changeType={kpis.total_revenue.change_type}
                icon={BanknotesIcon}
                iconColor="text-gray-900"
                iconBg="bg-gray-100"
              />

              {/* Total Orders */}
              <KPICard
                title="הזמנות"
                value={kpis.total_orders.value.toString()}
                change={kpis.total_orders.change}
                changeType={kpis.total_orders.change_type}
                icon={ShoppingCartIcon}
                iconColor="text-gray-900"
                iconBg="bg-gray-100"
              />

              {/* New Customers */}
              <KPICard
                title="לקוחות חדשים"
                value={kpis.new_customers.value.toString()}
                change={kpis.new_customers.change}
                changeType={kpis.new_customers.change_type}
                icon={UserPlusIcon}
                iconColor="text-gray-900"
                iconBg="bg-gray-100"
              />

              {/* Average Order Value */}
              <KPICard
                title="סכום הזמנה ממוצע"
                value={formatCurrency(kpis.average_order_value.value)}
                change={kpis.average_order_value.change}
                changeType={kpis.average_order_value.change_type}
                icon={BanknotesIcon}
                iconColor="text-gray-900"
                iconBg="bg-gray-100"
              />
            </>
          )}
        </div>

        {/* Charts Row */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Revenue Chart */}
          <Card className="p-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">הכנסות</h2>
            <div className="h-80">
              {loading || error ? (
                <div className="flex items-center justify-center h-full">
                  {loading && <div className="text-gray-500">טוען גרף...</div>}
                  {error && <div className="text-red-500">שגיאה בטעינת הגרף</div>}
                </div>
              ) : (
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart
                    data={charts.revenue}
                    margin={{ top: 20, right: 30, left: 20, bottom: 60 }}
                  >
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis
                      dataKey="date"
                      tickFormatter={formatDate}
                      angle={-45}
                      textAnchor="end"
                      height={70}
                    />
                    <YAxis
                      tickFormatter={(value) => `₪${value}`}
                      width={60}
                      tick={{ textAnchor: 'start' }}
                    />
                    <Tooltip
                      formatter={(value) => formatCurrency(value)}
                      labelFormatter={(label) => formatDate(label)}
                    />
                    <Bar dataKey="revenue" fill="#D12525" name="הכנסות" />
                  </BarChart>
                </ResponsiveContainer>
              )}
            </div>
          </Card>

          {/* Orders Chart */}
          <Card className="p-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">הזמנות</h2>
            <div className="h-80">
              {loading || error ? (
                <div className="flex items-center justify-center h-full">
                  {loading && <div className="text-gray-500">טוען גרף...</div>}
                  {error && <div className="text-red-500">שגיאה בטעינת הגרף</div>}
                </div>
              ) : (
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart
                    data={charts.orders}
                    margin={{ top: 20, right: 30, left: 20, bottom: 60 }}
                  >
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis
                      dataKey="date"
                      tickFormatter={formatDate}
                      angle={-45}
                      textAnchor="end"
                      height={70}
                    />
                    <YAxis
                      width={60}
                      tick={{ textAnchor: 'start' }}
                    />
                    <Tooltip labelFormatter={(label) => formatDate(label)} />
                    <Legend />
                    <Bar dataKey="orders" fill="#D12525" name="סה״כ הזמנות" />
                    <Bar dataKey="completed" fill="#10B981" name="הושלמו" />
                    <Bar dataKey="cancelled" fill="#EF4444" name="בוטלו" />
                  </BarChart>
                </ResponsiveContainer>
              )}
            </div>
          </Card>
        </div>

        {/* Top Products Table */}
        <Card className="p-6">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">מוצרים חמים</h2>
          <div className="overflow-x-auto">
            {loading || error ? (
              <div className="flex items-center justify-center py-12">
                {loading && <div className="text-gray-500">טוען מוצרים...</div>}
                {error && <div className="text-red-500">שגיאה בטעינת המוצרים</div>}
              </div>
            ) : (
              <table className="w-full text-sm text-right">
                <thead className="text-xs text-gray-700 uppercase bg-gray-50">
                  <tr>
                    <th className="px-4 py-3">שם המוצר</th>
                    <th className="px-4 py-3">מחיר המוצר</th>
                    <th className="px-4 py-3">מכירות</th>
                    <th className="px-4 py-3">הכנסות ממוצר (%)</th>
                  </tr>
                </thead>
                <tbody>
                  {analytics.top_products.map((product, index) => (
                    <tr key={index} className="border-b hover:bg-gray-50">
                      <td className="px-4 py-3 font-medium text-gray-900">
                        {product.product_name}
                      </td>
                      <td className="px-4 py-3 text-gray-700">
                        {formatCurrency(product.unit_price)}
                      </td>
                      <td className="px-4 py-3 text-gray-700">
                        {product.total_quantity}
                      </td>
                      <td className="px-4 py-3 text-gray-700">
                        {product.revenue_percentage}%
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </Card>
      </div>
    </div>
  );
};

// KPI Card Component
const KPICard = ({ title, value, change, changeType, icon: Icon, iconColor, iconBg }) => {
  const isIncrease = changeType === 'increase';
  const TrendIcon = isIncrease ? ArrowTrendingUpIcon : ArrowTrendingDownIcon;

  return (
    <Card className="p-6">
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-sm font-medium text-gray-600">{title}</h3>
        <div className={`p-3 rounded-full ${iconBg}`}>
          <Icon className={`w-6 h-6 ${iconColor}`} />
        </div>
      </div>
      <div className="flex items-baseline justify-between">
        <p className="text-2xl font-bold text-gray-900">{value}</p>
        {change !== 0 && (
          <div
            className={`flex items-center gap-1 text-sm font-medium ${
              isIncrease ? 'text-green-600' : 'text-red-600'
            }`}
          >
            <TrendIcon className="w-4 h-4" />
            <span>{Math.abs(change).toFixed(2)}%</span>
          </div>
        )}
      </div>
    </Card>
  );
};

export default ManagementDashboard;
