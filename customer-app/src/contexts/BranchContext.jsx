import React, { createContext, useContext, useState, useEffect } from 'react';
import publicApi from '../services/publicApi';

/**
 * Branch Context
 *
 * Manages branch selection state throughout the app.
 * Provides:
 * - branches: array of all available branches
 * - selectedBranch: currently selected branch object
 * - selectBranch(id): function to select a branch
 * - loading: boolean indicating if branches are being fetched
 * - error: string | null for error handling
 */

const BranchContext = createContext(null);

export function BranchProvider({ children }) {
  const [branches, setBranches] = useState([]);
  const [selectedBranch, setSelectedBranch] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Order type: 'pickup' | 'delivery' | null
  const [orderType, setOrderType] = useState(null);

  // Delivery address for delivery orders
  const [deliveryAddress, setDeliveryAddress] = useState({
    city: '',
    street: '',
    houseNumber: ''
  });

  // Pickup time for pickup orders
  const [pickupTime, setPickupTime] = useState(null);

  // Load branches on mount
  useEffect(() => {
    const loadBranches = async () => {
      try {
        setLoading(true);
        setError(null);

        const data = await publicApi.getBranches();
        setBranches(data);

        // Try to restore selected branch from sessionStorage
        const savedBranchId = sessionStorage.getItem('selectedBranchId');
        if (savedBranchId) {
          const savedBranch = data.find(b => b.id === parseInt(savedBranchId));
          if (savedBranch) {
            setSelectedBranch(savedBranch);
          }
        }

        // Restore order type
        const savedOrderType = sessionStorage.getItem('squidly_order_type');
        if (savedOrderType) {
          setOrderType(savedOrderType);
        }

        // Restore delivery address
        const savedAddress = sessionStorage.getItem('squidly_delivery_address');
        if (savedAddress) {
          try {
            setDeliveryAddress(JSON.parse(savedAddress));
          } catch (e) {
            console.warn('Failed to parse saved delivery address');
          }
        }

        // Restore pickup time
        const savedPickupTime = sessionStorage.getItem('squidly_pickup_time');
        if (savedPickupTime) {
          setPickupTime(savedPickupTime);
        }

      } catch (err) {
        setError(err.message || 'Failed to load branches');
      } finally {
        setLoading(false);
      }
    };

    loadBranches();
  }, []);

  /**
   * Select a branch and persist to sessionStorage
   */
  const selectBranch = (branchId) => {
    const branch = branches.find(b => b.id === branchId);

    if (!branch) {
      return;
    }

    setSelectedBranch(branch);
    sessionStorage.setItem('selectedBranchId', branchId.toString());
  };

  /**
   * Select a branch for delivery orders
   * @param {number} branchId - Branch ID
   * @param {Object} address - { city, street, houseNumber }
   */
  const selectBranchForDelivery = (branchId, address) => {
    const branch = branches.find(b => b.id === branchId);

    if (!branch) {
      return;
    }

    setSelectedBranch(branch);
    setOrderType('delivery');
    setDeliveryAddress(address);
    setPickupTime(null);

    sessionStorage.setItem('selectedBranchId', branchId.toString());
    sessionStorage.setItem('squidly_order_type', 'delivery');
    sessionStorage.setItem('squidly_delivery_address', JSON.stringify(address));
    sessionStorage.removeItem('squidly_pickup_time');
  };

  /**
   * Select a branch for pickup orders
   * @param {number} branchId - Branch ID
   * @param {string} time - Pickup time (ISO string or datetime-local value)
   */
  const selectBranchForPickup = (branchId, time) => {
    const branch = branches.find(b => b.id === branchId);

    if (!branch) {
      return;
    }

    setSelectedBranch(branch);
    setOrderType('pickup');
    setPickupTime(time);
    setDeliveryAddress({ city: '', street: '', houseNumber: '' });

    sessionStorage.setItem('selectedBranchId', branchId.toString());
    sessionStorage.setItem('squidly_order_type', 'pickup');
    sessionStorage.setItem('squidly_pickup_time', time);
    sessionStorage.removeItem('squidly_delivery_address');
  };

  /**
   * Clear selected branch and all related data
   */
  const clearSelection = () => {
    setSelectedBranch(null);
    setOrderType(null);
    setDeliveryAddress({ city: '', street: '', houseNumber: '' });
    setPickupTime(null);

    sessionStorage.removeItem('selectedBranchId');
    sessionStorage.removeItem('squidly_order_type');
    sessionStorage.removeItem('squidly_delivery_address');
    sessionStorage.removeItem('squidly_pickup_time');
  };

  const value = {
    branches,
    selectedBranch,
    selectBranch,
    selectBranchForDelivery,
    selectBranchForPickup,
    clearSelection,
    orderType,
    deliveryAddress,
    pickupTime,
    loading,
    error
  };

  return (
    <BranchContext.Provider value={value}>
      {children}
    </BranchContext.Provider>
  );
}

/**
 * Custom hook to use the Branch context
 */
export function useBranch() {
  const context = useContext(BranchContext);

  if (!context) {
    throw new Error('useBranch must be used within a BranchProvider');
  }

  return context;
}

export default BranchContext;
