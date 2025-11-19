import React, { useState } from 'react';

function App() {
  const [currentView, setCurrentView] = useState('branch-selection');

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
