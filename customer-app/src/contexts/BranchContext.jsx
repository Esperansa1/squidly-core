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
            console.log('✅ Restored selected branch:', savedBranch.name);
          }
        }

        console.log(`✅ Loaded ${data.length} branches`);
      } catch (err) {
        console.error('❌ Failed to load branches:', err);
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
      console.error('❌ Branch not found:', branchId);
      return;
    }

    setSelectedBranch(branch);
    sessionStorage.setItem('selectedBranchId', branchId.toString());
    console.log('✅ Selected branch:', branch.name);
  };

  /**
   * Clear selected branch
   */
  const clearSelection = () => {
    setSelectedBranch(null);
    sessionStorage.removeItem('selectedBranchId');
    console.log('✅ Cleared branch selection');
  };

  const value = {
    branches,
    selectedBranch,
    selectBranch,
    clearSelection,
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
