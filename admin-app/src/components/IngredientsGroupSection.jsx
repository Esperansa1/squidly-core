import React, { useMemo } from 'react';
import { DataSection } from './ui';
import IngredientsGroupModal from './ui/IngredientsGroupModal.jsx';
import api from '../services/api.js';

const IngredientsGroupSection = ({
  title = 'קבוצות',
  ingredientGroups = [],
  selectedIngredientGroup,
  setSelectedIngredientGroup,
  strings = {},
  loading: externalLoading = false,
  error: externalError = null,
  onIngredientGroupChange = () => {}
}) => {

  // Define columns for ingredient groups
  const columns = useMemo(() => [
    {
      key: 'name',
      label: strings.group_name || 'שם הקבוצה',
      width: '200px',
      render: (name) => (
        <span className="text-sm text-gray-800 font-medium w-full text-center" title={name}>
          {name}
        </span>
      )
    },
    {
      key: 'description',
      label: strings.description || 'תיאור',
      width: '300px',
      render: (description, item) => {
        if (!description) {
          return <span className="text-gray-400 italic text-sm">אין תיאור</span>;
        }
        const truncated = description.length > 50
          ? description.substring(0, 50) + '...'
          : description;
        return <span className="text-sm text-gray-700" title={description}>{truncated}</span>;
      }
    },
    {
      key: 'group_item_ids',
      label: strings.item_count || 'כמות פריטים',
      width: '120px',
      render: (group_item_ids) => (
        <span className="inline-flex items-center justify-center px-2 py-1 text-xs font-medium text-blue-800 bg-blue-100 rounded-full">
          {Array.isArray(group_item_ids) ? group_item_ids.length : 0}
        </span>
      )
    }
  ], [strings]);

  return (
    <DataSection
      title={title}
      data={ingredientGroups}
      selectedItem={selectedIngredientGroup}
      setSelectedItem={setSelectedIngredientGroup}
      strings={{
        ...strings,
        create: strings.create_ingredient_group || 'צור קבוצת מרכיבים חדשה',
        edit: strings.edit_ingredient_group || 'ערוך קבוצת מרכיבים',
        delete: strings.delete_ingredient_group || 'מחק קבוצת מרכיבים',
        search_placeholder: strings.search_ingredient_groups || 'חפש קבוצות מרכיבים...',
        no_items: strings.no_ingredient_groups || 'אין קבוצות מרכיבים להצגה',
        delete_title: 'מחיקת קבוצת מרכיבים',
        delete_message_prefix: 'האם אתה בטוח שברצונך למחוק את קבוצת המרכיבים',
        delete_message_suffix: 'פעולה זו תמחק את הקבוצה ולא ניתן לבטלה.',
        delete_confirm: 'כן, מחק',
        cancel: 'ביטול'
      }}
      loading={externalLoading}
      error={externalError}
      onItemChange={onIngredientGroupChange}
      columns={columns}
      apiService={{
        getAll: () => api.getIngredientGroups(),
        create: (data) => api.createIngredientGroup(data),
        update: (id, data) => api.updateIngredientGroup(id, data),
        delete: (id) => api.deleteIngredientGroup(id)
      }}
      Modal={IngredientsGroupModal}
      editingItemProp="ingredientGroup"
      itemIdProp="id"
      itemNameProp="name"
    />
  );
};

export default IngredientsGroupSection;