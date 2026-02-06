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

export default {
  getTodayRange,
  getThisWeekRange,
  getThisMonthRange,
  getThisYearRange,
  getDateRange,
  formatDate,
  getTimeframeLabel,
};
