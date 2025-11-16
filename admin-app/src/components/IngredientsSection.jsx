import React, { useMemo } from 'react';
import { DataSection, PriceDisplay, AvailabilityDisplay, IngredientModal } from './ui';
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

  // Define columns based on Ingredient model - RTL order: Name → Price → Availability
  const columns = useMemo(() => [
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
      render: (price) => <PriceDisplay price={price} strings={strings} />
    },
    {
      key: 'availability',
      label: strings.availability || 'זמינות',
      width: 'auto',
      maxWidth: '280px',
      cellStyle: {
        whiteSpace: 'normal',
        overflow: 'visible',
        textOverflow: 'initial'
      },
      render: (_, item) => (
        <AvailabilityDisplay
          availability={item.availability}
          selectedBranchId={selectedBranchId}
          branches={branches}
          strings={strings}
        />
      )
    }
  ], [strings, selectedBranchId, branches]);

  return (
    <div className="h-full">
      <DataSection
        title={title}
        data={ingredients}
        selectedItem={selectedIngredient}
        setSelectedItem={setSelectedIngredient}
        strings={{
          ...strings,
          create: strings.create_ingredient || 'צור מרכיב חדש',
          edit: strings.edit_ingredient || 'ערוך מרכיב',
          delete: strings.delete_ingredient || 'מחק מרכיב',
          search_placeholder: strings.search_ingredients || 'חפש מרכיבים...',
          no_items: strings.no_ingredients || 'אין מרכיבים להצגה',
          delete_title: 'מחיקת מרכיב',
          delete_message_prefix: 'האם אתה בטוח שברצונך למחוק את המרכיב',
          delete_message_suffix: 'פעולה זו תמחק את המרכיב מכל הסניפים ולא ניתן לבטלה.',
          delete_confirm: 'כן, מחק',
          cancel: 'ביטול'
        }}
        loading={externalLoading}
        error={externalError}
        branches={branches}
        selectedBranchId={selectedBranchId}
        onItemChange={onIngredientChange}
        columns={columns}
        apiService={api.ingredients || {
          getAll: () => api.getIngredients(),
          create: (data) => api.createIngredient(data),
          update: (id, data) => api.updateIngredient(id, data),
          delete: (id) => api.deleteIngredient(id)
        }}
        Modal={IngredientModal}
        editingItemProp="ingredient"
        itemIdProp="id"
        itemNameProp="name"
      />
    </div>
  );
};

export default IngredientsSection;