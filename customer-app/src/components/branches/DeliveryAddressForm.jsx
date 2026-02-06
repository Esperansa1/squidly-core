import React, { useState, useRef, useEffect, useCallback } from 'react';
import theme from '../../config/theme';
import { t, getCurrentLanguage } from '../../i18n/translations';
import { MapPinIcon } from '@heroicons/react/24/outline';

/**
 * DeliveryAddressForm Component
 *
 * Step 1: Street + city autocomplete via Photon API
 * Step 2: House number input (separate, since OSM often lacks house numbers)
 * Coordinates from street-level geocoding for delivery radius calculation
 */
export default function DeliveryAddressForm({
  address,
  onChange,
  errors = {},
  loading = false,
  onSubmit,
  branches = [],
}) {
  const direction = (getCurrentLanguage() === 'he' || getCurrentLanguage() === 'ar') ? 'rtl' : 'ltr';
  const isRtl = direction === 'rtl';

  const [query, setQuery] = useState('');
  const [suggestions, setSuggestions] = useState([]);
  const [showDropdown, setShowDropdown] = useState(false);
  const [isSearching, setIsSearching] = useState(false);
  const [selectedStreet, setSelectedStreet] = useState(null);
  const [houseNumber, setHouseNumber] = useState('');
  const [noDeliveryError, setNoDeliveryError] = useState('');

  const inputRef = useRef(null);
  const houseInputRef = useRef(null);
  const dropdownRef = useRef(null);
  const debounceRef = useRef(null);

  // Close dropdown on outside click
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (
        dropdownRef.current &&
        !dropdownRef.current.contains(event.target) &&
        inputRef.current &&
        !inputRef.current.contains(event.target)
      ) {
        setShowDropdown(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  // Search using Photon API
  const searchAddresses = useCallback(async (searchQuery) => {
    if (!searchQuery || searchQuery.length < 2) {
      setSuggestions([]);
      return;
    }

    setIsSearching(true);
    try {
      const response = await fetch(
        `https://photon.komoot.io/api/?q=${encodeURIComponent(searchQuery)}&limit=6&lang=default&lat=31.8&lon=34.8`
      );

      if (!response.ok) throw new Error('Search failed');

      const data = await response.json();

      // Filter to Israel, deduplicate by street+city
      const seen = new Set();
      const results = data.features
        .filter(f => {
          const c = f.properties.country;
          return c === 'ישראל' || c === 'Israel';
        })
        .map(f => {
          const p = f.properties;
          const street = p.street || p.name || '';
          const city = p.city || p.town || p.village || '';
          return {
            id: p.osm_id,
            street,
            city,
            latitude: f.geometry.coordinates[1],
            longitude: f.geometry.coordinates[0],
            displayName: city ? `${street}, ${city}` : street,
          };
        })
        .filter(r => {
          if (!r.street) return false;
          const key = `${r.street}|${r.city}`;
          if (seen.has(key)) return false;
          seen.add(key);
          return true;
        });

      setSuggestions(results);
      setShowDropdown(results.length > 0);
    } catch (error) {
      console.error('Address search error:', error);
      setSuggestions([]);
    } finally {
      setIsSearching(false);
    }
  }, []);

  // Debounced search
  const handleQueryChange = (e) => {
    const value = e.target.value;
    setQuery(value);
    setSelectedStreet(null);
    setNoDeliveryError('');

    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => searchAddresses(value), 300);
  };

  // Handle street selection from dropdown
  const handleSelectStreet = (suggestion) => {
    setQuery(suggestion.displayName);
    setSelectedStreet(suggestion);
    setShowDropdown(false);
    setNoDeliveryError('');

    onChange({
      fullAddress: suggestion.displayName,
      street: suggestion.street,
      houseNumber: houseNumber,
      city: suggestion.city,
      latitude: suggestion.latitude,
      longitude: suggestion.longitude,
    });

    // Focus house number input
    setTimeout(() => houseInputRef.current?.focus(), 100);
  };

  // Handle house number change
  const handleHouseNumberChange = (e) => {
    const value = e.target.value;
    setHouseNumber(value);
    setNoDeliveryError('');

    if (selectedStreet) {
      const fullAddr = value
        ? `${selectedStreet.street} ${value}, ${selectedStreet.city}`
        : selectedStreet.displayName;

      onChange({
        fullAddress: fullAddr,
        street: selectedStreet.street,
        houseNumber: value,
        city: selectedStreet.city,
        latitude: selectedStreet.latitude,
        longitude: selectedStreet.longitude,
      });
    }
  };

  // Clear street selection
  const handleClearStreet = () => {
    setQuery('');
    setSelectedStreet(null);
    setHouseNumber('');
    setNoDeliveryError('');
    setSuggestions([]);
    inputRef.current?.focus();
  };

  // Haversine distance (km)
  const calculateDistance = (lat1, lon1, lat2, lon2) => {
    const R = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a =
      Math.sin(dLat / 2) * Math.sin(dLat / 2) +
      Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
      Math.sin(dLon / 2) * Math.sin(dLon / 2);
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  };

  // Find nearest delivering branch
  const findDeliveryBranch = (lat, lon) => {
    let best = null;
    let bestDist = Infinity;

    for (const branch of branches) {
      if (!branch.latitude || !branch.longitude || !branch.delivery_enabled) continue;

      const dist = calculateDistance(lat, lon, branch.latitude, branch.longitude);
      if (dist <= branch.delivery_max_distance && dist < bestDist) {
        best = branch;
        bestDist = dist;
      }
    }

    return best ? { branch: best, distance: bestDist } : null;
  };

  // Submit
  const handleSubmit = (e) => {
    e.preventDefault();
    if (!selectedStreet || !houseNumber.trim()) return;

    const result = findDeliveryBranch(selectedStreet.latitude, selectedStreet.longitude);

    if (result) {
      onSubmit?.(result.branch, {
        fullAddress: `${selectedStreet.street} ${houseNumber}, ${selectedStreet.city}`,
        street: selectedStreet.street,
        houseNumber,
        city: selectedStreet.city,
        latitude: selectedStreet.latitude,
        longitude: selectedStreet.longitude,
      }, result.distance);
    } else {
      setNoDeliveryError(isRtl
        ? 'מצטערים, אין משלוחים לכתובת זו. נסה כתובת אחרת או בחר איסוף עצמי.'
        : 'Sorry, we don\'t deliver to this address. Try another address or choose pickup.'
      );
    }
  };

  const canSubmit = selectedStreet && houseNumber.trim() && !loading;

  const baseInputStyle = (hasError) => ({
    width: '100%',
    padding: `${theme.spacing.sm} ${theme.spacing.md}`,
    borderRadius: theme.borderRadius.lg,
    border: `2px solid ${hasError ? theme.colors.error : theme.colors.border}`,
    fontSize: '1rem',
    outline: 'none',
    boxShadow: 'none',
    WebkitAppearance: 'none',
    MozAppearance: 'none',
    appearance: 'none',
    transition: 'border-color 0.2s ease, box-shadow 0.2s ease',
    direction: direction,
    textAlign: isRtl ? 'right' : 'left',
    backgroundColor: theme.colors.cardBg,
  });

  const labelStyle = {
    display: 'block',
    fontSize: '0.875rem',
    fontWeight: '600',
    color: theme.colors.text.primary,
    marginBottom: theme.spacing.xs,
  };

  return (
    <form onSubmit={handleSubmit} style={{ direction }}>
      {/* Street + City Autocomplete */}
      <div style={{ marginBottom: theme.spacing.md, position: 'relative' }}>
        <label style={labelStyle}>
          {isRtl ? 'רחוב ועיר' : 'Street and city'} *
        </label>

        {selectedStreet ? (
          /* Selected street chip */
          <div
            style={{
              display: 'flex',
              alignItems: 'center',
              gap: theme.spacing.sm,
              padding: `${theme.spacing.sm} ${theme.spacing.md}`,
              backgroundColor: 'rgba(34, 197, 94, 0.1)',
              border: '2px solid rgba(34, 197, 94, 0.3)',
              borderRadius: theme.borderRadius.lg,
            }}
          >
            <MapPinIcon style={{ width: '18px', height: '18px', color: '#22c55e', flexShrink: 0 }} />
            <span style={{ flex: 1, fontSize: '0.9375rem', fontWeight: '500', color: theme.colors.text.primary }}>
              {selectedStreet.displayName}
            </span>
            <button
              type="button"
              onClick={handleClearStreet}
              style={{
                background: 'none',
                border: 'none',
                cursor: 'pointer',
                fontSize: '1.125rem',
                color: theme.colors.text.muted,
                padding: '2px 6px',
                borderRadius: theme.borderRadius.sm,
                lineHeight: 1,
              }}
              onMouseEnter={(e) => { e.currentTarget.style.color = theme.colors.error; }}
              onMouseLeave={(e) => { e.currentTarget.style.color = theme.colors.text.muted; }}
            >
              ×
            </button>
          </div>
        ) : (
          /* Search input */
          <div style={{ position: 'relative' }}>
            <MapPinIcon
              style={{
                position: 'absolute',
                top: '50%',
                transform: 'translateY(-50%)',
                [isRtl ? 'left' : 'right']: '12px',
                width: '20px',
                height: '20px',
                color: isSearching ? theme.colors.primary : theme.colors.text.muted,
                ...(isSearching && { animation: 'pulse 1s ease-in-out infinite' }),
              }}
            />
            <input
              ref={inputRef}
              type="text"
              value={query}
              onChange={handleQueryChange}
              placeholder={isRtl ? 'חפש רחוב ועיר...' : 'Search street and city...'}
              style={{
                ...baseInputStyle(false),
                paddingRight: isRtl ? theme.spacing.md : '44px',
                paddingLeft: isRtl ? '44px' : theme.spacing.md,
              }}
              disabled={loading}
              autoComplete="off"
              onFocus={() => {
                if (suggestions.length > 0) setShowDropdown(true);
              }}
            />
          </div>
        )}

        {/* Dropdown */}
        {showDropdown && suggestions.length > 0 && (
          <div
            ref={dropdownRef}
            style={{
              position: 'absolute',
              top: '100%',
              left: 0,
              right: 0,
              backgroundColor: theme.colors.cardBg,
              border: `2px solid ${theme.colors.primary}`,
              borderTop: 'none',
              borderRadius: `0 0 ${theme.borderRadius.lg} ${theme.borderRadius.lg}`,
              maxHeight: '220px',
              overflowY: 'auto',
              zIndex: 100,
              boxShadow: theme.shadows.lg,
            }}
          >
            {suggestions.map((s, i) => (
              <div
                key={s.id || i}
                onClick={() => handleSelectStreet(s)}
                style={{
                  padding: `${theme.spacing.sm} ${theme.spacing.md}`,
                  cursor: 'pointer',
                  fontSize: '0.9375rem',
                  color: theme.colors.text.primary,
                  borderBottom: i < suggestions.length - 1 ? `1px solid ${theme.colors.border}` : 'none',
                  transition: 'background-color 0.15s ease',
                  display: 'flex',
                  alignItems: 'center',
                  gap: theme.spacing.sm,
                }}
                onMouseEnter={(e) => { e.currentTarget.style.backgroundColor = 'rgba(220, 38, 38, 0.05)'; }}
                onMouseLeave={(e) => { e.currentTarget.style.backgroundColor = 'transparent'; }}
              >
                <MapPinIcon style={{ width: '16px', height: '16px', color: theme.colors.text.muted, flexShrink: 0 }} />
                <span>{s.displayName}</span>
              </div>
            ))}
          </div>
        )}

        {/* No results */}
        {showDropdown && query.length >= 2 && !isSearching && suggestions.length === 0 && (
          <div
            style={{
              position: 'absolute',
              top: '100%',
              left: 0,
              right: 0,
              backgroundColor: theme.colors.cardBg,
              border: `2px solid ${theme.colors.border}`,
              borderTop: 'none',
              borderRadius: `0 0 ${theme.borderRadius.lg} ${theme.borderRadius.lg}`,
              padding: theme.spacing.md,
              textAlign: 'center',
              color: theme.colors.text.muted,
              fontSize: '0.875rem',
              zIndex: 100,
            }}
          >
            {isRtl ? 'לא נמצאו תוצאות' : 'No results found'}
          </div>
        )}
      </div>

      {/* House Number - appears after street selection */}
      {selectedStreet && (
        <div style={{ marginBottom: theme.spacing.md }}>
          <label style={labelStyle}>
            {isRtl ? 'מספר בית' : 'House number'} *
          </label>
          <input
            ref={houseInputRef}
            type="text"
            value={houseNumber}
            onChange={handleHouseNumberChange}
            placeholder={isRtl ? 'מספר בית' : 'House number'}
            style={baseInputStyle(!houseNumber.trim() && noDeliveryError)}
            disabled={loading}
          />
        </div>
      )}

      {/* No Delivery Error */}
      {noDeliveryError && (
        <div
          style={{
            padding: theme.spacing.sm,
            backgroundColor: 'rgba(220, 38, 38, 0.1)',
            border: '1px solid rgba(220, 38, 38, 0.3)',
            borderRadius: theme.borderRadius.md,
            marginBottom: theme.spacing.md,
            color: theme.colors.error,
            fontSize: '0.875rem',
            textAlign: 'center',
          }}
        >
          {noDeliveryError}
        </div>
      )}

      {/* Submit */}
      <button
        type="submit"
        disabled={!canSubmit}
        style={{
          width: '100%',
          padding: theme.spacing.md,
          backgroundColor: canSubmit ? theme.colors.primary : theme.colors.text.muted,
          color: 'white',
          border: 'none',
          borderRadius: theme.borderRadius.lg,
          fontSize: '1rem',
          fontWeight: '600',
          cursor: canSubmit ? 'pointer' : 'not-allowed',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          gap: theme.spacing.sm,
          transition: 'background-color 0.2s ease',
          opacity: canSubmit ? 1 : 0.6,
        }}
        onMouseEnter={(e) => {
          if (canSubmit) e.currentTarget.style.backgroundColor = theme.colors.primaryHover;
        }}
        onMouseLeave={(e) => {
          if (canSubmit) e.currentTarget.style.backgroundColor = theme.colors.primary;
        }}
      >
        {loading ? (
          <>
            <span style={{
              width: '18px', height: '18px',
              border: '2px solid rgba(255,255,255,0.3)',
              borderTopColor: '#fff', borderRadius: '50%',
              animation: 'spin 0.8s linear infinite',
            }} />
            {t('branchModalFindingBranch')}
          </>
        ) : (
          isRtl ? 'בדוק זמינות משלוח' : 'Check delivery availability'
        )}
      </button>

      <style>{`
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.4; } }
        input:focus {
          outline: none !important;
          border-color: ${theme.colors.primary} !important;
          box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1) !important;
        }
        input:focus-visible { outline: none !important; }
      `}</style>
    </form>
  );
}
