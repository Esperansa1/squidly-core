import React, { useState, useMemo } from 'react';
import theme from '../../config/theme';
import { t, getCurrentLanguage } from '../../i18n/translations';
import { MapPinIcon, PhoneIcon, CheckCircleIcon, XCircleIcon, MagnifyingGlassIcon } from '@heroicons/react/24/outline';

/**
 * BranchPickupList Component
 *
 * Search input for filtering
 * List of branch cards
 * Sort: open branches first
 * Disable selection of closed branches
 */
export default function BranchPickupList({
  branches = [],
  selectedBranchId,
  onSelect,
}) {
  const [searchQuery, setSearchQuery] = useState('');
  const direction = (getCurrentLanguage() === 'he' || getCurrentLanguage() === 'ar') ? 'rtl' : 'ltr';

  // Filter and sort branches
  const filteredBranches = useMemo(() => {
    let filtered = branches;

    // Filter by search query
    if (searchQuery.trim()) {
      const query = searchQuery.toLowerCase().trim();
      filtered = filtered.filter(branch =>
        branch.name?.toLowerCase().includes(query) ||
        branch.city?.toLowerCase().includes(query) ||
        branch.address?.toLowerCase().includes(query)
      );
    }

    // Sort: open branches first
    return filtered.sort((a, b) => {
      if (a.is_currently_open && !b.is_currently_open) return -1;
      if (!a.is_currently_open && b.is_currently_open) return 1;
      return (a.name || '').localeCompare(b.name || '');
    });
  }, [branches, searchQuery]);

  return (
    <div style={{ direction: direction }}>
      {/* CSS to override default focus styles */}
      <style>{`
        input:focus, input:focus-visible {
          outline: none !important;
        }
      `}</style>

      {/* Search Input */}
      <div
        style={{
          position: 'relative',
          marginBottom: theme.spacing.md,
        }}
      >
        <MagnifyingGlassIcon
          style={{
            position: 'absolute',
            top: '50%',
            transform: 'translateY(-50%)',
            [direction === 'rtl' ? 'right' : 'left']: theme.spacing.md,
            width: '20px',
            height: '20px',
            color: theme.colors.text.muted,
          }}
        />
        <input
          type="text"
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          placeholder={t('branchModalSearchBranches')}
          style={{
            width: '100%',
            padding: `${theme.spacing.sm} ${theme.spacing.md}`,
            [direction === 'rtl' ? 'paddingRight' : 'paddingLeft']: '48px',
            borderRadius: theme.borderRadius.lg,
            border: `2px solid ${theme.colors.border}`,
            fontSize: '1rem',
            outline: 'none',
            boxShadow: 'none',
            WebkitAppearance: 'none',
            MozAppearance: 'none',
            appearance: 'none',
            transition: 'border-color 0.2s ease, box-shadow 0.2s ease',
            direction: direction,
            textAlign: direction === 'rtl' ? 'right' : 'left',
            backgroundColor: theme.colors.cardBg,
          }}
          onFocus={(e) => {
            e.target.style.borderColor = theme.colors.primary;
            e.target.style.boxShadow = '0 0 0 3px rgba(220, 38, 38, 0.1)';
            e.target.style.outline = 'none';
          }}
          onBlur={(e) => {
            e.target.style.borderColor = theme.colors.border;
            e.target.style.boxShadow = 'none';
          }}
        />
      </div>

      {/* Branch List */}
      <div
        style={{
          display: 'flex',
          flexDirection: 'column',
          gap: theme.spacing.sm,
          maxHeight: '400px',
          overflowY: 'auto',
        }}
      >
        {filteredBranches.length === 0 ? (
          <div
            style={{
              textAlign: 'center',
              padding: theme.spacing.xl,
              color: theme.colors.text.muted,
            }}
          >
            {t('noBranchesAvailable')}
          </div>
        ) : (
          filteredBranches.map((branch) => (
            <BranchPickupCard
              key={branch.id}
              branch={branch}
              selected={selectedBranchId === branch.id}
              onSelect={onSelect}
              direction={direction}
            />
          ))
        )}
      </div>
    </div>
  );
}

/**
 * Individual Branch Card for Pickup Selection
 */
