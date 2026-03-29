/**
 * Public API Service for Customer App
 * Handles all communication with the WordPress REST API public endpoints
 */

// Simple in-memory TTL cache to avoid redundant GET requests within a session
class ApiCache {
  constructor() {
    this._store = new Map();
  }

  get(key) {
    const entry = this._store.get(key);
    if (!entry) return null;
    if (Date.now() > entry.expiresAt) {
      this._store.delete(key);
      return null;
    }
    return entry.value;
  }

  set(key, value, ttlMs) {
    this._store.set(key, { value, expiresAt: Date.now() + ttlMs });
  }

  invalidate(prefix) {
    for (const key of this._store.keys()) {
      if (key.startsWith(prefix)) this._store.delete(key);
    }
  }
}

const TTL = {
  BRANCHES:  5 * 60 * 1000,  // 5 min — branches rarely change
  PRODUCTS:  2 * 60 * 1000,  // 2 min — products may change
  CUSTOMIZE: 5 * 60 * 1000,  // 5 min — product groups rarely change
};

class PublicApiService {
  constructor() {
    // API configuration from WordPress
    this.baseUrl = window.wpConfig?.publicApiUrl || '/wp-json/squidly/v1/public/';
    this.config = null;
    this._initPromise = null;
    this._cache = new ApiCache();
  }

  /**
   * Initialize the API service
   * Fetches public configuration (deduplicated — safe to call multiple times)
   */
  async init() {
    // Return existing promise if already initializing/initialized
    if (this._initPromise) {
      return this._initPromise;
    }

    this._initPromise = (async () => {
      try {
        this.config = await this.fetch('config');
        return this.config;
      } catch (error) {
        this._initPromise = null; // Allow retry on failure
        console.error('Failed to initialize public API:', error);
        throw error;
      }
    })();

    return this._initPromise;
  }

  /**
   * Generic fetch wrapper with error handling
   */
  async fetch(endpoint, options = {}) {
    const url = `${this.baseUrl}${endpoint}`;
    const config = {
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        ...options.headers,
      },
      ...options,
    };

