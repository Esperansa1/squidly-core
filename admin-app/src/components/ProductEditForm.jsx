/**
 * ProductEditForm Component
 * 
 * Hebrew RTL product editing form with collapsible sections
 * Features: image upload, general details, product attributes, group management
 */

import React, { useState } from 'react';
import { ChevronDownIcon, ChevronUpIcon, PlusIcon } from '@heroicons/react/24/outline';
import { Button } from './ui';
import { DEFAULT_THEME } from '../config/theme.js';

const ProductEditForm = ({ 
  initialData = null,
  onSave = () => {},
  onCancel = () => {},
  categories = [],
  groups = [],
  strings = {},
  isInModal = false
}) => {
  const theme = DEFAULT_THEME;

  // Form state
  const [formData, setFormData] = useState({
    name: initialData?.name || '',
    description: initialData?.description || '',
    price: initialData?.price || '',
    discountPrice: initialData?.discountPrice || '',
    categoryId: initialData?.categoryId || '',
    newCategory: '',
    useNewCategory: false,
    selectedGroups: initialData?.groups || [],
    image: initialData?.image || null
  });

  // Section collapse state
  const [collapsedSections, setCollapsedSections] = useState({
    attributes: true,
    groups: true
  });

  // Toggle section collapse
  const toggleSection = (section) => {
    setCollapsedSections(prev => ({
      ...prev,
      [section]: !prev[section]
    }));
  };

  // Handle form input changes
  const updateFormData = (field, value) => {
    setFormData(prev => ({
      ...prev,
      [field]: value
    }));
  };

  // Handle group addition
  const addGroup = (groupId) => {
    const group = groups.find(g => g.id === parseInt(groupId));
    if (group && !formData.selectedGroups.find(g => g.id === group.id)) {
      updateFormData('selectedGroups', [...formData.selectedGroups, group]);
    }
  };

  // Remove group
  const removeGroup = (groupId) => {
    updateFormData('selectedGroups', 
      formData.selectedGroups.filter(g => g.id !== groupId)
    );
  };

  // Handle image upload
  const handleImageUpload = (event) => {
    const file = event.target.files[0];
    if (file) {
      // Create preview URL
      const imageUrl = URL.createObjectURL(file);
      updateFormData('image', { file, preview: imageUrl });
    }
  };

  return (
    <div 
      className={isInModal ? "" : "min-h-screen flex items-center justify-center p-4"}
      style={{ 
        backgroundColor: isInModal ? 'transparent' : theme.bg_gray_50,
        fontFamily: 'LiaDiplomat, sans-serif',
        direction: 'rtl'
      }}
    >
      <div 
        className={`w-full ${isInModal ? '' : 'max-w-4xl bg-white rounded-lg shadow-lg p-8'}`}
        style={{ 
          backgroundColor: isInModal ? 'transparent' : theme.bg_white,
          padding: isInModal ? '0' : undefined
        }}
      >
        {/* Header - Only show when not in modal */}
        {!isInModal && (
          <div className="text-center mb-8">
            <h1 
              className="text-2xl font-bold"
              style={{ color: theme.text_primary }}
            >
              עריכת מוצר
            </h1>
          </div>
        )}

        {/* General Details Section */}
        <div className="mb-8">
          <h2 
            className="text-lg font-semibold mb-6 text-right"
            style={{ color: theme.text_primary }}
          >
            פרטים כלליים
          </h2>
          
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {/* Product Image - Left Side */}
            <div className="lg:col-span-1">
              <label className="block text-sm font-medium mb-2 text-right" style={{ color: theme.text_primary }}>
                תמונת המוצר
              </label>
              <div 
                className="relative border-2 border-dashed rounded-lg cursor-pointer transition-colors hover:border-gray-400"
                style={{ 
                  borderColor: theme.border_color,
                  backgroundColor: theme.bg_gray_50,
                  width: '280px', // Square dimensions
                  height: '280px' // Height to span all 4 text fields (Product Name through Price after discount)
                }}
              >
                {formData.image ? (
                  <img
                    src={formData.image.preview || formData.image}
                    alt="תמונת מוצר"
                    className="w-full h-full object-cover rounded-lg"
                  />
                ) : (
                  <div className="flex items-center justify-center h-full">
                    <div className="text-center">
                      <div 
                        className="w-12 h-12 mx-auto mb-2 rounded-lg flex items-center justify-center"
                        style={{ backgroundColor: theme.border_color }}
                      >
                        <PlusIcon className="w-6 h-6" style={{ color: theme.text_secondary }} />
                      </div>
                      <p className="text-sm" style={{ color: theme.text_secondary }}>
                        העלה תמונה
                      </p>
                    </div>
                  </div>
                )}
                <input
                  type="file"
                  accept="image/*"
                  onChange={handleImageUpload}
                  className="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                />
              </div>
            </div>

            {/* Product Details - Right Side */}
            <div className="lg:col-span-2 space-y-4">
              {/* Product Name */}
              <div>
                <label className="block text-sm font-medium mb-2 text-right" style={{ color: theme.text_primary }}>
                  שם המוצר *
                </label>
                <input
                  type="text"
                  value={formData.name}
                  onChange={(e) => updateFormData('name', e.target.value)}
                  className="w-full px-3 py-2 border rounded-lg text-right focus:outline-none focus:ring-2 transition-colors"
                  style={{ 
                    borderColor: theme.border_color,
                    backgroundColor: theme.bg_white,
                    focusBorderColor: theme.primary_color
                  }}
                  placeholder="הכנס שם המוצר"
                />
              </div>

              {/* Product Description */}
              <div>
                <label className="block text-sm font-medium mb-2 text-right" style={{ color: theme.text_primary }}>
                  תיאור המוצר
                </label>
                <input
                  type="text"
                  value={formData.description}
                  onChange={(e) => updateFormData('description', e.target.value)}
                  className="w-full px-3 py-2 border rounded-lg text-right focus:outline-none focus:ring-2 transition-colors"
                  style={{ 
                    borderColor: theme.border_color,
                    backgroundColor: theme.bg_white
                  }}
                  placeholder="הכנס תיאור המוצר"
                />
              </div>

              {/* Regular Price - Now first */}
              <div>
                <label className="block text-sm font-medium mb-2 text-right" style={{ color: theme.text_primary }}>
                  מחיר *
                </label>
                <input
                  type="number"
                  value={formData.price}
                  onChange={(e) => updateFormData('price', e.target.value)}
                  className="w-full px-3 py-2 border rounded-lg text-right focus:outline-none focus:ring-2 transition-colors"
                  style={{ 
                    borderColor: theme.border_color,
                    backgroundColor: theme.bg_white
                  }}
                  placeholder="0.00"
                  min="0"
                  step="0.01"
                />
              </div>

              {/* Discount Price - Now last */}
              <div>
                <label className="block text-sm font-medium mb-2 text-right" style={{ color: theme.text_primary }}>
                  מחיר אחרי הנחה (במידה ויש)
                </label>
                <input
                  type="number"
                  value={formData.discountPrice}
                  onChange={(e) => updateFormData('discountPrice', e.target.value)}
                  className="w-full px-3 py-2 border rounded-lg text-right focus:outline-none focus:ring-2 transition-colors"
                  style={{ 
                    borderColor: theme.border_color,
                    backgroundColor: theme.bg_white
                  }}
                  placeholder="0.00"
                  min="0"
                  step="0.01"
                />
              </div>
            </div>
          </div>
        </div>

        {/* Product Attributes Section - Collapsible */}
        <div className="mb-8">
          <button
            onClick={() => toggleSection('attributes')}
            className="w-full flex items-center justify-between p-4 border rounded-lg mb-4 transition-colors hover:bg-gray-50"
            style={{ 
              borderColor: theme.border_color,
              backgroundColor: theme.bg_white
            }}
          >
            <span className="text-lg font-medium" style={{ color: theme.text_primary }}>
              מאפייני מוצר
            </span>
            <div className="flex items-center gap-2">
              {collapsedSections.attributes ? (
                <ChevronDownIcon className="w-5 h-5" style={{ color: theme.text_secondary }} />
              ) : (
                <ChevronUpIcon className="w-5 h-5" style={{ color: theme.text_secondary }} />
              )}
            </div>
          </button>

          {/* Collapsible Content */}
          <div 
            className={`transition-all duration-300 overflow-hidden ${
              collapsedSections.attributes ? 'max-h-0' : 'max-h-screen'
            }`}
          >
            <div className="space-y-4 p-4 border rounded-lg" style={{ borderColor: theme.border_color }}>
              {/* Category Selection */}
              <div>
                <label className="block text-sm font-medium mb-2 text-right" style={{ color: theme.text_primary }}>
                  רשימת קטגוריות
                </label>
                <select
                  value={formData.useNewCategory ? '' : formData.categoryId}
                  onChange={(e) => {
                    updateFormData('categoryId', e.target.value);
                    updateFormData('useNewCategory', false);
                  }}
                  disabled={formData.useNewCategory}
                  className="w-full px-3 py-2 border rounded-lg text-right focus:outline-none focus:ring-2 transition-colors"
                  style={{ 
                    borderColor: theme.border_color,
                    backgroundColor: formData.useNewCategory ? theme.bg_gray_50 : theme.bg_white,
                    opacity: formData.useNewCategory ? 0.6 : 1
                  }}
                >
                  <option value="">בחר קטגוריה</option>
                  {categories.map(cat => (
                    <option key={cat.id} value={cat.id}>{cat.name}</option>
                  ))}
                </select>
              </div>

              {/* New Category Option */}
              <div className="space-y-2">
                <label className="flex items-center gap-2 cursor-pointer justify-end">
                  <span className="text-sm" style={{ color: theme.text_primary }}>קטגוריה חדשה</span>
                  <input
                    type="radio"
                    name="categoryOption"
                    checked={formData.useNewCategory}
                    onChange={(e) => updateFormData('useNewCategory', e.target.checked)}
                    className="w-4 h-4"
                    style={{ accentColor: theme.primary_color }}
                  />
                </label>
                
                {formData.useNewCategory && (
                  <input
                    type="text"
                    value={formData.newCategory}
                    onChange={(e) => updateFormData('newCategory', e.target.value)}
                    className="w-full px-3 py-2 border rounded-lg text-right focus:outline-none focus:ring-2 transition-colors"
                    style={{ 
                      borderColor: theme.border_color,
                      backgroundColor: theme.bg_white
                    }}
                    placeholder="הכנס שם קטגוריה חדשה"
                  />
                )}
              </div>
            </div>
          </div>
        </div>

        {/* Product Groups Section - Collapsible */}
        <div className="mb-8">
          <button
            onClick={() => toggleSection('groups')}
            className="w-full flex items-center justify-between p-4 border rounded-lg mb-4 transition-colors hover:bg-gray-50"
            style={{ 
              borderColor: theme.border_color,
              backgroundColor: theme.bg_white
            }}
          >
            <span className="text-lg font-medium" style={{ color: theme.text_primary }}>
              הוספת קבוצות למוצר
            </span>
            <div className="flex items-center gap-2">
              {collapsedSections.groups ? (
                <ChevronDownIcon className="w-5 h-5" style={{ color: theme.text_secondary }} />
              ) : (
                <ChevronUpIcon className="w-5 h-5" style={{ color: theme.text_secondary }} />
              )}
            </div>
          </button>

          {/* Collapsible Content */}
          <div 
            className={`transition-all duration-300 overflow-hidden ${
              collapsedSections.groups ? 'max-h-0' : 'max-h-screen'
            }`}
          >
            <div className="space-y-4 p-4 border rounded-lg" style={{ borderColor: theme.border_color }}>
              {/* Add Group Controls */}
              <div className="flex gap-2 justify-end">
                <Button
                  variant="primary"
                  size="sm"
                  onClick={() => {
                    const selectElement = document.getElementById('groupSelect');
                    if (selectElement.value) {
                      addGroup(selectElement.value);
                      selectElement.value = '';
                    }
                  }}
                >
                  הוספה
                </Button>
                <select
                  id="groupSelect"
                  className="px-3 py-2 border rounded-lg text-right focus:outline-none focus:ring-2 transition-colors"
                  style={{ 
                    borderColor: theme.border_color,
                    backgroundColor: theme.bg_white,
                    minWidth: '200px'
                  }}
                >
                  <option value="">קבוצות קיימות</option>
                  {groups.filter(group => 
                    !formData.selectedGroups.find(sg => sg.id === group.id)
                  ).map(group => (
                    <option key={group.id} value={group.id}>{group.name}</option>
                  ))}
                </select>
              </div>

              {/* Selected Groups Table */}
              {formData.selectedGroups.length > 0 && (
                <div className="border rounded-lg overflow-hidden" style={{ borderColor: theme.border_color }}>
                  <div className="grid grid-cols-3 gap-4 p-3 font-medium text-right bg-gray-50">
                    <div>פעולות</div>
                    <div>סטטוס הקבוצה</div>
                    <div>שם הקבוצה</div>
                  </div>
                  {formData.selectedGroups.map(group => (
                    <div key={group.id} className="grid grid-cols-3 gap-4 p-3 border-t" style={{ borderColor: theme.border_color }}>
                      <div className="flex justify-start">
                        <button
                          onClick={() => removeGroup(group.id)}
                          className="text-red-600 hover:text-red-800 text-sm"
                        >
                          הסר
                        </button>
                      </div>
                      <div className="flex items-center gap-2 justify-end">
                        <span className="text-sm" style={{ color: theme.text_secondary }}>פעילה</span>
                        <div 
                          className="w-2 h-2 rounded-full"
                          style={{ backgroundColor: theme.success_color }}
                        />
                      </div>
                      <div className="text-right">{group.name}</div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Action Buttons */}
        <div className="flex gap-4 justify-end">
          <Button
            variant="outline"
            onClick={onCancel}
          >
            חזור ללא שמירה
          </Button>
          <Button
            variant="primary"
            onClick={() => onSave(formData)}
          >
            שמור את ההגדרות
          </Button>
        </div>
      </div>

      {/* Custom CSS for smooth animations and focus styles */}
      <style jsx>{`
        input:focus, select:focus {
          outline: none;
          border-color: ${theme.primary_color} !important;
          box-shadow: 0 0 0 2px ${theme.primary_color}20 !important;
        }
        
        .transition-all {
          transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        button:focus {
          outline: 2px solid ${theme.primary_color};
          outline-offset: 2px;
        }
        
        @media (max-width: 1024px) {
          .grid-cols-3 {
            grid-template-columns: 1fr;
          }
          
          .lg\\:col-span-1, .lg\\:col-span-2 {
            grid-column: span 1;
          }
        }
      `}</style>
    </div>
  );
};

export default ProductEditForm;