function BranchPickupCard({ branch, selected, onSelect, direction }) {
  const {
    id,
    name,
    address,
    city,
    phone,
    is_currently_open,
  } = branch;

  const isDisabled = !is_currently_open;

  return (
    <button
      onClick={() => !isDisabled && onSelect(id)}
      disabled={isDisabled}
      style={{
        width: '100%',
        padding: theme.spacing.md,
        backgroundColor: selected ? 'rgba(220, 38, 38, 0.05)' : theme.colors.cardBg,
        border: `2px solid ${selected ? theme.colors.primary : theme.colors.border}`,
        borderRadius: theme.borderRadius.lg,
        cursor: isDisabled ? 'not-allowed' : 'pointer',
        opacity: isDisabled ? 0.6 : 1,
        textAlign: direction === 'rtl' ? 'right' : 'left',
        transition: 'all 0.2s ease',
        direction: direction,
      }}
      onMouseEnter={(e) => {
        if (!isDisabled && !selected) {
          e.currentTarget.style.borderColor = theme.colors.primary;
          e.currentTarget.style.backgroundColor = 'rgba(220, 38, 38, 0.02)';
        }
      }}
      onMouseLeave={(e) => {
        if (!isDisabled && !selected) {
          e.currentTarget.style.borderColor = theme.colors.border;
          e.currentTarget.style.backgroundColor = theme.colors.cardBg;
        }
      }}
    >
      {/* Header with Name and Status */}
      <div
        style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'flex-start',
          marginBottom: theme.spacing.sm,
        }}
      >
        <div>
          <h4
            style={{
              fontSize: '1rem',
              fontWeight: '600',
              color: theme.colors.text.primary,
              margin: 0,
            }}
          >
            {name}
          </h4>
          <p
            style={{
              fontSize: '0.8125rem',
              color: theme.colors.text.secondary,
              margin: 0,
              marginTop: '2px',
            }}
          >
            {city}
          </p>
        </div>

        {/* Open/Closed Badge */}
        <div
          style={{
            display: 'flex',
            alignItems: 'center',
            gap: '4px',
            padding: `${theme.spacing.xs} ${theme.spacing.sm}`,
            borderRadius: theme.borderRadius.full,
            fontSize: '0.75rem',
            fontWeight: '500',
            backgroundColor: is_currently_open ? '#DEF7EC' : '#FDE8E8',
            color: is_currently_open ? '#03543F' : '#9B1C1C',
          }}
        >
          {is_currently_open ? (
            <>
              <CheckCircleIcon style={{ width: '14px', height: '14px' }} />
              <span>{t('openNow')}</span>
            </>
          ) : (
            <>
              <XCircleIcon style={{ width: '14px', height: '14px' }} />
              <span>{t('closed')}</span>
            </>
          )}
        </div>
      </div>

      {/* Address */}
      <div
        style={{
          display: 'flex',
          alignItems: 'center',
          gap: theme.spacing.xs,
          fontSize: '0.8125rem',
          color: theme.colors.text.secondary,
          marginBottom: theme.spacing.xs,
        }}
      >
        <MapPinIcon style={{ width: '16px', height: '16px', flexShrink: 0 }} />
        <span>{address}</span>
      </div>

      {/* Phone */}
      {phone && (
        <div
          style={{
            display: 'flex',
            alignItems: 'center',
            gap: theme.spacing.xs,
            fontSize: '0.8125rem',
            color: theme.colors.text.secondary,
          }}
        >
          <PhoneIcon style={{ width: '16px', height: '16px', flexShrink: 0 }} />
          <span>{phone}</span>
        </div>
      )}

      {/* Selected indicator */}
      {selected && (
        <div
          style={{
            marginTop: theme.spacing.sm,
            paddingTop: theme.spacing.sm,
            borderTop: `1px solid ${theme.colors.border}`,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            gap: theme.spacing.xs,
            color: theme.colors.primary,
            fontWeight: '600',
            fontSize: '0.875rem',
          }}
        >
          <CheckCircleIcon style={{ width: '18px', height: '18px' }} />
          {t('selectedBranch')}
        </div>
      )}
    </button>
  );
}
