import React from 'react';
import { MapPinIcon, PhoneIcon, ClockIcon, CheckCircleIcon, XCircleIcon } from '@heroicons/react/24/outline';
import { t } from '../../i18n/translations';

/**
 * Branch Card Component
 *
 * Displays a single branch with all relevant information
 * Shows open/closed status, contact info, and allows selection
 */
export default function BranchCard({ branch, onSelect, selected = false }) {
  const {
    id,
    name,
    address,
    city,
    phone,
    is_currently_open,
    next_opening_time,
    kosher_type,
    accessibility_list = []
  } = branch;

  return (
    <div
      className={`
        bg-white rounded-lg shadow-sm border-2 transition-all duration-200
        ${selected
          ? 'border-primary shadow-md'
          : 'border-gray-200 hover:border-primary/30 hover:shadow-md'
        }
      `}
    >
      <div className="p-6">
        {/* Header with Name and Status */}
        <div className="flex items-start justify-between mb-4">
          <div>
            <h3 className="text-lg font-bold text-gray-900">{name}</h3>
            <p className="text-sm text-gray-500">{city}</p>
          </div>

          {/* Open/Closed Badge */}
          <div className={`
            flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium
            ${is_currently_open
              ? 'bg-green-100 text-green-800'
              : 'bg-red-100 text-red-800'
            }
          `}>
            {is_currently_open ? (
              <>
                <CheckCircleIcon className="w-4 h-4" />
                <span>{t('openNow')}</span>
              </>
            ) : (
              <>
                <XCircleIcon className="w-4 h-4" />
                <span>{t('closed')}</span>
              </>
            )}
          </div>
        </div>

        {/* Address */}
        <div className="flex items-start gap-2 mb-3 text-sm text-gray-600">
          <MapPinIcon className="w-5 h-5 flex-shrink-0 mt-0.5" />
          <span>{address}</span>
        </div>

        {/* Phone */}
        <div className="flex items-center gap-2 mb-3 text-sm text-gray-600">
          <PhoneIcon className="w-5 h-5 flex-shrink-0" />
          <a href={`tel:${phone}`} className="hover:text-primary transition">
            {phone}
          </a>
        </div>

        {/* Next Opening Time (if closed) */}
        {!is_currently_open && next_opening_time && (
          <div className="flex items-center gap-2 mb-4 text-sm text-gray-600">
            <ClockIcon className="w-5 h-5 flex-shrink-0" />
            <span>{t('opens')}: {next_opening_time}</span>
          </div>
        )}

        {/* Additional Info */}
        <div className="flex flex-wrap gap-2 mb-4">
          {kosher_type && (
            <span className="px-2 py-1 bg-blue-50 text-blue-700 text-xs rounded-full">
              {kosher_type}
            </span>
          )}
          {accessibility_list.length > 0 && accessibility_list.map((feature, idx) => (
            <span key={idx} className="px-2 py-1 bg-purple-50 text-purple-700 text-xs rounded-full">
              {feature}
            </span>
          ))}
        </div>

        {/* Select Button */}
        <button
          onClick={() => onSelect(id)}
          disabled={selected}
          className={`
            w-full px-4 py-2 rounded-lg font-medium transition-colors
            ${selected
              ? 'bg-primary text-white cursor-default'
              : is_currently_open
                ? 'bg-primary text-white hover:bg-primary-600'
                : 'bg-gray-200 text-gray-600 hover:bg-gray-300'
            }
          `}
        >
          {selected ? `✓ ${t('selectedBranch')}` : t('selectBranch')}
        </button>
      </div>
    </div>
  );
}
