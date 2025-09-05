import React, { createContext, useContext, useState, useEffect } from 'react';
import api from '../services/api.js';

const AppContext = createContext();

export const useApp = () => {
  const context = useContext(AppContext);
  if (!context) {
    throw new Error('useApp must be used within AppProvider');
  }
  return context;
};

export const AppProvider = ({ children }) => {
  const [config, setConfig] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [branches, setBranches] = useState([]);
  
  // Initialize API and load global data once
  useEffect(() => {
    initializeApp();
  }, []);

  const initializeApp = async () => {
    try {
      setLoading(true);
      console.log('AppContext: Starting API initialization...');
      const appConfig = await api.init();
      console.log('AppContext: API initialized, config:', appConfig);
      setConfig(appConfig);
      
      // Load branches
      console.log('AppContext: Loading branches...');
      const branchesData = await api.getBranches();
      console.log('AppContext: Branches loaded:', branchesData);
      setBranches(branchesData);
      
      console.log('AppContext: Initialization complete');
      setLoading(false);
    } catch (err) {
      console.error('AppContext: Initialization failed:', err);
      setError(err.message);
      setLoading(false);
    }
  };

  const value = {
    config,
    loading,
    error,
    branches,
    api,
    reload: initializeApp
  };

  return (
    <AppContext.Provider value={value}>
      {children}
    </AppContext.Provider>
  );
};