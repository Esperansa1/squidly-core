import React, { useState, useEffect } from 'react';
import theme from '../../config/theme';
import { t, getCurrentLanguage } from '../../i18n/translations';
import { useIsMobile, useIsTablet } from '../../hooks/useMediaQuery';
import { useBranch } from '../../contexts/BranchContext';
import { useToast } from '../../contexts/ToastContext';
import publicApi from '../../services/publicApi';
import DeliveryAddressForm from './DeliveryAddressForm';
import BranchPickupList from './BranchPickupList';
import PickupTimeSelector from './PickupTimeSelector';
import { TruckIcon, ShoppingBagIcon, ArrowRightIcon, ArrowLeftIcon } from '@heroicons/react/24/outline';

/**
 * BranchSelectionModal - Modal for selecting branch with Pickup/Delivery flow
 *
 * Steps:
 * - initial: Login button + Pickup/Delivery cards
 * - delivery: Address form → find branch
 * - pickup: Branch list → time selection
 * - pickup-time: Time selector (pickup only)
 *
 * Pattern: Follows ProductCustomizationModal (full-screen mobile, 420px desktop)
 */
export default function BranchSelectionModal({ isOpen }) {
  const isMobile = useIsMobile();
  const isTablet = useIsTablet();
  const { branches, selectBranchForDelivery, selectBranchForPickup, loading: branchesLoading } = useBranch();
  const { showToast } = useToast();

  // Current step: 'initial' | 'delivery' | 'pickup' | 'pickup-time'
  const [step, setStep] = useState('initial');
  const [direction, setDirection] = useState('rtl');

  // Delivery state
  const [deliveryAddress, setDeliveryAddress] = useState({
    fullAddress: '',
    city: '',
    street: '',
    houseNumber: '',
    latitude: null,
    longitude: null,
  });
  const [deliveryErrors, setDeliveryErrors] = useState({});
  const [findingBranch, setFindingBranch] = useState(false);

  // Pickup state
  const [selectedPickupBranchId, setSelectedPickupBranchId] = useState(null);
  const [pickupTime, setPickupTime] = useState('');

  // Detect direction when modal opens
  useEffect(() => {
    if (isOpen) {
      const lang = getCurrentLanguage();
      const newDirection = (lang === 'he' || lang === 'ar') ? 'rtl' : 'ltr';
      setDirection(newDirection);
    }
  }, [isOpen]);

  // Reset state when modal opens
  useEffect(() => {
    if (isOpen) {
      setStep('initial');
      setDeliveryAddress({
        fullAddress: '',
        city: '',
        street: '',
        houseNumber: '',
        latitude: null,
        longitude: null,
      });
      setDeliveryErrors({});
      setSelectedPickupBranchId(null);
      setPickupTime('');
    }
  }, [isOpen]);

  // Get the back arrow icon based on direction
  const BackIcon = direction === 'rtl' ? ArrowRightIcon : ArrowLeftIcon;

  // Handle delivery address submission (called from DeliveryAddressForm with found branch)
  const handleDeliverySubmit = (branch, address, distance) => {
    // Branch found by DeliveryAddressForm - select it
    selectBranchForDelivery(branch.id, address);
    console.log(`✅ Branch selected for delivery: ${branch.name} (${distance.toFixed(1)} km away)`);
  };

  // Handle pickup branch selection
  const handlePickupBranchSelect = (branchId) => {
    setSelectedPickupBranchId(branchId);
    setStep('pickup-time');
  };

  // Handle pickup time confirmation
  const handlePickupConfirm = () => {
    if (selectedPickupBranchId && pickupTime) {
      selectBranchForPickup(selectedPickupBranchId, pickupTime);
      console.log('✅ Branch selected for pickup:', selectedPickupBranchId, pickupTime);
    }
  };

  // Get selected branch name for pickup-time step
  const getSelectedBranchName = () => {
    const branch = branches.find(b => b.id === selectedPickupBranchId);
    return branch?.name || '';
  };

  if (!isOpen) return null;

  return (
    // Dark overlay background
    <div
      style={{
        position: 'fixed',
        inset: 0,
        backgroundColor: 'rgba(0, 0, 0, 0.5)',
        zIndex: 9999,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        padding: isMobile ? 0 : theme.spacing.md,
      }}
    >
      {/* Modal container - 70% on desktop, 85% on tablet, full on mobile */}
      <div
        style={{
          backgroundColor: theme.colors.cardBg,
          borderRadius: isMobile ? 0 : theme.borderRadius.xl,
          width: isMobile ? '100%' : isTablet ? '85%' : '70%',
          maxWidth: isMobile ? '100%' : '900px',
          height: isMobile ? '100%' : 'auto',
          maxHeight: isMobile ? '100%' : '85vh',
          minHeight: isMobile ? '100%' : '400px',
          display: 'flex',
          flexDirection: 'column',
          overflow: 'hidden',
          boxShadow: isMobile ? 'none' : '0 25px 60px rgba(0, 0, 0, 0.35)',
          direction: direction,
          ...(isMobile && {
            position: 'fixed',
            inset: 0,
          }),
        }}
      >
        {/* Header with back button (when not on initial step) */}
        {step !== 'initial' && (
          <div
            style={{
              padding: theme.spacing.md,
              borderBottom: `1px solid ${theme.colors.border}`,
              display: 'flex',
              alignItems: 'center',
              gap: theme.spacing.sm,
            }}
          >
            <button
              onClick={() => {
                if (step === 'pickup-time') {
                  setStep('pickup');
                } else {
                  setStep('initial');
                }
              }}
              style={{
                background: 'none',
                border: 'none',
                cursor: 'pointer',
                padding: theme.spacing.xs,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                borderRadius: theme.borderRadius.full,
                color: theme.colors.text.primary,
                transition: 'background-color 0.2s ease',
              }}
              onMouseEnter={(e) => {
                e.currentTarget.style.backgroundColor = theme.colors.background;
              }}
              onMouseLeave={(e) => {
                e.currentTarget.style.backgroundColor = 'transparent';
              }}
            >
              <BackIcon style={{ width: '24px', height: '24px' }} />
            </button>
            <span style={{ fontWeight: '600', fontSize: '1rem' }}>
              {t('branchModalBack')}
            </span>
          </div>
        )}

        {/* Scrollable content */}
        <div
          style={{
            flex: 1,
            overflowY: 'auto',
            padding: isMobile ? theme.spacing.lg : theme.spacing.xl,
          }}
        >
          {/* Initial Step - Login + Pickup/Delivery cards */}
          {step === 'initial' && (
            <InitialStep
              direction={direction}
              onDeliveryClick={() => setStep('delivery')}
              onPickupClick={() => setStep('pickup')}
            />
          )}

          {/* Delivery Step - Address form */}
          {step === 'delivery' && (
            <DeliveryAddressForm
              address={deliveryAddress}
              onChange={setDeliveryAddress}
              errors={deliveryErrors}
              loading={findingBranch}
              onSubmit={handleDeliverySubmit}
              branches={branches}
            />
          )}

          {/* Pickup Step - Branch list */}
          {step === 'pickup' && (
            <BranchPickupList
              branches={branches}
              selectedBranchId={selectedPickupBranchId}
              onSelect={handlePickupBranchSelect}
            />
          )}

          {/* Pickup Time Step */}
          {step === 'pickup-time' && (
            <PickupTimeSelector
              selectedTime={pickupTime}
              onChange={setPickupTime}
              onContinue={handlePickupConfirm}
              branchName={getSelectedBranchName()}
            />
          )}
        </div>
      </div>
    </div>
  );
}

