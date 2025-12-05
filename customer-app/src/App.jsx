import React, { useState, useEffect } from 'react';
import publicApi from './services/publicApi';
import { BranchProvider, useBranch } from './contexts/BranchContext';
import { CartProvider } from './contexts/CartContext';
import BranchSelector from './components/branches/BranchSelector';
import MenuLayout from './components/menu/MenuLayout';
import CheckoutFlow from './components/checkout/CheckoutFlow';
import OrderTracker from './components/orders/OrderTracker';
import { t } from './i18n/translations';
import './styles/animations.css';

function AppContent() {
  const [currentView, setCurrentView] = useState('branch-selection');
  const [apiStatus, setApiStatus] = useState({ initialized: false, error: null, config: null });
  const [testData, setTestData] = useState({ branches: null, products: null, categories: null });
  const { selectedBranch } = useBranch();

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

  // Handle checkout
  const handleCheckout = () => {
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
    <div className="min-h-screen">
      {/* Main Content */}
      <main>
        {currentView === 'branch-selection' && (
          <div className="container mx-auto px-4 py-8">
            <div className="bg-white rounded-lg shadow p-8">
              <BranchSelector
                onBranchSelected={(branch) => {
                  console.log('Branch selected:', branch);
                  setCurrentView('menu');
                }}
              />
            </div>
          </div>
        )}

        {currentView === 'menu' && (
          <MenuLayout
            branchId={selectedBranch?.id}
            onCheckout={handleCheckout}
          />
        )}

        {currentView === 'checkout' && (
          <div className="container mx-auto px-4 py-8">
            <CheckoutFlow
              onBack={() => setCurrentView('menu')}
              branchId={selectedBranch?.id}
            />
          </div>
        )}

        {currentView === 'tracking' && (
          <div className="container mx-auto px-4 py-8">
            <OrderTracker
              onBack={() => setCurrentView('menu')}
            />
          </div>
        )}

        {/* API Status - Only show on non-menu views */}
        {currentView !== 'menu' && (
          <div className="container mx-auto px-4">
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
          </div>
        )}
      </main>
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
