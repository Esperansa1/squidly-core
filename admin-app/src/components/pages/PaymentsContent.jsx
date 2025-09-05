import React from 'react';

const PaymentsContent = () => {
  return (
    <div className="h-full flex flex-col" style={{ backgroundColor: '#F2F2F2' }}>
      <div className="flex-1 p-6 overflow-y-auto">
        <div className="bg-white rounded-lg shadow-sm p-8">
          <h1 className="text-2xl text-gray-800 mb-4" style={{ fontWeight: 800 }}>
            מערכת תשלומים
          </h1>
          <p className="text-gray-600 mb-8" style={{ fontWeight: 400 }}>
            כאן תוכל לנהל את מערכת התשלומים, אמצעי התשלום והטרנזקציות. דף זה יפותח בקרוב.
          </p>
          
          <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 mb-8">
            <div className="bg-green-50 border border-green-100 rounded-lg p-4">
              <h3 className="text-sm text-green-600 mb-1" style={{ fontWeight: 600 }}>
                הכנסות היום
              </h3>
              <p className="text-2xl text-green-700" style={{ fontWeight: 800 }}>
                ₪0
              </p>
            </div>
            <div className="bg-blue-50 border border-blue-100 rounded-lg p-4">
              <h3 className="text-sm text-blue-600 mb-1" style={{ fontWeight: 600 }}>
                תשלומים ממתינים
              </h3>
              <p className="text-2xl text-blue-700" style={{ fontWeight: 800 }}>
                0
              </p>
            </div>
            <div className="bg-red-50 border border-red-100 rounded-lg p-4">
              <h3 className="text-sm text-red-600 mb-1" style={{ fontWeight: 600 }}>
                כרטיסי אשראי
              </h3>
              <p className="text-2xl text-red-700" style={{ fontWeight: 800 }}>
                פעיל
              </p>
            </div>
          </div>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="bg-gray-50 rounded-lg p-4">
              <h3 className="text-lg text-gray-800 mb-2" style={{ fontWeight: 600 }}>
                אמצעי תשלום
              </h3>
              <p className="text-sm text-gray-600">ניהול דרכי התשלום המקובלות</p>
            </div>
            <div className="bg-gray-50 rounded-lg p-4">
              <h3 className="text-lg text-gray-800 mb-2" style={{ fontWeight: 600 }}>
                היסטוריית תשלומים
              </h3>
              <p className="text-sm text-gray-600">מעקב אחרי כל הטרנזקציות</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default PaymentsContent;