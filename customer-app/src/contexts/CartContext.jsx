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
 * Uses optimistic updates for instant UI feedback, syncs with backend in background.
 */
export function CartProvider({ children }) {
  const [cartToken, setCartToken] = useState(() => {
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
   * Load cart from backend (only operation that shows loading state)
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
      setCartToken(null);
      setCart({ items: [] });
    } finally {
      setLoading(false);
    }
  };

  /**
   * Add item to cart — optimistic update with background sync
   * Supports both regular products (has .id) and edited cart items (has .product_id)
   */
  const addToCart = async (product, quantity = 1, customizations = null, notes = '', branchId = null) => {
    const branch = branchId || product.branch_id;
    if (!branch) {
      throw new Error('Branch ID is required to add items to cart');
    }

    // Resolve the real product ID — cart items use product_id, products use id
    const productId = product.product_id || product.id;

    const customizationsObject = (customizations && typeof customizations === 'object' && !Array.isArray(customizations))
      ? customizations
      : {};

    // Calculate unit price including customization modifiers
    // final_price is set by the customization modal and already includes add-ons
    const basePrice = product.discounted_price || product.price || 0;
    const unitPrice = product.final_price || basePrice;

    // Build optimistic cart item using backend field names
    const optimisticItem = {
      id: 'temp_' + Date.now(),
      product_id: productId,
      product_name: product.product_name || product.name,
      quantity,
      unit_price: unitPrice,
      total_price: unitPrice * quantity,
      customizations: customizationsObject,
      notes: notes || null,
      image_url: product.image_url || null,
    };

    // Snapshot for rollback
    const previousCart = cart;

    // Optimistic: show item immediately
    setCart(prev => ({
      ...prev,
      items: [...(prev.items || []), optimisticItem],
    }));
    setError(null);

    try {
      let response;

      if (!cartToken) {
        response = await publicApi.createCartSession(
          branch,
          productId,
          quantity,
          customizationsObject,
          notes
        );

        if (response.cart && response.cart.token) {
          setCartToken(response.cart.token);
        }
      } else {
        response = await publicApi.addToCart(
          cartToken,
          productId,
          quantity,
          branch,
          customizationsObject,
          notes
        );
      }

      // Sync with server state (corrects prices, IDs, etc.)
      if (response.cart) {
        setCart(response.cart);
      }

      return response;
    } catch (err) {
      console.error('Failed to add to cart:', err);
      setCart(previousCart);
      setError(err.message || 'Failed to add item to cart');
      throw err;
    }
  };

  /**
   * Update item quantity — optimistic update with background sync
   */
  const updateQuantity = async (itemId, quantity) => {
    if (!cartToken) return;

    if (quantity <= 0) {
      return removeFromCart(itemId);
    }

    const previousCart = cart;

    // Optimistic: update quantity and recalculate total immediately
    setCart(prev => ({
      ...prev,
      items: (prev.items || []).map(item =>
        item.id === itemId
          ? { ...item, quantity, total_price: (item.unit_price || 0) * quantity }
          : item
      ),
    }));
    setError(null);

    try {
      const response = await publicApi.updateCartItem(cartToken, itemId, quantity);

      if (response.cart) {
        setCart(response.cart);
      }

      return response;
    } catch (err) {
      console.error('Failed to update quantity:', err);
      setCart(previousCart);
      setError(err.message || 'Failed to update quantity');
      throw err;
    }
  };

  /**
   * Remove item from cart — optimistic update with background sync
   */
  const removeFromCart = async (itemId) => {
    if (!cartToken) return;

    const previousCart = cart;

    // Optimistic: remove item immediately
    setCart(prev => ({
      ...prev,
      items: (prev.items || []).filter(item => item.id !== itemId),
    }));
    setError(null);

    try {
      const response = await publicApi.removeCartItem(cartToken, itemId);

      if (response.cart) {
        setCart(response.cart);
      }

      return response;
    } catch (err) {
      console.error('Failed to remove item:', err);
      setCart(previousCart);
      setError(err.message || 'Failed to remove item');
      throw err;
    }
  };

  /**
   * Clear entire cart — optimistic update with background sync
   */
  const clearCart = async () => {
    const previousCart = cart;
    const previousToken = cartToken;

    // Optimistic: clear immediately
    setCart({ items: [] });
    setCartToken(null);

    if (!previousToken) return;

    try {
      setError(null);
      await publicApi.clearCart(previousToken);
    } catch (err) {
      console.error('Failed to clear cart:', err);
      setCart(previousCart);
      setCartToken(previousToken);
      setError(err.message || 'Failed to clear cart');
      throw err;
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
