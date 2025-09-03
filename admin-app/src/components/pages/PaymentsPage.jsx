import React from 'react';
import AppLayout from '../AppLayout.jsx';

const PaymentsPage = () => {
  return (
    <AppLayout activeNavItem="תשלומים">
      <div className="p-6" style={{ backgroundColor: '#F2F2F2' }}>
        <div className="max-w-7xl mx-auto">
          <div className="bg-white rounded-lg shadow-sm p-8">
            <h1 className="text-2xl text-gray-800 mb-4" style={{ fontWeight: 800 }}>
              ניהול תשלומים
            </h1>
            <p className="text-gray-600 mb-8" style={{ fontWeight: 400 }}>
              כאן תוכל לנהל את כל התשלומים, שיטות התשלום והחשבוניות. דף זה יפותח בקרוב.
            </p>
            
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <div className="bg-gray-50 rounded-lg p-6">
                <h3 className="text-lg text-gray-800 mb-4" style={{ fontWeight: 600 }}>
                  שיטות תשלום
                </h3>
                <div className="space-y-3">
                  <div className="flex justify-between items-center p-3 bg-white rounded border">
                    <span className="text-gray-800" style={{ fontWeight: 600 }}>כרטיס אשראי</span>
                    <span className="text-green-600 text-sm">פעיל</span>
                  </div>
                  <div className="flex justify-between items-center p-3 bg-white rounded border">
                    <span className="text-gray-800" style={{ fontWeight: 600 }}>מזומן</span>
                    <span className="text-green-600 text-sm">פעיל</span>
                  </div>
                  <div className="flex justify-between items-center p-3 bg-white rounded border">
                    <span className="text-gray-800" style={{ fontWeight: 600 }}>העברה בנקאית</span>
                    <span className="text-gray-400 text-sm">לא פעיל</span>
                  </div>
                </div>
              </div>
              
              <div className="bg-gray-50 rounded-lg p-6">
                <h3 className="text-lg text-gray-800 mb-4" style={{ fontWeight: 600 }}>
                  סיכום כספי
                </h3>
                <div className="space-y-4">
                  <div className="flex justify-between items-center">
                    <span className="text-gray-600">סך תקבולים השבוע</span>
                    <span className="text-xl text-gray-800" style={{ fontWeight: 600 }}>₪0</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-gray-600">תשלומים ממתינים</span>
                    <span className="text-xl text-orange-600" style={{ fontWeight: 600 }}>₪0</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-gray-600">החזרים השבוע</span>
                    <span className="text-xl text-red-600" style={{ fontWeight: 600 }}>₪0</span>
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

export default PaymentsPage;