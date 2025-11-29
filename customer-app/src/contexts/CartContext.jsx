import React, { createContext, useContext, useState, useEffect } from 'react';
import publicApi from '../services/publicApi';

const CartContext = createContext();

export function useCart() {
  const context = useContext(CartContext);
  if (!context) {
    throw new Error('useCart must be used within CartProvider');
  }
  return context;
}

/**
 * CartProvider - Manages shopping cart with backend session
 * Integrates with backend cart session API for server-side price calculation
 */
export function CartProvider({ children }) {
  const [cartToken, setCartToken] = useState(() => {
    // Load cart token from sessionStorage
    return sessionStorage.getItem('squidly_cart_token') || null;
  });

  const [cart, setCart] = useState({ items: [] });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  // Load cart from backend on mount if token exists
  useEffect(() => {
    if (cartToken) {
      loadCart();
    }
  }, [cartToken]);

  // Save cart token to sessionStorage whenever it changes
  useEffect(() => {
    if (cartToken) {
      sessionStorage.setItem('squidly_cart_token', cartToken);
    } else {
      sessionStorage.removeItem('squidly_cart_token');
    }
  }, [cartToken]);

  /**
   * Load cart from backend
   */
  const loadCart = async () => {
    if (!cartToken) return;

    try {
      setLoading(true);
      setError(null);
      const response = await publicApi.getCart(cartToken);
      setCart(response.cart || { items: [] });
    } catch (err) {
      console.error('Failed to load cart:', err);
      setError('Failed to load cart');
      // Clear invalid token
      setCartToken(null);
      setCart({ items: [] });
    } finally {
      setLoading(false);
    }
  };

  /**
   * Add item to cart
   * Creates cart session if doesn't exist, otherwise adds to existing cart
   */
  const addToCart = async (product, quantity = 1, customizations = null, notes = '', branchId = null) => {
    try {
      setLoading(true);
      setError(null);

      // Get branch ID from product or parameter
      const branch = branchId || product.branch_id;
      if (!branch) {
        throw new Error('Branch ID is required to add items to cart');
      }

      let response;

      if (!cartToken) {
        // Create new cart session with first item
        response = await publicApi.createCartSession(
          branch,
          product.id,
          quantity,
          customizations || [],
          notes
        );

        // Store cart token
        if (response.cart && response.cart.token) {
          setCartToken(response.cart.token);
        }
      } else {
        // Add to existing cart
        response = await publicApi.addToCart(
          cartToken,
          product.id,
          quantity,
          branch,
          customizations || [],
          notes
        );
      }

      // Update local cart state from backend response
      if (response.cart) {
        setCart(response.cart);
      }

      return response;
    } catch (err) {
      console.error('Failed to add to cart:', err);
      setError(err.message || 'Failed to add item to cart');
      throw err;
    } finally {
      setLoading(false);
    }
  };

  /**
   * Update item quantity
   * Uses backend cart item ID for updates
   */
  const updateQuantity = async (itemId, quantity) => {
    if (!cartToken) return;

    if (quantity <= 0) {
      return removeFromCart(itemId);
    }

    try {
      setLoading(true);
      setError(null);

      const response = await publicApi.updateCartItem(cartToken, itemId, quantity);

      if (response.cart) {
        setCart(response.cart);
      }

      return response;
    } catch (err) {
      console.error('Failed to update quantity:', err);
      setError(err.message || 'Failed to update quantity');
      throw err;
    } finally {
      setLoading(false);
    }
  };

  /**
   * Remove item from cart
   */
  const removeFromCart = async (itemId) => {
    if (!cartToken) return;

    try {
      setLoading(true);
      setError(null);

      const response = await publicApi.removeCartItem(cartToken, itemId);

      if (response.cart) {
        setCart(response.cart);
      }

      return response;
    } catch (err) {
      console.error('Failed to remove item:', err);
      setError(err.message || 'Failed to remove item');
      throw err;
    } finally {
      setLoading(false);
    }
  };

  /**
   * Clear entire cart
   */
  const clearCart = async () => {
    if (!cartToken) {
      setCart({ items: [] });
      return;
    }

    try {
      setLoading(true);
      setError(null);

      await publicApi.clearCart(cartToken);

      // Clear local state and token
      setCart({ items: [] });
      setCartToken(null);
    } catch (err) {
      console.error('Failed to clear cart:', err);
      setError(err.message || 'Failed to clear cart');
      throw err;
    } finally {
      setLoading(false);
    }
  };

  /**
   * Calculate totals from backend cart data
   * Backend calculates prices server-side (prevents manipulation)
   */
  const getTotal = () => {
    if (!cart.items || cart.items.length === 0) return 0;

    return cart.items.reduce((total, item) => {
      const price = item.final_price || item.unit_price || 0;
      const quantity = item.quantity || 1;
      return total + (price * quantity);
    }, 0);
  };

  /**
   * Get total item count
   */
  const getItemCount = () => {
    if (!cart.items || cart.items.length === 0) return 0;

    return cart.items.reduce((count, item) => count + (item.quantity || 0), 0);
  };

  /**
   * Get cart data for checkout
   */
  const getCartData = () => {
    return {
      token: cartToken,
      items: cart.items || [],
      subtotal: getTotal(),
      itemCount: getItemCount()
    };
  };

  return (
    <CartContext.Provider
      value={{
        cart,
        cartToken,
        loading,
        error,
        addToCart,
        updateQuantity,
        removeFromCart,
        clearCart,
        getTotal,
        getItemCount,
        getCartData,
        refreshCart: loadCart
      }}
    >
      {children}
    </CartContext.Provider>
  );
}
