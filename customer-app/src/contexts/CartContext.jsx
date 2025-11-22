import React, { createContext, useContext, useState, useEffect } from 'react';

const CartContext = createContext();

export function useCart() {
  const context = useContext(CartContext);
  if (!context) {
    throw new Error('useCart must be used within CartProvider');
  }
  return context;
}

/**
 * CartProvider - Manages shopping cart state
 * Persists cart to sessionStorage
 */
export function CartProvider({ children }) {
  const [cart, setCart] = useState(() => {
    // Load from sessionStorage on mount
    try {
      const saved = sessionStorage.getItem('squidly_cart');
      return saved ? JSON.parse(saved) : { items: [] };
    } catch (error) {
      console.error('Failed to load cart:', error);
      return { items: [] };
    }
  });

  // Save to sessionStorage whenever cart changes
  useEffect(() => {
    try {
      sessionStorage.setItem('squidly_cart', JSON.stringify(cart));
    } catch (error) {
      console.error('Failed to save cart:', error);
    }
  }, [cart]);

  // Add item to cart
  const addToCart = (product, quantity = 1, customizations = null) => {
    setCart(prev => {
      // Generate unique key for item (includes customizations)
      const itemKey = generateItemKey(product.id, customizations);

      // Check if exact same product+customizations already in cart
      const existingIndex = prev.items.findIndex(item =>
        generateItemKey(item.product.id, item.customizations) === itemKey
      );

      if (existingIndex >= 0) {
        // Update quantity of existing customized item
        const newItems = [...prev.items];
        newItems[existingIndex].quantity += quantity;
        return { ...prev, items: newItems };
      } else {
        // Add new item with customizations
        const newItem = {
          product,
          quantity,
          customizations: customizations || {},
          final_price: product.final_price || product.discounted_price || product.price
        };
        return {
          ...prev,
          items: [...prev.items, newItem]
        };
      }
    });
  };

  // Generate unique key for cart item (product + customizations)
  const generateItemKey = (productId, customizations) => {
    if (!customizations || Object.keys(customizations).length === 0) {
      return `product_${productId}`;
    }
    // Create consistent key from customizations
    const customizationKey = Object.keys(customizations)
      .sort()
      .map(groupId => {
        const items = customizations[groupId] || [];
        const itemIds = items.map(i => i.id).sort().join(',');
        return `${groupId}:${itemIds}`;
      })
      .join('|');
    return `product_${productId}_custom_${customizationKey}`;
  };

  // Update item quantity
  const updateQuantity = (productId, quantity) => {
    if (quantity <= 0) {
      removeFromCart(productId);
      return;
    }

    setCart(prev => ({
      ...prev,
      items: prev.items.map(item =>
        item.product.id === productId
          ? { ...item, quantity }
          : item
      )
    }));
  };

  // Remove item from cart
  const removeFromCart = (productId) => {
    setCart(prev => ({
      ...prev,
      items: prev.items.filter(item => item.product.id !== productId)
    }));
  };

  // Clear entire cart
  const clearCart = () => {
    setCart({ items: [] });
  };

  // Calculate totals
  const getTotal = () => {
    return cart.items.reduce((total, item) => {
      // Use final_price if available (includes customizations), otherwise base price
      const price = item.final_price || item.product.discounted_price || item.product.price;
      return total + (price * item.quantity);
    }, 0);
  };

  const getItemCount = () => {
    return cart.items.reduce((count, item) => count + item.quantity, 0);
  };

  return (
    <CartContext.Provider
      value={{
        cart,
        addToCart,
        updateQuantity,
        removeFromCart,
        clearCart,
        getTotal,
        getItemCount
      }}
    >
      {children}
    </CartContext.Provider>
  );
}
