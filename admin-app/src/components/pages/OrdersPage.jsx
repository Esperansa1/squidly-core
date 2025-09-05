import React from 'react';
import AppLayout from '../AppLayout.jsx';

const OrdersPage = () => {
  return (
    <AppLayout activeNavItem="הזמנות">
      <div className="p-6" style={{ backgroundColor: '#F2F2F2' }}>
        <div className="max-w-7xl mx-auto">
          <div className="bg-white rounded-lg shadow-sm p-8">
            <h1 className="text-2xl text-gray-800 mb-4" style={{ fontWeight: 800 }}>
              ניהול הזמנות
            </h1>
            <p className="text-gray-600 mb-8" style={{ fontWeight: 400 }}>
              כאן תוכל לנהל את כל הזמנות המסעדה, לעקוב אחרי סטטוס ההזמנות ולטפל בבעיות. דף זה יפותח בקרוב.
            </p>
            
            <div className="bg-gray-50 rounded-lg p-6 mb-6">
              <h3 className="text-lg text-gray-800 mb-4" style={{ fontWeight: 600 }}>
                הזמנות פעילות
              </h3>
              <div className="text-center py-12">
                <div className="text-6xl text-gray-300 mb-4">📋</div>
                <p className="text-gray-500">אין הזמנות פעילות כרגע</p>
              </div>
            </div>
            
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
              <div className="text-center p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                <div className="text-2xl text-yellow-600 mb-2" style={{ fontWeight: 800 }}>0</div>
                <div className="text-sm text-yellow-700" style={{ fontWeight: 600 }}>ממתינות לאישור</div>
              </div>
              <div className="text-center p-4 bg-blue-50 rounded-lg border border-blue-200">
                <div className="text-2xl text-blue-600 mb-2" style={{ fontWeight: 800 }}>0</div>
                <div className="text-sm text-blue-700" style={{ fontWeight: 600 }}>בהכנה</div>
              </div>
              <div className="text-center p-4 bg-green-50 rounded-lg border border-green-200">
                <div className="text-2xl text-green-600 mb-2" style={{ fontWeight: 800 }}>0</div>
                <div className="text-sm text-green-700" style={{ fontWeight: 600 }}>מוכנות למסירה</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </AppLayout>
  );
};

export default OrdersPage;