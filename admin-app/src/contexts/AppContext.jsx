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
      const appConfig = await api.init();
      setConfig(appConfig);
      
      // Load branches
      const branchesData = await api.getBranches();
      setBranches(branchesData);
      
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