import React, { useState, useMemo, useEffect } from 'react';
import theme from '../../config/theme';
import { t, getCurrentLanguage } from '../../i18n/translations';
import { ClockIcon, PlusIcon, MinusIcon } from '@heroicons/react/24/outline';

/**
 * PickupTimeSelector Component
 *
 * Simple date/time picker with:
 * - Today/Tomorrow buttons
 * - Time input with +/- buttons (15 min increments)
 */
export default function PickupTimeSelector({
  selectedTime,
  onChange,
  onContinue,
  branchName,
}) {
  const direction = (getCurrentLanguage() === 'he' || getCurrentLanguage() === 'ar') ? 'rtl' : 'ltr';
  const isRtl = direction === 'rtl';

  // State for ASAP vs specific time (default to ASAP)
  const [isAsap, setIsAsap] = useState(() => {
    if (!selectedTime) return true; // Default to ASAP
    return selectedTime === 'asap';
  });

  // Set default to ASAP on mount if no selection
  useEffect(() => {
    if (!selectedTime) {
      onChange('asap');
    }
  }, []); // Run once on mount

  // State for date and time selection
  const [selectedDate, setSelectedDate] = useState(() => {
    if (selectedTime && selectedTime !== 'asap') {
      return selectedTime.split('T')[0];
    }
    return null;
  });

  const [hours, setHours] = useState(() => {
    if (selectedTime && selectedTime !== 'asap') {
      const timePart = selectedTime.split('T')[1];
      if (timePart) {
        return parseInt(timePart.split(':')[0], 10);
      }
    }
    // Default to next rounded 15-min slot
    const now = new Date();
    let h = now.getHours();
    let m = now.getMinutes();
    m = Math.ceil(m / 15) * 15;
    if (m >= 60) {
      m = 0;
      h++;
    }
    if (h < 9) h = 9;
    if (h > 23) h = 9;
    return h;
  });

  const [minutes, setMinutes] = useState(() => {
    if (selectedTime && selectedTime !== 'asap') {
      const timePart = selectedTime.split('T')[1];
      if (timePart) {
        return parseInt(timePart.split(':')[1], 10);
      }
    }
    const now = new Date();
    let m = Math.ceil(now.getMinutes() / 15) * 15;
    if (m >= 60) m = 0;
    return m;
  });

  // Get today and tomorrow dates
  const today = useMemo(() => {
    const d = new Date();
    return formatDateValue(d);
  }, []);

  const tomorrow = useMemo(() => {
    const d = new Date();
    d.setDate(d.getDate() + 1);
    return formatDateValue(d);
  }, []);

  // Format date for value (YYYY-MM-DD)
  function formatDateValue(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  // Format date for display
  function formatDateDisplay(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    const dayNames = isRtl
      ? ['ראשון', 'שני', 'שלישי', 'רביעי', 'חמישי', 'שישי', 'שבת']
      : ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    const monthNames = isRtl
      ? ['ינואר', 'פברואר', 'מרץ', 'אפריל', 'מאי', 'יוני', 'יולי', 'אוגוסט', 'ספטמבר', 'אוקטובר', 'נובמבר', 'דצמבר']
      : ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    return `${dayNames[date.getDay()]}, ${date.getDate()} ${monthNames[date.getMonth()]}`;
  }

  // Format time string
  const formatTime = (h, m) => {
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
  };

  // Check if time is valid (not in the past for today)
  const isTimeValid = (h, m, date) => {
    if (date !== today) return true;
    const now = new Date();
    const currentMinutes = now.getHours() * 60 + now.getMinutes();
    const selectedMinutes = h * 60 + m;
    return selectedMinutes > currentMinutes + 15; // At least 15 min from now
  };

  // Update parent when date or time changes
  const updateSelection = (date, h, m) => {
    if (date && isTimeValid(h, m, date)) {
      onChange(`${date}T${formatTime(h, m)}`);
    } else {
      onChange('');
    }
  };

  // Handle date selection
  const handleDateSelect = (date) => {
    setSelectedDate(date);
    setIsAsap(false); // Clear ASAP when selecting specific date

    // If today, ensure time is not in past
    if (date === today) {
      const now = new Date();
      let h = hours;
      let m = minutes;
      const currentMinutes = now.getHours() * 60 + now.getMinutes();
      const selectedMinutes = h * 60 + m;

      if (selectedMinutes <= currentMinutes + 15) {
        // Bump to next valid 15-min slot
        const nextSlot = Math.ceil((currentMinutes + 15) / 15) * 15;
        h = Math.floor(nextSlot / 60);
        m = nextSlot % 60;
        if (h > 23) {
          h = 23;
          m = 45;
        }
        setHours(h);
        setMinutes(m);
      }
      updateSelection(date, h, m);
    } else {
      updateSelection(date, hours, minutes);
    }
  };

  // Increment time by 15 minutes
  const incrementTime = () => {
    let h = hours;
    let m = minutes + 15;
    if (m >= 60) {
      m = 0;
      h++;
    }
    if (h > 23) {
      h = 23;
      m = 45;
    }

    // Check if valid for today
    if (selectedDate === today && !isTimeValid(h, m, today)) {
      return; // Don't allow going to invalid time
    }

    setHours(h);
    setMinutes(m);
    updateSelection(selectedDate, h, m);
  };

  // Decrement time by 15 minutes
  const decrementTime = () => {
    let h = hours;
    let m = minutes - 15;
    if (m < 0) {
      m = 45;
      h--;
    }
    if (h < 9) {
      h = 9;
      m = 0;
    }

    // Check if valid for today
    if (selectedDate === today && !isTimeValid(h, m, today)) {
      return; // Don't allow going to invalid time
    }

    setHours(h);
    setMinutes(m);
    updateSelection(selectedDate, h, m);
  };

  // Handle ASAP selection
  const handleAsapSelect = () => {
    setIsAsap(true);
    setSelectedDate(null);
    onChange('asap');
  };

  // Handle specific time selection (deselect ASAP)
  const handleSpecificTimeSelect = () => {
    setIsAsap(false);
    onChange('');
  };

  const handleContinue = () => {
    if (selectedTime) {
      onContinue?.();
    }
  };

  // Check if decrement would be valid
  const canDecrement = () => {
    let h = hours;
    let m = minutes - 15;
    if (m < 0) {
      m = 45;
      h--;
    }
    if (h < 9) return false;
    if (selectedDate === today && !isTimeValid(h, m, today)) return false;
    return true;
  };

  // Check if increment would be valid
  const canIncrement = () => {
    let h = hours;
    let m = minutes + 15;
    if (m >= 60) {
      m = 0;
      h++;
    }
    if (h > 23) return false;
    return true;
  };

  return (
    <div style={{ direction: direction }}>
      {/* Branch Name Confirmation */}
      {branchName && (
        <div
          style={{
            display: 'flex',
            alignItems: 'center',
            gap: theme.spacing.sm,
            padding: theme.spacing.sm,
            backgroundColor: 'rgba(34, 197, 94, 0.1)',
            borderRadius: theme.borderRadius.md,
            marginBottom: theme.spacing.md,
            border: '1px solid rgba(34, 197, 94, 0.3)',
          }}
        >
          <div
            style={{
              width: '24px',
              height: '24px',
              borderRadius: theme.borderRadius.full,
              backgroundColor: '#22c55e',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              color: 'white',
              fontSize: '0.75rem',
              flexShrink: 0,
            }}
          >
            ✓
          </div>
          <span style={{ fontSize: '0.875rem', fontWeight: '500', color: theme.colors.text.primary }}>
            {branchName}
          </span>
        </div>
      )}

      {/* ASAP vs Specific Time Selection */}
      <div style={{ marginBottom: theme.spacing.md }}>
        <p style={{
          fontSize: '0.8125rem',
          color: theme.colors.text.secondary,
          marginBottom: theme.spacing.xs,
          margin: `0 0 ${theme.spacing.xs} 0`,
        }}>
          {isRtl ? 'מתי?' : 'When?'}
        </p>

        <div style={{ display: 'flex', gap: theme.spacing.sm }}>
          {/* ASAP Button */}
          <button
            onClick={handleAsapSelect}
            style={{
              flex: 1,
              padding: `${theme.spacing.sm} ${theme.spacing.md}`,
              backgroundColor: isAsap ? theme.colors.primary : theme.colors.cardBg,
              color: isAsap ? 'white' : theme.colors.text.primary,
              border: `2px solid ${isAsap ? theme.colors.primary : theme.colors.border}`,
              borderRadius: theme.borderRadius.md,
              cursor: 'pointer',
              fontSize: '0.875rem',
              fontWeight: '600',
              transition: 'all 0.2s ease',
            }}
            onMouseEnter={(e) => {
              if (!isAsap) {
                e.currentTarget.style.borderColor = theme.colors.primary;
              }
            }}
            onMouseLeave={(e) => {
              if (!isAsap) {
                e.currentTarget.style.borderColor = theme.colors.border;
              }
            }}
          >
            {isRtl ? 'הכי מהר שאפשר' : 'ASAP'}
          </button>

          {/* Specific Time Button */}
          <button
            onClick={handleSpecificTimeSelect}
            style={{
              flex: 1,
              padding: `${theme.spacing.sm} ${theme.spacing.md}`,
              backgroundColor: !isAsap && selectedDate ? theme.colors.primary : (!isAsap ? 'rgba(220, 38, 38, 0.1)' : theme.colors.cardBg),
              color: !isAsap ? theme.colors.primary : theme.colors.text.primary,
              border: `2px solid ${!isAsap ? theme.colors.primary : theme.colors.border}`,
              borderRadius: theme.borderRadius.md,
              cursor: 'pointer',
              fontSize: '0.875rem',
              fontWeight: '600',
              transition: 'all 0.2s ease',
            }}
            onMouseEnter={(e) => {
              if (isAsap) {
                e.currentTarget.style.borderColor = theme.colors.primary;
              }
            }}
            onMouseLeave={(e) => {
              if (isAsap) {
                e.currentTarget.style.borderColor = theme.colors.border;
              }
            }}
          >
            {isRtl ? 'לשעה מסוימת' : 'Specific time'}
          </button>
        </div>
      </div>

      {/* Date Selection - Only show when not ASAP */}
      {!isAsap && (
        <div style={{ marginBottom: theme.spacing.md }}>
          <p style={{
            fontSize: '0.8125rem',
            color: theme.colors.text.secondary,
            marginBottom: theme.spacing.xs,
            margin: `0 0 ${theme.spacing.xs} 0`,
          }}>
            {isRtl ? 'באיזה יום?' : 'Which day?'}
          </p>

          <div style={{ display: 'flex', gap: theme.spacing.sm }}>
            {/* Today Button */}
            <button
              onClick={() => handleDateSelect(today)}
              style={{
                flex: 1,
                padding: `${theme.spacing.sm} ${theme.spacing.md}`,
                backgroundColor: selectedDate === today ? theme.colors.primary : theme.colors.cardBg,
                color: selectedDate === today ? 'white' : theme.colors.text.primary,
                border: `2px solid ${selectedDate === today ? theme.colors.primary : theme.colors.border}`,
                borderRadius: theme.borderRadius.md,
                cursor: 'pointer',
                fontSize: '0.875rem',
                fontWeight: '600',
                transition: 'all 0.2s ease',
              }}
              onMouseEnter={(e) => {
                if (selectedDate !== today) {
                  e.currentTarget.style.borderColor = theme.colors.primary;
                }
              }}
              onMouseLeave={(e) => {
                if (selectedDate !== today) {
                  e.currentTarget.style.borderColor = theme.colors.border;
                }
              }}
            >
              {isRtl ? 'היום' : 'Today'}
            </button>

            {/* Tomorrow Button */}
            <button
              onClick={() => handleDateSelect(tomorrow)}
              style={{
                flex: 1,
                padding: `${theme.spacing.sm} ${theme.spacing.md}`,
                backgroundColor: selectedDate === tomorrow ? theme.colors.primary : theme.colors.cardBg,
                color: selectedDate === tomorrow ? 'white' : theme.colors.text.primary,
                border: `2px solid ${selectedDate === tomorrow ? theme.colors.primary : theme.colors.border}`,
                borderRadius: theme.borderRadius.md,
                cursor: 'pointer',
                fontSize: '0.875rem',
                fontWeight: '600',
                transition: 'all 0.2s ease',
              }}
              onMouseEnter={(e) => {
                if (selectedDate !== tomorrow) {
                  e.currentTarget.style.borderColor = theme.colors.primary;
                }
              }}
              onMouseLeave={(e) => {
                if (selectedDate !== tomorrow) {
                  e.currentTarget.style.borderColor = theme.colors.border;
                }
              }}
            >
              {isRtl ? 'מחר' : 'Tomorrow'}
            </button>
          </div>
        </div>
      )}

      {/* Time Selection - Only show when not ASAP and date is selected */}
      {!isAsap && selectedDate && (
        <div style={{ marginBottom: theme.spacing.md }}>
          <p style={{
            fontSize: '0.8125rem',
            color: theme.colors.text.secondary,
            marginBottom: theme.spacing.xs,
            margin: `0 0 ${theme.spacing.xs} 0`,
          }}>
            {isRtl ? 'באיזו שעה?' : 'What time?'}
          </p>

          {/* Time Picker */}
          <div
            style={{
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              gap: theme.spacing.md,
              padding: theme.spacing.md,
              backgroundColor: theme.colors.background,
              borderRadius: theme.borderRadius.lg,
              border: `1px solid ${theme.colors.border}`,
            }}
          >
            {/* Decrement Button */}
            <button
              onClick={decrementTime}
              disabled={!canDecrement()}
              style={{
                width: '40px',
                height: '40px',
                borderRadius: theme.borderRadius.full,
                border: `2px solid ${canDecrement() ? theme.colors.primary : theme.colors.border}`,
                backgroundColor: theme.colors.cardBg,
                color: canDecrement() ? theme.colors.primary : theme.colors.text.muted,
                cursor: canDecrement() ? 'pointer' : 'not-allowed',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                transition: 'all 0.2s ease',
                opacity: canDecrement() ? 1 : 0.5,
              }}
              onMouseEnter={(e) => {
                if (canDecrement()) {
                  e.currentTarget.style.backgroundColor = theme.colors.primary;
                  e.currentTarget.style.color = 'white';
                }
              }}
              onMouseLeave={(e) => {
                if (canDecrement()) {
                  e.currentTarget.style.backgroundColor = theme.colors.cardBg;
                  e.currentTarget.style.color = theme.colors.primary;
                }
              }}
            >
              <MinusIcon style={{ width: '20px', height: '20px' }} />
            </button>

            {/* Time Display */}
            <div
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: theme.spacing.xs,
                padding: `${theme.spacing.sm} ${theme.spacing.lg}`,
                backgroundColor: theme.colors.cardBg,
                borderRadius: theme.borderRadius.md,
                border: `2px solid ${theme.colors.primary}`,
                minWidth: '100px',
                justifyContent: 'center',
              }}
            >
              <ClockIcon style={{ width: '18px', height: '18px', color: theme.colors.primary }} />
              <span
                style={{
                  fontSize: '1.25rem',
                  fontWeight: '700',
                  color: theme.colors.text.primary,
                  fontFamily: 'monospace',
                }}
              >
                {formatTime(hours, minutes)}
              </span>
            </div>

            {/* Increment Button */}
            <button
              onClick={incrementTime}
              disabled={!canIncrement()}
              style={{
                width: '40px',
                height: '40px',
                borderRadius: theme.borderRadius.full,
                border: `2px solid ${canIncrement() ? theme.colors.primary : theme.colors.border}`,
                backgroundColor: theme.colors.cardBg,
                color: canIncrement() ? theme.colors.primary : theme.colors.text.muted,
                cursor: canIncrement() ? 'pointer' : 'not-allowed',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                transition: 'all 0.2s ease',
                opacity: canIncrement() ? 1 : 0.5,
              }}
              onMouseEnter={(e) => {
                if (canIncrement()) {
                  e.currentTarget.style.backgroundColor = theme.colors.primary;
                  e.currentTarget.style.color = 'white';
                }
              }}
              onMouseLeave={(e) => {
                if (canIncrement()) {
                  e.currentTarget.style.backgroundColor = theme.colors.cardBg;
                  e.currentTarget.style.color = theme.colors.primary;
                }
              }}
            >
              <PlusIcon style={{ width: '20px', height: '20px' }} />
            </button>
          </div>

          <p style={{
            fontSize: '0.6875rem',
            color: theme.colors.text.muted,
            textAlign: 'center',
            marginTop: theme.spacing.xs,
            margin: `${theme.spacing.xs} 0 0 0`,
          }}>
            {isRtl ? '±15 דקות' : '±15 minutes'}
          </p>
        </div>
      )}

      {/* Continue Button */}
      <button
        onClick={handleContinue}
        disabled={!selectedTime}
        style={{
          width: '100%',
          padding: theme.spacing.md,
          backgroundColor: selectedTime ? theme.colors.primary : theme.colors.text.muted,
          color: 'white',
          border: 'none',
          borderRadius: theme.borderRadius.md,
          fontSize: '1rem',
          fontWeight: '600',
          cursor: selectedTime ? 'pointer' : 'not-allowed',
          transition: 'all 0.2s ease',
          opacity: selectedTime ? 1 : 0.6,
        }}
        onMouseEnter={(e) => {
          if (selectedTime) {
            e.currentTarget.style.backgroundColor = theme.colors.primaryHover;
          }
        }}
        onMouseLeave={(e) => {
          if (selectedTime) {
            e.currentTarget.style.backgroundColor = theme.colors.primary;
          }
        }}
      >
        {isAsap
          ? `${t('branchModalContinue')} - ${isRtl ? 'הכי מהר שאפשר' : 'ASAP'}`
          : selectedTime
            ? `${t('branchModalContinue')} - ${formatDateDisplay(selectedDate)} ${isRtl ? 'ב' : 'at'} ${formatTime(hours, minutes)}`
            : t('branchModalContinue')
        }
      </button>
    </div>
  );
}
