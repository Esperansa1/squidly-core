import React, { useState, useEffect } from 'react';
import publicApi from './services/publicApi';

function App() {
  const [currentView, setCurrentView] = useState('branch-selection');
  const [apiStatus, setApiStatus] = useState({ initialized: false, error: null, config: null });
  const [testData, setTestData] = useState({ branches: null, products: null, categories: null });

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
            <h1 className="text-2xl font-bold text-primary">Squidly Orders</h1>
            <button className="px-4 py-2 text-sm text-primary hover:bg-primary-50 rounded-lg transition">
              Cart (0)
            </button>
          </div>
        </div>
      </header>

      {/* Main Content */}
      <main className="container mx-auto px-4 py-8">
        <div className="bg-white rounded-lg shadow p-8">
          <div className="text-center py-12">
            <h2 className="text-3xl font-bold mb-4 text-gray-900">Welcome to Squidly</h2>
            <p className="text-gray-600 mb-8">
              Your favorite restaurant, now online
            </p>
            <button
              className="px-6 py-3 bg-primary text-white rounded-lg font-medium hover:bg-primary-600 transition"
              onClick={() => setCurrentView('menu')}
            >
              Start Ordering
            </button>
          </div>
        </div>

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

export default App;
