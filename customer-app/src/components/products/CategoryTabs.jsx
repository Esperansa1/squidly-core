import React from 'react';

/**
 * CategoryTabs - Basic tab navigation for product categories
 */
export default function CategoryTabs({ categories, activeCategory, onCategoryChange }) {
  if (!categories || categories.length === 0) {
    return null;
  }

  return (
    <div className="border-b mb-6">
      <div className="flex gap-4 overflow-x-auto">
        {/* All Products Tab */}
        <button
          onClick={() => onCategoryChange(null)}
          className={`px-4 py-2 border-b-2 whitespace-nowrap ${
            activeCategory === null
              ? 'border-blue-600 text-blue-600 font-bold'
              : 'border-transparent text-gray-600'
          }`}
        >
          All
        </button>

        {/* Category Tabs */}
        {categories.map((category) => (
          <button
            key={category.id}
            onClick={() => onCategoryChange(category.id)}
            className={`px-4 py-2 border-b-2 whitespace-nowrap ${
              activeCategory === category.id
                ? 'border-blue-600 text-blue-600 font-bold'
                : 'border-transparent text-gray-600'
            }`}
          >
            {category.name}
          </button>
        ))}
      </div>
    </div>
  );
}