    try {
      const response = await fetch(url, config);

      if (!response.ok) {
        const error = await response.json().catch(() => ({ message: 'Request failed' }));
        throw new Error(error.message || `HTTP ${response.status}`);
      }

      // Handle pagination headers if requested
      if (options.includePaginationHeaders) {
        const data = await response.json();
        return {
          data,
          total: parseInt(response.headers.get('X-WP-Total') || '0'),
          totalPages: parseInt(response.headers.get('X-WP-TotalPages') || '0'),
        };
      }

      return await response.json();
    } catch (error) {
      console.error(`API Error [${endpoint}]:`, error);
      throw error;
    }
  }

  // ===== Products API =====

  /**
   * Get products with filtering
   * @param {Object} filters - { branch_id, category, search, per_page, offset }
   * @param {boolean} includePaginationHeaders - Include pagination info
   */
  async getProducts(filters = {}, includePaginationHeaders = false) {
    const params = new URLSearchParams(filters);
    const cacheKey = `products?${params}`;
    if (!includePaginationHeaders) {
      const cached = this._cache.get(cacheKey);
      if (cached) return cached;
    }
    const result = await this.fetch(`products?${params}`, { includePaginationHeaders });
    if (!includePaginationHeaders) {
      this._cache.set(cacheKey, result, TTL.PRODUCTS);
    }
    return result;
  }

  /**
   * Get single product by ID
   */
  async getProduct(id) {
    const cacheKey = `products/${id}`;
    const cached = this._cache.get(cacheKey);
    if (cached) return cached;
    const result = await this.fetch(cacheKey);
    this._cache.set(cacheKey, result, TTL.PRODUCTS);
    return result;
  }

  /**
   * Get product with groups for customization
   * @param {number} id Product ID
   */
  async getProductWithGroups(id) {
    const cacheKey = `products/${id}/customize`;
    const cached = this._cache.get(cacheKey);
    if (cached) return cached;
    const result = await this.fetch(cacheKey);
    this._cache.set(cacheKey, result, TTL.CUSTOMIZE);
    return result;
  }

  // ===== Branches API =====

  /**
   * Get all branches
   * @param {Object} filters - { city, city_like, kosher_type, etc. }
   */
  async getBranches(filters = {}) {
    const params = new URLSearchParams(filters);
    const cacheKey = `branches${params.toString() ? '?' + params : ''}`;
    const cached = this._cache.get(cacheKey);
    if (cached) return cached;
    const result = await this.fetch(cacheKey);
    this._cache.set(cacheKey, result, TTL.BRANCHES);
    return result;
  }

  // ===== Guest Customers API =====

  /**
   * Create a guest customer
   * @param {Object} data - { first_name, last_name, email, phone }
   */
  async createGuestCustomer(data) {
    return await this.fetch('guest-customer', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  // ===== Orders API =====

  /**
   * Get order status
   * @param {number} orderId
   * @param {string} token - Tracking token
   */
  async getOrderStatus(orderId, token) {
    return await this.fetch(`orders/${orderId}/status?token=${token}`);
  }

  // ===== Cart Session API =====

  /**
   * Create cart session with initial item
   * @param {number} branchId - Branch ID
   * @param {number} productId - Product ID
   * @param {number} quantity - Quantity (default 1)
   * @param {Object} customizations - Customization object { groupId: [{ id, name, price }, ...], ... }
   * @param {string} notes - Optional notes
   * @param {number} customerId - Optional customer ID
   * @returns {Object} { cart, message } - Cart object with token
   */
  async createCartSession(branchId, productId, quantity = 1, customizations = {}, notes = '', customerId = null) {
    const body = {
      branch_id: branchId,
      product_id: productId,
      quantity,
      customizations,
    };

    if (notes) body.notes = notes;
    if (customerId) body.customer_id = customerId;

    return await this.fetch('cart', {
      method: 'POST',
      body: JSON.stringify(body),
    });
  }

  /**
   * Add item to existing cart session
   * @param {string} cartToken - Cart token
   * @param {number} productId - Product ID
   * @param {number} quantity - Quantity
   * @param {number} branchId - Branch ID (required)
   * @param {Object} customizations - Customization object { groupId: [{ id, name, price }, ...], ... }
   * @param {string} notes - Optional notes
   * @returns {Object} { cart, message }
   */
  async addToCart(cartToken, productId, quantity, branchId, customizations = {}, notes = '') {
    const body = {
      token: cartToken,
      branch_id: branchId,
      product_id: productId,
      quantity,
      customizations,
    };

    if (notes) body.notes = notes;

    return await this.fetch('cart', {
      method: 'POST',
      body: JSON.stringify(body),
    });
  }

  /**
   * Get cart by token
   */
  async getCart(token) {
    return await this.fetch(`cart/${token}`);
  }

  /**
   * Update specific cart item
   * @param {string} cartToken - Cart token
   * @param {string} itemId - Cart item ID
   * @param {number} quantity - New quantity
   * @param {string} notes - Updated notes
   * @returns {Object} { cart, message }
   */
  async updateCartItem(cartToken, itemId, quantity, notes = '') {
    const body = { quantity };
    if (notes) body.notes = notes;

    return await this.fetch(`cart/${cartToken}/item/${itemId}`, {
      method: 'PUT',
      body: JSON.stringify(body),
    });
  }

  /**
   * Remove specific cart item
   * @param {string} cartToken - Cart token
   * @param {string} itemId - Cart item ID
   * @returns {Object} { cart, message }
   */
  async removeCartItem(cartToken, itemId) {
    return await this.fetch(`cart/${cartToken}/item/${itemId}`, {
      method: 'DELETE',
    });
  }

  /**
   * Clear all cart items
   */
  async clearCart(token) {
    return await this.fetch(`cart/${token}`, { method: 'DELETE' });
  }

  /**
   * Checkout cart session - convert to order
   * @param {string} cartToken - Cart token
   * @param {Object} checkoutData - Checkout details
   * @param {number} checkoutData.customer_id - Customer ID
   * @param {string} checkoutData.delivery_type - 'pickup' or 'delivery'
   * @param {string} checkoutData.delivery_address - Delivery address (if delivery)
   * @param {string} checkoutData.delivery_time - Scheduled delivery time
   * @param {string} checkoutData.payment_method - Payment method
   * @param {number} checkoutData.delivery_fee - Calculated delivery fee
   * @param {string} checkoutData.notes - Special instructions
   * @returns {Object} { order_id, tracking_token, total_price, payment_url }
   */
  async checkoutCart(cartToken, checkoutData) {
    return await this.fetch(`cart/${cartToken}/checkout`, {
      method: 'POST',
      body: JSON.stringify(checkoutData),
    });
  }

  // ===== Delivery Fee API =====

  /**
   * Calculate delivery fee
   * @param {number} branchId - Branch ID
   * @param {string} address - Delivery address
   * @param {number} subtotal - Order subtotal (for free delivery threshold check)
   * @returns {Object} { delivery_fee, is_deliverable, free_delivery_threshold, is_free_delivery }
   */
  async getDeliveryFee(branchId, address, subtotal = 0) {
    const params = new URLSearchParams({
      branch_id: branchId,
      address,
      subtotal: subtotal.toString()
    });
    return await this.fetch(`delivery-fee?${params}`);
  }

}

// Create and export singleton instance
const publicApi = new PublicApiService();
export default publicApi;
