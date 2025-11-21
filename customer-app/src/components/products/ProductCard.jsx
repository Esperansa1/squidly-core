import React from 'react';
import { t } from '../../i18n/translations';

/**
 * ProductCard - Minimal product display
 * Shows product info and add to cart button
 */
export default function ProductCard({ product, onAddToCart }) {
  const {
    id,
    name,
    description,
    price,
    discounted_price,
    image_url
  } = product;

  const displayPrice = discounted_price || price;
  const hasDiscount = discounted_price && discounted_price < price;

  return (
    <div className="border p-4 bg-white">
      {/* Product Image */}
      {image_url && (
        <img src={image_url} alt={name} className="w-full h-48 object-cover mb-3" />
      )}

      {/* Product Name */}
      <h3 className="font-bold text-lg mb-2">{name}</h3>

      {/* Description */}
      {description && (
        <p className="text-sm text-gray-600 mb-3">{description}</p>
      )}

      {/* Price */}
      <div className="mb-3">
        {hasDiscount && (
          <span className="text-gray-400 line-through mr-2">₪{price}</span>
        )}
        <span className="font-bold text-lg">₪{displayPrice}</span>
      </div>

      {/* Add to Cart Button */}
      <button
        onClick={() => onAddToCart(product)}
        className="w-full bg-blue-600 text-white px-4 py-2 hover:bg-blue-700"
      >
        {t('addToCart')}
      </button>
    </div>
  );
}
