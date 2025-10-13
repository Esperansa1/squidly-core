/**
 * Date Range Calculator Utility
 *
 * Calculates date ranges for different timeframes (day, week, month, year)
 * Used for filtering orders and statistics
 */

/**
 * Get date range for today
 */
export const getTodayRange = () => {
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  const endOfDay = new Date(today);
  endOfDay.setHours(23, 59, 59, 999);

  return {
    date_from: formatDate(today),
    date_to: formatDate(endOfDay),
  };
};

/**
 * Get date range for current week (Sunday to Saturday)
 */
export const getThisWeekRange = () => {
  const today = new Date();
  const dayOfWeek = today.getDay(); // 0 = Sunday, 6 = Saturday

  // Start of week (Sunday)
  const startOfWeek = new Date(today);
  startOfWeek.setDate(today.getDate() - dayOfWeek);
  startOfWeek.setHours(0, 0, 0, 0);

  // End of week (Saturday)
  const endOfWeek = new Date(startOfWeek);
  endOfWeek.setDate(startOfWeek.getDate() + 6);
  endOfWeek.setHours(23, 59, 59, 999);

  return {
    date_from: formatDate(startOfWeek),
    date_to: formatDate(endOfWeek),
  };
};

/**
 * Get date range for current month
 */
export const getThisMonthRange = () => {
  const today = new Date();

  // Start of month
  const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
  startOfMonth.setHours(0, 0, 0, 0);

  // End of month
  const endOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);
  endOfMonth.setHours(23, 59, 59, 999);

  return {
    date_from: formatDate(startOfMonth),
    date_to: formatDate(endOfMonth),
  };
};

/**
 * Get date range for current year
 */
export const getThisYearRange = () => {
  const today = new Date();

  // Start of year
  const startOfYear = new Date(today.getFullYear(), 0, 1);
  startOfYear.setHours(0, 0, 0, 0);

  // End of year
  const endOfYear = new Date(today.getFullYear(), 11, 31);
  endOfYear.setHours(23, 59, 59, 999);

  return {
    date_from: formatDate(startOfYear),
    date_to: formatDate(endOfYear),
  };
};

/**
 * Get date range for a specific timeframe
 */
export const getDateRange = (timeframe) => {
  switch (timeframe) {
    case 'today':
      return getTodayRange();
    case 'week':
      return getThisWeekRange();
    case 'month':
      return getThisMonthRange();
    case 'year':
      return getThisYearRange();
    default:
      return getThisMonthRange(); // Default to month
  }
};

/**
 * Format date to YYYY-MM-DD format for API
 */
export const formatDate = (date) => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');

  return `${year}-${month}-${day}`;
};

/**
 * Calculate percentage change between two values
 */
export const calculatePercentageChange = (oldValue, newValue) => {
  if (oldValue === 0) {
    return newValue > 0 ? 100 : 0;
  }

  return ((newValue - oldValue) / oldValue) * 100;
};

/**
 * Get display label for timeframe
 */
export const getTimeframeLabel = (timeframe) => {
  const labels = {
    today: 'היום',
    week: 'השבוע',
    month: 'החודש',
    year: 'השנה',
  };

  return labels[timeframe] || labels.month;
};

/**
 * Get custom date range
 */
export const getCustomDateRange = (startDate, endDate) => {
  const start = new Date(startDate);
  start.setHours(0, 0, 0, 0);

  const end = new Date(endDate);
  end.setHours(23, 59, 59, 999);

  return {
    date_from: formatDate(start),
    date_to: formatDate(end),
  };
};

/**
 * Get date range for last N days
 */
export const getLastNDaysRange = (days) => {
  const today = new Date();
  today.setHours(23, 59, 59, 999);

  const startDate = new Date(today);
  startDate.setDate(today.getDate() - days + 1);
  startDate.setHours(0, 0, 0, 0);

  return {
    date_from: formatDate(startDate),
    date_to: formatDate(today),
  };
};

/**
 * Parse date from YYYY-MM-DD format
 */
export const parseDate = (dateString) => {
  const [year, month, day] = dateString.split('-').map(Number);
  return new Date(year, month - 1, day);
};

/**
 * Check if a date is within a range
 */
export const isDateInRange = (date, dateFrom, dateTo) => {
  const checkDate = typeof date === 'string' ? parseDate(date) : date;
  const startDate = typeof dateFrom === 'string' ? parseDate(dateFrom) : dateFrom;
  const endDate = typeof dateTo === 'string' ? parseDate(dateTo) : dateTo;

  return checkDate >= startDate && checkDate <= endDate;
};

export default {
  getTodayRange,
  getThisWeekRange,
  getThisMonthRange,
  getThisYearRange,
  getDateRange,
  formatDate,
  calculatePercentageChange,
  getTimeframeLabel,
  getCustomDateRange,
  getLastNDaysRange,
  parseDate,
  isDateInRange,
};
