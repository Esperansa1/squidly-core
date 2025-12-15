import React, { useState, useEffect } from 'react';
import theme from '../../config/theme';
import MenuSidebar from './MenuSidebar';
import HeroBanner from './HeroBanner';
import ProductGrid from './ProductGrid';
import CartPanel from './CartPanel';
import ProductCustomizationModal from '../products/ProductCustomizationModal';
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
  const [customizingProduct, setCustomizingProduct] = useState(null);
  const [sidebarExpanded, setSidebarExpanded] = useState(true);

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
      const categoryMap = new Map();

      productsData.forEach((product) => {
        // Use the category field from the Product model
        const category = product.category || 'ללא קטגוריה';

        if (!categoryMap.has(category)) {
          categoryMap.set(category, {
            id: category, // Use category string as both id and name
            name: category,
            icon: getCategoryIcon(category),
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
    const productCategory = product.category || 'ללא קטגוריה';
    const matchesCategory = !activeCategory || productCategory === activeCategory;
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
    // Check if product has customization groups
    if (product.product_group_ids && product.product_group_ids.length > 0) {
      // Show customization modal
      setCustomizingProduct(product);
    } else {
      // Add directly to cart (no customization)
      addProductToCart(product, 1, null, '');
    }
  };

  // Add product to cart with customizations
  const addProductToCart = (product, quantity = 1, customizations = null, specialInstructions = '') => {
    setCart((prevCart) => {
      const existingItem = prevCart.items.find((item) => item.id === product.id);

      let newItems;
      if (existingItem && !customizations) {
        // Increment quantity for existing item without customizations
        newItems = prevCart.items.map((item) =>
          item.id === product.id ? { ...item, quantity: item.quantity + quantity } : item
        );
      } else {
        // Add new item (with or without customizations)
        newItems = [
          ...prevCart.items,
          {
            ...product,
            quantity,
            customizations,
            specialInstructions,
          },
        ];
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

  // Handle customization confirmation
  const handleCustomizationConfirm = (customizedProduct) => {
    addProductToCart(
      customizedProduct,
      1,
      customizedProduct.customizations,
      customizedProduct.specialInstructions || ''
    );
    setCustomizingProduct(null);
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
          gridTemplateColumns: `${sidebarExpanded ? '280px' : '70px'} 1fr ${theme.layout.cartPanelWidth}`,
          gap: '20px',
          transition: 'grid-template-columns 0.3s ease-out',
        }}
      >
        {/* Right Sidebar - Navigation */}
        <MenuSidebar
          activeCategory={activeCategory}
          onCategoryChange={setActiveCategory}
          categories={categories}
          isExpanded={sidebarExpanded}
          onToggle={setSidebarExpanded}
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
          <div style={{ flexShrink: 0, marginBottom: theme.spacing.md }}>
            <HeroBanner />
          </div>

          {/* Our Menu Header with Search and Filter - Static */}
          <div
            style={{
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center',
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
              scrollbarWidth: 'none', // Firefox
              msOverflowStyle: 'none', // IE and Edge
            }}
            className="hide-scrollbar"
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

      {/* Product Customization Modal */}
      <ProductCustomizationModal
        product={customizingProduct}
        isOpen={!!customizingProduct}
        onClose={() => setCustomizingProduct(null)}
        onConfirm={handleCustomizationConfirm}
      />
    </div>
  );
}
