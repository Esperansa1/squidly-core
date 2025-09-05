import React from 'react';

const OrdersContent = () => {
  return (
    <div className="h-full flex flex-col" style={{ backgroundColor: '#F2F2F2' }}>
      <div className="flex-1 p-6 overflow-y-auto">
        <div className="bg-white rounded-lg shadow-sm p-8">
          <h1 className="text-2xl text-gray-800 mb-4" style={{ fontWeight: 800 }}>
            ניהול הזמנות
          </h1>
          <p className="text-gray-600 mb-8" style={{ fontWeight: 400 }}>
            כאן תוכל לנהל את כל ההזמנות הנכנסות, לעקוב אחרי סטטוס ההזמנות ולנהל את זמני הכנה. דף זה יפותח בקרוב.
          </p>
          
          <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
            <div className="bg-orange-50 border border-orange-100 rounded-lg p-4">
              <h3 className="text-sm text-orange-600 mb-1" style={{ fontWeight: 600 }}>
                הזמנות ממתינות
              </h3>
              <p className="text-2xl text-orange-700" style={{ fontWeight: 800 }}>
                0
              </p>
            </div>
            <div className="bg-blue-50 border border-blue-100 rounded-lg p-4">
              <h3 className="text-sm text-blue-600 mb-1" style={{ fontWeight: 600 }}>
                בהכנה
              </h3>
              <p className="text-2xl text-blue-700" style={{ fontWeight: 800 }}>
                0
              </p>
            </div>
            <div className="bg-green-50 border border-green-100 rounded-lg p-4">
              <h3 className="text-sm text-green-600 mb-1" style={{ fontWeight: 600 }}>
                מוכנות
              </h3>
              <p className="text-2xl text-green-700" style={{ fontWeight: 800 }}>
                0
              </p>
            </div>
            <div className="bg-gray-50 border border-gray-100 rounded-lg p-4">
              <h3 className="text-sm text-gray-600 mb-1" style={{ fontWeight: 600 }}>
                הושלמו היום
              </h3>
              <p className="text-2xl text-gray-700" style={{ fontWeight: 800 }}>
                0
              </p>
            </div>
          </div>
          
          <div className="bg-gray-50 rounded-lg p-6">
            <h3 className="text-lg text-gray-800 mb-4" style={{ fontWeight: 600 }}>
              רשימת הזמנות
            </h3>
            <p className="text-gray-600">
              כאן תוצג רשימה של כל ההזמנות הפעילות עם אפשרות לעדכן סטטוס ולנהל את התהליך.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
};

export default OrdersContent;