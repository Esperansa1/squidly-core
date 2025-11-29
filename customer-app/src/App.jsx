import React, { useState, useEffect } from 'react';
import publicApi from './services/publicApi';
import { BranchProvider, useBranch } from './contexts/BranchContext';
import { CartProvider, useCart } from './contexts/CartContext';
import BranchSelector from './components/branches/BranchSelector';
import ProductGrid from './components/products/ProductGrid';
import CategoryTabs from './components/products/CategoryTabs';
import CartModal from './components/cart/CartModal';
import ProductCustomizationModal from './components/products/ProductCustomizationModal';
import CheckoutFlow from './components/checkout/CheckoutFlow';
import OrderTracker from './components/orders/OrderTracker';
import { t } from './i18n/translations';

function AppContent() {
  const [currentView, setCurrentView] = useState('branch-selection');
  const [apiStatus, setApiStatus] = useState({ initialized: false, error: null, config: null });
  const [testData, setTestData] = useState({ branches: null, products: null, categories: null });
  const [allProducts, setAllProducts] = useState([]);
  const [products, setProducts] = useState([]);
  const [categories, setCategories] = useState([]);
  const [activeCategory, setActiveCategory] = useState(null);
  const [loadingProducts, setLoadingProducts] = useState(false);
  const [showCart, setShowCart] = useState(false);
  const [customizingProduct, setCustomizingProduct] = useState(null);
  const { selectedBranch } = useBranch();
  const { addToCart, getItemCount } = useCart();

  // Initialize API on mount
  useEffect(() => {
    const initApi = async () => {
      try {
        const config = await publicApi.init();
        setApiStatus({ initialized: true, error: null, config });
        console.log('✅ Public API initialized:', config);
      } catch (error) {
        setApiStatus({ initialized: false, error: error.message, config: null });
        console.error('❌ Failed to initialize API:', error);
      }
    };

    initApi();
  }, []);

  // Detect payment return and auto-load order tracking
  useEffect(() => {
    const paymentReturn = window.wpConfig?.paymentReturn;

    if (paymentReturn?.isReturn && paymentReturn?.orderId) {
      console.log('🔔 Payment return detected:', paymentReturn);

      // Check if we have a saved tracking token
      const savedOrderId = sessionStorage.getItem('squidly_order_id');
      const savedToken = sessionStorage.getItem('squidly_tracking_token');

      if (savedOrderId && savedToken && parseInt(savedOrderId) === paymentReturn.orderId) {
        console.log('✅ Auto-loading order tracking for order #' + paymentReturn.orderId);
        // Auto-navigate to tracking view
        setCurrentView('tracking');
      } else {
        console.warn('⚠️ Payment return detected but no matching tracking token found');
      }
    }
  }, []);

  // Fetch all products when menu view is shown (to extract categories)
  useEffect(() => {
    if (currentView === 'menu' && selectedBranch) {
      fetchAllProductsAndCategories();
    }
  }, [currentView, selectedBranch]);

  // Filter displayed products when category changes
  useEffect(() => {
    if (allProducts.length > 0) {
      filterProductsByCategory();
    }
  }, [activeCategory, allProducts]);

  const fetchAllProductsAndCategories = async () => {
    setLoadingProducts(true);
    try {
      // Fetch all products for this branch
      const filters = { branch_id: selectedBranch.id };
      const fetchedProducts = await publicApi.getProducts(filters);

      setAllProducts(fetchedProducts);

      // Extract unique categories from products (free text field)
      const uniqueCategories = [...new Set(
        fetchedProducts
          .map(p => p.category)
          .filter(cat => cat) // Remove null/undefined
      )];

      // Create category objects
      const categoryObjects = uniqueCategories.map(name => ({
        id: name, // Use name as ID for filtering
        name
      }));

      setCategories(categoryObjects);

      // Initially show all products
      setProducts(fetchedProducts);

      console.log('✅ Products loaded:', fetchedProducts.length);
      console.log('✅ Categories extracted:', categoryObjects);
    } catch (error) {
      console.error('❌ Failed to load products:', error);
    } finally {
      setLoadingProducts(false);
    }
  };

  const filterProductsByCategory = () => {
    if (activeCategory === null) {
      // Show all products
      setProducts(allProducts);
    } else {
      // Filter by category
      const filtered = allProducts.filter(p => p.category === activeCategory);
      setProducts(filtered);
      console.log(`✅ Filtered to ${filtered.length} products in category "${activeCategory}"`);
    }
  };

  // Handle adding product to cart
  const handleAddToCart = (product) => {
    // Check if product has customization groups
    if (product.product_group_ids && product.product_group_ids.length > 0) {
      // Show customization modal
      setCustomizingProduct(product);
    } else {
      // Add directly to cart (no customization)
      addToCart(product, 1, null, '', selectedBranch?.id);
      console.log('Added to cart:', product.name);
    }
  };

  // Handle customized product confirmation
  const handleCustomizationConfirm = (customizedProduct) => {
    addToCart(
      customizedProduct,
      1,
      customizedProduct.customizations,
      '',
      selectedBranch?.id
    );
    console.log('Added customized product to cart:', customizedProduct.name);
    setCustomizingProduct(null);
  };

  // Handle checkout
  const handleCheckout = () => {
    setShowCart(false);
    setCurrentView('checkout');
    console.log('Proceeding to checkout...');
  };

  // Test API endpoints
  const testApiEndpoints = async () => {
    try {
      console.log('🧪 Testing API endpoints...');

      // Test branches
      const branches = await publicApi.getBranches();
      console.log('✅ Branches:', branches);
      setTestData(prev => ({ ...prev, branches }));

      // Test products
      const products = await publicApi.getProducts({ per_page: 5 });
      console.log('✅ Products:', products);
      setTestData(prev => ({ ...prev, products }));

      // Test categories (via products endpoint)
      const allProducts = await publicApi.getProducts();
      const categories = [...new Set(allProducts.flatMap(p => p.product_group_ids))];
      console.log('✅ Categories:', categories);
      setTestData(prev => ({ ...prev, categories }));

      alert('API test successful! Check console for details.');
    } catch (error) {
      console.error('❌ API test failed:', error);
      alert(`API test failed: ${error.message}`);
    }
  };

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <header className="bg-white shadow-sm sticky top-0 z-50">
        <div className="container mx-auto px-4 py-4">
          <div className="flex items-center justify-between">
            <h1 className="text-2xl font-bold">Squidly Orders</h1>
            <div className="flex items-center gap-3">
              <button
                onClick={() => setCurrentView('tracking')}
                className="px-4 py-2 text-sm text-blue-600 hover:bg-blue-50"
              >
                {t('trackOrder')}
              </button>
              <button
                onClick={() => setShowCart(true)}
                className="px-4 py-2 text-sm border hover:bg-gray-100 relative"
              >
                {t('cart')} ({getItemCount()})
              </button>
            </div>
          </div>
        </div>
      </header>

      {/* Main Content */}
      <main className="container mx-auto px-4 py-8">
        {currentView === 'branch-selection' && (
          <div className="bg-white rounded-lg shadow p-8">
            <BranchSelector
              onBranchSelected={(branch) => {
                console.log('Branch selected:', branch);
                setCurrentView('menu');
              }}
            />
          </div>
        )}

        {currentView === 'menu' && (
          <div>
            {/* Menu Header */}
            <div className="mb-6">
              <h2 className="text-3xl font-bold mb-2">{t('menu')}</h2>
              <p className="text-gray-600">
                {selectedBranch?.name}
              </p>
              <button
                className="text-sm text-blue-600 mt-2"
                onClick={() => setCurrentView('branch-selection')}
              >
                ← {t('back')}
              </button>
            </div>

            {/* Category Tabs */}
            <CategoryTabs
              categories={categories}
              activeCategory={activeCategory}
              onCategoryChange={setActiveCategory}
            />

            {/* Product Grid */}
            <ProductGrid
              products={products}
              onAddToCart={handleAddToCart}
              loading={loadingProducts}
            />
          </div>
        )}

        {currentView === 'checkout' && (
          <CheckoutFlow
            onBack={() => setCurrentView('menu')}
            branchId={selectedBranch?.id}
          />
        )}

        {currentView === 'tracking' && (
          <OrderTracker
            onBack={() => setCurrentView('menu')}
          />
        )}

        {/* API Status */}
        <div className={`mt-8 p-4 rounded-lg border ${
          apiStatus.initialized
            ? 'bg-green-50 border-green-200'
            : apiStatus.error
              ? 'bg-red-50 border-red-200'
              : 'bg-yellow-50 border-yellow-200'
        }`}>
          <h3 className={`font-bold mb-2 ${
            apiStatus.initialized ? 'text-green-900' : apiStatus.error ? 'text-red-900' : 'text-yellow-900'
          }`}>
            API Status
          </h3>
          {apiStatus.initialized && (
            <div>
              <p className="text-green-700 text-sm">✅ API initialized successfully</p>
              <p className="text-green-600 text-xs mt-1">
                Base URL: {apiStatus.config?.api?.base_url}
              </p>
              <p className="text-green-600 text-xs">
                Currency: {apiStatus.config?.currency?.symbol} ({apiStatus.config?.currency?.code})
              </p>
              <button
                onClick={testApiEndpoints}
                className="mt-3 px-4 py-2 bg-green-600 text-white text-sm rounded hover:bg-green-700 transition"
              >
                🧪 Test API Endpoints
              </button>
              {testData.branches && (
                <p className="text-green-600 text-xs mt-2">
                  Found {testData.branches.length} branches
                </p>
              )}
              {testData.products && (
                <p className="text-green-600 text-xs">
                  Found {testData.products.length} products
                </p>
              )}
            </div>
          )}
          {apiStatus.error && (
            <p className="text-red-700 text-sm">❌ {apiStatus.error}</p>
          )}
          {!apiStatus.initialized && !apiStatus.error && (
            <p className="text-yellow-700 text-sm">⏳ Initializing API...</p>
          )}
        </div>

        {/* Development Info */}
        <div className="mt-8 p-4 bg-blue-50 rounded-lg border border-blue-200">
          <h3 className="font-bold text-blue-900 mb-2">Development Status</h3>
          <p className="text-blue-700 text-sm">
            Customer app successfully built and integrated with WordPress!
          </p>
          <p className="text-blue-700 text-sm mt-2">
            Current view: <span className="font-mono font-bold">{currentView}</span>
          </p>
          <p className="text-blue-600 text-xs mt-2">
            Access this at: <strong>squidly.local/orders</strong>
          </p>
        </div>
      </main>

      {/* Cart Modal */}
      <CartModal
        isOpen={showCart}
        onClose={() => setShowCart(false)}
        onCheckout={handleCheckout}
      />

      {/* Product Customization Modal */}
      <ProductCustomizationModal
        product={customizingProduct}
        isOpen={!!customizingProduct}
        onClose={() => setCustomizingProduct(null)}
        onConfirm={handleCustomizationConfirm}
      />

      {/* Footer */}
      <footer className="bg-white border-t mt-12">
        <div className="container mx-auto px-4 py-6">
          <p className="text-center text-gray-500 text-sm">
            © 2025 Squidly. All rights reserved.
          </p>
        </div>
      </footer>
    </div>
  );
}

// Main App component with providers
function App() {
  return (
    <BranchProvider>
      <CartProvider>
        <AppContent />
      </CartProvider>
    </BranchProvider>
  );
}

export default App;
