import React from 'react';
import ProductCard from './ProductCard';
import { t } from '../../i18n/translations';

/**
 * ProductGrid - Simple grid layout for products
 */
export default function ProductGrid({ products, onAddToCart, loading }) {
  if (loading) {
    return <div className="text-center py-8">{t('loading')}</div>;
  }

  if (!products || products.length === 0) {
    return <div className="text-center py-8 text-gray-500">{t('noProducts')}</div>;
  }

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      {products.map((product) => (
        <ProductCard
          key={product.id}
          product={product}
          onAddToCart={onAddToCart}
        />
      ))}
    </div>
  );
}
