import React, { useState, useEffect } from 'react';
import theme from '../../config/theme';
import MenuSidebar from './MenuSidebar';
import HeroBanner from './HeroBanner';
import ProductGrid from './ProductGrid';
import CartPanel from './CartPanel';
import publicApi from '../../services/publicApi';

/**
 * MenuLayout - Main 3-column layout for menu page
 * Layout: [Cart Panel] [Main Content] [Navigation Sidebar]
 * RTL: Navigation on right, Cart on left
 */
export default function MenuLayout({ branchId, onCheckout }) {
  const [products, setProducts] = useState([]);
  const [categories, setCategories] = useState([]);
  const [activeCategory, setActiveCategory] = useState(null);
  const [loading, setLoading] = useState(true);
  const [cart, setCart] = useState({ items: [], subtotal: 0, deliveryFee: 25.0, tax: 0 });
  const [searchQuery, setSearchQuery] = useState('');

  // Load products and categories
  useEffect(() => {
    loadMenuData();
  }, [branchId]);

  const loadMenuData = async () => {
    try {
      setLoading(true);
      console.log('🔄 Loading menu data for branch:', branchId);

      // Fetch products for this branch
      const filters = branchId ? { branch_id: branchId } : {};
      console.log('📦 Fetching products with filters:', filters);

      const productsData = await publicApi.getProducts(filters);
      console.log('✅ Products loaded:', productsData);

      setProducts(productsData);

      // Extract unique categories from products
      const uniqueCategories = [];
      const categoryMap = new Map();

      productsData.forEach((product) => {
        const categoryId = product.category_id || 'uncategorized';
        const categoryName = product.category_name || 'ללא קטגוריה';

        if (!categoryMap.has(categoryId)) {
          categoryMap.set(categoryId, {
            id: categoryId,
            name: categoryName,
            icon: getCategoryIcon(categoryName),
          });
        }
      });

      const cats = Array.from(categoryMap.values());
      console.log('📂 Categories extracted:', cats);

      setCategories(cats);
      if (cats.length > 0) {
        setActiveCategory(cats[0].id);
      }

      setLoading(false);
    } catch (error) {
      console.error('❌ Failed to load menu data:', error);
      setLoading(false);
    }
  };

  // Get icon for category
  const getCategoryIcon = (categoryName) => {
    const icons = {
      'מנה ראשונה': '🥗',
      'מנה עיקרית': '🍖',
      'תוספות': '🍟',
      'שתיה': '🥤',
      'קינוחים': '🍰',
    };
    return icons[categoryName] || '📋';
  };

  // Filter products by category and search
  const filteredProducts = products.filter((product) => {
    const productCategoryId = product.category_id || 'uncategorized';
    const matchesCategory = !activeCategory || productCategoryId === activeCategory;
    const matchesSearch = !searchQuery || product.name.includes(searchQuery);
    return matchesCategory && matchesSearch;
  });

  console.log('🔍 Filtered products:', {
    total: products.length,
    filtered: filteredProducts.length,
    activeCategory,
    searchQuery,
  });

  // Handle add to cart
  const handleAddToCart = (product) => {
    setCart((prevCart) => {
      const existingItem = prevCart.items.find((item) => item.id === product.id);

      let newItems;
      if (existingItem) {
        newItems = prevCart.items.map((item) =>
          item.id === product.id ? { ...item, quantity: item.quantity + 1 } : item
        );
      } else {
        newItems = [...prevCart.items, { ...product, quantity: 1 }];
      }

      const subtotal = newItems.reduce((sum, item) => sum + item.price * item.quantity, 0);
      const tax = subtotal * 0.17; // 17% VAT

      return {
        ...prevCart,
        items: newItems,
        subtotal,
        tax,
      };
    });
  };

  // Handle clear cart
  const handleClearCart = () => {
    setCart({ items: [], subtotal: 0, deliveryFee: 25.0, tax: 0 });
  };

  return (
    <div
      style={{
        height: '100vh',
        padding: theme.spacing.md,
        direction: 'rtl',
        overflow: 'hidden',
      }}
    >
      <div
        style={{
          height: '100%',
          display: 'grid',
          gridTemplateColumns: `${theme.layout.sidebarWidth} 1fr ${theme.layout.cartPanelWidth}`,
          gap: '20px',
        }}
      >
        {/* Right Sidebar - Navigation */}
        <MenuSidebar
          activeCategory={activeCategory}
          onCategoryChange={setActiveCategory}
          categories={categories}
        />

        {/* Center - Main Content */}
        <div
          style={{
            display: 'flex',
            flexDirection: 'column',
            height: '100%',
            overflow: 'hidden',
          }}
        >
          {/* Hero Banner - Static */}
          <div style={{ flexShrink: 0 }}>
            <HeroBanner />
          </div>

          {/* Our Menu Header with Search and Filter - Static */}
          <div
            style={{
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center',
              marginTop: theme.spacing.sm,
              marginBottom: theme.spacing.md,
              flexShrink: 0,
            }}
          >
            {/* Our Menu Label - Right Side */}
            <h2
              style={{
                fontSize: '1.5rem',
                fontWeight: 'bold',
                color: theme.colors.text.primary,
                margin: 0,
              }}
            >
              התפריט שלנו
            </h2>

            {/* Search and Filter Buttons - Left Side */}
            <div
              style={{
                display: 'flex',
                gap: theme.spacing.sm,
                alignItems: 'center',
              }}
            >
              <button
                style={{
                  width: '40px',
                  height: '40px',
                  backgroundColor: theme.colors.cardBg,
                  border: 'none',
                  borderRadius: theme.borderRadius.md,
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  cursor: 'pointer',
                  boxShadow: theme.shadows.sm,
                }}
              >
                <svg
                  width="20"
                  height="20"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke={theme.colors.text.secondary}
                  strokeWidth="2"
                >
                  <circle cx="11" cy="11" r="8" />
                  <path d="M21 21l-4.35-4.35" />
                </svg>
              </button>
              <button
                style={{
                  width: '40px',
                  height: '40px',
                backgroundColor: theme.colors.cardBg,
                border: 'none',
                borderRadius: theme.borderRadius.md,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                cursor: 'pointer',
                boxShadow: theme.shadows.sm,
              }}
            >
              <svg
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke={theme.colors.text.secondary}
                strokeWidth="2"
              >
                <path d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
              </svg>
            </button>
            </div>
          </div>

          {/* Product Grid - Scrollable */}
          <div
            style={{
              flex: 1,
              overflowY: 'auto',
              paddingBottom: theme.spacing.md,
            }}
          >
            <ProductGrid
              products={filteredProducts}
              onAddToCart={handleAddToCart}
              loading={loading}
            />
          </div>
        </div>

        {/* Left Sidebar - Cart Panel */}
        <CartPanel cart={cart} onCheckout={onCheckout} onClearCart={handleClearCart} />
      </div>
    </div>
  );
}
