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
  const addToCart = (product, quantity = 1) => {
    setCart(prev => {
      // Check if product already in cart
      const existingIndex = prev.items.findIndex(item => item.product.id === product.id);

      if (existingIndex >= 0) {
        // Update quantity
        const newItems = [...prev.items];
        newItems[existingIndex].quantity += quantity;
        return { ...prev, items: newItems };
      } else {
        // Add new item
        return {
          ...prev,
          items: [...prev.items, { product, quantity }]
        };
      }
    });
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
      const price = item.product.discounted_price || item.product.price;
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
