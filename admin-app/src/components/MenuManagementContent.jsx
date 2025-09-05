import React, { useState, useEffect } from 'react';
import { ChevronDownIcon, PlusIcon, PencilIcon, TrashIcon } from '@heroicons/react/24/outline';
import { useApp } from '../contexts/AppContext.jsx';

const MenuManagementContent = () => {
  const { config, branches, api } = useApp();
  
  // Local state
  const [selectedBranch, setSelectedBranch] = useState('כל הסניפים');
  const [selectedBranchId, setSelectedBranchId] = useState(0);
  const [activeTab, setActiveTab] = useState('קבוצות');
  const [branchDropdownOpen, setBranchDropdownOpen] = useState(false);
  
  // Data state
  const [productGroups, setProductGroups] = useState([]);
  const [ingredientGroups, setIngredientGroups] = useState([]);
  const [selectedProductGroup, setSelectedProductGroup] = useState(null);
  const [selectedIngredientGroup, setSelectedIngredientGroup] = useState(null);

  // Load data when branch changes
  useEffect(() => {
    if (config) {
      loadData();
    }
  }, [selectedBranchId, config]);


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


  // Only access API methods if config is loaded
  if (!config) {
    return (
      <div className="h-full flex items-center justify-center" style={{ backgroundColor: '#F2F2F2' }}>
        <div className="text-center">
          <div className="w-8 h-8 border-4 border-gray-300 border-t-red-600 rounded-full animate-spin mx-auto mb-4"></div>
          <p className="text-gray-600">טוען נתונים...</p>
        </div>
      </div>
    );
  }

  const theme = api.getTheme();
  const strings = api.getStrings();
  const tabs = [strings.groups || 'קבוצות', strings.ingredients || 'מרכיבים', strings.products || 'מוצרים'];

  const StatusIndicator = ({ status }) => (
    <div className="flex items-center gap-2 rtl:gap-2">
      <div 
        className={`w-2 h-2 rounded-full ${
          status === 'active' ? 'bg-green-500' : 'bg-red-500'
        }`}
      />
      <span className="text-sm text-gray-600">
        {status === 'active' ? (strings.active || 'פעילה') : (strings.inactive || 'לא פעילה')}
      </span>
    </div>
  );

  const ActionButton = ({ icon: Icon, onClick, variant = 'primary', disabled = false }) => {
    const bgColor = variant === 'danger' ? theme.danger_color : theme.primary_color;
    
    return (
      <button
        onClick={onClick}
        disabled={disabled}
        className={`w-8 h-8 rounded-full flex items-center justify-center text-white transition-all ${
          disabled ? 'opacity-50 cursor-not-allowed' : 'hover:opacity-80 hover:scale-105'
        }`}
        style={{ backgroundColor: bgColor }}
      >
        <Icon className="w-4 h-4" />
      </button>
    );
  };

  const GroupSection = ({ title, groups, selectedGroup, setSelectedGroup, type }) => (
    <div className="bg-white rounded-lg shadow-sm h-full flex flex-col">
      {/* Section Header - Fixed */}
      <div className="flex-shrink-0 p-6 border-b border-gray-200">
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-lg text-gray-800" style={{ fontWeight: 800 }}>{title}</h2>
          <div className="flex gap-2 rtl:flex-row-reverse">
            <ActionButton 
              icon={PlusIcon} 
              onClick={() => console.log('Add clicked', type)} 
            />
            <ActionButton 
              icon={PencilIcon} 
              onClick={() => console.log('Edit clicked', type, selectedGroup)}
              disabled={!selectedGroup}
            />
            <ActionButton 
              icon={TrashIcon} 
              onClick={() => selectedGroup && handleDeleteGroup(selectedGroup, type)}
              variant="danger"
              disabled={!selectedGroup}
            />
          </div>
        </div>

        {/* Table Header */}
        <div className="grid grid-cols-12 gap-4 pb-3">
          <div className="col-span-6 text-right">
            <span className="text-sm text-gray-700" style={{ fontWeight: 600 }}>{strings.group_name || 'שם הקבוצה'}</span>
          </div>
          <div className="col-span-5 text-right">
            <span className="text-sm text-gray-700" style={{ fontWeight: 600 }}>{strings.group_status || 'סטטוס הקבוצה'}</span>
          </div>
          <div className="col-span-1"></div>
        </div>
      </div>

      {/* Table Rows - Scrollable */}
      <div className="flex-1 p-6 pt-4 overflow-y-auto">
        {groups.length === 0 ? (
          <div className="flex items-center justify-center h-full text-gray-500">
            <div className="text-center">
              <div className="text-4xl mb-4">📋</div>
              <p>אין קבוצות להצגה</p>
            </div>
          </div>
        ) : (
          <div className="space-y-3">
            {groups.map((group) => (
              <div 
                key={group.id}
                className="grid grid-cols-12 gap-4 py-3 hover:bg-gray-50 rounded-lg px-2 transition-colors cursor-pointer"
                onClick={() => setSelectedGroup(group.id)}
              >
                <div className="col-span-6 text-right">
                  <span className="text-sm text-gray-800">{group.name}</span>
                </div>
                <div className="col-span-5 text-right">
                  <StatusIndicator status={group.status} />
                </div>
                <div className="col-span-1 flex justify-center">
                  <input
                    type="radio"
                    name={`${type}-selection`}
                    checked={selectedGroup === group.id}
                    onChange={() => setSelectedGroup(group.id)}
                    className="w-4 h-4"
                    style={{ accentColor: theme.primary_color }}
                  />
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );

  return (
    <div className="h-full flex flex-col" style={{ backgroundColor: theme.secondary_color }}>
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
                backgroundColor: theme.primary_color,
                width: `${100 / tabs.length}%`,
                right: `${tabs.indexOf(activeTab) * (100 / tabs.length)}%`,
              }}
            />
            
            {/* Tab Buttons */}
            {tabs.map((tab) => (
              <button
                key={tab}
                onClick={() => setActiveTab(tab)}
                className={`relative z-10 flex-1 px-6 py-2 text-sm transition-colors duration-300 focus:outline-none ${
                  activeTab === tab
                    ? 'text-white'
                    : 'text-gray-600 hover:text-gray-800'
                }`}
                style={{ fontWeight: 600 }}
              >
                {tab}
              </button>
              ))}
          </div>

          {/* Branch Dropdown */}
          <div className="relative">
            <button
              onClick={() => setBranchDropdownOpen(!branchDropdownOpen)}
              className="flex items-center gap-2 px-4 py-2 bg-white rounded-lg shadow-sm border border-gray-200 hover:bg-gray-50 transition-colors"
            >
              <span className="text-sm text-gray-700" style={{ fontWeight: 600 }}>{selectedBranch}</span>
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
              groups={productGroups}
              selectedGroup={selectedProductGroup}
              setSelectedGroup={setSelectedProductGroup}
              type="product"
            />
          </div>

          {/* Ingredient Groups Section */}
          <div className="min-h-0 flex flex-col">
            <GroupSection
              title={strings.ingredient_groups || 'קבוצות מרכיבים'}
              groups={ingredientGroups}
              selectedGroup={selectedIngredientGroup}
              setSelectedGroup={setSelectedIngredientGroup}
              type="ingredient"
            />
          </div>
        </div>
      </div>
    </div>
  );
};

export default MenuManagementContent;