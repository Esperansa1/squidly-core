import React, { useState, useEffect } from 'react';
import api from '../services/api.js';
import { TabSelector, BranchSelector } from './ui';
import DeliveryTypeSelector from './ui/DeliveryTypeSelector.jsx';

const OrderManagement = () => {
  // State management
  const [branches, setBranches] = useState([]);
  const [selectedBranch, setSelectedBranch] = useState({ id: 0, name: 'כל הסניפים' });
  const [activeTab, setActiveTab] = useState('הזמנות חיות');
  const [deliveryType, setDeliveryType] = useState(null);
  const [timeframe, setTimeframe] = useState('יום');
  const [loading, setLoading] = useState(true);

  // Tab options
  const mainTabs = ['הזמנות חיות', 'הזמנות קודמות'];
  const timeframeTabs = ['יום', 'שבוע', 'חודש', 'שנה', 'מותאם אישית'];

  // Initialize API and load branches
  useEffect(() => {
    initializeApp();
  }, []);

  const initializeApp = async () => {
    try {
      setLoading(true);
      await api.init();

      // Load branches
      const branchesData = await api.getBranches();
      setBranches(branchesData);

      setLoading(false);
    } catch (err) {
      console.error('Failed to initialize:', err);
      setLoading(false);
    }
  };

  return (
    <div className="h-full flex flex-col" dir="rtl">
      {/* Fixed Header */}
      <div className="flex-shrink-0 px-6 pt-6">
        {/* Single Row: Tabs + Filters */}
        <div className="flex justify-between items-center mb-6">
          {/* Left Side: Primary Navigation Tabs */}
          <TabSelector
            tabs={mainTabs}
            activeTab={activeTab}
            onTabChange={setActiveTab}
          />

          {/* Right Side: Branch + Conditional Dropdown */}
          <div className="flex items-center gap-4">
            {/* Branch Selector - Always visible */}
            <BranchSelector
              branches={branches}
              selectedBranchId={selectedBranch.id}
              selectedBranchName={selectedBranch.name}
              onBranchChange={setSelectedBranch}
              showAllBranches={true}
            />

            {/* Conditional: Delivery Type OR Timeframe */}
            {activeTab === 'הזמנות חיות' ? (
              <DeliveryTypeSelector
                value={deliveryType}
                onChange={setDeliveryType}
              />
            ) : (
              <TabSelector
                tabs={timeframeTabs}
                activeTab={timeframe}
                onTabChange={setTimeframe}
                className="w-auto"
              />
            )}
          </div>
        </div>
      </div>

      {/* Scrollable Content Area */}
      <div className="flex-1 px-6 pb-6 overflow-y-auto">
        <div className="text-center mt-20">
          <h1 className="text-3xl font-bold text-gray-900 mb-4">
            {activeTab}
          </h1>
          <p className="text-gray-600">
            Branch: {selectedBranch.name}
          </p>
          {activeTab === 'הזמנות חיות' && deliveryType && (
            <p className="text-gray-600">
              Delivery Type: {deliveryType}
            </p>
          )}
          {activeTab === 'הזמנות קודמות' && (
            <p className="text-gray-600">
              Timeframe: {timeframe}
            </p>
          )}
        </div>
      </div>
    </div>
  );
};

export default OrderManagement;
