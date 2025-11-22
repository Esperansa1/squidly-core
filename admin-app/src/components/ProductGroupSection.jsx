import React, { useMemo } from 'react';
import { DataSection, AvailabilityDisplay } from './ui';
import ProductGroupModal from './ui/ProductGroupModal.jsx';
import api from '../services/api.js';

const ProductGroupSection = ({
  title = 'קבוצות',
  productGroups = [],
  selectedProductGroup,
  setSelectedProductGroup,
  strings = {},
  loading: externalLoading = false,
  error: externalError = null,
  branches = [],
  selectedBranchId = 0,
  onProductGroupChange = () => {}
}) => {

  // Define columns for product groups (both ingredient and product types)
  const columns = useMemo(() => [
    {
      key: 'name',
      label: strings.group_name || 'שם הקבוצה',
      width: '180px',
      render: (name) => (
        <span className="text-sm text-gray-800 font-medium w-full text-center" title={name}>
          {name}
        </span>
      )
    },
    {
      key: 'description',
      label: strings.description || 'תיאור',
      width: '200px',
      render: (description, item) => {
        if (!description) {
          return <span className="text-gray-400 italic text-sm">אין תיאור</span>;
        }
        const truncated = description.length > 40
          ? description.substring(0, 40) + '...'
          : description;
        return <span className="text-sm text-gray-700" title={description}>{truncated}</span>;
      }
    },
    {
      key: 'type',
      label: strings.type || 'סוג',
      width: '100px',
      render: (type) => {
        const isIngredient = type === 'ingredient';
        return (
          <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${
            isIngredient
              ? 'bg-green-100 text-green-800'
              : 'bg-blue-100 text-blue-800'
          }`}>
            {isIngredient ? 'מרכיב' : 'מוצר'}
          </span>
        );
      }
    },
    {
      key: 'group_items',
      label: strings.group_items || 'פריטי קבוצה',
      width: '120px',
      cellStyle: {
        textAlign: 'center'
      },
      render: (_, item) => {
        if (!item.group_item_ids || item.group_item_ids.length === 0) {
          return <span className="text-gray-400 italic text-sm">ללא פריטים</span>;
        }

        // For now, show count - we'll need to resolve actual names via API
        const count = item.group_item_ids.length;
        return (
          <span className="text-sm text-gray-700">
            {count} {count === 1 ? 'פריט' : 'פריטים'}
          </span>
        );
      }
    },
    {
      key: 'selections',
      label: strings.selection_range || 'טווח בחירה',
      width: '120px',
      cellStyle: {
        textAlign: 'center'
      },
      render: (_, item) => {
        const min = item.min_selections || 0;
        const max = item.max_selections || 0;

        // Build readable text
        let text = '';
        if (min === 0 && max === 0) {
          text = 'אופציונלי, ללא הגבלה';
        } else if (min === 0 && max > 0) {
          text = `עד ${max} בחירות`;
        } else if (min > 0 && max === 0) {
          text = `לפחות ${min} בחירות`;
        } else if (min === max) {
          text = `בדיוק ${min} ${min === 1 ? 'בחירה' : 'בחירות'}`;
        } else {
          text = `${min}-${max} בחירות`;
        }

        return (
          <span className="text-sm text-gray-700" title={`מינימום: ${min}, מקסימום: ${max === 0 ? 'ללא הגבלה' : max}`}>
            {text}
          </span>
        );
      }
    },
    {
      key: 'final_availability',
      label: strings.availability || 'זמינות',
      width: '180px',
      cellStyle: {
        whiteSpace: 'normal',
        overflow: 'visible',
        textOverflow: 'initial',
        minWidth: '180px'
      },
      render: (_, item) => (
        <AvailabilityDisplay
          availability={item.final_availability || item.availability}
          selectedBranchId={selectedBranchId}
          branches={branches}
          strings={strings}
        />
      )
    }
  ], [strings, branches, selectedBranchId]);

  return (
    <div className="h-full">
      <DataSection
        title={title}
        data={productGroups}
        selectedItem={selectedProductGroup}
        setSelectedItem={setSelectedProductGroup}
        strings={{
          ...strings,
          create: strings.create_product_group || 'צור קבוצה חדשה',
          edit: strings.edit_product_group || 'ערוך קבוצה',
          delete: strings.delete_product_group || 'מחק קבוצה',
          search_placeholder: strings.search_product_groups || 'חפש קבוצות...',
          no_items: strings.no_product_groups || 'אין קבוצות להצגה',
          delete_title: 'מחיקת קבוצה',
          delete_message_prefix: 'האם אתה בטוח שברצונך למחוק את הקבוצה',
          delete_message_suffix: 'פעולה זו תמחק את הקבוצה ולא ניתן לבטלה.',
          delete_confirm: 'כן, מחק',
          cancel: 'ביטול'
        }}
        loading={externalLoading}
        error={externalError}
        branches={branches}
        selectedBranchId={selectedBranchId}
        onItemChange={onProductGroupChange}
        columns={columns}
        apiService={{
          getAll: () => api.getAllGroups(),
          create: (data) => {
            // Route to correct API based on type
            return data.type === 'ingredient'
              ? api.createIngredientGroup(data)
              : api.createProductGroup(data);
          },
          update: (id, data) => {
            // Route to correct API based on type
            return data.type === 'ingredient'
              ? api.updateIngredientGroup(id, data)
              : api.updateProductGroup(id, data);
          },
          delete: (id, itemData = null) => {
            if (!id) {
              throw new Error('No item selected for deletion');
            }

            // Use the provided itemData if available (from DataSection),
            // otherwise fallback to finding in productGroups prop
            const item = itemData || productGroups.find(group => group.id === id);
            if (!item) {
              throw new Error('Item not found in current data');
            }

            // Route to correct API based on type
            return item.type === 'ingredient'
              ? api.deleteIngredientGroup(id)
              : api.deleteProductGroup(id);
          }
        }}
        Modal={ProductGroupModal}
        editingItemProp="group"
        itemIdProp="id"
        itemNameProp="name"
      />
    </div>
  );
};

export default ProductGroupSection;