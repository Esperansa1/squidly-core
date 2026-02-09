import React, { useState, useEffect } from 'react';
import publicApi from './services/publicApi';
import { AuthProvider } from './contexts/AuthContext';
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
      } catch (error) {
        setApiStatus({ initialized: false, error: error.message, config: null });
        console.error('Failed to initialize API:', error);
      }
    };

    initApi();
  }, []);

  // Detect payment return and auto-load order tracking
  useEffect(() => {
    const paymentReturn = window.wpConfig?.paymentReturn;

    if (paymentReturn?.isReturn && paymentReturn?.orderId) {
      // Check if we have a saved tracking token
      const savedOrderId = sessionStorage.getItem('squidly_order_id');
      const savedToken = sessionStorage.getItem('squidly_tracking_token');

      if (savedOrderId && savedToken && parseInt(savedOrderId) === paymentReturn.orderId) {
        // Auto-navigate to tracking view
        setCurrentView('tracking');
      }
    }
  }, []);

  // Handle checkout
  const handleCheckout = () => {
    setCurrentView('checkout');
  };

  return (
    <div className="min-h-screen">
      {/* Branch Selection Modal - opens when no branch selected */}
      <BranchSelectionModal isOpen={!selectedBranch} />

      {/* Main Content */}
      <main>
        <MenuLayout
          branchId={selectedBranch?.id}
          onCheckout={handleCheckout}
        />

        {/* Checkout Modal Overlay - renders on top of menu */}
        {currentView === 'checkout' && (
          <CheckoutFlow
            onBack={() => setCurrentView('menu')}
            branchId={selectedBranch?.id}
          />
        )}

        {/* Order Tracking Modal - render on top */}
        {currentView === 'tracking' && (
          <div style={{
            position: 'fixed',
            inset: 0,
            zIndex: 1000,
            backgroundColor: 'rgba(0, 0, 0, 0.5)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            padding: '2rem',
          }}>
            <div style={{
              backgroundColor: 'white',
              borderRadius: '1rem',
              maxWidth: '800px',
              width: '100%',
              maxHeight: '90vh',
              overflow: 'auto',
            }}>
              <OrderTracker
                onBack={() => setCurrentView('menu')}
              />
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
    <AuthProvider>
      <BranchProvider>
        <CartProvider>
          <ToastProvider>
            <AppContent />
          </ToastProvider>
        </CartProvider>
      </BranchProvider>
    </AuthProvider>
  );
}

export default App;
