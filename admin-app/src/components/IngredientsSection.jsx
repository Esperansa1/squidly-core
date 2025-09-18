import React, { useState, useMemo, useEffect, useCallback, useRef } from 'react';
import { Card, TableHeader, SearchBar, DataTable } from './ui';
import api from '../services/api.js';

const IngredientsSection = ({
  title = 'מרכיבים',
  ingredients = [],
  selectedIngredient,
  setSelectedIngredient,
  strings = {},
  loading: externalLoading = false,
  error: externalError = null,
  branches = [],
  selectedBranchId = 0,
  onIngredientChange = () => {}
}) => {
  const [searchTerm, setSearchTerm] = useState('');
  const [apiIngredients, setApiIngredients] = useState([]);
  const [apiLoading, setApiLoading] = useState(false);
  const [apiError, setApiError] = useState(null);
  const [lastFetchedBranchId, setLastFetchedBranchId] = useState(null);
  const branchDataCache = useRef(new Map());

  // Stable fetch function with useCallback
  const fetchIngredients = useCallback(async () => {
    // Skip if we already have data for this branch and it hasn't changed
    if (lastFetchedBranchId === selectedBranchId && branchDataCache.current.has(selectedBranchId)) {
      return;
    }

    if (ingredients.length > 0) {
      // Use provided ingredients if available
      setApiIngredients(ingredients);
      setLastFetchedBranchId(selectedBranchId);
      return;
    }

    // Check if we already have data for this branch in cache
    if (branchDataCache.current.has(selectedBranchId)) {
      setApiIngredients(branchDataCache.current.get(selectedBranchId));
      setLastFetchedBranchId(selectedBranchId);
      return;
    }

    try {
      setApiLoading(true);
      setApiError(null);
      
      // Build filters based on selected branch
      const filters = {};
      if (selectedBranchId > 0) {
        filters.branch_id = selectedBranchId;
      }
      
      const response = await api.getIngredients(filters);
      const ingredientsData = response.data || response || [];
      
      // Cache the data for this branch
      branchDataCache.current.set(selectedBranchId, ingredientsData);
      setApiIngredients(ingredientsData);
      setLastFetchedBranchId(selectedBranchId);
    } catch (error) {
      console.error('Failed to fetch ingredients:', error);
      setApiError(error.message || 'Failed to load ingredients');
      
      // Fallback to mock data on error
      const mockIngredients = [
        {
          id: 1,
          name: 'עגבניות',
          price: 2.5,
          availability: { 0: true, 1: true, 2: false }
        },
        {
          id: 2,
          name: 'גבינת מוצרלה',
          price: 8.0,
          availability: { 0: true, 1: false, 2: true }
        },
        {
          id: 3,
          name: 'בצל',
          price: 1.5,
          availability: { 0: true, 1: true, 2: true }
        },
        {
          id: 4,
          name: 'קמח',
          price: 0.0,
          availability: { 0: true, 1: true, 2: true }
        },
        {
          id: 5,
          name: 'ביצים',
          price: 3.0,
          availability: { 0: false, 1: true, 2: true }
        },
        {
          id: 6,
          name: 'פלפל',
          price: 2.0,
          availability: { 0: true, 1: false, 2: true }
        }
      ];
      
      // Cache mock data too
      branchDataCache.current.set(selectedBranchId, mockIngredients);
      setApiIngredients(mockIngredients);
      setLastFetchedBranchId(selectedBranchId);
    } finally {
      setApiLoading(false);
    }
  }, [selectedBranchId, ingredients, lastFetchedBranchId]);

  // Fetch ingredients from API only when branch actually changes
  useEffect(() => {
    fetchIngredients();
  }, [fetchIngredients]);

  // Determine data source and loading/error states
  const dataToUse = apiIngredients;
  const loading = externalLoading || apiLoading;
  const error = externalError || apiError;

  // Filter data based on search term
  const filteredData = useMemo(() => {
    if (!searchTerm) return dataToUse;
    
    const term = searchTerm.toLowerCase();
    return dataToUse.filter(ingredient => 
      ingredient.name.toLowerCase().includes(term)
    );
  }, [dataToUse, searchTerm]);

  // Price display component
  const PriceDisplay = ({ price }) => (
    <div className="text-center w-full" style={{ fontFeatureSettings: '"tnum"' }}>
      {price === 0 ? (
        <span className="text-sm text-green-600 font-semibold">
          {strings.free || 'חינם'}
        </span>
      ) : (
        <span className="text-sm text-gray-800 font-semibold" dir="ltr">
          ₪{price.toFixed(2)}
        </span>
      )}
    </div>
  );

  // Availability display component (skeleton for future implementation)
  const AvailabilityDisplay = ({ availability }) => {
    const isAvailable = availability && availability[selectedBranchId] !== false;
    return (
      <div className="flex items-center justify-center gap-2 w-full">
        <span className="text-sm text-gray-600">
          {isAvailable ? (strings.available || 'זמין') : (strings.unavailable || 'לא זמין')}
        </span>
        <div className={`w-2 h-2 rounded-full ${isAvailable ? 'bg-green-500' : 'bg-red-500'}`} />
      </div>
    );
  };

  // Define columns based on Ingredient model - RTL order: Name → Price → Availability
  const columns = [
    {
      key: 'name',
      label: strings.ingredient_name || 'שם המרכיב',
      width: '200px',
      render: (name) => (
        <span className="text-sm text-gray-800 font-medium w-full text-center" title={name}>
          {name}
        </span>
      )
    },
    {
      key: 'price',
      label: strings.price || 'מחיר',
      width: '100px',
      render: (price) => <PriceDisplay price={price} />
    },
    {
      key: 'availability',
      label: strings.availability || 'זמינות',
      width: '140px',
      cellStyle: { 
        whiteSpace: 'normal', 
        overflow: 'visible',
        textOverflow: 'initial'
      },
      render: (_, item) => <AvailabilityDisplay availability={item.availability} />
    }
  ];

  return (
    <Card className="h-full flex flex-col" padding="none">
      <div className="flex-shrink-0 p-6 border-b border-gray-200">
        <TableHeader
          title={title}
          onCreateClick={() => {}}
          onEditClick={() => {}}
          onDeleteClick={() => {}}
          hasSelectedItem={!!selectedIngredient}
          strings={{
            create: strings.create_ingredient || 'צור מרכיב חדש',
            edit: strings.edit_ingredient || 'ערוך מרכיב',
            delete: strings.delete_ingredient || 'מחק מרכיב'
          }}
        />
        
        <SearchBar
          value={searchTerm}
          onChange={setSearchTerm}
          placeholder={strings.search_ingredients || 'חפש מרכיבים...'}
        />
      </div>

      <div className="flex-1 p-6 pt-4 min-h-0">
        <DataTable
          columns={columns}
          data={filteredData}
          selectedId={selectedIngredient}
          onSelectionChange={setSelectedIngredient}
          loading={loading}
          error={error}
          emptyMessage={strings.no_ingredients || 'אין מרכיבים להצגה'}
        />
      </div>
    </Card>
  );
};

export default IngredientsSection;