/**
 * Initial Step Component - Login button + Pickup/Delivery cards
 */
function InitialStep({ direction, onDeliveryClick, onPickupClick }) {
  const isMobile = useIsMobile();

  return (
    <div
      style={{
        textAlign: 'center',
        padding: isMobile ? theme.spacing.md : theme.spacing.xl,
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        minHeight: isMobile ? 'auto' : '350px',
      }}
    >
      {/* Title */}
      <h2
        style={{
          fontSize: isMobile ? '1.5rem' : '2rem',
          fontWeight: '700',
          color: theme.colors.text.primary,
          marginBottom: isMobile ? theme.spacing.md : theme.spacing.lg,
          marginTop: theme.spacing.md,
        }}
      >
        {t('branchModalTitle')}
      </h2>

      {/* Login Button */}
      <button
        onClick={() => {
          // Login functionality - placeholder for now
          console.log('Login clicked - not implemented yet');
        }}
        style={{
          padding: isMobile ? `${theme.spacing.sm} ${theme.spacing.xl}` : `${theme.spacing.md} ${theme.spacing['2xl']}`,
          backgroundColor: theme.colors.cardBg,
          color: theme.colors.primary,
          border: `2px solid ${theme.colors.primary}`,
          borderRadius: theme.borderRadius.lg,
          fontSize: isMobile ? '1rem' : '1.125rem',
          fontWeight: '600',
          cursor: 'pointer',
          marginBottom: isMobile ? theme.spacing.xl : theme.spacing['2xl'],
          transition: 'all 0.2s ease',
        }}
        onMouseEnter={(e) => {
          e.currentTarget.style.backgroundColor = 'rgba(220, 38, 38, 0.05)';
        }}
        onMouseLeave={(e) => {
          e.currentTarget.style.backgroundColor = theme.colors.cardBg;
        }}
      >
        {t('branchModalLogin')}
      </button>

      {/* Pickup/Delivery Cards */}
      <div
        style={{
          display: 'flex',
          gap: isMobile ? theme.spacing.md : theme.spacing.xl,
          marginTop: theme.spacing.lg,
          flexDirection: direction === 'rtl' ? 'row-reverse' : 'row',
          width: '100%',
          maxWidth: '500px',
        }}
      >
        {/* Pickup Card */}
        <OrderTypeCard
          icon={<ShoppingBagIcon style={{ width: isMobile ? '48px' : '64px', height: isMobile ? '48px' : '64px' }} />}
          label={t('branchModalPickup')}
          onClick={onPickupClick}
          isMobile={isMobile}
        />

        {/* Delivery Card */}
        <OrderTypeCard
          icon={<TruckIcon style={{ width: isMobile ? '48px' : '64px', height: isMobile ? '48px' : '64px' }} />}
          label={t('branchModalDelivery')}
          onClick={onDeliveryClick}
          isMobile={isMobile}
        />
      </div>
    </div>
  );
}

