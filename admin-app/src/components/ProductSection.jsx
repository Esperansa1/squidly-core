import React, { useMemo } from 'react';
import { DataSection, PriceDisplay, AvailabilityDisplay, ProductModal } from './ui';
import api from '../services/api.js';

const ProductSection = ({
  title = 'מוצרים',
  products = [],
  selectedProduct,
  setSelectedProduct,
  strings = {},
  loading: externalLoading = false,
  error: externalError = null,
  branches = [],
  selectedBranchId = 0,
  onProductChange = () => {},
  productGroups = []
}) => {

  // Utility function to truncate text
  const truncateText = (text, maxLength = 50) => {
    if (!text) return '-';
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
  };

  // Utility function to render tags or product groups
  const renderList = (items, maxVisible = 2) => {
    if (!items || items.length === 0) return '-';

    const visible = items.slice(0, maxVisible);
    const remaining = items.length - maxVisible;

    return (
      <div className="text-sm text-gray-600 text-center">
        {visible.join(', ')}
        {remaining > 0 && (
          <span className="text-gray-400"> +{remaining}</span>
        )}
      </div>
    );
  };

  // Custom price display with discount logic
  const PriceDisplayWithDiscount = ({ price, discounted_price, strings }) => {
    const hasDiscount = discounted_price && discounted_price !== price && discounted_price > 0;

    if (!hasDiscount) {
      return <PriceDisplay price={price} strings={strings} />;
    }

    return (
      <div className="text-center w-full" style={{ fontFeatureSettings: '"tnum"' }}>
        <div className="flex flex-col items-center gap-1">
          {/* Discounted price in red - shown first */}
          <span className="text-sm text-red-600 font-semibold" dir="ltr">
            ₪{discounted_price.toFixed(2)}
          </span>
          {/* Original price with strikethrough - shown below */}
          <span className="text-xs text-gray-500 line-through" dir="ltr">
            ₪{price.toFixed(2)}
          </span>
        </div>
      </div>
    );
  };

  // Define columns based on Product model - RTL order: Name → Description → Price → Category → Tags → Product Groups
  const columns = useMemo(() => [
    {
      key: 'name',
      label: strings.product_name || 'שם המוצר',
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
      cellStyle: {
        whiteSpace: 'normal',
        overflow: 'visible',
        textOverflow: 'initial'
      },
      render: (description) => (
        <span
          className="text-sm text-gray-600 w-full text-center block leading-relaxed"
          title={description}
        >
          {truncateText(description, 60)}
        </span>
      )
    },
    {
      key: 'price',
      label: strings.price || 'מחיר',
      width: '120px',
      render: (price, item) => (
        <PriceDisplayWithDiscount
          price={price}
          discounted_price={item.discounted_price}
          strings={strings}
        />
      )
    },
    {
      key: 'category',
      label: strings.category || 'קטגוריה',
      width: '120px',
      render: (category) => (
        <span className="text-sm text-gray-600 w-full text-center" title={category}>
          {category || '-'}
        </span>
      )
    },
    {
      key: 'tags',
      label: strings.tags || 'תגיות',
      width: '140px',
      cellStyle: {
        whiteSpace: 'normal',
        overflow: 'visible',
        textOverflow: 'initial'
      },
      render: (tags) => renderList(tags, 2)
    },
    {
      key: 'product_group_ids',
      label: strings.product_groups || 'קבוצות מוצרים',
      width: '150px',
      cellStyle: {
        whiteSpace: 'normal',
        overflow: 'visible',
        textOverflow: 'initial'
      },
      render: (product_group_ids) => {
        if (!product_group_ids || product_group_ids.length === 0) return '-';

        // Map group IDs to actual group names
        const groupNames = product_group_ids
          .map(id => {
            const group = productGroups.find(group => group.id === id);
            if (!group) {
              console.warn(`Product group with ID ${id} not found in productGroups:`, productGroups);
              return null; // Don't show unknown groups
            }
            return group.name;
          })
          .filter(name => name); // Remove any null/undefined names

        if (groupNames.length === 0) {
          return '-';
        }

        return renderList(groupNames, 2);
      }
    }
  ], [strings, productGroups]);

  return (
    <DataSection
      title={title}
      data={products}
      selectedItem={selectedProduct}
      setSelectedItem={setSelectedProduct}
      strings={{
        ...strings,
        create: strings.create_product || 'צור מוצר חדש',
        edit: strings.edit_product || 'ערוך מוצר',
        delete: strings.delete_product || 'מחק מוצר',
        search_placeholder: strings.search_products || 'חפש מוצרים...',
        no_items: strings.no_products || 'אין מוצרים להצגה',
        delete_title: 'מחיקת מוצר',
        delete_message_prefix: 'האם אתה בטוח שברצונך למחוק את המוצר',
        delete_message_suffix: 'פעולה זו תמחק את המוצר מכל הסניפים ולא ניתן לבטלה.',
        delete_confirm: 'כן, מחק',
        cancel: 'ביטול'
      }}
      loading={externalLoading}
      error={externalError}
      branches={branches}
      selectedBranchId={selectedBranchId}
      onItemChange={onProductChange}
      columns={columns}
      apiService={api.products || {
        getAll: () => api.getProducts(),
        create: (data) => api.createProduct(data),
        update: (id, data) => api.updateProduct(id, data),
        delete: (id) => api.deleteProduct(id)
      }}
      Modal={ProductModal}
      editingItemProp="product"
      itemIdProp="id"
      itemNameProp="name"
      productGroups={productGroups}
    />
  );
};

export default ProductSection;