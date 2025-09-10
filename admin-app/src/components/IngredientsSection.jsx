import React, { useState } from 'react';
import { PlusIcon, PencilIcon, TrashIcon, MagnifyingGlassIcon } from '@heroicons/react/24/outline';
import { Card, ActionButton } from './ui';
import { DEFAULT_THEME } from '../config/theme.js';

const IngredientsSection = ({ 
  title,
  ingredients = [],
  selectedIngredient,
  setSelectedIngredient,
  strings = {},
  loading = false,
  error = null,
  branches = [],
  selectedBranchId = 0,
  onIngredientChange = () => {}
}) => {
  const theme = DEFAULT_THEME;
  const [searchTerm, setSearchTerm] = useState('');

  const mockIngredients = [
    { id: 1, name: 'עגבניות', price: 2.5, availability: { 0: true, 1: true, 2: false } },
    { id: 2, name: 'גבינת מוצרלה', price: 8.0, availability: { 0: true, 1: false, 2: true } },
    { id: 3, name: 'בצל', price: 1.5, availability: { 0: true, 1: true, 2: true } },
    { id: 4, name: 'קמח', price: 0.0, availability: { 0: true, 1: true, 2: true } },
    { id: 5, name: 'ביצים', price: 3.0, availability: { 0: false, 1: true, 2: true } },
    { id: 6, name: 'פלפל', price: 2.0, availability: { 0: true, 1: false, 2: true } },
    { id: 7, name: 'זיתים', price: 4.5, availability: { 0: true, 1: true, 2: false } },
    { id: 8, name: 'פטה', price: 6.0, availability: { 0: false, 1: true, 2: true } }
  ];

  const PriceDisplay = ({ price }) => (
    <div className="text-right" style={{ fontFeatureSettings: '"tnum"' }}>
      {price === 0 ? (
        <span className="text-sm text-green-600 font-semibold">{strings.free || 'חינם'}</span>
      ) : (
        <span className="text-sm text-gray-800 font-semibold" dir="ltr">
          ₪{price.toFixed(2)}
        </span>
      )}
    </div>
  );

  const AvailabilityIndicator = ({ availability }) => {
    const isAvailable = availability[selectedBranchId] !== false;
    return (
      <div className="flex items-center justify-end" style={{ gap: '8px' }}>
        <div 
          className={`w-2 h-2 rounded-full flex-shrink-0 ${
            isAvailable ? 'bg-green-500' : 'bg-red-500'
          }`}
        />
        <span className="text-sm text-gray-600">
          {isAvailable ? (strings.available || 'זמין') : (strings.unavailable || 'לא זמין')}
        </span>
      </div>
    );
  };

  return (
    <Card className="h-full flex flex-col" padding="none">
      {/* Section Header - Fixed */}
      <div className="flex-shrink-0 p-6 border-b border-gray-200">
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-lg text-neutral-800 font-bold">{title}</h2>
          <div className="flex gap-2 rtl:flex-row-reverse">
            <ActionButton 
              icon={PlusIcon} 
              variant="primary" 
              onClick={() => {}}
              title={strings.create_ingredient || 'צור מרכיב חדש'}
            />
            <ActionButton 
              icon={PencilIcon} 
              variant="secondary"
              disabled={!selectedIngredient}
              onClick={() => {}}
              title={strings.edit_ingredient || 'ערוך מרכיב'}
            />
            <ActionButton 
              icon={TrashIcon} 
              variant="error"
              disabled={!selectedIngredient}
              onClick={() => {}}
              title={strings.delete_ingredient || 'מחק מרכיב'}
            />
          </div>
        </div>

        {/* Search Bar */}
        <div className="relative mb-4">
          <MagnifyingGlassIcon className="absolute right-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
          <input
            type="text"
            placeholder={strings.search_ingredients || 'חפש מרכיבים...'}
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full pr-10 pl-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
          />
        </div>

        {/* Table Header */}
        <div className="flex items-center justify-between pb-2 px-2">
          <div className="text-right" style={{ width: '140px' }}>
            <span className="text-sm text-gray-700 font-semibold">
              {strings.availability || 'זמינות'}
            </span>
          </div>
          <div className="text-right" style={{ width: '80px' }}>
            <span className="text-sm text-gray-700 font-semibold">
              {strings.price || 'מחיר'}
            </span>
          </div>
          <div className="text-right flex-1">
            <span className="text-sm text-gray-700 font-semibold">
              {strings.ingredient_name || 'שם המרכיב'}
            </span>
          </div>
          <div style={{ width: '40px' }}></div>
        </div>
      </div>

      {/* Table Rows - Scrollable */}
      <div className="flex-1 p-6 pt-4 overflow-y-auto">
        {loading ? (
          <div className="flex items-center justify-center h-full text-gray-500">
            <div className="text-center">
              <div className="w-8 h-8 border-4 border-gray-300 border-t-red-600 rounded-full animate-spin mx-auto mb-4"></div>
              <p>טוען מרכיבים...</p>
            </div>
          </div>
        ) : error ? (
          <div className="flex items-center justify-center h-full text-red-500">
            <div className="text-center">
              <div className="text-red-600 text-xl mb-4">⚠️</div>
              <p className="text-red-600 mb-4">שגיאה בטעינה: {error}</p>
            </div>
          </div>
        ) : (
          <div className="space-y-3">
            {mockIngredients.map((ingredient) => (
              <div 
                key={ingredient.id}
                className="py-3 hover:bg-gray-50 rounded-lg px-2 transition-colors cursor-pointer flex items-center justify-between"
                onClick={() => setSelectedIngredient(ingredient.id)}
              >
                <div style={{ width: '140px' }}>
                  <AvailabilityIndicator availability={ingredient.availability} />
                </div>
                <div style={{ width: '80px' }}>
                  <PriceDisplay price={ingredient.price} />
                </div>
                <div className="text-right flex-1">
                  <span className="text-sm text-gray-800 font-medium">{ingredient.name}</span>
                </div>
                <div className="flex justify-center" style={{ width: '40px' }}>
                  <div
                    onClick={() => setSelectedIngredient(ingredient.id)}
                    className="w-4 h-4 rounded-full border-2 cursor-pointer transition-all flex items-center justify-center"
                    style={{
                      borderColor: selectedIngredient === ingredient.id ? theme.primary_color : theme.border_light,
                      backgroundColor: selectedIngredient === ingredient.id ? theme.primary_color : theme.bg_white
                    }}
                  >
                    {selectedIngredient === ingredient.id && (
                      <div className="w-1.5 h-1.5 rounded-full bg-white" />
                    )}
                  </div>
                  <input
                    type="radio"
                    name="ingredient-selection"
                    value={ingredient.id}
                    checked={selectedIngredient === ingredient.id}
                    onChange={() => setSelectedIngredient(ingredient.id)}
                    style={{ display: 'none' }}
                  />
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </Card>
  );
};

export default IngredientsSection;