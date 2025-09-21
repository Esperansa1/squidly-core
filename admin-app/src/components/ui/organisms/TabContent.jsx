import React, { useState } from 'react';
import IngredientsSection from '../../IngredientsSection.jsx';
import ProductSection from '../../ProductSection.jsx';

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
    <div className="flex items-center justify-center h-full text-gray-500">
      <div className="text-center">
        <div className="text-6xl mb-4">🏗️</div>
        <p className="text-lg">תוכן הקבוצות יתווסף בקרוב</p>
      </div>
    </div>
  );

  const renderProductsContent = () => (
    <div className="h-full">
      <ProductSection
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
        productGroups={productGroups}
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