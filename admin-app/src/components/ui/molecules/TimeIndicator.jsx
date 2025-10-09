import React, { useState, useEffect } from 'react';

/**
 * TimeIndicator component with color-coded urgency
 * Green: < 15 minutes
 * Yellow: 15-30 minutes
 * Red: > 30 minutes
 */
const TimeIndicator = ({ orderDate, className = '' }) => {
  const [elapsedMinutes, setElapsedMinutes] = useState(0);

  useEffect(() => {
    const calculateElapsed = () => {
      const orderTime = new Date(orderDate);
      const now = new Date();
      const diffMs = now - orderTime;
      const diffMins = Math.floor(diffMs / 60000);
      setElapsedMinutes(diffMins);
    };

    // Calculate immediately
    calculateElapsed();

    // Update every minute
    const interval = setInterval(calculateElapsed, 60000);

    return () => clearInterval(interval);
  }, [orderDate]);

  const getColorClass = () => {
    if (elapsedMinutes < 15) {
      return 'bg-green-100 text-green-800';
    } else if (elapsedMinutes < 30) {
      return 'bg-yellow-100 text-yellow-800';
    } else {
      return 'bg-red-100 text-red-800';
    }
  };

  const formatTime = () => {
    const date = new Date(orderDate);
    const hours = date.getHours().toString().padStart(2, '0');
    const minutes = date.getMinutes().toString().padStart(2, '0');
    return `${hours}:${minutes}`;
  };

  return (
    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getColorClass()} ${className}`}>
      {formatTime()}
    </span>
  );
};

export default TimeIndicator;
