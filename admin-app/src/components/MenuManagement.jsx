import React, { useState, useEffect } from 'react';
import { ChevronDownIcon } from '@heroicons/react/24/outline';
import api from '../services/api.js';
import { TabButton } from './ui';
import { useSorting } from '../hooks/useSorting.js';
import GroupSection from './GroupSection.jsx';

const MenuManagement = () => {
  const [config, setConfig] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  
  // State
  const [branches, setBranches] = useState([]);
  const [selectedBranch, setSelectedBranch] = useState('כל הסניפים');
  const [selectedBranchId, setSelectedBranchId] = useState(0);
  const [activeTab, setActiveTab] = useState('קבוצות');
  const [branchDropdownOpen, setBranchDropdownOpen] = useState(false);
  
  // Data state
  const [productGroups, setProductGroups] = useState([]);
  const [ingredientGroups, setIngredientGroups] = useState([]);
  const [selectedProductGroup, setSelectedProductGroup] = useState(null);
  const [selectedIngredientGroup, setSelectedIngredientGroup] = useState(null);
  
  // Sorting hooks
  const productSorting = useSorting(productGroups);
  const ingredientSorting = useSorting(ingredientGroups);

  // Initialize API and load data
  useEffect(() => {
    initializeApp();
  }, []);

  // Load data when branch changes
  useEffect(() => {
    if (config) {
      loadData();
    }
  }, [selectedBranchId, config]);

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
      setError(err.message);
      setLoading(false);
    }
  };

  const loadData = async () => {
    try {
      const filters = selectedBranchId > 0 ? { branch_id: selectedBranchId } : {};
      
      const [productGroupsData, ingredientGroupsData] = await Promise.all([
        api.getProductGroups(filters),
        api.getIngredientGroups(filters)
      ]);
      
      setProductGroups(productGroupsData);
      setIngredientGroups(ingredientGroupsData);
    } catch (err) {
      console.error('Failed to load data:', err);
    }
  };

  const handleDeleteGroup = async (groupId, type) => {
    const strings = api.getStrings();
    if (!confirm(strings.confirm_delete || 'האם אתה בטוח שברצונך למחוק?')) {
      return;
    }

    try {
      if (type === 'product') {
        await api.deleteProductGroup(groupId);
      } else {
        await api.deleteIngredientGroup(groupId);
      }
      
      // Reload data
      loadData();
    } catch (err) {
      alert(`שגיאה במחיקה: ${err.message}`);
    }
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
          {/* Tab Selector with Sliding Background */}
          <div className="relative flex bg-white rounded-lg shadow-sm p-1">
            {/* Sliding Background */}
            <div
              className="absolute inset-y-0 rounded-md transition-all duration-300 ease-out"
              style={{
                backgroundColor: '#dc2626',
                width: `${100 / tabs.length}%`,
                right: `${tabs.indexOf(activeTab) * (100 / tabs.length)}%`,
              }}
            />
            
            {/* Tab Buttons */}
            {tabs.map((tab) => (
              <TabButton
                key={tab}
                onClick={() => setActiveTab(tab)}
                isActive={activeTab === tab}
                className="relative z-10 flex-1 px-6 py-2 text-sm font-semibold"
              >
                {tab}
              </TabButton>
              ))}
          </div>

          {/* Branch Dropdown */}
          <div className="relative">
            <button
              onClick={() => setBranchDropdownOpen(!branchDropdownOpen)}
              className="flex items-center gap-2 px-4 py-2 bg-white rounded-lg shadow-sm border border-gray-200 hover:bg-gray-50 transition-colors"
            >
              <span className="text-sm text-neutral-700 font-semibold">{selectedBranch}</span>
              <ChevronDownIcon 
                className={`w-4 h-4 text-gray-500 transition-transform ${
                  branchDropdownOpen ? 'rotate-180' : ''
                }`}
              />
            </button>
            
            {branchDropdownOpen && (
              <div className="absolute top-full mt-1 right-0 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-10">
                {branches.map((branch) => (
                  <button
                    key={branch.id}
                    onClick={() => {
                      setSelectedBranch(branch.name);
                      setSelectedBranchId(branch.id);
                      setBranchDropdownOpen(false);
                    }}
                    className="block w-full text-right px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 first:rounded-t-lg last:rounded-b-lg transition-colors"
                  >
                    {branch.name}
                  </button>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Scrollable Content Area */}
      <div className="flex-1 px-6 pb-6 overflow-y-auto">
        {/* Content Sections - Full Height Grid */}
        <div className="grid grid-cols-1 xl:grid-cols-2 gap-6 h-full">
          {/* Product Groups Section */}
          <div className="min-h-0 flex flex-col">
            <GroupSection
              title={strings.product_groups || 'קבוצות מוצרים'}
              groups={productSorting.sortedData}
              selectedGroup={selectedProductGroup}
              setSelectedGroup={setSelectedProductGroup}
              type="product"
              strings={strings}
              sortField={productSorting.sortField}
              sortDirection={productSorting.sortDirection}
              onSort={productSorting.handleSort}
              onDeleteGroup={handleDeleteGroup}
              loading={loading}
              error={error}
            />
          </div>

          {/* Ingredient Groups Section */}
          <div className="min-h-0 flex flex-col">
            <GroupSection
              title={strings.ingredient_groups || 'קבוצות מרכיבים'}
              groups={ingredientSorting.sortedData}
              selectedGroup={selectedIngredientGroup}
              setSelectedGroup={setSelectedIngredientGroup}
              type="ingredient"
              strings={strings}
              sortField={ingredientSorting.sortField}
              sortDirection={ingredientSorting.sortDirection}
              onSort={ingredientSorting.handleSort}
              onDeleteGroup={handleDeleteGroup}
              loading={loading}
              error={error}
            />
          </div>
        </div>
      </div>
    </div>
  );
};

export default MenuManagement;