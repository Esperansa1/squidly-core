import React from 'react';

const PriceDisplay = ({ price, strings = {} }) => (
  <div className="text-center w-full" style={{ fontFeatureSettings: '"tnum"' }}>
    {price === 0 ? (
      <span className="text-sm text-green-600 font-semibold">
        {strings.free || 'חינם'}
      </span>
    ) : (
      <span className="text-sm text-gray-800 font-semibold" dir="ltr">
        ₪{price.toFixed(2)}
      </span>
    )}
  </div>
);

export default PriceDisplay;