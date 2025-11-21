/**
 * Public API Service for Customer App
 * Handles all communication with the WordPress REST API public endpoints
 */

class PublicApiService {
  constructor() {
    // API configuration from WordPress
    this.baseUrl = window.wpConfig?.publicApiUrl || '/wp-json/squidly/v1/public/';
    this.config = null;
  }

  /**
   * Initialize the API service
   * Fetches public configuration
   */
  async init() {
    try {
      // Load public configuration (no auth required)
      this.config = await this.fetch('config');
      return this.config;
    } catch (error) {
      console.error('Failed to initialize public API:', error);
      throw error;
    }
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
    return await this.fetch(`products?${params}`, { includePaginationHeaders });
  }

  /**
   * Get single product by ID
   */
  async getProduct(id) {
    return await this.fetch(`products/${id}`);
  }

  /**
   * Get all product categories
   */
  async getCategories() {
    return await this.fetch('products/categories');
  }

  /**
   * Check product availability at branch
   * @param {number} branchId
   * @param {Array} productIds
   */
  async checkAvailability(branchId, productIds) {
    const params = new URLSearchParams({
      branch_id: branchId,
      product_ids: productIds.join(',')
    });
    return await this.fetch(`availability?${params}`);
  }

  // ===== Branches API =====

  /**
   * Get all branches
   * @param {Object} filters - { city, city_like, kosher_type, etc. }
   */
  async getBranches(filters = {}) {
    const params = new URLSearchParams(filters);
    return await this.fetch(`branches${params.toString() ? '?' + params : ''}`);
  }

  /**
   * Get single branch by ID
   */
  async getBranch(id) {
    return await this.fetch(`branches/${id}`);
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
   * Create a new order
   * @param {Object} orderData - Order details
   */
  async createOrder(orderData) {
    return await this.fetch('orders', {
      method: 'POST',
      body: JSON.stringify(orderData),
    });
  }

  /**
   * Get order status
   * @param {number} orderId
   * @param {string} token - Tracking token
   */
  async getOrderStatus(orderId, token) {
    return await this.fetch(`orders/${orderId}/status?token=${token}`);
  }

  // ===== Cart Session API (if implemented server-side) =====

  /**
   * Create cart session
   */
  async createCart() {
    return await this.fetch('cart', { method: 'POST' });
  }

  /**
   * Get cart by token
   */
  async getCart(token) {
    return await this.fetch(`cart/${token}`);
  }

  /**
   * Update cart items
   */
  async updateCart(token, items) {
    return await this.fetch(`cart/${token}`, {
      method: 'PUT',
      body: JSON.stringify({ items }),
    });
  }

  /**
   * Clear cart
   */
  async clearCart(token) {
    return await this.fetch(`cart/${token}`, { method: 'DELETE' });
  }

  // ===== Delivery Fee API =====

  /**
   * Calculate delivery fee
   * @param {number} branchId
   * @param {string} address
   */
  async getDeliveryFee(branchId, address) {
    const params = new URLSearchParams({ branch_id: branchId, address });
    return await this.fetch(`delivery-fee?${params}`);
  }

  // ===== Payment API =====

  /**
   * Start payment process
   * @param {number} orderId
   */
  async startPayment(orderId) {
    return await this.fetch(`../pay/start`, {
      method: 'POST',
      body: JSON.stringify({ order_id: orderId }),
    });
  }
}

// Create and export singleton instance
const publicApi = new PublicApiService();
export default publicApi;
