/**
 * API Service for Squidly Admin
 * 
 * Handles all communication with the WordPress REST API backend
 */

import { DEFAULT_THEME } from '../config/theme.js';

class ApiService {
  constructor() {
    // Use wpConfig provided by WordPress template
    if (window.wpConfig) {
      this.baseUrl = window.wpConfig.apiUrl;
      this.nonce = window.wpConfig.nonce;
    } else {
      // Fallback to detecting from current domain
      const currentDomain = window.location.origin;
      const wpPath = window.location.pathname.includes('/wp-admin') ? 
        window.location.pathname.split('/wp-admin')[0] : '';
      
      this.baseUrl = `${currentDomain}${wpPath}/wp-json/squidly/v1/`;
      this.nonce = null;
    }
    this.config = null;
  }

  /**
   * Initialize API service and check authentication
   */
  async init() {
    try {
      
      // Check authentication and get config
      const authResponse = await this.fetch('auth/check');
      
      if (!authResponse.authenticated || !authResponse.authorized) {
        throw new Error('Not authenticated');
      }

      // Get admin configuration
      this.config = await this.fetch('admin/config');
      
      // Update nonce from config if needed
      if (this.config.api && this.config.api.nonce && !this.nonce) {
        this.nonce = this.config.api.nonce;
      }
      
      return this.config;
    } catch (error) {
      console.error('API initialization failed:', error);
      throw error;
    }
  }

