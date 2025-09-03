import React from 'react';
import AppLayout from '../AppLayout.jsx';

const SuppliersPage = () => {
  return (
    <AppLayout activeNavItem="ניהול ספקים">
      <div className="p-6" style={{ backgroundColor: '#F2F2F2' }}>
        <div className="max-w-7xl mx-auto">
          <div className="bg-white rounded-lg shadow-sm p-8">
            <h1 className="text-2xl text-gray-800 mb-4" style={{ fontWeight: 800 }}>
              ניהול ספקים
            </h1>
            <p className="text-gray-600 mb-8" style={{ fontWeight: 400 }}>
              כאן תוכל לנהל את רשימת הספקים, להוסיף ספקים חדשים ולעקוב אחרי הזמנות מספקים. דף זה יפותח בקרוב.
            </p>
            
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <div className="bg-gray-50 rounded-lg p-6">
                <h3 className="text-lg text-gray-800 mb-4" style={{ fontWeight: 600 }}>
                  ספקים פעילים
                </h3>
                <div className="space-y-3">
                  <div className="p-4 bg-white rounded border">
                    <div className="flex justify-between items-center">
                      <div>
                        <div className="text-gray-800" style={{ fontWeight: 600 }}>ספק לדוגמה</div>
                        <div className="text-sm text-gray-500">קטגוריה: ירקות</div>
                      </div>
                      <span className="px-2 py-1 bg-green-100 text-green-700 text-xs rounded">פעיל</span>
                    </div>
                  </div>
                  <div className="text-center py-8 text-gray-500">
                    רשימת הספקים תוצג כאן
                  </div>
                </div>
              </div>
              
              <div className="bg-gray-50 rounded-lg p-6">
                <h3 className="text-lg text-gray-800 mb-4" style={{ fontWeight: 600 }}>
                  הזמנות מספקים
                </h3>
                <div className="space-y-3">
                  <div className="flex justify-between items-center py-3">
                    <span className="text-gray-600">הזמנות השבוע</span>
                    <span className="text-xl text-gray-800" style={{ fontWeight: 600 }}>0</span>
                  </div>
                  <div className="flex justify-between items-center py-3">
                    <span className="text-gray-600">ממתינות לאישור</span>
                    <span className="text-xl text-orange-600" style={{ fontWeight: 600 }}>0</span>
                  </div>
                  <div className="flex justify-between items-center py-3">
                    <span className="text-gray-600">הוזמנו השבוע</span>
                    <span className="text-xl text-blue-600" style={{ fontWeight: 600 }}>₪0</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </AppLayout>
  );
};

export default SuppliersPage;