/**
 * Order Type Card Component (Pickup/Delivery)
 */
function OrderTypeCard({ icon, label, onClick, isMobile }) {
  return (
    <button
      onClick={onClick}
      style={{
        flex: 1,
        padding: isMobile ? theme.spacing.lg : theme.spacing.xl,
        backgroundColor: theme.colors.background,
        border: `2px solid ${theme.colors.border}`,
        borderRadius: theme.borderRadius.xl,
        cursor: 'pointer',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        gap: isMobile ? theme.spacing.md : theme.spacing.lg,
        transition: 'all 0.2s ease',
        minHeight: isMobile ? '140px' : '180px',
      }}
      onMouseEnter={(e) => {
        e.currentTarget.style.borderColor = theme.colors.primary;
        e.currentTarget.style.backgroundColor = 'rgba(220, 38, 38, 0.02)';
        e.currentTarget.style.transform = 'translateY(-4px)';
        e.currentTarget.style.boxShadow = '0 8px 25px rgba(220, 38, 38, 0.15)';
      }}
      onMouseLeave={(e) => {
        e.currentTarget.style.borderColor = theme.colors.border;
        e.currentTarget.style.backgroundColor = theme.colors.background;
        e.currentTarget.style.transform = 'translateY(0)';
        e.currentTarget.style.boxShadow = 'none';
      }}
    >
      <div style={{ color: theme.colors.primary }}>
        {icon}
      </div>
      <span
        style={{
          fontSize: isMobile ? '1.125rem' : '1.375rem',
          fontWeight: '600',
          color: theme.colors.text.primary,
        }}
      >
        {label}
      </span>
    </button>
  );
}
