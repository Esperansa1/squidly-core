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

      // Fetch products for this branch
      const productsData = await publicApi.getProducts(branchId);
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
      setCategories(cats);
      if (cats.length > 0) {
        setActiveCategory(cats[0].id);
      }

      setLoading(false);
    } catch (error) {
      console.error('Failed to load menu data:', error);
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
    const matchesCategory = !activeCategory || product.category_id === activeCategory;
    const matchesSearch = !searchQuery || product.name.includes(searchQuery);
    return matchesCategory && matchesSearch;
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

  return (
    <div
      style={{
        minHeight: '100vh',
        backgroundColor: theme.colors.background,
        padding: theme.spacing.lg,
        direction: 'rtl',
      }}
    >
      <div
        style={{
          maxWidth: theme.layout.maxContentWidth,
          margin: '0 auto',
          display: 'grid',
          gridTemplateColumns: `${theme.layout.sidebarWidth} 1fr ${theme.layout.cartPanelWidth}`,
          gap: theme.spacing.lg,
        }}
      >
        {/* Right Sidebar - Navigation */}
        <MenuSidebar
          activeCategory={activeCategory}
          onCategoryChange={setActiveCategory}
          categories={categories}
        />

        {/* Center - Main Content */}
        <div style={{ display: 'flex', flexDirection: 'column', gap: theme.spacing.lg }}>
          {/* Hero Banner */}
          <HeroBanner />

          {/* Search and Filter Controls */}
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

          {/* Menu Heading */}
          <h1
            style={{
              fontSize: '2rem',
              fontWeight: 'bold',
              color: theme.colors.text.primary,
              margin: 0,
            }}
          >
            המפריט שלנו
          </h1>

          {/* Product Grid */}
          <ProductGrid
            products={filteredProducts}
            onAddToCart={handleAddToCart}
            loading={loading}
          />
        </div>

        {/* Left Sidebar - Cart Panel */}
        <CartPanel cart={cart} onCheckout={onCheckout} />
      </div>
    </div>
  );
}
