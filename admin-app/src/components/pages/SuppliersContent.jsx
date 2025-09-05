import React from 'react';

const SuppliersContent = () => {
  return (
    <div className="h-full flex flex-col" style={{ backgroundColor: '#F2F2F2' }}>
      <div className="flex-1 p-6 overflow-y-auto">
        <div className="bg-white rounded-lg shadow-sm p-8">
          <h1 className="text-2xl text-gray-800 mb-4" style={{ fontWeight: 800 }}>
            ניהול ספקים
          </h1>
          <p className="text-gray-600 mb-8" style={{ fontWeight: 400 }}>
            כאן תוכל לנהל את כל הספקים שלך, לעקוב אחרי הזמנות ולנהל מלאי. דף זה יפותח בקרוב.
          </p>
          
          <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 mb-8">
            <div className="bg-purple-50 border border-purple-100 rounded-lg p-4">
              <h3 className="text-sm text-purple-600 mb-1" style={{ fontWeight: 600 }}>
                ספקים פעילים
              </h3>
              <p className="text-2xl text-purple-700" style={{ fontWeight: 800 }}>
                0
              </p>
            </div>
            <div className="bg-yellow-50 border border-yellow-100 rounded-lg p-4">
              <h3 className="text-sm text-yellow-600 mb-1" style={{ fontWeight: 600 }}>
                הזמנות השבוע
              </h3>
              <p className="text-2xl text-yellow-700" style={{ fontWeight: 800 }}>
                0
              </p>
            </div>
            <div className="bg-indigo-50 border border-indigo-100 rounded-lg p-4">
              <h3 className="text-sm text-indigo-600 mb-1" style={{ fontWeight: 600 }}>
                חשבוניות ממתינות
              </h3>
              <p className="text-2xl text-indigo-700" style={{ fontWeight: 800 }}>
                0
              </p>
            </div>
          </div>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="bg-gray-50 rounded-lg p-4">
              <h3 className="text-lg text-gray-800 mb-2" style={{ fontWeight: 600 }}>
                רשימת ספקים
              </h3>
              <p className="text-sm text-gray-600">ניהול פרטי ספקים ויצירת קשר</p>
            </div>
            <div className="bg-gray-50 rounded-lg p-4">
              <h3 className="text-lg text-gray-800 mb-2" style={{ fontWeight: 600 }}>
                מעקב מלאי
              </h3>
              <p className="text-sm text-gray-600">עקיבה אחרי רמות מלאי ותזכורות הזמנה</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default SuppliersContent;