import React, { useState, useEffect, useRef } from 'react';
import api from '../services/api.js';
import { TabContent, BranchSelector, TabSelector } from './ui';
import { useSorting } from '../hooks/useSorting.js';

const MenuManagement = () => {
  const [config, setConfig] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // State
  const [branches, setBranches] = useState([]);
  const [selectedBranch, setSelectedBranch] = useState({ id: 0, name: 'כל הסניפים' });
  const [activeTab, setActiveTab] = useState('קבוצות');

  // Data state
  const [productGroups, setProductGroups] = useState([]);
  const [ingredientGroups, setIngredientGroups] = useState([]);
  const [selectedProductGroup, setSelectedProductGroup] = useState(null);
  const [selectedIngredientGroup, setSelectedIngredientGroup] = useState(null);

  // Sorting hooks
  const productSorting = useSorting(productGroups);
  const ingredientSorting = useSorting(ingredientGroups);

  // Track whether initial load is complete
  const initialLoadDone = useRef(false);

  // Initialize API and load all data in parallel
  useEffect(() => {
    initializeApp();
  }, []);

  // Reload data only when branch selection changes (after initial load)
  useEffect(() => {
    if (initialLoadDone.current && config) {
      loadData();
    }
  }, [selectedBranch.id]);

  const initializeApp = async () => {
    try {
      setLoading(true);

      // api.init() must complete first (verifies auth, loads config)
      const appConfig = await api.init();
      setConfig(appConfig);

      // Then load all data in parallel
      const [branchesData, productGroupsData, ingredientGroupsData] = await Promise.all([
        api.getBranches(),
        api.getProductGroups(),
        api.getIngredientGroups(),
      ]);

      setBranches(branchesData);
      setProductGroups(productGroupsData);
      setIngredientGroups(ingredientGroupsData);
      initialLoadDone.current = true;
      setLoading(false);
    } catch (err) {
      setError(err.message);
      setLoading(false);
    }
  };

  const loadData = async () => {
    try {
      const filters = selectedBranch.id > 0 ? { branch_id: selectedBranch.id } : {};

      const [productGroupsResponse, ingredientGroupsResponse] = await Promise.all([
        api.getProductGroups(filters),
        api.getIngredientGroups(filters)
      ]);

      setProductGroups(productGroupsResponse);
      setIngredientGroups(ingredientGroupsResponse);
    } catch (err) {
      console.error('Failed to load data:', err);
    }
  };

  // Handle group changes (create/edit/delete) - refresh data
  const handleGroupChange = () => {
    loadData();
  };

  // Get strings with fallbacks
  const strings = config ? api.getStrings() : {};
  const tabs = [strings.groups || 'קבוצות', strings.ingredients || 'מרכיבים', strings.products || 'מוצרים'];

  // Return ONLY the content, no AppLayout wrapper
  return (
    <div className="h-full flex flex-col">
      {/* Fixed Header */}
      <div className="flex-shrink-0 px-6 pt-6">
        {/* Page Header Controls */}
        <div className="flex justify-between items-center mb-6">
          {/* Tab Selector */}
          <TabSelector
            tabs={tabs}
            activeTab={activeTab}
            onTabChange={setActiveTab}
          />

          {/* Branch Selector */}
          <BranchSelector
            branches={branches}
            selectedBranchId={selectedBranch.id}
            selectedBranchName={selectedBranch.name}
            onBranchChange={setSelectedBranch}
            showAllBranches={true}
          />
        </div>
      </div>

      {/* Scrollable Content Area */}
      <div className="flex-1 px-6 pb-6 min-h-0">
        <TabContent
          activeTab={activeTab}
          productGroups={productGroups}
          ingredientGroups={ingredientGroups}
          selectedProductGroup={selectedProductGroup}
          setSelectedProductGroup={setSelectedProductGroup}
          selectedIngredientGroup={selectedIngredientGroup}
          setSelectedIngredientGroup={setSelectedIngredientGroup}
          productSorting={productSorting}
          ingredientSorting={ingredientSorting}
          strings={strings}
          loading={loading}
          error={error}
          branches={branches}
          selectedBranchId={selectedBranch.id}
          onGroupChange={handleGroupChange}
        />
      </div>
    </div>
  );
};

export default MenuManagement;