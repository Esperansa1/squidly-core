import React, { useState, useEffect } from 'react';
import publicApi from './services/publicApi';
import { BranchProvider, useBranch } from './contexts/BranchContext';
import { CartProvider } from './contexts/CartContext';
import { ToastProvider } from './contexts/ToastContext';
import BranchSelectionModal from './components/branches/BranchSelectionModal';
import MenuLayout from './components/menu/MenuLayout';
import CheckoutFlow from './components/checkout/CheckoutFlow';
import OrderTracker from './components/orders/OrderTracker';
import './styles/animations.css';

function AppContent() {
  const [currentView, setCurrentView] = useState('menu');
  const [apiStatus, setApiStatus] = useState({ initialized: false, error: null, config: null });
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

  return (
    <div className="min-h-screen">
      {/* Branch Selection Modal - opens when no branch selected */}
      <BranchSelectionModal isOpen={!selectedBranch} />

      {/* Main Content */}
      <main>
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
      </main>
    </div>
  );
}

// Main App component with providers
function App() {
  return (
    <BranchProvider>
      <CartProvider>
        <ToastProvider>
          <AppContent />
        </ToastProvider>
      </CartProvider>
    </BranchProvider>
  );
}

export default App;
