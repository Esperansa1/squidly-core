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
    const url = `${this.baseUrl}${endpoint}`;
    
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

    try {
      const response = await fetch(url, config);
      
      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
      }

      return await response.json();
    } catch (error) {
      console.error(`API request failed: ${endpoint}`, error);
      throw error;
    }
  }

  // ===== BRANCHES API =====
  
  async getBranches() {
    return await this.fetch('branches');
  }

  async getBranch(id) {
    return await this.fetch(`branches/${id}`);
  }

  // ===== PRODUCT GROUPS API =====
  
  async getProductGroups(filters = {}) {
    // Add item_type filter to get only product groups
    const productFilters = { ...filters, item_type: 'product' };
    const queryParams = new URLSearchParams(productFilters).toString();
    const endpoint = queryParams ? `product-groups?${queryParams}` : 'product-groups?item_type=product';
    return await this.fetch(endpoint);
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

  // ===== INGREDIENT GROUPS API =====
  
  async getIngredientGroups(filters = {}) {
    // Add item_type filter to get only ingredient groups, using product-groups endpoint
    const ingredientFilters = { ...filters, item_type: 'ingredient' };
    const queryParams = new URLSearchParams(ingredientFilters).toString();
    const endpoint = queryParams ? `product-groups?${queryParams}` : 'product-groups?item_type=ingredient';
    return await this.fetch(endpoint);
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