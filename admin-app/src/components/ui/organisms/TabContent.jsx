import React, { useState } from 'react';
import GroupSection from '../../GroupSection.jsx';
import ProductsSection from '../../ProductsSection.jsx';
import IngredientsSection from '../../IngredientsSection.jsx';

const TabContent = ({
  activeTab,
  productGroups = [],
  ingredientGroups = [],
  selectedProductGroup,
  setSelectedProductGroup,
  selectedIngredientGroup,
  setSelectedIngredientGroup,
  productSorting,
  ingredientSorting,
  strings = {},
  loading = false,
  error = null,
  branches = [],
  selectedBranchId = 0,
  onGroupChange = () => {}
}) => {
  // State for products and ingredients tabs
  const [selectedProduct, setSelectedProduct] = useState(null);
  const [selectedIngredient, setSelectedIngredient] = useState(null);
  const renderGroupsContent = () => (
    <div className="grid grid-cols-1 xl:grid-cols-2 gap-6 h-full">
      <div className="min-h-0 flex flex-col">
        <GroupSection
          title={strings.product_groups || 'קבוצות מוצרים'}
          groups={productSorting.sortedData}
          selectedGroup={selectedProductGroup}
          setSelectedGroup={setSelectedProductGroup}
          type="product"
          strings={strings}
          sortField={productSorting.sortField}
          sortDirection={productSorting.sortDirection}
          onSort={productSorting.handleSort}
          loading={loading}
          error={error}
          branches={branches}
          onGroupChange={onGroupChange}
        />
      </div>

      <div className="min-h-0 flex flex-col">
        <GroupSection
          title={strings.ingredient_groups || 'קבוצות מרכיבים'}
          groups={ingredientSorting.sortedData}
          selectedGroup={selectedIngredientGroup}
          setSelectedGroup={setSelectedIngredientGroup}
          type="ingredient"
          strings={strings}
          sortField={ingredientSorting.sortField}
          sortDirection={ingredientSorting.sortDirection}
          onSort={ingredientSorting.handleSort}
          loading={loading}
          error={error}
          branches={branches}
          onGroupChange={onGroupChange}
        />
      </div>
    </div>
  );

  const renderProductsContent = () => (
    <div className="h-full">
      <ProductsSection
        title={strings.products || 'מוצרים'}
        products={[]}
        selectedProduct={selectedProduct}
        setSelectedProduct={setSelectedProduct}
        strings={strings}
        loading={loading}
        error={error}
        branches={branches}
        selectedBranchId={selectedBranchId}
        onProductChange={() => {}}
      />
    </div>
  );

  const renderIngredientsContent = () => (
    <div className="h-full">
      <IngredientsSection
        title={strings.ingredients || 'מרכיבים'}
        ingredients={[]}
        selectedIngredient={selectedIngredient}
        setSelectedIngredient={setSelectedIngredient}
        strings={strings}
        loading={loading}
        error={error}
        branches={branches}
        selectedBranchId={selectedBranchId}
        onIngredientChange={() => {}}
      />
    </div>
  );

  const getTabContent = () => {
    const groupsTab = strings.groups || 'קבוצות';
    const productsTab = strings.products || 'מוצרים';
    const ingredientsTab = strings.ingredients || 'מרכיבים';

    switch (activeTab) {
      case groupsTab:
        return renderGroupsContent();
      case productsTab:
        return renderProductsContent();
      case ingredientsTab:
        return renderIngredientsContent();
      default:
        return renderGroupsContent();
    }
  };

  return (
    <div className="h-full">
      {getTabContent()}
    </div>
  );
};

export default TabContent;