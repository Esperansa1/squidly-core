/**
 * ProductEditUI Component
 * 
 * Complete ground-up redesign following exact specifications:
 * - Two-card system (expanded/collapsed states)
 * - Hebrew RTL with LiaDiplomat font
 * - Theme-only colors from DEFAULT_THEME
 * - 12-column responsive grid system
 * - Exact field positioning and measurements
 */

import React, { useState, useMemo } from 'react';
import { ChevronDownIcon, ChevronUpIcon, PlusIcon } from '@heroicons/react/24/outline';
import { Button } from './ui';
import { DEFAULT_THEME } from '../config/theme.js';

const ProductEditUI = ({ 
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

  // Canvas state - controls which card is expanded
  const [canvasState, setCanvasState] = useState({
    expandedCard: null, // 'attributes' | 'groups' | null
    leftCard: 'general' // Always show general details on left
  });

  // Toggle section expansion
  const toggleSection = (section) => {
    setCanvasState(prev => ({
      ...prev,
      expandedCard: prev.expandedCard === section ? null : section
    }));
  };

  // Update form data
  const updateFormData = (field, value) => {
    setFormData(prev => ({
      ...prev,
      [field]: value
    }));
  };

  // Handle image upload
  const handleImageUpload = (event) => {
    const file = event.target.files[0];
    if (file) {
      const imageUrl = URL.createObjectURL(file);
      updateFormData('image', { file, preview: imageUrl });
    }
  };

  // Add group
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

  // Calculate responsive grid classes
  const getResponsiveClasses = () => {
    const isExpanded = canvasState.expandedCard !== null;
    return {
      canvas: isInModal ? "w-full" : "w-full min-h-screen",
      container: "max-w-screen-xl mx-auto px-4 py-6",
      grid: "grid grid-cols-12 gap-6",
      leftCard: `col-span-12 ${isExpanded ? 'lg:col-span-8' : 'lg:col-span-6'}`,
      rightCard: `col-span-12 ${isExpanded ? 'lg:col-span-4' : 'lg:col-span-6'}`
    };
  };

  const classes = useMemo(() => getResponsiveClasses(), [canvasState.expandedCard]);

  return (
    <div 
      className={classes.canvas}
      style={{ 
        backgroundColor: isInModal ? 'transparent' : theme.bg_gray_50,
        fontFamily: 'LiaDiplomat, sans-serif',
        direction: 'rtl'
      }}
    >
      <div className={classes.container}>
        {/* Header - Only show when not in modal */}
        {!isInModal && (
          <div className="mb-8 text-center">
            <h1 
              className="text-2xl font-bold mb-2"
              style={{ color: theme.text_primary }}
            >
              עריכת מוצר
            </h1>
            <div 
              className="w-full h-px"
              style={{ backgroundColor: theme.border_color }}
            />
          </div>
        )}

        {/* Two-Card System */}
        <div className={classes.grid}>
          
          {/* LEFT CARD - General Details + Expanded Sections */}
          <div className={classes.leftCard}>
            <div 
              className="bg-white rounded-lg shadow-sm border p-6"
              style={{ 
                backgroundColor: theme.bg_white,
                borderColor: theme.border_color
              }}
            >
              {/* General Details Section */}
              <div className="mb-8">
                <div className="flex items-center justify-between mb-6">
                  <h2 
                    className="text-lg font-semibold"
                    style={{ color: theme.text_primary }}
                  >
                    פרטים כלליים
                  </h2>
                  <div 
                    className="w-2 h-2 rounded-full"
                    style={{ backgroundColor: theme.success_color }}
                  />
                </div>

                <div className="grid grid-cols-12 gap-4">
                  {/* Product Image */}
                  <div className="col-span-12 md:col-span-4">
                    <label className="block text-sm font-medium mb-2 text-right" style={{ color: theme.text_primary }}>
                      תמונת המוצר
                    </label>
                    <div 
                      className="relative border-2 border-dashed rounded-lg cursor-pointer transition-colors hover:border-opacity-70 aspect-square"
                      style={{ 
                        borderColor: theme.border_color,
                        backgroundColor: theme.bg_gray_50
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

                  {/* Product Details */}
                  <div className="col-span-12 md:col-span-8 space-y-4">
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
                          backgroundColor: theme.bg_white
                        }}
                        placeholder="הכנס שם המוצר"
                      />
                    </div>

                    {/* Product Description */}
                    <div>
                      <label className="block text-sm font-medium mb-2 text-right" style={{ color: theme.text_primary }}>
                        תיאור המוצר
                      </label>
                      <textarea
                        value={formData.description}
                        onChange={(e) => updateFormData('description', e.target.value)}
                        rows={3}
                        className="w-full px-3 py-2 border rounded-lg text-right focus:outline-none focus:ring-2 transition-colors resize-none"
                        style={{ 
                          borderColor: theme.border_color,
                          backgroundColor: theme.bg_white
                        }}
                        placeholder="הכנס תיאור המוצר"
                      />
                    </div>

                    {/* Price Fields */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                      <div>
                        <label className="block text-sm font-medium mb-2 text-right" style={{ color: theme.text_primary }}>
                          מחיר אחרי הנחה
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
              </div>

              {/* Expanded Sections Content */}
              {canvasState.expandedCard === 'attributes' && (
                <div className="border-t pt-6" style={{ borderColor: theme.border_color }}>
                  <h3 className="text-lg font-semibold mb-4" style={{ color: theme.text_primary }}>
                    מאפייני מוצר
                  </h3>
                  <div className="space-y-4">
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
              )}

              {canvasState.expandedCard === 'groups' && (
                <div className="border-t pt-6" style={{ borderColor: theme.border_color }}>
                  <h3 className="text-lg font-semibold mb-4" style={{ color: theme.text_primary }}>
                    הוספת קבוצות למוצר
                  </h3>
                  <div className="space-y-4">
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

                    {/* Selected Groups */}
                    {formData.selectedGroups.length > 0 && (
                      <div className="border rounded-lg overflow-hidden" style={{ borderColor: theme.border_color }}>
                        <div className="grid grid-cols-3 gap-4 p-3 font-medium text-right" style={{ backgroundColor: theme.bg_gray_50 }}>
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
              )}

            </div>
          </div>

          {/* RIGHT CARD - Collapsed Sections */}
          <div className={classes.rightCard}>
            <div 
              className="bg-white rounded-lg shadow-sm border p-6 space-y-4"
              style={{ 
                backgroundColor: theme.bg_white,
                borderColor: theme.border_color
              }}
            >
              {/* Collapsed Section: Attributes */}
              <button
                onClick={() => toggleSection('attributes')}
                className={`w-full p-4 border rounded-lg transition-all hover:shadow-sm ${
                  canvasState.expandedCard === 'attributes' ? 'ring-2' : ''
                }`}
                style={{ 
                  borderColor: canvasState.expandedCard === 'attributes' ? theme.primary_color : theme.border_color,
                  backgroundColor: theme.bg_white,
                  ringColor: canvasState.expandedCard === 'attributes' ? `${theme.primary_color}20` : 'transparent'
                }}
              >
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <div 
                      className="w-2 h-2 rounded-full"
                      style={{ backgroundColor: theme.warning_color }}
                    />
                    <span className="text-sm font-medium" style={{ color: theme.text_primary }}>
                      מאפייני מוצר
                    </span>
                  </div>
                  {canvasState.expandedCard === 'attributes' ? (
                    <ChevronUpIcon className="w-4 h-4" style={{ color: theme.text_secondary }} />
                  ) : (
                    <ChevronDownIcon className="w-4 h-4" style={{ color: theme.text_secondary }} />
                  )}
                </div>
              </button>

              {/* Collapsed Section: Groups */}
              <button
                onClick={() => toggleSection('groups')}
                className={`w-full p-4 border rounded-lg transition-all hover:shadow-sm ${
                  canvasState.expandedCard === 'groups' ? 'ring-2' : ''
                }`}
                style={{ 
                  borderColor: canvasState.expandedCard === 'groups' ? theme.primary_color : theme.border_color,
                  backgroundColor: theme.bg_white,
                  ringColor: canvasState.expandedCard === 'groups' ? `${theme.primary_color}20` : 'transparent'
                }}
              >
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <div 
                      className="w-2 h-2 rounded-full"
                      style={{ backgroundColor: formData.selectedGroups.length > 0 ? theme.success_color : theme.warning_color }}
                    />
                    <span className="text-sm font-medium" style={{ color: theme.text_primary }}>
                      הוספת קבוצות למוצר ({formData.selectedGroups.length})
                    </span>
                  </div>
                  {canvasState.expandedCard === 'groups' ? (
                    <ChevronUpIcon className="w-4 h-4" style={{ color: theme.text_secondary }} />
                  ) : (
                    <ChevronDownIcon className="w-4 h-4" style={{ color: theme.text_secondary }} />
                  )}
                </div>
              </button>
            </div>
          </div>
        </div>

        {/* Action Buttons */}
        <div className="flex gap-4 justify-end mt-8">
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

      {/* Custom CSS for responsive behavior and animations */}
      <style jsx>{`
        input:focus, select:focus, textarea:focus {
          outline: none !important;
          border-color: ${theme.primary_color} !important;
          box-shadow: 0 0 0 2px ${theme.primary_color}20 !important;
        }
        
        button:focus {
          outline: 2px solid ${theme.primary_color};
          outline-offset: 2px;
        }
        
        .transition-all {
          transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* Responsive breakpoints */
        @media (max-width: 767px) {
          .grid-cols-12 > div {
            grid-column: span 12 !important;
          }
        }
        
        @media (min-width: 768px) and (max-width: 1023px) {
          .md\\:col-span-4 {
            grid-column: span 4;
          }
          .md\\:col-span-8 {
            grid-column: span 8;
          }
          .md\\:grid-cols-2 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
          }
        }
        
        @media (min-width: 1024px) {
          .lg\\:col-span-4 {
            grid-column: span 4;
          }
          .lg\\:col-span-6 {
            grid-column: span 6;
          }
          .lg\\:col-span-8 {
            grid-column: span 8;
          }
        }
      `}</style>
    </div>
  );
};

export default ProductEditUI;