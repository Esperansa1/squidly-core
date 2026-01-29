import React, { useState, useEffect, useMemo } from 'react';
import theme from '../../config/theme';
import MenuSidebar from './MenuSidebar';
import HeroBanner from './HeroBanner';
import ProductGrid from './ProductGrid';
import CartPanel from './CartPanel';
import ProductCustomizationModal from '../products/ProductCustomizationModal';
import publicApi from '../../services/publicApi';
import { useIsMobile, useIsTablet, useIsDesktop } from '../../hooks/useMediaQuery';
import MobileUserHeader from './MobileUserHeader';
import MobileCheckoutBar from './MobileCheckoutBar';
import MobileCartSheet from './MobileCartSheet';
import CategoryBadgeButtons from './CategoryBadgeButtons';
import { useCart } from '../../contexts/CartContext';

/**
 * MenuLayout - Responsive layout for menu page
 * Mobile: Single column with bottom checkout bar
 * Desktop: 3-column grid [Navigation] [Content] [Cart]
 * RTL: Navigation on right, Cart on left
 */
export default function MenuLayout({ branchId, onCheckout }) {
  // Responsive hooks
  const isMobile = useIsMobile();
  const isTablet = useIsTablet();
  const isDesktop = useIsDesktop();

  // CartContext integration - sync with backend cart
  const {
    cart: contextCart,
    addToCart: contextAddToCart,
    clearCart: contextClearCart,
    removeFromCart: contextRemoveFromCart,
    getTotal,
    loading: cartLoading
  } = useCart();

  // Transform context cart to display format for UI components
  const cart = useMemo(() => {
    const items = (contextCart?.items || []).map(item => ({
      id: item.id,
      product_id: item.product_id,
      name: item.product_name || item.name,
      price: item.final_price || item.unit_price || 0,
      quantity: item.quantity || 1,
      image_url: item.image_url || item.product_image,
      customizations: item.customizations,
      specialInstructions: item.special_instructions || item.notes || '',
      _original: item, // Keep reference for editing/deleting
    }));

    const subtotal = getTotal();
    const tax = subtotal * 0.17;
    const deliveryFee = 25.0;

    return { items, subtotal, tax, deliveryFee };
  }, [contextCart, getTotal]);

  const [products, setProducts] = useState([]);
  const [categories, setCategories] = useState([]);
  const [activeCategory, setActiveCategory] = useState(null);
  const [loading, setLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');
  const [customizingProduct, setCustomizingProduct] = useState(null);
  const [editingCartItem, setEditingCartItem] = useState(null); // Track item being edited
  // Default sidebar closed on tablet, open on desktop
  const [sidebarExpanded, setSidebarExpanded] = useState(isDesktop);
  const [mobileCartOpen, setMobileCartOpen] = useState(false);
  const [showSearch, setShowSearch] = useState(false);
  const [showFilters, setShowFilters] = useState(false);
  const [selectedBadges, setSelectedBadges] = useState([]);
  const [sortOption, setSortOption] = useState('default');
  const [recentSearches, setRecentSearches] = useState([]);

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
    // No icons - return null
    return null;
  };

  // Extract unique badges from all products
  const availableBadges = [...new Set(products.flatMap((product) => product.badges || []))];

  // Count active filters
  const getActiveFilterCount = () => {
    let count = 0;
    if (activeCategory) count++;
    count += selectedBadges.length;
    return count;
  };

  // Clear all filters
  const clearAllFilters = () => {
    setActiveCategory(null);
    setSelectedBadges([]);
    setSortOption('default');
  };

  // Filter and sort products
  let filteredProducts = products.filter((product) => {
    const productCategory = product.category || 'ללא קטגוריה';
    const matchesCategory = !activeCategory || productCategory === activeCategory;
    const matchesSearch = !searchQuery || product.name.toLowerCase().includes(searchQuery.toLowerCase());

    // Badge filters - product must have ALL selected badges
    const matchesBadges = selectedBadges.length === 0 || selectedBadges.every((badge) => product.badges?.includes(badge));

    return matchesCategory && matchesSearch && matchesBadges;
  });

  // Sort products
  if (sortOption === 'price-low') {
    filteredProducts = [...filteredProducts].sort((a, b) => a.price - b.price);
  } else if (sortOption === 'price-high') {
    filteredProducts = [...filteredProducts].sort((a, b) => b.price - a.price);
  } else if (sortOption === 'name') {
    filteredProducts = [...filteredProducts].sort((a, b) => a.name.localeCompare(b.name, 'he'));
  } else if (sortOption === 'popular') {
    filteredProducts = [...filteredProducts].sort((a, b) => (b.popularity || 0) - (a.popularity || 0));
  }

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

  // Add product to cart with customizations - uses CartContext for backend sync
  const addProductToCart = async (product, quantity = 1, customizations = null, specialInstructions = '') => {
    try {
      // Backend expects customizations in object format: { groupId: [{ id, name, price }, ...], ... }
      // This is the SAME format the ProductCustomizationModal sends
      // Pass through as-is, but ensure it's a valid object (not array, not null)
      let customizationsToSend = {};

      if (customizations && typeof customizations === 'object' && !Array.isArray(customizations)) {
        // Filter to only include groups with valid IDs and items
        Object.entries(customizations).forEach(([groupId, groupItems]) => {
          const numericGroupId = parseInt(groupId, 10);
          if (Array.isArray(groupItems) && groupItems.length > 0 && numericGroupId > 0) {
            customizationsToSend[groupId] = groupItems;
          }
        });
      }

      console.log('🛒 Adding to cart:', {
        product_id: product.id,
        quantity,
        customizations: customizationsToSend,
        branchId
      });

      await contextAddToCart(
        product,
        quantity,
        customizationsToSend,
        specialInstructions,
        branchId
      );
    } catch (error) {
      console.error('Failed to add product to cart:', error);
      // Could show a toast/notification here
    }
  };

  // Handle customization confirmation
  const handleCustomizationConfirm = async (customizedProduct) => {
    if (editingCartItem) {
      // Editing mode: Remove old item and add new one
      // (Backend doesn't support in-place edit with customization changes)
      try {
        const originalItemId = editingCartItem._original?.id || editingCartItem.id;
        await contextRemoveFromCart(originalItemId);
        await addProductToCart(
          customizedProduct,
          customizedProduct.quantity || 1,
          customizedProduct.customizations,
          customizedProduct.specialInstructions || ''
        );
      } catch (error) {
        console.error('Failed to update cart item:', error);
      }
      setEditingCartItem(null);
    } else {
      // Add new item to cart
      await addProductToCart(
        customizedProduct,
        customizedProduct.quantity || 1,
        customizedProduct.customizations,
        customizedProduct.specialInstructions || ''
      );
    }
    setCustomizingProduct(null);
  };

  // Handle clear cart - uses CartContext for backend sync
  const handleClearCart = async () => {
    try {
      await contextClearCart();
    } catch (error) {
      console.error('Failed to clear cart:', error);
    }
  };

  // Handle editing a cart item - open customization modal with existing data
  const handleEditCartItem = (cartItem) => {
    setEditingCartItem(cartItem);
    setCustomizingProduct(cartItem); // Opens modal with this product
  };

  // Handle deleting individual cart item - uses CartContext for backend sync
  const handleDeleteItem = async (itemToDelete) => {
    try {
      // Get the backend item ID from _original reference or directly from item
      const itemId = itemToDelete._original?.id || itemToDelete.id;
      await contextRemoveFromCart(itemId);
    } catch (error) {
      console.error('Failed to delete cart item:', error);
    }
  };

  // Handle search with suggestions
  const handleSearch = (value) => {
    setSearchQuery(value);
    if (value && !recentSearches.includes(value)) {
      setRecentSearches((prev) => [value, ...prev].slice(0, 5));
    }
  };

  // Toggle badge filter
  const toggleBadgeFilter = (badge) => {
    setSelectedBadges((prev) =>
      prev.includes(badge)
        ? prev.filter((b) => b !== badge)
        : [...prev, badge]
    );
  };

  return (
    <>
      {isMobile ? (
        /* ===== MOBILE LAYOUT ===== */
        <div
          style={{
            height: '100vh',
            display: 'flex',
            flexDirection: 'column',
            direction: 'rtl',
            overflow: 'hidden',
            paddingBottom: theme.layout.mobileCheckoutBarHeight,
            backgroundColor: theme.colors.background,
          }}
        >
          {/* Scrollable content area */}
          <div
            style={{
              flex: 1,
              overflowY: 'auto',
              WebkitOverflowScrolling: 'touch',
            }}
            className="hide-scrollbar smooth-scroll"
          >
            {/* User greeting at top right */}
            <MobileUserHeader username="משתמש" />

            {/* Hero Banner */}
            <div style={{ padding: `0 ${theme.spacing.mobile.md}` }}>
              <HeroBanner />
            </div>

            {/* "Our Menu" header with search/filter buttons */}
            <div
              style={{
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
                padding: theme.spacing.mobile.md,
                paddingTop: theme.spacing.mobile.lg,
                paddingBottom: theme.spacing.mobile.sm,
              }}
            >
              {/* Our Menu Label - Right Side */}
              <h2
                style={{
                  fontSize: theme.typography.mobile.h2,
                  fontWeight: 600,
                  color: theme.colors.text.primary,
                  margin: 0,
                }}
              >
                התפריט שלנו
              </h2>

              {/* Search and Filter Buttons - Left Side */}
              <div style={{ display: 'flex', gap: theme.spacing.mobile.sm }}>
                <button
                  onClick={() => setShowSearch(!showSearch)}
                  style={{
                    width: '40px',
                    height: '40px',
                    backgroundColor: showSearch ? theme.colors.primary : theme.colors.cardBg,
                    border: 'none',
                    borderRadius: '50%',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    cursor: 'pointer',
                    boxShadow: theme.shadows.sm,
                    transition: 'all 0.2s ease',
                  }}
                >
                  <svg
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke={showSearch ? '#FFFFFF' : theme.colors.text.secondary}
                    strokeWidth="2"
                  >
                    <circle cx="11" cy="11" r="8" />
                    <path d="M21 21l-4.35-4.35" />
                  </svg>
                </button>
                <button
                  onClick={() => setShowFilters(!showFilters)}
                  style={{
                    position: 'relative',
                    width: '40px',
                    height: '40px',
                    backgroundColor: showFilters ? theme.colors.primary : theme.colors.cardBg,
                    border: 'none',
                    borderRadius: '50%',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    cursor: 'pointer',
                    boxShadow: theme.shadows.sm,
                    transition: 'all 0.2s ease',
                  }}
                >
                  <svg
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke={showFilters ? '#FFFFFF' : theme.colors.text.secondary}
                    strokeWidth="2"
                  >
                    <path d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
                  </svg>
                  {getActiveFilterCount() > 0 && (
                    <span style={{
                      position: 'absolute',
                      top: '-4px',
                      right: '-4px',
                      backgroundColor: '#DC2626',
                      color: '#FFFFFF',
                      borderRadius: '50%',
                      width: '18px',
                      height: '18px',
                      fontSize: '0.625rem',
                      fontWeight: 'bold',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                    }}>
                      {getActiveFilterCount()}
                    </span>
                  )}
                </button>
              </div>
            </div>

            {/* Search Input - Conditional */}
            {showSearch && (
              <div style={{ padding: `0 ${theme.spacing.mobile.md}`, marginBottom: theme.spacing.mobile.md }}>
                <input
                  type="text"
                  placeholder="חפש מוצרים..."
                  value={searchQuery}
                  onChange={(e) => handleSearch(e.target.value)}
                  autoFocus
                  style={{
                    width: '100%',
                    padding: `${theme.spacing.mobile.sm} ${theme.spacing.mobile.md}`,
                    fontSize: theme.typography.mobile.body,
                    border: `1px solid ${theme.colors.border}`,
                    borderRadius: theme.borderRadius.lg,
                    backgroundColor: '#FFFFFF',
                    textAlign: 'right',
                    outline: 'none',
                  }}
                />
                {/* Search Suggestions */}
                {!searchQuery && recentSearches.length > 0 && (
                  <div style={{
                    marginTop: theme.spacing.mobile.xs,
                    padding: theme.spacing.mobile.sm,
                    backgroundColor: '#FFFFFF',
                    borderRadius: theme.borderRadius.md,
                    boxShadow: theme.shadows.sm,
                  }}>
                    <div style={{ fontSize: theme.typography.mobile.small, color: theme.colors.text.secondary, marginBottom: theme.spacing.mobile.xs }}>
                      חיפושים אחרונים:
                    </div>
                    {recentSearches.map((search, idx) => (
                      <div
                        key={idx}
                        onClick={() => setSearchQuery(search)}
                        style={{
                          padding: `${theme.spacing.mobile.xs} 0`,
                          cursor: 'pointer',
                          fontSize: theme.typography.mobile.body,
                          color: theme.colors.text.primary,
                        }}
                      >
                        🔍 {search}
                      </div>
                    ))}
                  </div>
                )}
              </div>
            )}

            {/* Filter Panel - Conditional */}
            {showFilters && (
              <div style={{
                padding: theme.spacing.mobile.md,
                backgroundColor: '#FFFFFF',
                margin: `0 ${theme.spacing.mobile.md}`,
                marginBottom: theme.spacing.mobile.md,
                borderRadius: theme.borderRadius.lg,
                boxShadow: theme.shadows.sm,
              }}>
                {/* Category Filters */}
                <div style={{ marginBottom: theme.spacing.mobile.md }}>
                  <div style={{ marginBottom: theme.spacing.mobile.sm, fontWeight: 600 }}>סינון לפי קטגוריה:</div>
                  <div style={{ display: 'flex', flexWrap: 'wrap', gap: theme.spacing.mobile.xs }}>
                    {categories.map((category) => (
                      <button
                        key={category.id}
                        onClick={() => setActiveCategory(activeCategory === category.id ? null : category.id)}
                        style={{
                          padding: `${theme.spacing.mobile.xs} ${theme.spacing.mobile.sm}`,
                          backgroundColor: activeCategory === category.id ? theme.colors.primary : '#F3F4F6',
                          color: activeCategory === category.id ? '#FFFFFF' : theme.colors.text.primary,
                          border: 'none',
                          borderRadius: theme.borderRadius.md,
                          fontSize: theme.typography.mobile.small,
                          cursor: 'pointer',
                        }}
                      >
                        {category.name}
                      </button>
                    ))}
                  </div>
                </div>

                {/* Badge Filters */}
                {availableBadges.length > 0 && (
                  <div style={{ marginBottom: theme.spacing.mobile.md }}>
                    <div style={{ marginBottom: theme.spacing.mobile.sm, fontWeight: 600 }}>סינון לפי תגיות:</div>
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: theme.spacing.mobile.xs }}>
                      {availableBadges.map((badge) => (
                        <button
                          key={badge}
                          onClick={() => toggleBadgeFilter(badge)}
                          style={{
                            padding: `${theme.spacing.mobile.xs} ${theme.spacing.mobile.sm}`,
                            backgroundColor: selectedBadges.includes(badge) ? theme.colors.primary : '#F3F4F6',
                            color: selectedBadges.includes(badge) ? '#FFFFFF' : theme.colors.text.primary,
                            border: 'none',
                            borderRadius: theme.borderRadius.md,
                            fontSize: theme.typography.mobile.small,
                            cursor: 'pointer',
                          }}
                        >
                          {badge}
                        </button>
                      ))}
                    </div>
                  </div>
                )}

                {/* Sort Options */}
                <div style={{ marginBottom: theme.spacing.mobile.md }}>
                  <div style={{ marginBottom: theme.spacing.mobile.sm, fontWeight: 600 }}>מיון:</div>
                  <div style={{ display: 'flex', flexWrap: 'wrap', gap: theme.spacing.mobile.xs }}>
                    <button
                      onClick={() => setSortOption('default')}
                      style={{
                        padding: `${theme.spacing.mobile.xs} ${theme.spacing.mobile.sm}`,
                        backgroundColor: sortOption === 'default' ? theme.colors.primary : '#F3F4F6',
                        color: sortOption === 'default' ? '#FFFFFF' : theme.colors.text.primary,
                        border: 'none',
                        borderRadius: theme.borderRadius.md,
                        fontSize: theme.typography.mobile.small,
                        cursor: 'pointer',
                      }}
                    >
                      ברירת מחדל
                    </button>
                    <button
                      onClick={() => setSortOption('price-low')}
                      style={{
                        padding: `${theme.spacing.mobile.xs} ${theme.spacing.mobile.sm}`,
                        backgroundColor: sortOption === 'price-low' ? theme.colors.primary : '#F3F4F6',
                        color: sortOption === 'price-low' ? '#FFFFFF' : theme.colors.text.primary,
                        border: 'none',
                        borderRadius: theme.borderRadius.md,
                        fontSize: theme.typography.mobile.small,
                        cursor: 'pointer',
                      }}
                    >
                      מחיר: נמוך לגבוה
                    </button>
                    <button
                      onClick={() => setSortOption('price-high')}
                      style={{
                        padding: `${theme.spacing.mobile.xs} ${theme.spacing.mobile.sm}`,
                        backgroundColor: sortOption === 'price-high' ? theme.colors.primary : '#F3F4F6',
                        color: sortOption === 'price-high' ? '#FFFFFF' : theme.colors.text.primary,
                        border: 'none',
                        borderRadius: theme.borderRadius.md,
                        fontSize: theme.typography.mobile.small,
                        cursor: 'pointer',
                      }}
                    >
                      מחיר: גבוה לנמוך
                    </button>
                    <button
                      onClick={() => setSortOption('name')}
                      style={{
                        padding: `${theme.spacing.mobile.xs} ${theme.spacing.mobile.sm}`,
                        backgroundColor: sortOption === 'name' ? theme.colors.primary : '#F3F4F6',
                        color: sortOption === 'name' ? '#FFFFFF' : theme.colors.text.primary,
                        border: 'none',
                        borderRadius: theme.borderRadius.md,
                        fontSize: theme.typography.mobile.small,
                        cursor: 'pointer',
                      }}
                    >
                      שם א-ת
                    </button>
                    <button
                      onClick={() => setSortOption('popular')}
                      style={{
                        padding: `${theme.spacing.mobile.xs} ${theme.spacing.mobile.sm}`,
                        backgroundColor: sortOption === 'popular' ? theme.colors.primary : '#F3F4F6',
                        color: sortOption === 'popular' ? '#FFFFFF' : theme.colors.text.primary,
                        border: 'none',
                        borderRadius: theme.borderRadius.md,
                        fontSize: theme.typography.mobile.small,
                        cursor: 'pointer',
                      }}
                    >
                      הכי פופולרי
                    </button>
                  </div>
                </div>

                {/* Clear All Filters Button */}
                {getActiveFilterCount() > 0 && (
                  <button
                    onClick={clearAllFilters}
                    style={{
                      width: '100%',
                      padding: `${theme.spacing.mobile.sm} ${theme.spacing.mobile.md}`,
                      backgroundColor: '#DC2626',
                      color: '#FFFFFF',
                      border: 'none',
                      borderRadius: theme.borderRadius.md,
                      fontSize: theme.typography.mobile.body,
                      fontWeight: 600,
                      cursor: 'pointer',
                    }}
                  >
                    נקה את כל הסינונים ({getActiveFilterCount()})
                  </button>
                )}
              </div>
            )}

            {/* Category badge buttons (inline, scrollable) */}
            <CategoryBadgeButtons
              categories={categories}
              activeCategory={activeCategory}
              onCategoryChange={setActiveCategory}
            />

            {/* Product Grid */}
            <div style={{ padding: `0 ${theme.spacing.mobile.md} ${theme.spacing.mobile.lg}` }}>
              <ProductGrid
                products={filteredProducts}
                onAddToCart={handleAddToCart}
                loading={loading}
                hasActiveFilters={getActiveFilterCount() > 0}
                onClearFilters={clearAllFilters}
              />
            </div>
          </div>

          {/* Fixed bottom checkout bar */}
          <MobileCheckoutBar
            cart={cart}
            onOpenCart={() => setMobileCartOpen(true)}
            onCheckout={onCheckout}
          />

          {/* Cart sheet (pull up) */}
          <MobileCartSheet
            isOpen={mobileCartOpen}
            onClose={() => setMobileCartOpen(false)}
            cart={cart}
            onClearCart={handleClearCart}
            onItemClick={handleEditCartItem}
            onDeleteItem={handleDeleteItem}
          />
        </div>
      ) : (
        /* ===== DESKTOP LAYOUT (3-column grid) ===== */
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
                    onClick={() => setShowSearch(!showSearch)}
                    style={{
                      width: '40px',
                      height: '40px',
                      backgroundColor: showSearch ? theme.colors.primary : theme.colors.cardBg,
                      border: 'none',
                      borderRadius: '50%',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      cursor: 'pointer',
                      boxShadow: theme.shadows.sm,
                      transition: 'all 0.2s ease',
                    }}
                  >
                    <svg
                      width="20"
                      height="20"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke={showSearch ? '#FFFFFF' : theme.colors.text.secondary}
                      strokeWidth="2"
                    >
                      <circle cx="11" cy="11" r="8" />
                      <path d="M21 21l-4.35-4.35" />
                    </svg>
                  </button>
                  <button
                    onClick={() => setShowFilters(!showFilters)}
                    style={{
                      position: 'relative',
                      width: '40px',
                      height: '40px',
                      backgroundColor: showFilters ? theme.colors.primary : theme.colors.cardBg,
                      border: 'none',
                      borderRadius: '50%',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      cursor: 'pointer',
                      boxShadow: theme.shadows.sm,
                      transition: 'all 0.2s ease',
                    }}
                  >
                    <svg
                      width="20"
                      height="20"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke={showFilters ? '#FFFFFF' : theme.colors.text.secondary}
                      strokeWidth="2"
                    >
                      <path d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
                    </svg>
                    {getActiveFilterCount() > 0 && (
                      <span style={{
                        position: 'absolute',
                        top: '-4px',
                        right: '-4px',
                        backgroundColor: '#DC2626',
                        color: '#FFFFFF',
                        borderRadius: '50%',
                        width: '18px',
                        height: '18px',
                        fontSize: '0.625rem',
                        fontWeight: 'bold',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                      }}>
                        {getActiveFilterCount()}
                      </span>
                    )}
                  </button>
                </div>
              </div>

              {/* Search Input - Conditional (Desktop) */}
              {showSearch && (
                <div style={{ marginBottom: theme.spacing.md, flexShrink: 0 }}>
                  <input
                    type="text"
                    placeholder="חפש מוצרים..."
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    autoFocus
                    style={{
                      width: '100%',
                      padding: `${theme.spacing.sm} ${theme.spacing.md}`,
                      fontSize: '1rem',
                      border: `1px solid ${theme.colors.border}`,
                      borderRadius: theme.borderRadius.lg,
                      backgroundColor: '#FFFFFF',
                      textAlign: 'right',
                      outline: 'none',
                    }}
                  />
                </div>
              )}

              {/* Filter Panel - Conditional (Desktop) */}
              {showFilters && (
                <div style={{
                  padding: theme.spacing.md,
                  backgroundColor: '#FFFFFF',
                  marginBottom: theme.spacing.md,
                  borderRadius: theme.borderRadius.lg,
                  boxShadow: theme.shadows.sm,
                  flexShrink: 0,
                }}>
                  {/* Category Filters */}
                  <div style={{ marginBottom: theme.spacing.md }}>
                    <div style={{ marginBottom: theme.spacing.sm, fontWeight: 600 }}>סינון לפי קטגוריה:</div>
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: theme.spacing.xs }}>
                      {categories.map((category) => (
                        <button
                          key={category.id}
                          onClick={() => setActiveCategory(activeCategory === category.id ? null : category.id)}
                          style={{
                            padding: `${theme.spacing.xs} ${theme.spacing.sm}`,
                            backgroundColor: activeCategory === category.id ? theme.colors.primary : '#F3F4F6',
                            color: activeCategory === category.id ? '#FFFFFF' : theme.colors.text.primary,
                            border: 'none',
                            borderRadius: theme.borderRadius.md,
                            fontSize: '0.875rem',
                            cursor: 'pointer',
                          }}
                        >
                          {category.name}
                        </button>
                      ))}
                    </div>
                  </div>

                  {/* Badge Filters */}
                  {availableBadges.length > 0 && (
                    <div style={{ marginBottom: theme.spacing.md }}>
                      <div style={{ marginBottom: theme.spacing.sm, fontWeight: 600 }}>סינון לפי תגיות:</div>
                      <div style={{ display: 'flex', flexWrap: 'wrap', gap: theme.spacing.xs }}>
                        {availableBadges.map((badge) => (
                          <button
                            key={badge}
                            onClick={() => toggleBadgeFilter(badge)}
                            style={{
                              padding: `${theme.spacing.xs} ${theme.spacing.sm}`,
                              backgroundColor: selectedBadges.includes(badge) ? theme.colors.primary : '#F3F4F6',
                              color: selectedBadges.includes(badge) ? '#FFFFFF' : theme.colors.text.primary,
                              border: 'none',
                              borderRadius: theme.borderRadius.md,
                              fontSize: '0.875rem',
                              cursor: 'pointer',
                            }}
                          >
                            {badge}
                          </button>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* Sort Options */}
                  <div style={{ marginBottom: theme.spacing.md }}>
                    <div style={{ marginBottom: theme.spacing.sm, fontWeight: 600 }}>מיון:</div>
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: theme.spacing.xs }}>
                      <button
                        onClick={() => setSortOption('default')}
                        style={{
                          padding: `${theme.spacing.xs} ${theme.spacing.sm}`,
                          backgroundColor: sortOption === 'default' ? theme.colors.primary : '#F3F4F6',
                          color: sortOption === 'default' ? '#FFFFFF' : theme.colors.text.primary,
                          border: 'none',
                          borderRadius: theme.borderRadius.md,
                          fontSize: '0.875rem',
                          cursor: 'pointer',
                        }}
                      >
                        ברירת מחדל
                      </button>
                      <button
                        onClick={() => setSortOption('price-low')}
                        style={{
                          padding: `${theme.spacing.xs} ${theme.spacing.sm}`,
                          backgroundColor: sortOption === 'price-low' ? theme.colors.primary : '#F3F4F6',
                          color: sortOption === 'price-low' ? '#FFFFFF' : theme.colors.text.primary,
                          border: 'none',
                          borderRadius: theme.borderRadius.md,
                          fontSize: '0.875rem',
                          cursor: 'pointer',
                        }}
                      >
                        מחיר: נמוך לגבוה
                      </button>
                      <button
                        onClick={() => setSortOption('price-high')}
                        style={{
                          padding: `${theme.spacing.xs} ${theme.spacing.sm}`,
                          backgroundColor: sortOption === 'price-high' ? theme.colors.primary : '#F3F4F6',
                          color: sortOption === 'price-high' ? '#FFFFFF' : theme.colors.text.primary,
                          border: 'none',
                          borderRadius: theme.borderRadius.md,
                          fontSize: '0.875rem',
                          cursor: 'pointer',
                        }}
                      >
                        מחיר: גבוה לנמוך
                      </button>
                      <button
                        onClick={() => setSortOption('name')}
                        style={{
                          padding: `${theme.spacing.xs} ${theme.spacing.sm}`,
                          backgroundColor: sortOption === 'name' ? theme.colors.primary : '#F3F4F6',
                          color: sortOption === 'name' ? '#FFFFFF' : theme.colors.text.primary,
                          border: 'none',
                          borderRadius: theme.borderRadius.md,
                          fontSize: '0.875rem',
                          cursor: 'pointer',
                        }}
                      >
                        שם א-ת
                      </button>
                      <button
                        onClick={() => setSortOption('popular')}
                        style={{
                          padding: `${theme.spacing.xs} ${theme.spacing.sm}`,
                          backgroundColor: sortOption === 'popular' ? theme.colors.primary : '#F3F4F6',
                          color: sortOption === 'popular' ? '#FFFFFF' : theme.colors.text.primary,
                          border: 'none',
                          borderRadius: theme.borderRadius.md,
                          fontSize: '0.875rem',
                          cursor: 'pointer',
                        }}
                      >
                        הכי פופולרי
                      </button>
                    </div>
                  </div>

                  {/* Clear All Filters Button */}
                  {getActiveFilterCount() > 0 && (
                    <button
                      onClick={clearAllFilters}
                      style={{
                        width: '100%',
                        padding: `${theme.spacing.sm} ${theme.spacing.md}`,
                        backgroundColor: '#DC2626',
                        color: '#FFFFFF',
                        border: 'none',
                        borderRadius: theme.borderRadius.md,
                        fontSize: '1rem',
                        fontWeight: 600,
                        cursor: 'pointer',
                      }}
                    >
                      נקה את כל הסינונים ({getActiveFilterCount()})
                    </button>
                  )}
                </div>
              )}

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
                  hasActiveFilters={getActiveFilterCount() > 0}
                  onClearFilters={clearAllFilters}
                />
              </div>
            </div>

            {/* Left Sidebar - Cart Panel */}
            <CartPanel cart={cart} onCheckout={onCheckout} onClearCart={handleClearCart} onItemClick={handleEditCartItem} onDeleteItem={handleDeleteItem} />
          </div>
        </div>
      )}

      {/* Product Customization Modal (both mobile and desktop) */}
      <ProductCustomizationModal
        product={customizingProduct}
        isOpen={!!customizingProduct}
        onClose={() => {
          setCustomizingProduct(null);
          setEditingCartItem(null);
        }}
        onConfirm={handleCustomizationConfirm}
        editingItem={editingCartItem}
      />
    </>
  );
}
