import React from 'react';
import AppLayout from '../AppLayout.jsx';

const CustomersPage = () => {
  return (
    <AppLayout activeNavItem="ניהול לקוחות">
      <div className="p-6" style={{ backgroundColor: '#F2F2F2' }}>
        <div className="max-w-7xl mx-auto">
          <div className="bg-white rounded-lg shadow-sm p-8">
            <h1 className="text-2xl text-gray-800 mb-4" style={{ fontWeight: 800 }}>
              ניהול לקוחות
            </h1>
            <p className="text-gray-600 mb-8" style={{ fontWeight: 400 }}>
              כאן תוכל לנהל את בסיס הלקוחות, לעקוב אחרי נתוני לקוחות ולנהל תוכנית נאמנות. דף זה יפותח בקרוב.
            </p>
            
            <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
              <div className="bg-blue-50 border border-blue-100 rounded-lg p-4 text-center">
                <div className="text-2xl text-blue-600 mb-1" style={{ fontWeight: 800 }}>0</div>
                <div className="text-sm text-blue-700" style={{ fontWeight: 600 }}>סך לקוחות</div>
              </div>
              <div className="bg-green-50 border border-green-100 rounded-lg p-4 text-center">
                <div className="text-2xl text-green-600 mb-1" style={{ fontWeight: 800 }}>0</div>
                <div className="text-sm text-green-700" style={{ fontWeight: 600 }}>לקוחות פעילים</div>
              </div>
              <div className="bg-purple-50 border border-purple-100 rounded-lg p-4 text-center">
                <div className="text-2xl text-purple-600 mb-1" style={{ fontWeight: 800 }}>0</div>
                <div className="text-sm text-purple-700" style={{ fontWeight: 600 }}>חברי מועדון</div>
              </div>
              <div className="bg-yellow-50 border border-yellow-100 rounded-lg p-4 text-center">
                <div className="text-2xl text-yellow-600 mb-1" style={{ fontWeight: 800 }}>0</div>
                <div className="text-sm text-yellow-700" style={{ fontWeight: 600 }}>הרשמות השבוע</div>
              </div>
            </div>
            
            <div className="bg-gray-50 rounded-lg p-6">
              <h3 className="text-lg text-gray-800 mb-4" style={{ fontWeight: 600 }}>
                רשימת לקוחות
              </h3>
              <div className="bg-white rounded border">
                <div className="grid grid-cols-4 gap-4 p-4 border-b bg-gray-50 text-sm" style={{ fontWeight: 600 }}>
                  <div className="text-gray-700">שם לקוח</div>
                  <div className="text-gray-700">טלפון</div>
                  <div className="text-gray-700">הזמנות</div>
                  <div className="text-gray-700">סטטוס</div>
                </div>
                <div className="p-8 text-center text-gray-500">
                  רשימת הלקוחות תוצג כאן
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </AppLayout>
  );
};

export default CustomersPage;