  /**
   * Base fetch method with authentication
   */
  async fetch(endpoint, options = {}) {
    let url = `${this.baseUrl}${endpoint}`;

    // Add query parameters if provided
    if (options.params) {
      const params = new URLSearchParams();
      Object.entries(options.params).forEach(([key, value]) => {
        if (value !== null && value !== undefined) {
          params.append(key, value);
        }
      });
      const queryString = params.toString();
      if (queryString) {
        url += `?${queryString}`;
      }
    }

    const defaultHeaders = {
      'Content-Type': 'application/json',
    };

    if (this.nonce) {
      defaultHeaders['X-WP-Nonce'] = this.nonce;
    }

    const config = {
      credentials: 'include', // Important for WordPress auth
      headers: {
        ...defaultHeaders,
        ...options.headers,
      },
      ...options,
    };

    // Remove params from config to avoid issues
    delete config.params;

    try {
      const response = await fetch(url, config);

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
      }

      const data = await response.json();

      // If caller wants pagination headers, return them along with data
      if (options.includePaginationHeaders) {
        return {
          data,
          total: parseInt(response.headers.get('X-WP-Total') || '0', 10),
          totalPages: parseInt(response.headers.get('X-WP-TotalPages') || '1', 10),
        };
      }

      return data;
    } catch (error) {
      console.error(`API request failed: ${endpoint}`, error);
      throw error;
    }
  }

  // ===== BRANCHES API =====
  
  async getBranches(filters = {}, includePaginationHeaders = false) {
    const queryParams = new URLSearchParams(filters).toString();
    const endpoint = queryParams ? `branches?${queryParams}` : 'branches';
    return await this.fetch(endpoint, { includePaginationHeaders });
  }

  async getBranch(id) {
    return await this.fetch(`branches/${id}`);
  }

  async createBranch(data) {
    return await this.fetch('branches', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateBranch(id, data) {
    return await this.fetch(`branches/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteBranch(id) {
    return await this.fetch(`branches/${id}`, {
      method: 'DELETE',
    });
  }

  // Branch service wrapper for compatibility with DataSection
  getBranchService() {
    return {
      getAll: (filters = {}, includePaginationHeaders = false) => this.getBranches(filters, includePaginationHeaders),
      get: (id) => this.getBranch(id),
      create: (data) => this.createBranch(data),
      update: (id, data) => this.updateBranch(id, data),
      delete: (id) => this.deleteBranch(id)
    };
  }

  // ===== PRODUCT GROUPS API =====
  
  async getProductGroups(filters = {}, includePaginationHeaders = false) {
    // Add item_type filter to get only product groups
    const productFilters = { ...filters, item_type: 'product' };
    const queryParams = new URLSearchParams(productFilters).toString();
    const endpoint = queryParams ? `product-groups?${queryParams}` : 'product-groups?item_type=product';
    return await this.fetch(endpoint, { includePaginationHeaders });
  }

  async getProductGroup(id) {
    return await this.fetch(`product-groups/${id}`);
  }

  async createProductGroup(data) {
    return await this.fetch('product-groups', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateProductGroup(id, data) {
    return await this.fetch(`product-groups/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteProductGroup(id) {
    return await this.fetch(`product-groups/${id}`, {
      method: 'DELETE',
    });
  }

  // ===== INGREDIENTS API =====
  
  async getIngredients(filters = {}, includePaginationHeaders = false) {
    const queryParams = new URLSearchParams(filters).toString();
    const endpoint = queryParams ? `ingredients?${queryParams}` : 'ingredients';
    return await this.fetch(endpoint, { includePaginationHeaders });
  }

  async getIngredient(id) {
    return await this.fetch(`ingredients/${id}`);
  }

  async createIngredient(data) {
    return await this.fetch('ingredients', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateIngredient(id, data) {
    return await this.fetch(`ingredients/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteIngredient(id) {
    return await this.fetch(`ingredients/${id}`, {
      method: 'DELETE',
    });
  }

  // ===== PRODUCTS API =====

  async getProducts(filters = {}, includePaginationHeaders = false) {
    const queryParams = new URLSearchParams(filters).toString();
    const endpoint = queryParams ? `products?${queryParams}` : 'products';
    return await this.fetch(endpoint, { includePaginationHeaders });
  }

  async getProduct(id) {
    return await this.fetch(`products/${id}`);
  }

  async createProduct(data) {
    return await this.fetch('products', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateProduct(id, data) {
    return await this.fetch(`products/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteProduct(id) {
    return await this.fetch(`products/${id}`, {
      method: 'DELETE',
    });
  }

  // ===== INGREDIENT GROUPS API =====

  async getIngredientGroups(filters = {}, includePaginationHeaders = false) {
    const queryParams = new URLSearchParams(filters).toString();
    const endpoint = queryParams ? `ingredient-groups?${queryParams}` : 'ingredient-groups';
    return await this.fetch(endpoint, { includePaginationHeaders });
  }

  async getIngredientGroup(id) {
    return await this.fetch(`ingredient-groups/${id}`);
  }

  async createIngredientGroup(data) {
    return await this.fetch('ingredient-groups', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateIngredientGroup(id, data) {
    return await this.fetch(`ingredient-groups/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteIngredientGroup(id) {
    return await this.fetch(`ingredient-groups/${id}`, {
      method: 'DELETE',
    });
  }

  // Get all groups (both ingredient and product groups combined)
  async getAllGroups(filters = {}) {
    try {
      // Get only product groups by using the filtered endpoint
      const allProductGroupsResponse = await this.getProductGroups(filters);
      const ingredientGroupsResponse = await this.getIngredientGroups(filters);

      // APIs now consistently return data directly
      const combinedData = [...allProductGroupsResponse, ...ingredientGroupsResponse];

      return combinedData;
    } catch (error) {
      return [];
    }
  }

  // ===== CUSTOMERS API =====

  async getCustomers(filters = {}, includePaginationHeaders = false) {
    const queryParams = new URLSearchParams(filters).toString();
    const endpoint = queryParams ? `customers?${queryParams}` : 'customers';
    return await this.fetch(endpoint, { includePaginationHeaders });
  }

  async getCustomer(id) {
    return await this.fetch(`customers/${id}`);
  }

  async createCustomer(data) {
    return await this.fetch('customers', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateCustomer(id, data) {
    return await this.fetch(`customers/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteCustomer(id) {
    return await this.fetch(`customers/${id}`, {
      method: 'DELETE',
    });
  }

  // ===== ORDERS API =====

  async getOrders(filters = {}, includePaginationHeaders = false) {
    const queryParams = new URLSearchParams(filters).toString();
    const endpoint = queryParams ? `orders?${queryParams}` : 'orders';
    return await this.fetch(endpoint, { includePaginationHeaders });
  }

  async getOrder(id) {
    return await this.fetch(`orders/${id}`);
  }

  async updateOrder(id, data) {
    return await this.fetch(`orders/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteOrder(id) {
    return await this.fetch(`orders/${id}`, {
      method: 'DELETE',
    });
  }

  async getOrderStatistics(filters = {}) {
    const queryParams = new URLSearchParams(filters).toString();
    const endpoint = queryParams ? `orders/statistics?${queryParams}` : 'orders/statistics';
    return await this.fetch(endpoint);
  }

  // ===== ADMIN USERS API =====

  async getAdminUsers(filters = {}, includePaginationHeaders = false) {
    const queryParams = new URLSearchParams(filters).toString();
    const endpoint = queryParams ? `admin-users?${queryParams}` : 'admin-users';
    return await this.fetch(endpoint, { includePaginationHeaders });
  }

  async getAdminUser(id) {
    return await this.fetch(`admin-users/${id}`);
  }

  async createAdminUser(data) {
    return await this.fetch('admin-users', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateAdminUser(id, data) {
    return await this.fetch(`admin-users/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deleteAdminUser(id) {
    return await this.fetch(`admin-users/${id}`, {
      method: 'DELETE',
    });
  }

  async getCurrentUser() {
    return await this.fetch('admin-users/me');
  }

  async updateCurrentUser(data) {
    return await this.fetch('admin-users/me', {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async uploadAvatar(userId, formData) {
    const endpoint = userId === 'me'
      ? 'admin-users/me/avatar'
      : `admin-users/${userId}/avatar`;

    return await this.fetch(endpoint, {
      method: 'POST',
      headers: { 'X-WP-Nonce': this.nonce },
      body: formData,
    });
  }

  // ===== SYSTEM SETTINGS =====

  async getAdminConfig() {
    return await this.fetch('admin/config');
  }

  async updateSystemSettings(settings) {
    return await this.fetch('admin/settings', {
      method: 'POST',
      body: JSON.stringify(settings),
    });
  }

  // ===== UTILITY METHODS =====

  getConfig() {
    return this.config;
  }

  getTheme() {
    return this.config?.theme || DEFAULT_THEME;
  }

  getStrings() {
    return this.config?.strings || {};
  }
}

// Create singleton instance
const api = new ApiService();

export default api;