import React, { useState } from 'react';
import { PlusIcon, PencilIcon, TrashIcon, MagnifyingGlassIcon } from '@heroicons/react/24/outline';
import { Card, ActionButton } from './ui';
import { DEFAULT_THEME } from '../config/theme.js';

const ProductsSection = ({ 
  title,
  products = [],
  selectedProduct,
  setSelectedProduct,
  strings = {},
  loading = false,
  error = null,
  branches = [],
  selectedBranchId = 0,
  onProductChange = () => {}
}) => {
  const theme = DEFAULT_THEME;
  const [searchTerm, setSearchTerm] = useState('');

  const mockProducts = [
    { 
      id: 1, 
      name: 'פיצה מרגריטה', 
      category: 'פיצות', 
      price: 45.0, 
      discounted_price: 42.0, 
      tags: ['פופולרי', 'צמחוני'],
      availability: { 0: true, 1: true, 2: false } // branch_id: available
    },
    { 
      id: 2, 
      name: 'סלט יוני', 
      category: 'סלטים', 
      price: 32.0, 
      discounted_price: null, 
      tags: ['בריא', 'צמחוני'],
      availability: { 0: true, 1: false, 2: true }
    },
    { 
      id: 3, 
      name: 'המבורגר בקר', 
      category: 'המבורגרים', 
      price: 55.0, 
      discounted_price: null, 
      tags: ['בשרי', 'פופולרי'],
      availability: { 0: false, 1: true, 2: true }
    },
    { 
      id: 4, 
      name: 'פסטה ארביאטה', 
      category: 'פסטות', 
      price: 42.0, 
      discounted_price: 38.0, 
      tags: ['חריף', 'איטלקי'],
      availability: { 0: true, 1: true, 2: true }
    }
  ];

  const PriceDisplay = ({ price, discountedPrice }) => (
    <div className="text-right" style={{ fontFeatureSettings: '"tnum"', height: '40px', display: 'flex', flexDirection: 'column', justifyContent: 'center' }}>
      {discountedPrice ? (
        <div className="flex flex-col items-end leading-tight">
          <span className="text-sm text-red-600 font-semibold" dir="ltr" style={{ lineHeight: '1.2' }}>₪{discountedPrice.toFixed(2)}</span>
          <span className="text-xs text-gray-500 line-through" dir="ltr" style={{ lineHeight: '1.2' }}>₪{price.toFixed(2)}</span>
        </div>
      ) : (
        <span className="text-sm text-gray-800 font-semibold" dir="ltr">₪{price.toFixed(2)}</span>
      )}
    </div>
  );

  const TagList = ({ tags }) => (
    <div className="flex flex-wrap gap-1 justify-end">
      {tags.slice(0, 2).map((tag, index) => (
        <span key={index} className="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">
          {tag}
        </span>
      ))}
      {tags.length > 2 && (
        <span className="text-xs text-gray-500">+{tags.length - 2}</span>
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
              title={strings.create_product || 'צור מוצר חדש'}
            />
            <ActionButton 
              icon={PencilIcon} 
              variant="secondary"
              disabled={!selectedProduct}
              onClick={() => {}}
              title={strings.edit_product || 'ערוך מוצר'}
            />
            <ActionButton 
              icon={TrashIcon} 
              variant="error"
              disabled={!selectedProduct}
              onClick={() => {}}
              title={strings.delete_product || 'מחק מוצר'}
            />
          </div>
        </div>

        {/* Search Bar */}
        <div className="relative mb-4">
          <MagnifyingGlassIcon className="absolute right-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
          <input
            type="text"
            placeholder={strings.search_products || 'חפש מוצרים...'}
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full pr-10 pl-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
          />
        </div>

        {/* Table Header */}
        <div className="flex items-center justify-between pb-2 px-2">
          <div className="text-right" style={{ width: '120px' }}>
            <span className="text-sm text-gray-700 font-semibold">
              {strings.availability || 'זמינות'}
            </span>
          </div>
          <div className="text-right" style={{ width: '100px' }}>
            <span className="text-sm text-gray-700 font-semibold">
              {strings.tags || 'תגיות'}
            </span>
          </div>
          <div className="text-right" style={{ width: '80px' }}>
            <span className="text-sm text-gray-700 font-semibold">
              {strings.price || 'מחיר'}
            </span>
          </div>
          <div className="text-right" style={{ width: '100px' }}>
            <span className="text-sm text-gray-700 font-semibold">
              {strings.category || 'קטגוריה'}
            </span>
          </div>
          <div className="text-right flex-1">
            <span className="text-sm text-gray-700 font-semibold">
              {strings.product_name || 'שם המוצר'}
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
              <p>טוען מוצרים...</p>
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
            {mockProducts.map((product) => (
              <div 
                key={product.id}
                className="hover:bg-gray-50 rounded-lg px-2 transition-colors cursor-pointer flex items-center justify-between"
                style={{
                  minHeight: '60px',
                  paddingTop: '8px',
                  paddingBottom: '8px'
                }}
                onClick={() => setSelectedProduct(product.id)}
              >
                <div style={{ width: '120px' }}>
                  <AvailabilityIndicator availability={product.availability} />
                </div>
                <div className="text-right" style={{ width: '100px' }}>
                  <TagList tags={product.tags} />
                </div>
                <div style={{ width: '80px' }}>
                  <PriceDisplay price={product.price} discountedPrice={product.discounted_price} />
                </div>
                <div className="text-right" style={{ width: '100px' }}>
                  <span className="text-sm text-gray-600">{product.category}</span>
                </div>
                <div className="text-right flex-1">
                  <span className="text-sm text-gray-800 font-medium">{product.name}</span>
                </div>
                <div className="flex justify-center" style={{ width: '40px' }}>
                  <div
                    onClick={() => setSelectedProduct(product.id)}
                    className="w-4 h-4 rounded-full border-2 cursor-pointer transition-all flex items-center justify-center"
                    style={{
                      borderColor: selectedProduct === product.id ? theme.primary_color : theme.border_light,
                      backgroundColor: selectedProduct === product.id ? theme.primary_color : theme.bg_white
                    }}
                  >
                    {selectedProduct === product.id && (
                      <div className="w-1.5 h-1.5 rounded-full bg-white" />
                    )}
                  </div>
                  <input
                    type="radio"
                    name="product-selection"
                    value={product.id}
                    checked={selectedProduct === product.id}
                    onChange={() => setSelectedProduct(product.id)}
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

export default ProductsSection;