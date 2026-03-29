import React, { useState, useEffect } from 'react';
import publicApi from './services/publicApi';
import { BranchProvider, useBranch } from './contexts/BranchContext';
import { CartProvider } from './contexts/CartContext';
import { ToastProvider } from './contexts/ToastContext';
import BranchSelectionModal from './components/branches/BranchSelectionModal';
import MenuLayout from './components/menu/MenuLayout';
import CheckoutModal from './components/checkout/CheckoutModal';
import OrderTracker from './components/orders/OrderTracker';
import './styles/animations.css';

function AppContent() {
  const [currentView, setCurrentView] = useState('menu');
  const [checkoutOpen, setCheckoutOpen] = useState(false);
  const [branchEditOpen, setBranchEditOpen] = useState(false);
  const [apiStatus, setApiStatus] = useState({ initialized: false, error: null, config: null });
  const { selectedBranch } = useBranch();

  // Initialize API on mount (non-blocking — public endpoints work without config)
  useEffect(() => {
    publicApi.init()
      .then(config => setApiStatus({ initialized: true, error: null, config }))
      .catch(error => {
        setApiStatus({ initialized: false, error: error.message, config: null });
        console.error('Failed to initialize API:', error);
      });
  }, []);

  // Detect payment return and auto-load order tracking
  useEffect(() => {
    const paymentReturn = window.wpConfig?.paymentReturn;

    if (paymentReturn?.isReturn && paymentReturn?.orderId) {
      const savedOrderId = sessionStorage.getItem('squidly_order_id');
      const savedToken = sessionStorage.getItem('squidly_tracking_token');

      if (savedOrderId && savedToken && parseInt(savedOrderId) === paymentReturn.orderId) {
        setCurrentView('tracking');
      }
    }
  }, []);

  return (
    <div className="min-h-screen">
      {/* Branch Selection Modal - opens when no branch selected, or when user wants to edit */}
      <BranchSelectionModal isOpen={!selectedBranch || branchEditOpen} onClose={() => setBranchEditOpen(false)} />

      {/* Checkout Modal - overlay on top of menu */}
      <CheckoutModal
        isOpen={checkoutOpen}
        onClose={() => setCheckoutOpen(false)}
        onEditOrderDetails={() => setBranchEditOpen(true)}
      />

      {/* Main Content */}
      <main>
        {currentView === 'menu' && (
          <MenuLayout
            branchId={selectedBranch?.id}
            onCheckout={() => setCheckoutOpen(true)}
          />
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
